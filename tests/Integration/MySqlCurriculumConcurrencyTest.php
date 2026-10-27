<?php

namespace Tests\Integration;

use App\Models\Course;
use App\Models\CreditRecognition;
use App\Models\CurriculumAudit;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\User;
use App\Services\AiCurriculumApprovalService;
use App\Services\CreditRecognitionService;
use App\Services\CurriculumDraftImporter;
use App\Services\CurriculumEditGuard;
use App\Services\ProgramExcelImportService;
use App\Services\ProgramVersionService;
use App\Services\StudentCourseResultService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlCurriculumConcurrencyTest extends TestCase
{
    private ?string $testDatabase = null;

    private array $mysqlConfig = [];

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Run explicitly with RUN_MYSQL_CONCURRENCY=1; uses disposable MySQL databases.');
        }
        $this->mysqlConfig = config('database.connections.mysql');
        config(['database.connections.mysql_race_admin' => array_merge($this->mysqlConfig, ['database' => 'mysql', 'url' => null])]);
        $candidate = 'vwa_tpms_concurrency_'.bin2hex(random_bytes(6));
        DB::connection('mysql_race_admin')->statement('CREATE DATABASE `'.$candidate.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->testDatabase = $candidate;
        config(['database.default' => 'mysql', 'database.connections.mysql' => array_merge($this->mysqlConfig, ['database' => $this->testDatabase, 'url' => null])]);
        DB::purge('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        $this->seed(OfficeRolesSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        if ($this->testDatabase !== null && preg_match('/^vwa_tpms_concurrency_[a-f0-9]{12}$/', $this->testDatabase)) {
            DB::disconnect('mysql');
            DB::connection('mysql_race_admin')->statement('DROP DATABASE `'.$this->testDatabase.'`');
        }
        parent::tearDown();
    }

    private function users(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $this->actingAs($owner);

        return [$owner, $manager];
    }

    private function program(string $code = 'RACE'): array
    {
        $program = Program::create(['code' => $code, 'name_vi' => 'Tên trước', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'review_cycle_years' => 3]);
        $course = Course::create(['course_code' => $code.'-HP', 'name_vi' => 'Học phần trước', 'credits' => 3, 'theory_hours' => 45, 'practice_hours' => 0]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);

        return [$program, $course];
    }

    private function aiDraft(Program $program, Course $course, User $owner, User $manager): DocumentImport
    {
        $draft = DocumentImport::create(['program_id' => $program->id, 'uploaded_by' => $owner->id, 'original_name' => 'race.docx', 'path' => 'race.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'status' => 'review', 'payload' => ['program' => ['name_vi' => 'Tên từ AI'], 'courses' => [['course_code' => $course->course_code, 'name_vi' => $course->name_vi, 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]], 'plos' => [], 'matrix' => []]]);
        $hash = app(CurriculumDraftImporter::class)->comparison($draft)['hash'];
        app(AiCurriculumApprovalService::class)->submit($draft, $hash, $owner->id);
        app(AiCurriculumApprovalService::class)->review($draft, true, 'Duyệt', $manager->id);

        return $draft->fresh();
    }

    private function excelDraft(Program $program, User $owner, User $manager): ProgramExcelImport
    {
        $payload = app(ProgramVersionService::class)->snapshot($program->fresh());
        $payload['program']['name_vi'] = 'Tên từ Excel';
        $draft = ProgramExcelImport::create(['created_by' => $owner->id, 'program_code' => $program->code, 'filename' => 'race.xlsx', 'payload' => $payload, 'baseline_hash' => app(ProgramExcelImportService::class)->baseline($payload), 'baseline_snapshot' => app(ProgramVersionService::class)->snapshot($program), 'status' => 'review', 'expires_at' => now()->addHours(2)]);
        app(ProgramExcelImportService::class)->submit($draft, $owner->id);
        app(ProgramExcelImportService::class)->review($draft, $manager->id, true, 'Duyệt');

        return $draft->fresh();
    }

    private function courseFields(Course $course, int $credits): array
    {
        return ['course_code' => $course->course_code, 'name_vi' => $course->name_vi, 'credits' => $credits, 'theory_hours' => 45, 'practice_hours' => 0, 'is_active' => 1];
    }

    private function race(string $name, array $a, array $b): array
    {
        $directory = storage_path('framework/testing/mysql-concurrency/'.$name.'-'.bin2hex(random_bytes(4)));
        mkdir($directory, 0777, true);
        $paths = [$directory.'/a.json', $directory.'/b.json'];
        $env = ['RUN_MYSQL_CONCURRENCY' => '1', 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $this->testDatabase, 'DB_HOST' => (string) $this->mysqlConfig['host'], 'DB_PORT' => (string) $this->mysqlConfig['port'], 'DB_USERNAME' => (string) $this->mysqlConfig['username'], 'DB_PASSWORD' => (string) $this->mysqlConfig['password'], 'DB_URL' => false, 'DB_SOCKET' => false, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_CONFIG_CACHE' => $directory.'/nonexistent-config.php'];
        foreach ([$a + ['hold' => true], $b + ['hold' => false]] as $index => $input) {
            file_put_contents($paths[$index], json_encode($input, JSON_THROW_ON_ERROR));
            $process = new Process([PHP_BINARY, base_path('tests/Support/curriculum-concurrency-worker.php'), $paths[$index]], base_path(), $env);
            $process->setTimeout(30);
            $this->processes[] = $process;
            $process->start();
            if ($index === 0) {
                $this->await(fn () => is_file($paths[0].'.ready'), 'First worker did not reach held transaction: '.$process->getOutput().$process->getErrorOutput());
            }
        }
        try {
            $this->await(fn () => is_file($paths[1].'.attempt'), 'Second worker did not attempt the operation.');
            $connection = (int) file_get_contents($paths[1].'.attempt');
            $this->await(fn () => (int) DB::connection('mysql_race_admin')->selectOne("SELECT COUNT(*) AS waiting FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'", [$connection])->waiting > 0, 'MySQL did not report an actual lock wait for the second connection.');
            $this->assertFileDoesNotExist($paths[1].'.result.json');
        } finally {
            file_put_contents($paths[0].'.release', 'commit first transaction');
        }
        foreach (array_slice($this->processes, -2) as $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        }
        $left = json_decode(file_get_contents($paths[0].'.result.json'), true, 512, JSON_THROW_ON_ERROR);
        $right = json_decode(file_get_contents($paths[1].'.result.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame($left['connection_id'], $right['connection_id']);
        $this->assertSame(200, $left['status'], json_encode($left));
        $server = DB::selectOne('SELECT VERSION() AS version, @@transaction_isolation AS isolation_level');
        file_put_contents($directory.'/evidence.json', json_encode(['scenario' => $name, 'database' => $this->testDatabase, 'server' => $server, 'lock_wait_observed' => true, 'first' => $left, 'second' => $right], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [$left, $right];
    }

    private function await(callable $condition, string $message): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($condition()) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail($message);
    }

    public function test_two_sessions_cannot_apply_the_same_approved_ai_import_twice(): void
    {
        [$owner, $manager] = $this->users();
        [$program, $course] = $this->program();
        $draft = $this->aiDraft($program, $course, $owner, $manager);
        $operation = ['action' => 'ai_apply', 'user_id' => $owner->id, 'data' => ['id' => $draft->id, 'hash' => $draft->approved_hash]];
        [, $second] = $this->race('same-ai-import', $operation, $operation);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame('imported', $draft->fresh()->status);
        $this->assertSame(1, CurriculumAudit::where('subject_type', 'document_import')->where('subject_id', $draft->id)->where('action', 'ai_applied')->count());
    }

    public function test_two_sessions_cannot_apply_the_same_excel_import_twice(): void
    {
        [$owner, $manager] = $this->users();
        [$program] = $this->program();
        $draft = $this->excelDraft($program, $owner, $manager);
        $operation = ['action' => 'excel_apply', 'user_id' => $owner->id, 'data' => ['id' => $draft->id]];
        [, $second] = $this->race('same-excel-import', $operation, $operation);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame('Tên từ Excel', $program->fresh()->name_vi);
        $this->assertSame(1, CurriculumAudit::where('subject_type', 'program')->where('subject_id', $program->id)->where('action', 'imported')->count());
    }

    public function test_manual_program_edit_invalidates_an_ai_import_waiting_for_the_lock(): void
    {
        [$owner, $manager] = $this->users();
        [$program, $course] = $this->program();
        $draft = $this->aiDraft($program, $course, $owner, $manager);
        [, $second] = $this->race('program-edit-vs-ai-import', ['action' => 'program_update', 'user_id' => $owner->id, 'data' => ['id' => $program->id, 'hash' => app(CurriculumEditGuard::class)->programHash($program), 'fields' => ['code' => $program->code, 'name_vi' => 'Tên sửa thủ công', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'review_cycle_years' => 3, 'is_active' => 1]]], ['action' => 'ai_apply', 'user_id' => $owner->id, 'data' => ['id' => $draft->id, 'hash' => $draft->approved_hash]]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame('Tên sửa thủ công', $program->fresh()->name_vi);
        $this->assertSame('approved', $draft->fresh()->status);
    }

    public function test_shared_course_edit_invalidates_pending_excel_confirmation(): void
    {
        [$owner, $manager] = $this->users();
        [$program, $course] = $this->program();
        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'CT khác', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $other->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $draft = $this->excelDraft($program, $owner, $manager);
        [, $second] = $this->race('shared-course-vs-excel-import', ['action' => 'course_update', 'user_id' => $owner->id, 'data' => ['id' => $course->id, 'hash' => app(CurriculumEditGuard::class)->courseHash($course), 'fields' => $this->courseFields($course, 4)]], ['action' => 'excel_apply', 'user_id' => $owner->id, 'data' => ['id' => $draft->id]]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame(4, (int) $course->fresh()->credits);
        $this->assertSame(4, (int) $other->fresh()->courses->first()->credits);
        $this->assertSame('Tên trước', $program->fresh()->name_vi);
    }

    public function test_new_shared_course_attachment_invalidates_ai_approval(): void
    {
        [$owner, $manager] = $this->users();
        [$program, $course] = $this->program();
        $draft = $this->aiDraft($program, $course, $owner, $manager);
        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'CT khác', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        [, $second] = $this->race('attach-shared-course-vs-ai-import', ['action' => 'course_attach', 'user_id' => $owner->id, 'data' => ['program_id' => $other->id, 'course_id' => $course->id]], ['action' => 'ai_apply', 'user_id' => $owner->id, 'data' => ['id' => $draft->id, 'hash' => $draft->approved_hash]]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame(1, $other->courses()->count());
        $this->assertSame('Tên trước', $program->fresh()->name_vi);
    }

    public function test_version_capture_is_consistent_while_a_shared_course_is_edited(): void
    {
        [$owner] = $this->users();
        [$program, $course] = $this->program();
        [$first, $second] = $this->race('snapshot-vs-course-edit', ['action' => 'version_capture', 'user_id' => $owner->id, 'data' => ['program_id' => $program->id]], ['action' => 'course_update', 'user_id' => $owner->id, 'data' => ['id' => $course->id, 'hash' => app(CurriculumEditGuard::class)->courseHash($course), 'fields' => $this->courseFields($course, 4)]]);
        $this->assertSame(200, $second['status'], json_encode($second));
        $this->assertSame(3, ProgramVersion::findOrFail($first['value'])->snapshot['courses'][0]['credits']);
        $this->assertSame(4, (int) $course->fresh()->credits);
    }

    public function test_two_recognition_approvals_cannot_count_the_same_result_twice(): void
    {
        [$owner, $manager] = $this->users();
        [$program, $course] = $this->program();
        $version = app(ProgramVersionService::class)->capture($program, 2026, '01/QĐ', '2026-01-01', $owner->id);
        $version->update(['status' => 'published', 'effective_from' => '2026-01-01']);
        $student = Student::create(['student_code' => 'SV-RACE', 'full_name' => 'Sinh viên thử', 'program_id' => $program->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2026]);
        $result = app(StudentCourseResultService::class)->record($student, ['program_version_id' => $version->id, 'course_code' => $course->course_code, 'semester' => '2026-1', 'outcome' => 'passed', 'evidence' => 'Bảng điểm thử'], $owner->id);
        $service = app(CreditRecognitionService::class);
        $data = ['result_ids' => [$result->id], 'decision_number' => 'CN-RACE', 'note' => 'Hồ sơ thử'];
        $a = $service->submit($student, $version, $data, $owner->id);
        $b = $service->submit($student, $version, $data, $owner->id);
        [, $second] = $this->race('duplicate-credit-approval', ['action' => 'credit_review', 'user_id' => $manager->id, 'data' => ['student_id' => $student->id, 'id' => $a->id]], ['action' => 'credit_review', 'user_id' => $manager->id, 'data' => ['student_id' => $student->id, 'id' => $b->id]]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame(1, CreditRecognition::where('student_id', $student->id)->where('status', 'approved')->count());
        $this->assertSame(3, $service->summary($student, $version)['counted']);
    }

    public function test_first_version_review_wins_and_second_review_cannot_overwrite_it(): void
    {
        [$owner, $manager] = $this->users();
        [$program] = $this->program();
        $service = app(ProgramVersionService::class);
        $version = $service->capture($program, 2026, '01/QĐ', '2026-01-01', $owner->id);
        $service->submit($version, ['effective_from' => '2026-01-01'], $owner->id);
        [, $second] = $this->race('approve-vs-return-version', ['action' => 'version_review', 'user_id' => $manager->id, 'data' => ['id' => $version->id, 'approve' => true]], ['action' => 'version_review', 'user_id' => $manager->id, 'data' => ['id' => $version->id, 'approve' => false]]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame('approved', $version->fresh()->status);
    }

    public function test_second_manual_edit_cannot_overwrite_the_first_saved_form(): void
    {
        [$owner] = $this->users();
        [$program] = $this->program();
        $data = ['id' => $program->id, 'hash' => app(CurriculumEditGuard::class)->programHash($program), 'fields' => ['code' => $program->code, 'name_vi' => 'Tên của người A', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'review_cycle_years' => 3, 'is_active' => 1]];
        $secondData = $data;
        $secondData['fields']['name_vi'] = 'Tên của người B';
        [, $second] = $this->race('two-manual-program-edits', ['action' => 'program_update', 'user_id' => $owner->id, 'data' => $data], ['action' => 'program_update', 'user_id' => $owner->id, 'data' => $secondData]);
        $this->assertSame(422, $second['status'], json_encode($second));
        $this->assertSame('Tên của người A', $program->fresh()->name_vi);
    }
}
