<?php

namespace Tests\Feature;

use App\Exceptions\CurriculumExtractionException;
use App\Models\Course;
use App\Models\CurriculumAudit;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use App\Services\CurriculumDraftImporter;
use App\Services\ModelApiCurriculumExtractor;
use App\Services\ScannedPdfPreparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramDocumentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.modelapi.pdf_python', null);
        Config::set('services.modelapi.pdf_batch_pages', 1);
    }

    #[TestWith([false, false])]
    #[TestWith([false, true])]
    #[TestWith([true, false])]
    public function test_conflicting_shared_course_rolls_back_the_entire_import(bool $createNew, bool $alreadyAttached): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.create', 'programs.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'Chương trình khác', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $course = Course::create(['course_code' => 'SHARED', 'name_vi' => 'Học phần gốc', 'credits' => 3]);
        $other->courses()->attach($course, ['knowledge_block' => 'Chuyên ngành', 'is_mandatory' => true, 'suggested_semester' => 3]);
        $target = $createNew ? null : Program::create(['code' => 'TARGET', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        if ($alreadyAttached) {
            $target->courses()->attach($course, ['knowledge_block' => 'Đại cương']);
        }
        $otherBefore = $other->fresh()->getAttributes();
        $pivotBefore = $other->courses()->first()->pivot->getAttributes();
        $draft = $this->curriculumDraft($target, $user->id, 'SHARED', 'Tên bị thay đổi', 4);
        $payload = $draft->payload;
        array_unshift($payload['courses'], ['course_code' => 'EARLY', 'name_vi' => 'Học phần nhập trước', 'credits' => 2, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]);
        $payload['plos'] = [['code' => 'PLO1', 'description_vi' => 'Chuẩn nhập trước']];
        $draft->update(['payload' => $payload]);
        $auditCount = CurriculumAudit::count();
        $url = $createNew ? route('programs.document-imports.new.import', $draft) : route('programs.document-imports.import', [$target, $draft]);

        $this->actingAs($user)->post($url, ['confirm' => '1', 'comparison_hash' => $this->approvedHash($draft, $user)])->assertSessionHasErrors('payload');
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name_vi' => 'Học phần gốc', 'credits' => 3]);
        $this->assertDatabaseMissing('courses', ['course_code' => 'EARLY']);
        $this->assertDatabaseCount('plos', 0);
        $this->assertDatabaseCount('curriculum_audits', $auditCount);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame($pivotBefore, $other->courses()->first()->pivot->getAttributes());
        $this->assertSame('approved', $draft->fresh()->status);
        if ($createNew) {
            $this->assertDatabaseMissing('programs', ['code' => 'TARGET']);
        } else {
            $this->assertSame('Tên cũ', $target->fresh()->name_vi);
            $this->assertSame($alreadyAttached ? 1 : 0, $target->courses()->count());
        }
        Http::assertNothingSent();
    }

    public function test_import_reuses_matching_shared_course_without_changing_the_other_program(): void
    {
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $other = Program::create(['code' => 'OTHER-MATCH', 'name_vi' => 'Chương trình khác', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $target = Program::create(['code' => 'TARGET', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $course = Course::create(['course_code' => 'MATCH', 'name_vi' => 'Học phần chung', 'credits' => 3]);
        $other->courses()->attach($course, ['knowledge_block' => 'Đại cương']);
        $before = $course->fresh()->getAttributes();
        $draft = $this->curriculumDraft($target, $user->id, 'MATCH', 'Học phần chung', 3);

        $this->actingAs($user)->post(route('programs.document-imports.import', [$target, $draft]), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($draft, $user)])->assertRedirect(route('programs.show', $target));
        $this->assertSame($before, $course->fresh()->getAttributes());
        $this->assertSame($course->id, $target->courses()->first()->id);
        $this->assertSame($course->id, $other->courses()->first()->id);
        $this->assertDatabaseCount('courses', 1);
        $this->assertSame('imported', $draft->fresh()->status);
    }

    public function test_import_can_update_a_course_used_only_by_the_target_program(): void
    {
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $target = Program::create(['code' => 'TARGET', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $course = Course::create(['course_code' => 'OWN', 'name_vi' => 'Học phần cũ', 'credits' => 3]);
        $target->courses()->attach($course, ['knowledge_block' => 'Đại cương']);
        $draft = $this->curriculumDraft($target, $user->id, 'OWN', 'Học phần mới', 4);

        $this->actingAs($user)->post(route('programs.document-imports.import', [$target, $draft]), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($draft, $user)])->assertRedirect(route('programs.show', $target));
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name_vi' => 'Học phần mới', 'credits' => 4]);
        $this->assertSame('imported', $draft->fresh()->status);
    }

    public function test_import_resolves_forward_prerequisites_and_records_only_one_complete_audit(): void
    {
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $target = Program::create(['code' => 'TARGET', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $draft = $this->curriculumDraft($target, $user->id, 'ADV', 'Nâng cao', 3);
        $payload = $draft->payload;
        $payload['program']['total_credits'] = 7;
        $payload['courses'][0]['prerequisite_course_code'] = 'BASE';
        $payload['courses'][0]['corequisite_course_code'] = 'LAB';
        foreach (['BASE', 'LAB'] as $code) {
            $payload['courses'][] = ['course_code' => $code, 'name_vi' => $code, 'credits' => 2, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true];
        }
        $draft->update(['payload' => $payload]);
        $auditCount = CurriculumAudit::count();

        $this->actingAs($user)->post(route('programs.document-imports.import', [$target, $draft]), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($draft, $user)])->assertRedirect();
        $advanced = Course::where('course_code', 'ADV')->firstOrFail();
        $this->assertSame('BASE', $advanced->prerequisite->course_code);
        $this->assertSame('LAB', $advanced->corequisite->course_code);
        $this->assertDatabaseCount('curriculum_audits', $auditCount + 2);
        $audit = CurriculumAudit::where('action', 'ai_document_imported')->firstOrFail();
        $this->assertSame($advanced->prerequisite_course_id, $audit->new_values['courses'][0]['prerequisite_course_id']);
        $advanced->update(['name_vi' => 'Sửa thủ công']);
        $this->assertDatabaseCount('curriculum_audits', $auditCount + 3);
    }

    #[TestWith(['BASE', 'ADV'])]
    #[TestWith(['MISSING', null])]
    public function test_invalid_ai_prerequisites_roll_back_every_change(string $firstPrerequisite, ?string $secondPrerequisite): void
    {
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $target = Program::create(['code' => 'TARGET', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $draft = $this->curriculumDraft($target, $user->id, 'ADV', 'Nâng cao', 3);
        $payload = $draft->payload;
        $payload['courses'][0]['prerequisite_course_code'] = $firstPrerequisite;
        $payload['courses'][] = ['course_code' => 'BASE', 'name_vi' => 'Cơ sở', 'credits' => 2, 'prerequisite_course_code' => $secondPrerequisite];
        $draft->update(['payload' => $payload]);
        $auditCount = CurriculumAudit::count();

        $this->actingAs($user)->post(route('programs.document-imports.import', [$target, $draft]), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($draft, $user)])->assertSessionHasErrors('payload');
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('curriculum_audits', $auditCount);
        $this->assertSame('Tên cũ', $target->fresh()->name_vi);
        $this->assertSame('approved', $draft->fresh()->status);
    }

    private function approvedHash(DocumentImport $draft, User $owner): string
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::findOrCreate('Lãnh đạo phòng', 'web'));
        if ($draft->comparison_after === null) {
            try {
                app(CurriculumDraftImporter::class)->comparison($draft);
            } catch (ValidationException) {
            }
        }
        $hash = app(CurriculumDraftImporter::class)->comparisonHash($draft);
        $draft->update(['status' => 'approved', 'submitted_by' => $owner->id, 'submitted_at' => now(), 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'approval_expires_at' => now()->addDay(), 'comparison_hash' => $hash, 'approved_hash' => $hash]);

        return $hash;
    }

    private function curriculumDraft(?Program $program, int $userId, string $courseCode, string $name, int $credits): DocumentImport
    {
        return DocumentImport::create([
            'program_id' => $program?->id, 'uploaded_by' => $userId, 'original_name' => 'draft.pdf', 'path' => 'draft.pdf', 'mime_type' => 'application/pdf', 'status' => 'review',
            'payload' => [
                'program' => ['code' => 'TARGET', 'name_vi' => 'Tên mới', 'degree_level' => 'dai_hoc', 'total_credits' => $credits],
                'plos' => [], 'matrix' => [],
                'courses' => [['course_code' => $courseCode, 'name_vi' => $name, 'credits' => $credits, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]],
            ],
        ]);
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
            'program' => ['code' => 'AI-01', 'name_vi' => 'Kinh tế số', 'degree_level' => 'dai_hoc', 'total_credits' => 3],
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

        $this->actingAs($user)->post(route('programs.document-imports.import', [$program, $import]), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($import, $user)])->assertRedirect(route('programs.show', $program));
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name_vi' => 'Kinh tế số', 'total_credits' => 3]);
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
            'program' => ['code' => 'NEW-AI-01', 'name_vi' => 'Chương trình mới', 'degree_level' => 'dai_hoc', 'total_credits' => 3],
            'knowledge_blocks' => [], 'plos' => [], 'courses' => [['course_code' => 'NEW-VALID', 'name_vi' => 'Học phần mới', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]], 'matrix' => [], 'warnings' => [], 'source_notes' => [],
        ], JSON_UNESCAPED_UNICODE)]]]])]);

        $response = $this->actingAs($user)->post(route('programs.document-imports.new.store'), [
            'file' => UploadedFile::fake()->create('new-program.pdf', 10, 'application/pdf'),
        ]);
        $import = DocumentImport::firstOrFail();
        $response->assertRedirect(route('programs.document-imports.new.show', $import));
        $this->get(route('programs.document-imports.new'))
            ->assertOk()
            ->assertSee('href="'.route('programs.document-imports.new.show', $import).'"', false);
        $this->actingAs($user)->post(route('programs.document-imports.new.import', $import), ['confirm' => '1', 'comparison_hash' => $this->approvedHash($import, $user)])->assertRedirect();

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

    public function test_saved_preview_groups_conflicts_and_evaluates_missing_data_after_merging(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('programs.view', 'web'));
        Http::preventStrayRequests();
        $draft = DocumentImport::create([
            'uploaded_by' => $user->id, 'original_name' => 'saved.pdf', 'path' => 'test/saved.pdf', 'mime_type' => 'application/pdf', 'status' => 'review',
            'payload' => [
                'program' => ['code' => 'LAW', 'name_vi' => 'Luật', 'degree_level' => 'dai_hoc', 'total_credits' => 3],
                'courses' => [['course_code' => 'LAW101', 'name_vi' => 'Luật', 'credits' => null, 'is_mandatory' => false, 'suggested_semester' => 1]],
                'plos' => [], 'matrix' => [],
                'warnings' => [
                    ['severity' => 'high', 'message' => 'Trang này không thấy tổng tín chỉ.', 'source' => 'Trang 2'],
                    ['severity' => 'high', 'message' => 'Trang này không thấy tổng tín chỉ.', 'source' => 'Trang 3'],
                    ['severity' => 'high', 'message' => 'Dữ liệu courses mã LAW101 khác nhau ở trường name_vi. Giữ lần xuất hiện đầu; cần đối chiếu.'],
                    ['severity' => 'high', 'message' => 'Dữ liệu courses mã LAW101 khác nhau ở trường knowledge_block. Giữ lần xuất hiện đầu; cần đối chiếu.'],
                ],
            ],
        ]);
        $this->actingAs($user)->get(route('programs.document-imports.new.show', $draft))
            ->assertOk()
            ->assertViewHas('reviewSummary', function ($summary): bool {
                $messages = implode(' ', array_column($summary['issues'], 'message'));

                return count($summary['issues']) === 4
                    && count($summary['details']) === 1
                    && count($summary['details'][0]['sources']) === 2
                    && ! str_contains($messages, 'Thông tin chương trình còn thiếu')
                    && str_contains($messages, '1 mã cần đối chiếu');
            })
            ->assertSee('Nhận xét AI theo từng trang')
            ->assertSee('Học phần chưa xác định số tín chỉ: 1 mã.');
        Http::assertNothingSent();
    }

    public function test_seven_uncached_scan_pages_need_only_two_api_requests(): void
    {
        Config::set('services.modelapi.api_key', 'test-key');
        Config::set('services.modelapi.pdf_batch_pages', 6);
        $pages = [];
        for ($number = 1; $number <= 7; $number++) {
            $pages[] = ['number' => $number, 'data' => base64_encode('batch-test-'.$number)];
        }
        $this->mock(ScannedPdfPreparer::class, function ($mock) use ($pages): void {
            $mock->shouldReceive('pages')->once()->andReturn($pages);
        });
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['program' => ['name_vi' => 'Thử nhóm trang'], 'courses' => []])]]]])]);
        $payload = app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('grouped.pdf', 10, 'application/pdf'));
        $this->assertSame('Thử nhóm trang', $payload['program']['name_vi']);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $images = array_filter($request['messages'][0]['content'], fn ($part) => $part['type'] === 'image_url');

            return count($images) === 6;
        });
        $this->assertStringContainsString('7 trang scan', end($payload['source_notes']));
    }

    public function test_only_a_timed_out_group_is_split_and_successful_pages_are_reused(): void
    {
        Config::set('services.modelapi.api_key', 'test-key');
        Config::set('services.modelapi.pdf_batch_pages', 3);
        $pages = [
            ['number' => 1, 'data' => 'cmVjb3ZlcjE='],
            ['number' => 2, 'data' => 'cmVjb3ZlcjI='],
            ['number' => 3, 'data' => 'cmVjb3ZlcjM='],
        ];
        $this->mock(ScannedPdfPreparer::class, function ($mock) use ($pages): void {
            $mock->shouldReceive('pages')->twice()->andReturn($pages);
        });
        $body = ['choices' => [['message' => ['content' => json_encode(['program' => ['code' => 'RECOVER'], 'courses' => []])]]]];
        Http::fakeSequence()->push('timeout', 524)->push($body)->push($body)->push($body);
        $file = UploadedFile::fake()->create('recover.pdf', 10, 'application/pdf');
        $payload = app(ModelApiCurriculumExtractor::class)->extract($file);
        $this->assertSame('RECOVER', $payload['program']['code']);
        Http::assertSentCount(4);
        $again = app(ModelApiCurriculumExtractor::class)->extract($file);
        $this->assertSame('RECOVER', $again['program']['code']);
        Http::assertSentCount(4);
    }

    public function test_a_group_with_a_broken_stream_falls_back_to_individual_pages(): void
    {
        Config::set('services.modelapi.api_key', 'test-key');
        Config::set('services.modelapi.pdf_batch_pages', 2);
        $this->mock(ScannedPdfPreparer::class, function ($mock): void {
            $mock->shouldReceive('pages')->once()->andReturn([
                ['number' => 1, 'data' => 'c3NlcmVjb3ZlcjE='],
                ['number' => 2, 'data' => 'c3NlcmVjb3ZlcjI='],
            ]);
        });
        $body = ['choices' => [['message' => ['content' => json_encode(['program' => ['code' => 'STREAM-RECOVER'], 'courses' => []])]]]];
        Http::fakeSequence()->push("data: {\"error\":{\"message\":\"upstream failed\"}}\n\n", 200)
            ->push($body)->push($body);
        $payload = app(ModelApiCurriculumExtractor::class)->extract(UploadedFile::fake()->create('broken-group.pdf', 10, 'application/pdf'));
        $this->assertSame('STREAM-RECOVER', $payload['program']['code']);
        Http::assertSentCount(3);
    }
}
