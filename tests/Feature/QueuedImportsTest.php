<?php

namespace Tests\Feature;

use App\Jobs\ExtractCurriculumDocumentJob;
use App\Jobs\ImportGraduationJob;
use App\Models\AcademicImport;
use App\Models\DiplomaBlank;
use App\Models\DocumentImport;
use App\Models\Student;
use App\Models\User;
use App\Services\ModelApiCurriculumExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QueuedImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_upload_waits_for_job_and_stored_file_becomes_reviewable(): void
    {
        Queue::fake([ExtractCurriculumDocumentJob::class]);
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.modelapi.api_key' => 'test-key', 'services.modelapi.base_url' => 'https://model.example/v1', 'services.modelapi.pdf_python' => null]);
        Http::fake(['https://model.example/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['program' => ['name_vi' => 'Bản nháp'], 'courses' => [], 'plos' => [], 'matrix' => []])]]]])]);
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.import'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user)->post(route('programs.document-imports.new.store'), ['file' => UploadedFile::fake()->create('curriculum.pdf', 10, 'application/pdf')])->assertRedirect();
        $draft = DocumentImport::firstOrFail();
        $this->assertSame('processing', $draft->status);
        Queue::assertPushed(ExtractCurriculumDocumentJob::class, fn ($job) => $job->documentImportId === $draft->id);
        Http::assertNothingSent();
        $this->get(route('programs.document-imports.new.show', $draft))->assertOk()->assertSee('Tải lại để xem kết quả');
        $this->flushSession();
        Auth::forgetGuards();

        (new ExtractCurriculumDocumentJob($draft->id))->handle(app(ModelApiCurriculumExtractor::class));
        $this->assertSame('review', $draft->fresh()->status);
        $this->assertSame('Bản nháp', $draft->fresh()->payload['program']['name_vi']);
        $this->assertDatabaseCount('programs', 0);
        Storage::disk('local')->assertExists($draft->path);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_graduation_job_uses_original_actor_and_does_not_import_twice(bool $databaseWorker): void
    {
        if ($databaseWorker) {
            config(['queue.default' => 'database']);
        } else {
            Queue::fake([ImportGraduationJob::class]);
        }
        Storage::fake('local');
        $user = User::factory()->create();
        foreach (['diploma.view', 'diploma.issue'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Student::create(['student_code' => 'GRAD-JOB', 'full_name' => 'Người học', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        DiplomaBlank::create(['serial_number' => 'JOB-001', 'type' => 'dai_hoc', 'import_date' => '2026-10-01']);
        $csv = "MaSV,LoaiVanBang,SoPhoi,NgayCap,MaKhoa,SoQuyetDinh\nGRAD-JOB,dai_hoc,JOB-001,2026-10-01,K12,501\n";
        $this->actingAs($user)->post(route('diplomas.import'), ['file' => UploadedFile::fake()->createWithContent('graduates.csv', $csv)])->assertRedirect();
        $batch = AcademicImport::where('kind', 'graduation')->firstOrFail();
        $this->assertSame('queued', $batch->status);
        $job = null;
        if ($databaseWorker) {
            $this->assertDatabaseCount('jobs', 1);
            $path = Storage::disk('local')->files('graduation-imports')[0];
            $job = new ImportGraduationJob($path, $user->id, $batch->id);
        } else {
            Queue::assertPushed(ImportGraduationJob::class, function ($queued) use ($batch, &$job) {
                $job = $queued;

                return $queued->importId === $batch->id;
            });
        }
        $this->assertDatabaseCount('diploma_requests', 0);
        $this->get(route('diplomas.import.form'))->assertOk()->assertSee('graduates.csv')->assertSee('Chờ xử lý');
        $this->flushSession();
        Auth::forgetGuards();

        if ($databaseWorker) {
            $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertExitCode(0);
            $this->assertDatabaseCount('jobs', 0);
            $this->assertDatabaseCount('failed_jobs', 0);
        } else {
            $job->handle();
        }
        $job->handle();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(1, $batch->fresh()->rows_imported);
        $this->assertDatabaseCount('diploma_requests', 1);
        $this->assertDatabaseHas('diploma_requests', ['requested_by' => $user->id, 'status' => 'pending']);
        Storage::disk('local')->assertMissing($job->path);
    }

    public function test_job_rejects_revoked_actor_and_records_failure_without_writing_requests(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_active' => false]);
        $batch = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'blocked.csv', 'kind' => 'graduation', 'status' => 'queued']);
        Storage::disk('local')->put('blocked.csv', 'No import');
        $job = new ImportGraduationJob('blocked.csv', $user->id, $batch->id);
        try {
            $job->handle();
            $this->fail('Expected revoked actor to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('không còn quyền', $exception->getMessage());
        }

        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertDatabaseCount('diploma_requests', 0);
        Storage::disk('local')->assertMissing('blocked.csv');
    }
}
