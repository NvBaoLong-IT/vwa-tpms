<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\ProgramController;
use App\Models\Course;
use App\Models\CreditRecognition;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\User;
use App\Services\AiCurriculumApprovalService;
use App\Services\CreditRecognitionService;
use App\Services\CurriculumAuditService;
use App\Services\CurriculumEditGuard;
use App\Services\CurriculumWriteLock;
use App\Services\ProgramExcelImportService;
use App\Services\ProgramVersionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$inputPath = realpath($argv[1] ?? '');
$root = realpath(storage_path('framework/testing/mysql-concurrency'));
if (! $app->environment('testing') || getenv('RUN_MYSQL_CONCURRENCY') !== '1' || DB::getDriverName() !== 'mysql' || ! preg_match('/^vwa_tpms_concurrency_[a-f0-9]{12}$/', DB::getDatabaseName()) || ! $inputPath || ! $root || ! str_starts_with($inputPath, $root.DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Concurrency worker requires an isolated, explicitly enabled MySQL test database and a test-owned input file.');
}
$input = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
Auth::guard()->setUser(User::findOrFail($input['user_id']));
$connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($inputPath.'.attempt', (string) $connectionId);
try {
    $value = CurriculumWriteLock::transaction(function () use ($input, $inputPath): mixed {
        $data = $input['data'] ?? [];
        $value = match ($input['action']) {
            'ai_apply' => app(AiCurriculumApprovalService::class)->apply(DocumentImport::findOrFail($data['id']), $data['hash'], $input['user_id'])->id,
            'excel_apply' => app(ProgramExcelImportService::class)->confirm(ProgramExcelImport::findOrFail($data['id']), $input['user_id'])->id,
            'credit_review' => app(CreditRecognitionService::class)->review(Student::findOrFail($data['student_id']), CreditRecognition::findOrFail($data['id']), true, 'Duyệt đồng thời MySQL', $input['user_id']),
            'version_capture' => app(ProgramVersionService::class)->capture(Program::findOrFail($data['program_id']), 2026, 'RACE/QĐ', '2026-01-01', $input['user_id'])->id,
            'version_review' => app(ProgramVersionService::class)->review(ProgramVersion::findOrFail($data['id']), $data['approve'], 'Xét duyệt MySQL', $input['user_id'])->id,
            'program_update' => (function () use ($data): int {
                $program = Program::findOrFail($data['id']);
                if (! hash_equals($data['hash'], app(CurriculumEditGuard::class)->programHash($program))) {
                    throw ValidationException::withMessages(['editing_hash' => 'Program changed while waiting for the curriculum write lock.']);
                }
                app(ProgramController::class)->update(Request::create('/programs/'.$program->id, 'PUT', $data['fields']), $program);

                return $program->id;
            })(),
            'course_update' => (function () use ($data): int {
                $course = Course::findOrFail($data['id']);
                if (! hash_equals($data['hash'], app(CurriculumEditGuard::class)->courseHash($course))) {
                    throw ValidationException::withMessages(['editing_hash' => 'Course changed while waiting for the curriculum write lock.']);
                }
                app(CourseController::class)->update(Request::create('/courses/'.$course->id, 'PUT', $data['fields']), $course);

                return $course->id;
            })(),
            'course_attach' => (function () use ($data): int {
                app(ProgramController::class)->attachCourse(Request::create('/programs/'.$data['program_id'].'/courses', 'POST', ['course_id' => $data['course_id'], 'knowledge_block' => 'Đại cương', 'is_mandatory' => 1]), Program::findOrFail($data['program_id']), app(CurriculumAuditService::class));

                return $data['course_id'];
            })(),
            default => throw new InvalidArgumentException('Unknown concurrency test action.'),
        };
        if ($input['hold'] ?? false) {
            file_put_contents($inputPath.'.ready', 'transaction holds lock and has applied its uncommitted changes');
            $deadline = microtime(true) + 20;
            while (! is_file($inputPath.'.release')) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Timed out awaiting parent release signal.');
                }
                usleep(10000);
            }
        }

        return $value;
    });
    $result = ['status' => 200, 'value' => $value, 'connection_id' => $connectionId];
} catch (Throwable $exception) {
    $status = $exception instanceof ValidationException ? 422 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
    $result = ['status' => $status, 'error' => $exception instanceof ValidationException ? $exception->validator->errors()->first() : $exception->getMessage(), 'exception' => get_class($exception), 'connection_id' => $connectionId];
}
file_put_contents($inputPath.'.result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
