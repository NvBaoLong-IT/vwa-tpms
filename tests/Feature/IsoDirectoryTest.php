<?php

namespace Tests\Feature;

use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IsoDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('programs.view', 'web'), Permission::findOrCreate('iso.view', 'web')]);

        return $user;
    }

    private function workflow(string $code, array $attributes = []): IsoWorkflow
    {
        $program = Program::create(['code' => $code, 'name_vi' => 'Chương trình '.$code, 'degree_level' => 'dai_hoc', 'total_credits' => 120]);

        return IsoWorkflow::create($attributes + ['program_id' => $program->id, 'type' => 'minor', 'title' => 'Rà soát '.$code, 'start_date' => '2026-01-01', 'expected_end_date' => '2026-12-31', 'status' => 'in_progress']);
    }

    public function test_progress_next_step_and_overdue_counts_use_date_boundaries(): void
    {
        $this->travelTo('2026-10-01');
        $user = $this->viewer();
        $workflow = $this->workflow('PROGRESS');
        $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Bước hoàn thành', 'deadline' => '2026-09-01', 'status' => 'completed']);
        $workflow->steps()->create(['step_number' => 2, 'step_name' => 'Bước tiếp theo', 'deadline' => '2026-09-30', 'status' => 'in_progress', 'assigned_to_user_id' => $user->id]);
        $workflow->steps()->create(['step_number' => 3, 'step_name' => 'Đến hạn hôm nay', 'deadline' => '2026-10-01', 'status' => 'not_started']);

        $this->actingAs($user)->get(route('iso.index'))
            ->assertViewHas('workflows', fn ($workflows) => $workflows->first()->completed_steps === 1 && $workflows->first()->steps_count === 3 && $workflows->first()->overdue_steps === 1)
            ->assertSee('Bước tiếp theo')
            ->assertSee($user->name)
            ->assertSee('33%')
            ->assertSee('1 bước quá hạn')
            ->assertDontSee('href="'.route('iso.create').'"', false);
    }

    public function test_search_and_filters_preserve_program_status_and_type(): void
    {
        $user = $this->viewer();
        $matching = $this->workflow('MATCH');
        $this->workflow('OTHER', ['type' => 'major', 'status' => 'completed']);

        $this->actingAs($user)->get(route('iso.index', ['q' => 'MATCH', 'program_id' => $matching->program_id, 'status' => 'in_progress', 'type' => 'minor']))
            ->assertViewHas('workflows', fn ($workflows) => $workflows->pluck('id')->all() === [$matching->id]);
        $this->get(route('iso.index', ['q' => 'Chương trình MATCH']))
            ->assertViewHas('workflows', fn ($workflows) => $workflows->pluck('id')->all() === [$matching->id]);
    }

    public function test_global_overdue_summary_excludes_cancelled_workflows(): void
    {
        $this->travelTo('2026-10-01');
        $user = $this->viewer();
        $active = $this->workflow('ACTIVE');
        $cancelled = $this->workflow('CANCELLED', ['status' => 'cancelled']);
        foreach ([$active, $cancelled] as $workflow) {
            $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Quá hạn', 'deadline' => '2026-09-01', 'status' => 'in_progress']);
        }

        $this->actingAs($user)->get(route('iso.index', ['status' => 'in_progress', 'overdue' => 1]))
            ->assertViewHas('workflows', fn ($workflows) => $workflows->pluck('id')->all() === [$active->id])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2 && $summary['overdue'] === 1);
    }

    public function test_deadline_sort_pagination_and_invalid_options(): void
    {
        $user = $this->viewer();
        $later = $this->workflow('LATER');
        $earlier = $this->workflow('EARLIER', ['expected_end_date' => '2026-10-01']);

        $this->actingAs($user)->get(route('iso.index', ['sort' => 'deadline', 'per_page' => 30]))
            ->assertViewHas('workflows', fn ($workflows) => $workflows->pluck('id')->all() === [$earlier->id, $later->id] && $workflows->perPage() === 30);
        $this->get(route('iso.index', ['sort' => 'expected_end_date desc', 'per_page' => 1000]))
            ->assertSessionHasErrors(['sort', 'per_page']);
    }
}
