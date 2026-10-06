<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CourseDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('programs.view', 'web'), Permission::findOrCreate('courses.view', 'web')]);

        return $user;
    }

    private function course(string $code, array $attributes = []): Course
    {
        return Course::create($attributes + ['course_code' => $code, 'name_vi' => 'Học phần '.$code, 'credits' => 3, 'is_active' => true]);
    }

    public function test_search_and_combined_filters_find_course_in_selected_program(): void
    {
        $user = $this->viewer();
        $program = Program::create(['code' => 'DIR', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $course = $this->course('DATA101', ['name_vi' => 'Phân tích dữ liệu', 'name_en' => 'Data Analysis']);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $this->course('OTHER', ['name_en' => 'Data Science', 'is_active' => false]);

        $this->actingAs($user)->get(route('courses.index', ['q' => 'Data', 'program_id' => $program->id, 'is_active' => '1', 'credits' => 3]))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$course->id])
            ->assertSee('href="'.route('programs.show', $program).'#program-courses"', false);
        $this->get(route('courses.index', ['q' => 'DATA101']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$course->id]);
        $this->get(route('courses.index', ['q' => 'Phân tích dữ liệu']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$course->id]);
        $this->get(route('courses.index', ['is_active' => '0']))
            ->assertViewHas('courses', fn ($courses) => $courses->total() === 1 && ! $courses->first()->is_active);
    }

    public function test_coverage_and_prerequisite_filters_match_relationships(): void
    {
        $user = $this->viewer();
        $base = $this->course('BASE');
        $advanced = $this->course('ADVANCED', ['prerequisite_course_id' => $base->id]);
        $advanced->materials()->create(['title' => 'Giáo trình', 'material_type' => 'textbook']);
        $program = Program::create(['code' => 'COVERAGE', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $program->courses()->attach($advanced, ['knowledge_block' => 'Cơ sở ngành', 'is_mandatory' => true]);

        $this->actingAs($user)->get(route('courses.index', ['coverage' => 'missing_materials']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$base->id])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2 && $summary['missing_materials'] === 1 && $summary['unassigned'] === 1);
        $this->get(route('courses.index', ['coverage' => 'unassigned']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$base->id]);
        $this->get(route('courses.index', ['prerequisite' => 'has']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$advanced->id]);
        $this->get(route('courses.index', ['prerequisite' => 'none']))
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$base->id]);
    }

    public function test_sorting_pagination_and_page_links_preserve_search(): void
    {
        $user = $this->viewer();
        for ($index = 1; $index <= 21; $index++) {
            $this->course(sprintf('PAGE-%02d', $index), ['credits' => $index]);
        }

        $this->actingAs($user)->get(route('courses.index', ['q' => 'PAGE', 'sort' => 'credits']))
            ->assertViewHas('courses', fn ($courses) => $courses->perPage() === 20 && $courses->first()->course_code === 'PAGE-21')
            ->assertSee(e(route('courses.index', ['q' => 'PAGE', 'sort' => 'credits', 'page' => 2])), false);
        $this->get(route('courses.index', ['q' => 'PAGE', 'sort' => 'credits', 'page' => 2]))
            ->assertViewHas('courses', fn ($courses) => $courses->count() === 1 && $courses->first()->course_code === 'PAGE-01');
        $this->get(route('courses.index', ['per_page' => 30]))
            ->assertViewHas('courses', fn ($courses) => $courses->perPage() === 30 && $courses->count() === 21);
    }

    public function test_read_only_user_sees_links_without_mutation_actions(): void
    {
        $user = $this->viewer();
        $course = $this->course('READ');

        $this->actingAs($user)->get(route('courses.index'))
            ->assertSee('href="'.route('courses.show', $course).'#course-materials"', false)
            ->assertDontSee('href="'.route('courses.create').'"', false)
            ->assertDontSee('href="'.route('courses.edit', $course).'"', false);
        $this->get(route('courses.edit', $course))->assertForbidden();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = $this->viewer();

        $this->actingAs($user)->get(route('courses.index', ['sort' => 'name_vi desc', 'per_page' => 10000, 'credits' => 0]))
            ->assertSessionHasErrors(['sort', 'per_page', 'credits']);
    }
}
