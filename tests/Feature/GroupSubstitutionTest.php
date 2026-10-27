<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupSubstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_to_many_substitution_validates_credit_budget_and_preserves_snapshot(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên');
        $this->actingAs($user);
        $program = Program::create(['code' => 'GROUP-RULE', 'name_vi' => 'CT nhóm', 'degree_level' => 'dai_hoc', 'total_credits' => 6]);
        foreach (['OLD1', 'OLD2'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $service = app(ProgramVersionService::class);
        $source = $service->capture($program, 2025, '01/QĐ', '2025-01-01', $user->id);
        $source->update(['status' => 'published', 'effective_from' => '2025-01-01']);
        $program->courses()->detach();
        foreach (['NEW1', 'NEW2'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $data = ['source_version_id' => $source->id, 'source_codes' => 'OLD1, OLD2', 'target_lines' => "NEW1=3\nNEW2=3", 'kind' => 'equivalent', 'decision_number' => '02/QĐ', 'note' => 'Công nhận cả nhóm khi đủ hai học phần nguồn'];
        $this->post(route('programs.substitutions.store', $program), $data)->assertSessionHasNoErrors()->assertRedirect();
        $rule = $program->fresh()->course_substitutions[0];
        $this->assertSame(['OLD1', 'OLD2'], $rule['source_course_codes']);
        $this->assertSame(6, $rule['recognized_credits']);
        $target = $service->capture($program, 2026, '02/QĐ', '2026-01-01', $user->id);
        $this->post(route('programs.versions.submit', [$program, $target]), ['effective_from' => '2026-01-01'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('programs.versions.show', [$program, $target]))->assertOk()->assertSee('OLD1 + OLD2')->assertSee('NEW1 + NEW2');
        $this->post(route('programs.substitutions.store', $program), array_merge($data, ['source_codes' => 'OLD1', 'target_lines' => "NEW1=3\nNEW2=3"]))->assertSessionHasErrors('substitution');
        $this->post(route('programs.substitutions.store', $program), array_merge($data, ['target_lines' => 'NEW1=4']))->assertSessionHasErrors('substitution');
        $this->post(route('programs.substitutions.store', $program), $data)->assertSessionHasErrors('substitution');
        $this->assertCount(1, $target->fresh()->snapshot['substitutions']);
    }
}
