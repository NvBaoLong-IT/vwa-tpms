<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\CurriculumEditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CoursePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        $user = User::factory()->create();
        foreach (['programs.view', 'courses.create', 'courses.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function course(string $code, ?int $prerequisite = null): Course
    {
        return Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3, 'theory_hours' => 30, 'practice_hours' => 0, 'prerequisite_course_id' => $prerequisite]);
    }

    private function input(Course $course, ?int $prerequisite): array
    {
        return ['editing_hash' => app(CurriculumEditGuard::class)->courseHash($course), 'course_code' => $course->course_code, 'name_vi' => $course->name_vi, 'credits' => 3, 'theory_hours' => 30, 'practice_hours' => 0, 'prerequisite_course_id' => $prerequisite, 'is_active' => 1];
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([5])]
    public function test_update_rejects_direct_and_indirect_cycles_without_saving(int $length): void
    {
        $user = $this->officer();
        $target = $this->course('A');
        $last = $target;
        for ($index = 1; $index <= $length; $index++) {
            $last = $this->course('CHAIN'.$index, $last->id);
        }
        $this->actingAs($user)->from(route('courses.edit', $target))->put(route('courses.update', $target), $this->input($target, $last->id))
            ->assertRedirect(route('courses.edit', $target))->assertSessionHasErrors('prerequisite_course_id');
        $this->assertNull($target->fresh()->prerequisite_course_id);
        $this->get(route('courses.edit', $target))->assertSee('Học phần tiên quyết tạo thành vòng lặp');
    }

    public function test_create_rejects_an_existing_cyclic_chain(): void
    {
        $user = $this->officer();
        $a = $this->course('A');
        $b = $this->course('B', $a->id);
        $a->update(['prerequisite_course_id' => $b->id]);
        $input = $this->input($a, $a->id);
        $input['course_code'] = 'NEW';
        $this->actingAs($user)->post(route('courses.store'), $input)->assertSessionHasErrors('prerequisite_course_id');
        $this->assertDatabaseMissing('courses', ['course_code' => 'NEW']);
    }

    public function test_valid_chain_can_be_created_updated_and_removed(): void
    {
        $user = $this->officer();
        $a = $this->course('A');
        $b = $this->course('B', $a->id);
        $c = $this->course('C');
        $this->actingAs($user)->put(route('courses.update', $c), $this->input($c, $b->id))->assertRedirect(route('courses.show', $c));
        $this->assertSame($b->id, $c->fresh()->prerequisite_course_id);
        $input = $this->input($c, $c->id);
        $input['course_code'] = 'NEW';
        $this->post(route('courses.store'), $input)->assertRedirect(route('courses.index'));
        $this->assertDatabaseHas('courses', ['course_code' => 'NEW', 'prerequisite_course_id' => $c->id]);
        $this->put(route('courses.update', $c), $this->input($c, null))->assertRedirect(route('courses.show', $c));
        $this->assertNull($c->fresh()->prerequisite_course_id);
    }

    public function test_self_reference_is_rejected(): void
    {
        $a = $this->course('A');
        $this->actingAs($this->officer())->put(route('courses.update', $a), $this->input($a, $a->id))->assertSessionHasErrors('prerequisite_course_id');
        $this->assertNull($a->fresh()->prerequisite_course_id);
    }
}
