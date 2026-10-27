<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePloMatrix;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ProgramExcelImportService
{
    public const HEADERS = [
        'CTDT' => ['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'],
        'PLO' => ['MaPLO', 'MoTa'],
        'HocPhan' => ['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy'],
        'MaTran' => ['MaHocPhan', 'MaPLO', 'MucDongGop'],
    ];

    public const OPTIONAL_HEADERS = [
        'KhoiKienThuc' => ['TenKhoi'],
        'NhomTuChon' => ['MaNhom', 'TenNhom', 'KhoiKienThuc', 'TinChiCanDat'],
    ];

    public function __construct(private ProgramVersionService $versions, private CurriculumSnapshotDiffer $differ, private CurriculumAuditService $audit) {}

    public function preview(UploadedFile $file, int $userId): ProgramExcelImport
    {
        $payload = $this->parse($file);
        $this->assertSharedCourses($payload);

        return ProgramExcelImport::create([
            'created_by' => $userId, 'filename' => $file->getClientOriginalName(),
            'program_code' => $payload['program']['code'], 'payload' => $payload,
            'baseline_hash' => $this->baseline($payload), 'status' => 'review', 'expires_at' => now()->addHours(2),
            'baseline_snapshot' => $this->current($payload),
        ]);
    }

    public function current(array $payload): ?array
    {
        $program = Program::where('code', $payload['program']['code'])->first();

        return $program ? $this->versions->snapshot($program) : null;
    }

    public function baseline(array $payload): string
    {
        $program = Program::withTrashed()->where('code', $payload['program']['code'])->first();
        $catalog = Course::withTrashed()->whereIn('course_code', array_column($payload['courses'], 'course_code'))
            ->with(['programs' => fn ($query) => $query->orderBy('programs.id')])->orderBy('id')->get()
            ->map(fn ($course) => ['id' => $course->id, 'code' => $course->course_code, 'name' => $course->name_vi, 'credits' => (int) $course->credits, 'deleted_at' => $course->deleted_at?->toISOString(), 'program_ids' => $course->programs->pluck('id')->all()])->all();

        return $this->differ->fingerprint(['program_id' => $program?->id, 'deleted_at' => $program?->deleted_at?->toISOString(), 'snapshot' => $this->current($payload), 'catalog' => $catalog]);
    }

    public function confirm(ProgramExcelImport $import, int $userId): Program
    {
        return CurriculumWriteLock::transaction(function () use ($import, $userId): Program {
            $import = ProgramExcelImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            abort_unless($import->created_by === $userId, 403);
            abort_unless($import->status === 'approved' && $import->expires_at->isFuture(), 422, 'Bản nhập phải được lãnh đạo khác người trình phê duyệt và còn hạn trước khi ghi dữ liệu.');
            abort_unless($import->reviewed_by !== null && $import->reviewed_by !== $import->created_by && hash_equals($import->reviewed_payload_hash ?? '', $this->differ->fingerprint($import->payload)), 422, 'Nội dung không còn đúng bản đã duyệt. Hãy tải lại tệp và trình duyệt lại.');
            $payload = $import->payload;
            $program = Program::withTrashed()->where('code', $import->program_code)->lockForUpdate()->first();
            Course::withTrashed()->whereIn('course_code', array_column($payload['courses'], 'course_code'))->orderBy('id')->lockForUpdate()->get();
            if (! hash_equals($import->baseline_hash, $this->baseline($payload))) {
                throw ValidationException::withMessages(['file' => 'Dữ liệu đã thay đổi sau khi xem trước. Hãy tải lại tệp và đối chiếu lại trước khi xác nhận.']);
            }
            $this->assertSharedCourses($payload);
            $before = $this->current($payload);
            $program = $program ?? new Program;
            $program->fill($payload['program']);
            $program->required_knowledge_blocks = $payload['required_blocks'];
            $program->elective_groups = $payload['elective_groups'] ?? [];
            $program->save();
            $ploCodes = [];
            foreach ($payload['plos'] as $row) {
                $plo = $program->plos()->updateOrCreate(['code' => $row['code']], ['description_vi' => $row['description_vi']]);
                $ploCodes[$row['code']] = $plo->id;
            }
            $program->plos()->whereNotIn('code', array_keys($ploCodes))->delete();
            $pivots = [];
            $courseIds = [];
            foreach ($payload['courses'] as $row) {
                $course = Course::updateOrCreate(['course_code' => $row['course_code']], ['name_vi' => $row['name_vi'], 'credits' => $row['credits']]);
                $courseIds[$row['course_code']] = $course->id;
                $pivots[$course->id] = array_intersect_key($row, array_flip(['knowledge_block', 'is_mandatory', 'suggested_semester', 'sort_order', 'elective_group_code', 'counts_toward_total']));
            }
            $program->courses()->sync($pivots);
            CoursePloMatrix::whereIn('plo_id', $program->plos()->pluck('id'))->delete();
            foreach ($payload['matrix'] as $row) {
                CoursePloMatrix::create(['course_id' => $courseIds[$row['course_code']], 'plo_id' => $ploCodes[$row['plo_code']], 'contribution_level' => $row['contribution_level']]);
            }
            $this->audit->record('program', $program->id, 'imported', $before, $this->versions->snapshot($program), $program->id, ['excel_import_id' => $import->id, 'filename' => $import->filename]);
            $import->update(['status' => 'imported', 'imported_at' => now()]);

            return $program;
        });
    }

    public function submit(ProgramExcelImport $import, int $userId): void
    {
        CurriculumWriteLock::transaction(function () use ($import, $userId): void {
            $import = ProgramExcelImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            abort_unless($import->created_by === $userId, 403);
            abort_unless(in_array($import->status, ['review', 'returned'], true) && $import->expires_at->isFuture(), 422, 'Bản xem trước không còn khả dụng để trình duyệt.');
            $this->assertBaseline($import);
            $before = $import->status;
            $import->update(['status' => 'submitted', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null, 'reviewed_payload_hash' => null, 'expires_at' => now()->addDays(7)]);
            $this->recordReview($import, 'excel_submitted', $before);
        });
    }

    public function review(ProgramExcelImport $import, int $userId, bool $approve, string $note): void
    {
        CurriculumWriteLock::transaction(function () use ($import, $userId, $approve, $note): void {
            $user = User::findOrFail($userId);
            abort_unless($user->hasRole('Lãnh đạo phòng'), 403);
            $import = ProgramExcelImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            abort_unless($import->created_by !== $userId, 403, 'Người trình không được tự phê duyệt hoặc trả về bản nhập của mình.');
            abort_unless($import->status === 'submitted' && $import->expires_at->isFuture(), 422, 'Bản nhập không còn chờ duyệt hoặc đã hết hạn.');
            if ($approve) {
                $this->assertBaseline($import);
                $this->assertSharedCourses($import->payload);
            }
            $import->update(['status' => $approve ? 'approved' : 'returned', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note, 'reviewed_payload_hash' => $approve ? $this->differ->fingerprint($import->payload) : null]);
            $this->recordReview($import, $approve ? 'excel_approved' : 'excel_returned', 'submitted');
        });
    }

    private function assertBaseline(ProgramExcelImport $import): void
    {
        if (! hash_equals($import->baseline_hash, $this->baseline($import->payload))) {
            throw ValidationException::withMessages(['file' => 'Dữ liệu đã thay đổi sau khi xem trước. Hãy tải lại tệp và đối chiếu lại trước khi trình duyệt hoặc nhập.']);
        }
    }

    private function recordReview(ProgramExcelImport $import, string $action, string $before): void
    {
        $program = Program::where('code', $import->program_code)->first();
        $this->audit->record('program_excel_import', $import->id, $action, ['status' => $before], $import->only(['status', 'program_code', 'reviewed_by', 'review_note']), $program?->id);
    }

    private function assertSharedCourses(array $payload): void
    {
        $program = Program::withTrashed()->where('code', $payload['program']['code'])->first();
        if ($program?->trashed()) {
            throw ValidationException::withMessages(['file' => 'Chương trình đã bị xóa tạm. Hãy khôi phục trước khi nhập.']);
        }
        foreach ($payload['courses'] as $row) {
            $course = Course::withTrashed()->where('course_code', $row['course_code'])->first();
            if ($course?->trashed()) {
                throw ValidationException::withMessages(['file' => 'Học phần '.$row['course_code'].' đã bị xóa tạm. Hãy khôi phục trước khi nhập.']);
            }
            if ($course && ($course->name_vi !== $row['name_vi'] || (int) $course->credits !== $row['credits']) && $course->programs()->when($program, fn ($query) => $query->where('programs.id', '!=', $program->id))->exists()) {
                throw ValidationException::withMessages(['file' => 'Học phần '.$row['course_code'].' đang dùng ở chương trình khác. Không được ghi đè tên hoặc tín chỉ qua tệp nhập; hãy đối chiếu danh mục học phần dùng chung.']);
            }
        }
    }

    private function parse(UploadedFile $file): array
    {
        try {
            $book = IOFactory::load($file->getRealPath());
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'Không đọc được tệp Excel. Vui lòng dùng đúng tệp mẫu .xlsx.']);
        }
        try {
            $rows = [];
            foreach (self::HEADERS + self::OPTIONAL_HEADERS as $name => $headers) {
                $sheet = $book->getSheetByName($name);
                if (isset(self::OPTIONAL_HEADERS[$name]) && ! $sheet) {
                    continue;
                }
                if (! $sheet) {
                    throw ValidationException::withMessages(['file' => 'Thiếu trang tính '.$name.'.']);
                }
                $values = $sheet->toArray(null, true, true, false);
                if (array_slice($values[0] ?? [], 0, count($headers)) !== $headers) {
                    throw ValidationException::withMessages(['file' => 'Trang tính '.$name.' phải có các cột: '.implode(', ', $headers)]);
                }
                if ($name === 'HocPhan' && ($values[0][6] ?? null) === 'MaNhomTuChon') {
                    $headers[] = 'MaNhomTuChon';
                }
                $rows[$name] = [];
                foreach (array_slice($values, 1) as $index => $row) {
                    if (collect($row)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                        continue;
                    }
                    $rows[$name][$index + 2] = array_combine($headers, array_map(fn ($value) => is_string($value) ? trim($value) : $value, array_slice(array_pad($row, count($headers), null), 0, count($headers))));
                }
            }
            if (count($rows['CTDT']) !== 1) {
                throw ValidationException::withMessages(['file' => 'Trang tính CTDT phải có đúng một dòng chương trình.']);
            }
            $line = array_key_first($rows['CTDT']);
            $data = $this->check('CTDT', $line, $rows['CTDT'][$line], ['MaCTDT' => 'required|string|max:30', 'TenCTDT' => 'required|string|max:255', 'TrinhDo' => 'required|in:dai_hoc,thac_si,tien_si', 'TongTinChi' => 'required|integer|min:1|max:999', 'TinChiTuChon' => 'required|integer|min:0|max:999']);
            $existing = Program::where('code', $data['MaCTDT'])->first();
            $snapshot = $existing ? $this->versions->snapshot($existing) : ['program' => ['name_en' => null, 'delivery_mode' => null, 'review_cycle_years' => 3], 'required_blocks' => []];
            $snapshot['program'] = array_merge($snapshot['program'], ['code' => $data['MaCTDT'], 'name_vi' => $data['TenCTDT'], 'degree_level' => $data['TrinhDo'], 'total_credits' => (int) $data['TongTinChi'], 'elective_credits_required' => (int) $data['TinChiTuChon']]);
            $snapshot['elective_groups'] = $snapshot['elective_groups'] ?? [];
            if (isset($rows['NhomTuChon'])) {
                $snapshot['elective_groups'] = [];
                $groupCodes = [];
                foreach ($rows['NhomTuChon'] as $line => $row) {
                    $group = $this->check('NhomTuChon', $line, $row, ['MaNhom' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/'], 'TenNhom' => 'required|string|max:150', 'KhoiKienThuc' => 'required|string|max:100', 'TinChiCanDat' => 'required|integer|min:1|max:999']);
                    $this->uniqueCode($groupCodes, $group['MaNhom'], 'NhomTuChon', $line);
                    $snapshot['elective_groups'][] = ['code' => $group['MaNhom'], 'name' => $group['TenNhom'], 'knowledge_block' => $group['KhoiKienThuc'], 'credits_required' => (int) $group['TinChiCanDat']];
                }
            }
            if (isset($rows['KhoiKienThuc'])) {
                $blocks = [];
                foreach ($rows['KhoiKienThuc'] as $line => $row) {
                    $blocks[] = $this->check('KhoiKienThuc', $line, $row, ['TenKhoi' => 'required|string|max:100'])['TenKhoi'];
                }
                $snapshot['required_blocks'] = collect($blocks)->unique(fn ($block) => mb_strtolower($block))->values()->all();
            }
            $snapshot['courses'] = $snapshot['plos'] = $snapshot['matrix'] = [];
            $courseCodes = $ploCodes = $matrixPairs = [];
            foreach ($rows['PLO'] as $line => $row) {
                $data = $this->check('PLO', $line, $row, ['MaPLO' => 'required|string|max:30', 'MoTa' => 'required|string']);
                $this->uniqueCode($ploCodes, $data['MaPLO'], 'PLO', $line);
                $previous = $existing?->plos()->where('code', $data['MaPLO'])->first();
                $snapshot['plos'][] = ['code' => $data['MaPLO'], 'description_vi' => $data['MoTa'], 'description_en' => $previous?->description_en];
            }
            foreach ($rows['HocPhan'] as $line => $row) {
                $data = $this->check('HocPhan', $line, $row, ['MaHocPhan' => 'required|string|max:30', 'TenHocPhan' => 'required|string|max:255', 'TinChi' => 'required|integer|min:1|max:99', 'KhoiKienThuc' => 'required|string|max:100', 'BatBuoc' => 'required|in:0,1', 'HocKy' => 'nullable|integer|min:1|max:20', 'MaNhomTuChon' => 'sometimes|nullable|string|max:30', 'TinhVaoTong' => 'sometimes|required|in:0,1']);
                $this->uniqueCode($courseCodes, $data['MaHocPhan'], 'HocPhan', $line);
                $course = Course::where('course_code', $data['MaHocPhan'])->first();
                $groupCode = array_key_exists('MaNhomTuChon', $data) ? ($data['MaNhomTuChon'] ?: null) : $existing?->courses()->where('course_code', $data['MaHocPhan'])->first()?->pivot->elective_group_code;
                $countsTowardTotal = array_key_exists('TinhVaoTong', $data) ? (bool) $data['TinhVaoTong'] : (bool) ($existing?->courses()->where('course_code', $data['MaHocPhan'])->first()?->pivot->counts_toward_total ?? true);
                $group = collect($snapshot['elective_groups'])->firstWhere('code', $groupCode);
                if ($groupCode && (! $countsTowardTotal || ! $group || (bool) $data['BatBuoc'] || mb_strtolower(trim($group['knowledge_block'])) !== mb_strtolower(trim($data['KhoiKienThuc'])))) {
                    throw ValidationException::withMessages(['file' => 'Trang tính HocPhan, dòng '.$line.': nhóm tự chọn không hợp lệ, sai khối hoặc gắn vào học phần bắt buộc.']);
                }
                $snapshot['courses'][] = ['course_code' => $data['MaHocPhan'], 'name_vi' => $data['TenHocPhan'], 'name_en' => $course?->name_en, 'credits' => (int) $data['TinChi'], 'prerequisite_course_id' => $course?->prerequisite_course_id, 'corequisite_course_id' => $course?->corequisite_course_id, 'knowledge_block' => $data['KhoiKienThuc'], 'is_mandatory' => (bool) $data['BatBuoc'], 'suggested_semester' => $data['HocKy'] ? (int) $data['HocKy'] : null, 'sort_order' => count($snapshot['courses']) + 1, 'elective_group_code' => $groupCode, 'counts_toward_total' => $countsTowardTotal];
            }
            foreach ($rows['MaTran'] as $line => $row) {
                $data = $this->check('MaTran', $line, $row, ['MaHocPhan' => 'required|string', 'MaPLO' => 'required|string', 'MucDongGop' => 'required|integer|between:1,3']);
                if (! in_array($data['MaHocPhan'], array_column($snapshot['courses'], 'course_code'), true) || ! in_array($data['MaPLO'], array_column($snapshot['plos'], 'code'), true)) {
                    throw ValidationException::withMessages(['file' => 'Trang tính MaTran, dòng '.$line.': mã học phần hoặc mã chuẩn đầu ra không thuộc chương trình đào tạo.']);
                }
                $this->uniqueCode($matrixPairs, $data['MaHocPhan'].'/'.$data['MaPLO'], 'MaTran', $line);
                $snapshot['matrix'][] = ['course_code' => $data['MaHocPhan'], 'plo_code' => $data['MaPLO'], 'contribution_level' => (int) $data['MucDongGop']];
            }

            return $snapshot;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function uniqueCode(array &$seen, string $code, string $sheet, int $line): void
    {
        $key = mb_strtolower($code);
        if (isset($seen[$key])) {
            throw ValidationException::withMessages(['file' => 'Trang tính '.$sheet.', dòng '.$line.': trùng mã '.$code.'.']);
        }
        $seen[$key] = true;
    }

    private function check(string $sheet, int $line, array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['file' => 'Trang tính '.$sheet.', dòng '.$line.': '.$validator->errors()->first()]);
        }

        return $validator->validated();
    }
}
