<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CoursePloMatrix;
use App\Models\Program;
use App\Services\CurriculumAuditService;
use App\Services\ProgramVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramImportController extends Controller
{
    private const HEADERS = [
        'CTDT' => ['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'],
        'PLO' => ['MaPLO', 'MoTa'],
        'HocPhan' => ['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy'],
        'MaTran' => ['MaHocPhan', 'MaPLO', 'MucDongGop'],
    ];

    private const OPTIONAL_HEADERS = ['KhoiKienThuc' => ['TenKhoi']];

    public function create(): View
    {
        return view('programs.import');
    }

    public function template(): StreamedResponse
    {
        $book = new Spreadsheet;
        foreach (array_merge(self::HEADERS, self::OPTIONAL_HEADERS) as $index => $headers) {
            $sheet = $index === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($index);
            $sheet->fromArray($headers);
        }

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'mau-import-ctdt.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function store(Request $request, ProgramVersionService $versions, CurriculumAuditService $audit): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']])['file'];
        try {
            $book = IOFactory::load($file->getRealPath());
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'Không đọc được tệp Excel. Vui lòng dùng đúng tệp mẫu .xlsx.']);
        }

        try {
            $rows = [];
            foreach (self::HEADERS as $sheetName => $headers) {
                $sheet = $book->getSheetByName($sheetName);
                if (! $sheet) {
                    throw ValidationException::withMessages(['file' => "Thiếu trang tính {$sheetName} trong tệp mẫu."]);
                }
                $values = $sheet->toArray(null, true, true, false);
                if (array_slice($values[0] ?? [], 0, count($headers)) !== $headers) {
                    throw ValidationException::withMessages(['file' => "Trang tính {$sheetName} phải có các cột: ".implode(', ', $headers)]);
                }
                $rows[$sheetName] = collect(array_slice($values, 1))->filter(fn ($row) => collect($row)->contains(fn ($value) => $value !== null && trim((string) $value) !== ''));
            }
            $blocksSheet = $book->getSheetByName('KhoiKienThuc');
            if ($blocksSheet) {
                $values = $blocksSheet->toArray(null, true, true, false);
                if (($values[0][0] ?? null) !== 'TenKhoi') {
                    throw ValidationException::withMessages(['file' => 'Trang tính KhoiKienThuc phải có cột TenKhoi.']);
                }
                $rows['KhoiKienThuc'] = collect(array_slice($values, 1))->filter(fn ($row) => trim((string) ($row[0] ?? '')) !== '');
            }

            if ($rows['CTDT']->count() !== 1) {
                throw ValidationException::withMessages(['file' => 'Trang tính CTDT phải có đúng một dòng chương trình.']);
            }

            $program = DB::transaction(function () use ($rows, $versions, $audit): Program {
                $programLine = $rows['CTDT']->keys()->first();
                $programData = $this->check('CTDT', $programLine + 2, array_combine(self::HEADERS['CTDT'], array_slice(array_pad($rows['CTDT']->first(), 5, null), 0, 5)), [
                    'MaCTDT' => 'required|string|max:30', 'TenCTDT' => 'required|string|max:255',
                    'TrinhDo' => 'required|in:dai_hoc,thac_si,tien_si', 'TongTinChi' => 'required|integer|min:1|max:999',
                    'TinChiTuChon' => 'required|integer|min:0|max:999',
                ]);
                $attributes = [
                    'name_vi' => $programData['TenCTDT'], 'degree_level' => $programData['TrinhDo'],
                    'total_credits' => $programData['TongTinChi'], 'elective_credits_required' => $programData['TinChiTuChon'],
                ];
                if (isset($rows['KhoiKienThuc'])) {
                    $blocks = [];
                    foreach ($rows['KhoiKienThuc'] as $index => $row) {
                        $data = $this->check('KhoiKienThuc', $index + 2, ['TenKhoi' => trim((string) ($row[0] ?? ''))], ['TenKhoi' => 'required|string|max:100']);
                        $blocks[] = $data['TenKhoi'];
                    }
                    $attributes['required_knowledge_blocks'] = collect($blocks)->unique(fn ($block) => mb_strtolower($block))->values()->all();
                }
                $existing = Program::where('code', $programData['MaCTDT'])->first();
                $before = $existing ? $versions->snapshot($existing) : null;
                $program = Program::updateOrCreate(['code' => $programData['MaCTDT']], $attributes);

                $ploCodes = [];
                foreach ($rows['PLO'] as $index => $row) {
                    $data = $this->check('PLO', $index + 2, array_combine(self::HEADERS['PLO'], array_slice(array_pad($row, 2, null), 0, 2)), ['MaPLO' => 'required|string|max:30', 'MoTa' => 'required|string']);
                    $program->plos()->updateOrCreate(['code' => $data['MaPLO']], ['description_vi' => $data['MoTa']]);
                    $ploCodes[] = $data['MaPLO'];
                }
                $program->plos()->whereNotIn('code', $ploCodes)->delete();

                $coursePivots = [];
                foreach ($rows['HocPhan'] as $index => $row) {
                    $data = $this->check('HocPhan', $index + 2, array_combine(self::HEADERS['HocPhan'], array_slice(array_pad($row, 6, null), 0, 6)), [
                        'MaHocPhan' => 'required|string|max:30', 'TenHocPhan' => 'required|string|max:255', 'TinChi' => 'required|integer|min:1|max:99',
                        'KhoiKienThuc' => 'required|string|max:100', 'BatBuoc' => 'required|in:0,1', 'HocKy' => 'nullable|integer|min:1|max:20',
                    ]);
                    $course = Course::updateOrCreate(['course_code' => $data['MaHocPhan']], ['name_vi' => $data['TenHocPhan'], 'credits' => $data['TinChi']]);
                    $coursePivots[$course->id] = [
                        'knowledge_block' => $data['KhoiKienThuc'], 'is_mandatory' => (bool) $data['BatBuoc'],
                        'suggested_semester' => $data['HocKy'] ?: null, 'sort_order' => $index + 1,
                    ];
                }
                $program->courses()->sync($coursePivots);
                CoursePloMatrix::whereIn('plo_id', $program->plos()->pluck('id'))->delete();

                foreach ($rows['MaTran'] as $index => $row) {
                    $data = $this->check('MaTran', $index + 2, array_combine(self::HEADERS['MaTran'], array_slice(array_pad($row, 3, null), 0, 3)), [
                        'MaHocPhan' => 'required|string', 'MaPLO' => 'required|string', 'MucDongGop' => 'required|integer|between:1,3',
                    ]);
                    $course = $program->courses()->where('course_code', $data['MaHocPhan'])->first();
                    $plo = $program->plos()->where('code', $data['MaPLO'])->first();
                    if (! $course || ! $plo) {
                        throw ValidationException::withMessages(['file' => 'Trang tính MaTran, dòng '.($index + 2).': mã học phần hoặc mã chuẩn đầu ra không thuộc chương trình đào tạo.']);
                    }
                    CoursePloMatrix::updateOrCreate(['course_id' => $course->id, 'plo_id' => $plo->id], ['contribution_level' => $data['MucDongGop']]);
                }

                $audit->record('program', $program->id, 'imported', $before, $versions->snapshot($program), $program->id);

                return $program;
            });

            return redirect()->route('programs.show', $program)->with('success', 'Đã nhập chương trình đào tạo, học phần và mức đáp ứng chuẩn đầu ra.');
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function check(string $sheet, int $line, array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['file' => "Trang tính {$sheet}, dòng {$line}: ".$validator->errors()->first()]);
        }

        return $validator->validated();
    }
}
