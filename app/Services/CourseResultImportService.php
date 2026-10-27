<?php

namespace App\Services;

use App\Models\CourseResultImport;
use App\Models\Program;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\StudentCourseResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

class CourseResultImportService
{
    public const HEADERS = ['MaCTDT', 'Khoa', 'LanSua', 'MaHocPhan', 'HocKy', 'KetQua', 'CanCu'];

    public function __construct(private StudentCourseResultService $results, private CurriculumSnapshotDiffer $differ, private CurriculumAuditService $audit) {}

    public function preview(Student $student, UploadedFile $file, int $userId): CourseResultImport
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            if ($reader instanceof Csv) {
                $reader->setInputEncoding('UTF-8');
            }
            $book = $reader->load($file->getRealPath());
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'Không đọc được tệp. Hãy dùng mẫu Excel .xlsx hoặc CSV UTF-8.']);
        }
        try {
            $sheet = $book->getSheet(0);
            if ($sheet->getHighestDataRow() > 1001 || Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 26) {
                throw ValidationException::withMessages(['file' => 'Mỗi tệp tối đa 1.000 dòng kết quả và chỉ dùng các cột theo mẫu.']);
            }
            $values = $sheet->toArray(null, false, false, false);
            $headers = array_map(fn ($value) => trim(ltrim((string) $value, "\xEF\xBB\xBF")), array_slice($values[0] ?? [], 0, count(self::HEADERS)));
            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages(['file' => 'Các cột phải đúng thứ tự: '.implode(', ', self::HEADERS)]);
            }
            $payload = $errors = $seen = [];
            foreach (array_slice($values, 1) as $index => $row) {
                if (collect($row)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                    continue;
                }
                $line = $index + 2;
                $data = array_combine(self::HEADERS, array_map(fn ($value) => trim((string) $value), array_slice(array_pad($row, count(self::HEADERS), null), 0, count(self::HEADERS))));
                $validator = Validator::make($data, [
                    'MaCTDT' => 'required|string|max:30', 'Khoa' => 'required|integer|between:1900,2100', 'LanSua' => 'required|integer|min:1|max:65535',
                    'MaHocPhan' => 'required|string|max:30', 'HocKy' => 'required|string|max:30', 'KetQua' => 'required|in:Dat,ChuaDat,Mien', 'CanCu' => 'required|string|max:255',
                ]);
                if ($validator->fails()) {
                    $errors[] = ['line' => $line, 'message' => $validator->errors()->first()];

                    continue;
                }
                $program = Program::withTrashed()->where('code', $data['MaCTDT'])->first();
                $version = $program ? ProgramVersion::where('program_id', $program->id)->where('cohort_year', $data['Khoa'])->where('revision', $data['LanSua'])->first() : null;
                if (! $version) {
                    $errors[] = ['line' => $line, 'message' => 'Không tìm thấy phiên bản theo mã CTĐT, khóa và lần sửa.'];

                    continue;
                }
                $result = ['program_version_id' => $version->id, 'course_code' => $data['MaHocPhan'], 'semester' => $data['HocKy'], 'outcome' => ['Dat' => 'passed', 'ChuaDat' => 'failed', 'Mien' => 'exempt'][$data['KetQua']], 'evidence' => $data['CanCu']];
                $key = mb_strtolower($version->id.'|'.$result['course_code'].'|'.$result['semester']);
                if (isset($seen[$key])) {
                    $errors[] = ['line' => $line, 'message' => 'Trùng học phần, phiên bản và kỳ học với dòng '.$seen[$key].'.'];

                    continue;
                }
                $seen[$key] = $line;
                try {
                    $metadata = $this->results->validateResult($student, $result);
                    $payload[] = $result + ['line' => $line, 'credits' => $metadata['credits'], 'program_code' => $data['MaCTDT'], 'cohort_year' => (int) $data['Khoa'], 'revision' => (int) $data['LanSua']];
                } catch (ValidationException $exception) {
                    $errors[] = ['line' => $line, 'message' => $exception->validator->errors()->first()];
                }
            }
            if ($payload === [] && $errors === []) {
                throw ValidationException::withMessages(['file' => 'Tệp không có dòng kết quả.']);
            }

            return CourseResultImport::create(['student_id' => $student->id, 'created_by' => $userId, 'filename' => $file->getClientOriginalName(), 'payload' => $payload, 'errors' => $errors, 'baseline_hash' => $this->baseline($student, $payload), 'status' => 'review', 'expires_at' => now()->addHours(2)]);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function baseline(Student $student, array $rows): string
    {
        return $this->differ->fingerprint([
            'student' => $student->fresh()->only(['id', 'program_id', 'degree_level', 'deleted_at']),
            'versions' => ProgramVersion::whereIn('id', array_column($rows, 'program_version_id'))->orderBy('id')->get()->map(fn ($version) => $version->only(['id', 'status', 'snapshot']))->all(),
            'results' => StudentCourseResult::where('student_id', $student->id)->orderBy('id')->get()->map(fn ($result) => $result->only(['id', 'program_version_id', 'course_code', 'semester', 'outcome', 'voided_at']))->all(),
        ]);
    }

    public function confirm(Student $student, CourseResultImport $import, int $userId): int
    {
        return CurriculumWriteLock::transaction(function () use ($student, $import, $userId): int {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $import = CourseResultImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            abort_unless($import->student_id === $student->id && $import->created_by === $userId, 403);
            abort_unless($import->status === 'review' && $import->expires_at->isFuture() && $import->errors === [], 422, 'Tệp còn lỗi, đã hết hạn hoặc đã nhập. Hãy kiểm tra và tải lại tệp.');
            if (! hash_equals($import->baseline_hash, $this->baseline($student, $import->payload))) {
                throw ValidationException::withMessages(['file' => 'Dữ liệu sinh viên, phiên bản hoặc kết quả đã thay đổi sau khi xem trước. Hãy tải lại tệp.']);
            }
            foreach ($import->payload as $row) {
                $this->results->record($student, $row, $userId);
            }
            $import->update(['status' => 'imported', 'imported_at' => now()]);
            $count = count($import->payload);
            $this->audit->record('course_result_import', $import->id, 'results_imported', null, ['student_id' => $student->id, 'filename' => $import->filename, 'count' => $count], $student->program_id);

            return $count;
        });
    }
}
