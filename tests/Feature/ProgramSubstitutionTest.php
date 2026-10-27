<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramSubstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_substitution_is_version_scoped_audited_and_does_not_add_to_total_credits(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $this->actingAs($officer);
        $program = Program::create(['code' => 'RULE', 'name_vi' => 'Chương trình thay thế', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $sourceCourse = Course::create(['course_code' => 'OLD', 'name_vi' => 'Học phần cũ', 'credits' => 3]);
        $target = Course::create(['course_code' => 'NEW', 'name_vi' => 'Học phần mới', 'credits' => 3]);
        $program->courses()->attach($sourceCourse, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $service = app(ProgramVersionService::class);
        $source = $service->capture($program, 2025, '01/QĐ', '2025-01-01', $officer->id);
        $service->submit($source, ['effective_from' => '2025-01-01'], $officer->id);
        $service->review($source, true, 'Đồng ý', $manager->id);
        $service->publish($source);
        $program->courses()->detach();
        $program->courses()->attach($target, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $data = ['source_version_id' => (string) $source->id, 'source_course_code' => 'OLD', 'target_course_code' => 'NEW', 'recognized_credits' => '3', 'decision_number' => '02/QĐ', 'note' => 'Áp dụng khi chuyển từ khóa 2025'];
        $this->post(route('programs.substitutions.store', $program), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('programs.substitutions.store', $program), $data)->assertSessionHasErrors('substitution');
        $this->get(route('programs.show', $program))->assertOk()->assertSee('OLD')->assertSee('Áp dụng khi chuyển từ khóa 2025');
        $new = $service->capture($program, 2026, '02/QĐ', '2026-01-01', $officer->id);
        $rule = $new->snapshot['substitutions'][0];
        $this->assertSame(3, $rule['recognized_credits']);
        $this->assertSame(3, $new->snapshot['program']['total_credits']);
        $this->assertEmpty($source->snapshot['substitutions']);
        $this->get(route('programs.versions.show', [$program, $new]))->assertOk()->assertSee('OLD');
        $this->delete(route('programs.substitutions.destroy', [$program, $rule['id']]))->assertRedirect();
        $this->assertEmpty($program->fresh()->course_substitutions);
        $this->assertCount(1, $new->fresh()->snapshot['substitutions']);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program', 'subject_id' => $program->id, 'action' => 'substitution_updated', 'actor_id' => $officer->id]);
        $this->post(route('programs.substitutions.store', $program), array_merge($data, ['recognized_credits' => 4]))->assertSessionHasErrors('substitution');
        $this->post(route('programs.substitutions.store', $program), array_merge($data, ['source_version_id' => $new->id]))->assertSessionHasErrors('substitution');
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->post(route('programs.substitutions.store', $program), $data)->assertForbidden();
    }
}
