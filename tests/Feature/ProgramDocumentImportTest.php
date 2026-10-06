<?php

namespace Tests\Feature;

use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
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
}
