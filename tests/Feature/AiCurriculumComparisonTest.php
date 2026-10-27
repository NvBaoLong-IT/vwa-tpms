<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiCurriculumComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_matches_merge_preserves_omitted_courses_and_rejects_stale_confirmation(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Lãnh đạo phòng');
        $user = User::factory()->create();
        foreach (['programs.view', 'programs.update', 'programs.import'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $program = Program::create(['code' => 'AI', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 6]);
        foreach (['KEEP', 'CHANGE'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => 'Học phần '.$code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $draft = DocumentImport::create(['program_id' => $program->id, 'uploaded_by' => $user->id, 'status' => 'review', 'original_name' => 'ai.docx', 'path' => 'fake.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'payload' => ['program' => ['name_vi' => 'Tên AI', 'total_credits' => 7], 'plos' => [], 'matrix' => [], 'warnings' => [], 'courses' => [['course_code' => 'CHANGE', 'name_vi' => 'Tên học phần AI', 'credits' => 4, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]]]]);
        $this->actingAs($user)->get(route('programs.document-imports.show', [$program, $draft]))->assertOk()->assertSee('Đối chiếu thay đổi')->assertSee('Tên học phần AI');
        $comparison = $draft->fresh();
        $this->assertSame('Tên cũ', $program->fresh()->name_vi);
        $this->assertSame(['CHANGE', 'KEEP'], collect($comparison->comparison_after['courses'])->pluck('course_code')->sort()->values()->all());
        $this->assertDatabaseCount('courses', 2);
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $comparison->comparison_hash])->assertSessionHasNoErrors()->assertRedirect();
        $program->update(['name_vi' => 'Người khác vừa sửa']);
        $this->actingAs($reviewer)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertSessionHasErrors('payload');
        $this->post(route('programs.document-imports.review', $draft), ['decision' => 'return', 'review_note' => 'Đối chiếu lại'])->assertRedirect();
        $this->actingAs($user);
        $this->assertSame('Người khác vừa sửa', $program->fresh()->name_vi);
        $this->get(route('programs.document-imports.show', [$program, $draft]))->assertOk();
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $draft->fresh()->comparison_hash])->assertRedirect();
        $this->actingAs($reviewer)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Đã đối chiếu lại'])->assertRedirect();
        $this->actingAs($user)->post(route('programs.document-imports.import', [$program, $draft]), ['confirm' => 1, 'comparison_hash' => $draft->fresh()->comparison_hash])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Tên AI', $program->fresh()->name_vi);
        $this->assertDatabaseCount('courses', 2);
        $this->assertSame('imported', $draft->fresh()->status);
        $this->assertSame('Người khác vừa sửa', $draft->fresh()->comparison_before['program']['name_vi']);
        $this->get(route('programs.document-imports.show', [$program, $draft]))->assertOk()->assertSee('Tên học phần AI');
    }
}
