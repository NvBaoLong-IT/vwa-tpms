<?php

namespace Tests\Feature;

use App\Exceptions\CurriculumExtractionException;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use App\Services\ModelApiCurriculumExtractor;
use App\Services\ScannedPdfPreparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgramDocumentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.modelapi.pdf_python', null);
    }

    public function test_user_can_extract_review_and_confirm_a_curriculum_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('programs.view', 'web'),
            Permission::findOrCreate('programs.import', 'web'),
            Permission::findOrCreate('programs.update', 'web'),
        ]);
        $program = Program::create(['code' => 'AI-01', 'name_vi' => 'Bản cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        Config::set('services.modelapi.api_key', 'test-key');
        $payload = [
            'program' => ['code' => 'AI-01', 'name_vi' => 'Kinh tế số', 'degree_level' => 'dai_hoc', 'total_credits' => 121],
            'knowledge_blocks' => ['Đại cương'],
            'plos' => [['code' => 'PLO1', 'description_vi' => 'Vận dụng kiến thức']],
            'courses' => [['course_code' => 'AI101', 'name_vi' => 'Nhập môn', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]],
            'matrix' => [['course_code' => 'AI101', 'plo_code' => 'PLO1', 'contribution_level' => 2]],
            'warnings' => [], 'source_notes' => [],
        ];
        Http::fake(['*modelapi.vn/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
        ])]);

        $response = $this->actingAs($user)->post(route('programs.document-imports.store', $program), [
            'file' => UploadedFile::fake()->create('quyet-dinh.pdf', 10, 'application/pdf'),
        ]);
        $import = DocumentImport::firstOrFail();
        $response->assertRedirect(route('programs.document-imports.show', [$program, $import]));
        $this->assertSame('review', $import->status);
        $this->actingAs($user)->get(route('programs.document-imports.show', [$program, $import]))
            ->assertOk()->assertSee('Kinh tế số')->assertSee('AI101');

        $this->actingAs($user)->post(route('programs.document-imports.import', [$program, $import]), ['confirm' => '1'])->assertRedirect(route('programs.show', $program));
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name_vi' => 'Kinh tế số', 'total_credits' => 121]);
        $this->assertDatabaseHas('courses', ['course_code' => 'AI101']);
        $this->assertDatabaseHas('plos', ['program_id' => $program->id, 'code' => 'PLO1']);
        $this->assertDatabaseHas('document_imports', ['id' => $import->id, 'status' => 'imported']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'modelapi.vn/v1/chat/completions') && $request->hasHeader('Authorization'));
    }

    public function test_missing_modelapi_key_keeps_a_failed_import_without_writing_program_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('programs.view', 'web'),
            Permission::findOrCreate('programs.import', 'web'),
        ]);
        $program = Program::create(['code' => 'AI-02', 'name_vi' => 'Bản cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        Config::set('services.modelapi.api_key', null);

        $this->actingAs($user)->post(route('programs.document-imports.store', $program), [
            'file' => UploadedFile::fake()->create('quyet-dinh.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect(route('programs.document-imports.create', $program));

        $this->assertDatabaseHas('document_imports', ['program_id' => $program->id, 'status' => 'failed']);
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name_vi' => 'Bản cũ']);
    }

    public function test_user_can_create_a_new_program_from_a_reviewed_document(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('programs.view', 'web'),
            Permission::findOrCreate('programs.import', 'web'),
            Permission::findOrCreate('programs.create', 'web'),
        ]);
        Config::set('services.modelapi.api_key', 'test-key');
        Http::fake(['*modelapi.vn/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'program' => ['code' => 'NEW-AI-01', 'name_vi' => 'Chương trình mới', 'degree_level' => 'dai_hoc', 'total_credits' => 120],
            'knowledge_blocks' => [], 'plos' => [], 'courses' => [], 'matrix' => [], 'warnings' => [], 'source_notes' => [],
        ], JSON_UNESCAPED_UNICODE)]]]])]);

        $response = $this->actingAs($user)->post(route('programs.document-imports.new.store'), [
            'file' => UploadedFile::fake()->create('new-program.pdf', 10, 'application/pdf'),
        ]);
        $import = DocumentImport::firstOrFail();
        $response->assertRedirect(route('programs.document-imports.new.show', $import));
        $this->get(route('programs.document-imports.new'))
            ->assertOk()
            ->assertSee('href="'.route('programs.document-imports.new.show', $import).'"', false);
        $this->actingAs($user)->post(route('programs.document-imports.new.import', $import), ['confirm' => '1'])->assertRedirect();

        $this->assertDatabaseHas('programs', ['code' => 'NEW-AI-01', 'name_vi' => 'Chương trình mới']);
        $this->assertDatabaseHas('document_imports', ['id' => $import->id, 'status' => 'imported']);
    }

    public function test_gateway_timeout_shows_a_useful_error_without_creating_a_program(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('programs.view', 'web'),
            Permission::findOrCreate('programs.import', 'web'),
        ]);
        Config::set('services.modelapi.api_key', 'test-key');
        Http::fake(['*modelapi.vn/*' => Http::response('Gateway timeout', 524)]);

        $this->actingAs($user)->post(route('programs.document-imports.new.store'), [
            'file' => UploadedFile::fake()->create('timeout.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('programs.document-imports.new'))
            ->assertSessionHasErrors('file');

        $import = DocumentImport::firstOrFail();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('HTTP 524', $import->error_message);
        $this->assertDatabaseCount('programs', 0);
        $this->get(route('programs.document-imports.new.show', $import))
            ->assertOk()->assertSee('HTTP 524');
    }

    public function test_scanned_pages_are_sent_as_images_and_merged_without_duplicate_courses(): void
    {
        $this->mock(ScannedPdfPreparer::class, function ($mock): void {
            $mock->shouldReceive('pages')->twice()->andReturn([
                ['number' => 1, 'data' => 'cGFnZTE='], ['number' => 2, 'data' => 'cGFnZTI='],

            ]);
        });
        Config::set('services.modelapi.api_key', 'test-key');
        $course = ['course_code' => 'SCAN101', 'name_vi' => 'Học phần', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1];
        $first = ['program' => ['code' => 'SCAN', 'total_credits' => 3], 'courses' => [$course]];
        $first['courses'][0]['suggested_semester'] = null;
        $first['program']['decision_date'] = '25 tháng 6 năm 2025';
        $second = ['program' => ['name_vi' => 'Ngành scan'], 'courses' => [$course], 'plos' => [['code' => 'PLO1', 'description_vi' => 'Chuẩn đầu ra']]];
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => json_encode($first)]]]])
            ->push(['choices' => [['message' => ['content' => json_encode($second)]]]]);

        $payload = app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'));
        $this->assertSame('SCAN', $payload['program']['code']);
        $this->assertSame('Ngành scan', $payload['program']['name_vi']);
        $this->assertSame('2025-06-25', $payload['program']['decision_date']);
        $this->assertSame(1, $payload['courses'][0]['suggested_semester']);
        $this->assertCount(1, $payload['courses']);
        $this->assertCount(1, $payload['plos']);
        $again = app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'));
        $this->assertSame($payload, $again);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['messages'][0]['content'][2]['type'] === 'image_url');
    }

    public function test_failed_scan_batch_never_returns_a_partial_curriculum(): void
    {
        $this->mock(ScannedPdfPreparer::class, function ($mock): void {
            $mock->shouldReceive('pages')->once()->andReturn([['number' => 1, 'data' => 'cGFnZQ==']]);
        });
        Config::set('services.modelapi.api_key', 'test-key');
        Http::fake(['*' => Http::response('timeout', 524)]);
        $this->expectException(CurriculumExtractionException::class);
        $this->expectExceptionMessage('Không đọc được trang 1 (HTTP 524)');
        app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'));
    }

    public function test_streamed_json_is_assembled_before_creating_the_preview_payload(): void
    {
        Config::set('services.modelapi.api_key', 'test-key');
        $payload = ['program' => ['name_vi' => 'Luật'], 'courses' => []];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $body = 'data: '.json_encode(['choices' => [['delta' => ['content' => substr($json, 0, 12)]]]])."\n\n"
            .'data: '.json_encode(['choices' => [['delta' => ['content' => substr($json, 12)], 'finish_reason' => 'stop']]])."\n\n"
            ."data: [DONE]\n\n";
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
        $actual = app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('small.pdf', 10, 'application/pdf'));
        $this->assertSame($payload, $actual);
        Http::assertSent(fn (Request $request): bool => $request['stream'] === true);
    }

    public function test_incomplete_stream_cannot_be_accepted_even_if_json_is_valid(): void
    {
        Config::set('services.modelapi.api_key', 'test-key');
        $body = 'data: '.json_encode(['choices' => [['delta' => ['content' => '{"program":{}}']]]])."\n\n";
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
        $this->expectException(CurriculumExtractionException::class);
        $this->expectExceptionMessage('Luồng phản hồi AI chưa hoàn tất');
        app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('small.pdf', 10, 'application/pdf'));
    }
}
