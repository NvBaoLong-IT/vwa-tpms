<?php

namespace Tests\Feature;

use App\Models\DocumentImport;
use App\Models\User;
use App\Services\CurriculumDraftImporter;
use App\Services\ProgramCreditReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgramRealSourceDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_source_duplicate_code_blocks_submission_without_writing_curriculum_data(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.import'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $payload = $this->fixture()['candidate'];
        $draft = DocumentImport::create([
            'uploaded_by' => $user->id,
            'original_name' => 'Kinh tế số 2026 - QĐ604 và QĐ608',
            'path' => 'test-fixtures/not-an-upload.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'review',
            'payload' => $payload,
        ]);
        $hash = app(CurriculumDraftImporter::class)->comparisonHash($draft);

        $this->actingAs($user)->post(route('programs.document-imports.submit', $draft), [
            'confirm' => '1', 'comparison_hash' => $hash,
        ])->assertSessionHasErrors('payload');

        $this->assertSame('review', $draft->fresh()->status);
        $this->assertNull($draft->submitted_at);
        $this->assertNull($draft->comparison_after);
        $this->assertSame($payload, $draft->payload);
        foreach (['programs', 'courses', 'plos', 'course_plo_matrix', 'program_versions', 'curriculum_audits'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertNothingSent();
    }

    public function test_real_source_counted_scope_reconciles_to_122_credits_with_three_elective_groups(): void
    {
        $fixture = $this->fixture();

        $result = app(ProgramCreditReconciler::class)->forSnapshot([
            'program' => $fixture['candidate']['program'],
            'courses' => array_values(array_filter($fixture['source']['courses'], fn (array $course): bool => $course['counts_toward_total'])),
            'required_blocks' => $fixture['candidate']['knowledge_blocks'],
            'elective_groups' => $fixture['candidate']['elective_groups'],
        ]);

        $this->assertSame(122, $result['effective_credits']);
        $this->assertSame(105, $result['mandatory_credits']);
        $this->assertSame(17, $result['elective_required']);
        $this->assertSame(44, $result['elective_offered']);
        $this->assertSame([8, 24, 12], array_column($result['elective_groups'], 'credits_offered'));
        $this->assertSame([], array_values(array_filter($result['issues'], fn (array $issue): bool => $issue['severity'] === 'error')));
    }

    public function test_real_source_outside_total_courses_are_kept_separate_from_the_122_credit_total(): void
    {
        $fixture = $this->fixture();

        $result = app(ProgramCreditReconciler::class)->forSnapshot([
            'program' => $fixture['candidate']['program'],
            'courses' => $fixture['source']['courses'],
            'required_blocks' => $fixture['candidate']['knowledge_blocks'],
            'elective_groups' => $fixture['candidate']['elective_groups'],
        ]);

        $this->assertSame(122, $result['effective_credits']);
        $this->assertSame(105, $result['mandatory_credits']);
        $this->assertSame(21, $result['outside_total_credits']);
        $this->assertSame([], array_values(array_filter($result['issues'], fn (array $issue): bool => $issue['severity'] === 'error')));
    }

    #[TestWith(['unknown_course'])]
    #[TestWith(['unknown_plo'])]
    #[TestWith(['duplicate_pair'])]
    #[TestWith(['duplicate_plo'])]
    public function test_invalid_matrix_is_rejected_before_creating_a_program(string $defect): void
    {
        $user = User::factory()->create();
        $payload = $this->matrixPayload();
        $expected = '';
        if ($defect === 'unknown_course') {
            $payload['matrix'][0]['course_code'] = 'DHNT12';
            $expected = 'Ma trận dòng 1 tham chiếu học phần DHNT12 chưa có trong bản nháp. Hãy đối chiếu mã với danh mục học phần.';
        } elseif ($defect === 'unknown_plo') {
            $payload['matrix'][0]['plo_code'] = 'PLO99';
            $expected = 'Ma trận dòng 1 tham chiếu chuẩn đầu ra PLO99 chưa có trong bản nháp.';
        } elseif ($defect === 'duplicate_pair') {
            $payload['matrix'][] = array_merge($payload['matrix'][0], ['contribution_level' => 3]);
            $expected = 'Ma trận lặp cặp DHIT48 – PLO10. Hãy đối chiếu và giữ một mức đóng góp đã xác nhận.';
        } else {
            $payload['plos'][] = array_merge($payload['plos'][0], ['description_vi' => 'Nội dung mâu thuẫn cùng mã PLO']);
            $expected = 'Mã chuẩn đầu ra trong bản nháp bị trùng. Hãy đối chiếu lại PLO.';
        }
        $draft = DocumentImport::create([
            'uploaded_by' => $user->id, 'original_name' => 'matrix-validation.json',
            'path' => 'test-fixtures/matrix-validation.json', 'mime_type' => 'application/json',
            'status' => 'review', 'payload' => $payload,
        ]);

        try {
            app(CurriculumDraftImporter::class)->importNew($draft, $user->id);
            $this->fail('Bản nháp có ma trận sai phải bị từ chối trước khi ghi dữ liệu.');
        } catch (ValidationException $exception) {
            $this->assertSame([$expected], $exception->errors()['payload']);
        }

        foreach (['programs', 'courses', 'plos', 'course_plo_matrix', 'curriculum_audits'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('review', $draft->fresh()->status);
    }

    public function test_valid_matrix_is_persisted_when_its_course_and_plo_are_present(): void
    {
        $user = User::factory()->create();
        $draft = DocumentImport::create([
            'uploaded_by' => $user->id, 'original_name' => 'isolated-valid-matrix.json',
            'path' => 'test-fixtures/isolated-valid-matrix.json', 'mime_type' => 'application/json',
            'status' => 'review', 'payload' => $this->matrixPayload(),
        ]);

        $program = app(CurriculumDraftImporter::class)->importNew($draft, $user->id);

        $this->assertDatabaseCount('course_plo_matrix', 1);
        $this->assertDatabaseHas('course_plo_matrix', [
            'course_id' => $program->courses()->firstOrFail()->id,
            'plo_id' => $program->plos()->firstOrFail()->id,
            'contribution_level' => 2,
        ]);
    }

    /**
     * Minimal synthetic case to isolate matrix validation; not an approved source correction.
     */
    private function matrixPayload(): array
    {
        return [
            'program' => ['code' => 'MATRIX-TEST', 'name_vi' => 'Kiểm thử ma trận độc lập', 'degree_level' => 'dai_hoc', 'total_credits' => 3],
            'courses' => [['course_code' => 'DHIT48', 'name_vi' => 'Lý thuyết xác suất và thống kê toán', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true]],
            'plos' => [['code' => 'PLO10', 'description_vi' => 'Chuẩn dùng để kiểm tra tham chiếu']],
            'matrix' => [['course_code' => 'DHIT48', 'plo_code' => 'PLO10', 'contribution_level' => 2]],
        ];
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/curriculum/kinh-te-so-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
