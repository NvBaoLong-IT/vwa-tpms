<?php

namespace Tests\Feature;

use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\ReportHistory;
use App\Models\Student;
use App\Models\User;
use App\Models\WarningLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));

        return $user;
    }

    private function program(string $code, string $degree = 'dai_hoc'): Program
    {
        return Program::create(['code' => $code, 'name_vi' => 'Ngành '.$code, 'degree_level' => $degree, 'total_credits' => 120, 'decision_date' => '2026-01-01']);
    }

    public function test_academic_preview_shows_filtered_rows_and_metrics_without_exporting(): void
    {
        $user = $this->viewer();
        $program = $this->program('PREVIEW');
        $student = Student::create(['student_code' => 'PREVIEW-01', 'full_name' => 'Sinh viên báo cáo', 'program_id' => $program->id, 'degree_level' => 'dai_hoc']);
        WarningLog::create(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'GPA thấp', 'status' => 'open', 'issued_at' => '2026-10-01']);
        WarningLog::create(['student_id' => $student->id, 'warning_type' => 'credit_debt', 'reason' => 'Cảnh báo năm cũ', 'status' => 'resolved', 'issued_at' => '2025-10-01']);

        $this->actingAs($user)->get(route('reports.index', ['type' => 'academic', 'academic_year' => 2026, 'program_id' => $program->id]))
            ->assertSee('Sinh viên báo cáo')
            ->assertSee('Điểm trung bình thấp')
            ->assertSee('Điểm trung bình hệ 4 thấp')
            ->assertDontSee('Cảnh báo năm cũ')
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 1 && $preview->first()['TrangThai'] === 'Chưa xử lý')
            ->assertViewHas('metrics', fn ($metrics) => $metrics['Tổng cảnh báo'] === 1 && $metrics['Chưa xử lý'] === 1);
        $this->assertDatabaseCount('report_histories', 0);
    }

    public function test_preview_and_export_use_same_program_filters(): void
    {
        $user = $this->viewer();
        $user->givePermissionTo(Permission::findOrCreate('reports.export', 'web'));
        $matching = $this->program('MATCH');
        $this->program('OTHER', 'thac_si');
        $filters = ['academic_year' => 2026, 'program_id' => $matching->id, 'degree_level' => 'dai_hoc'];

        $this->actingAs($user)->get(route('reports.index', ['type' => 'programs'] + $filters))
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 1 && $preview->first()['Ma'] === 'MATCH')
            ->assertSee('href="'.e(route('reports.export', ['programs', 'csv'] + $filters)).'"', false);
        $this->get(route('reports.export', ['programs', 'csv'] + $filters))->assertSee('MATCH')->assertDontSee('OTHER');
        $this->assertDatabaseHas('report_histories', ['report_type' => 'programs', 'row_count' => 1]);
    }

    public function test_iso_degree_filter_applies_to_preview_and_export(): void
    {
        $user = $this->viewer();
        $user->givePermissionTo(Permission::findOrCreate('reports.export', 'web'));
        $undergraduate = $this->program('DH');
        $postgraduate = $this->program('THS', 'thac_si');
        foreach ([$undergraduate, $postgraduate] as $program) {
            IsoWorkflow::create(['program_id' => $program->id, 'type' => 'minor', 'title' => 'Rà soát '.$program->code, 'start_date' => '2026-01-01', 'expected_end_date' => '2026-12-31', 'status' => 'in_progress']);
        }

        $this->actingAs($user)->get(route('reports.index', ['type' => 'iso', 'academic_year' => 2026, 'degree_level' => 'thac_si']))
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 1 && $preview->first()['TrinhDo'] === 'Thạc sĩ');
        $this->get(route('reports.export', ['iso', 'csv', 'academic_year' => 2026, 'degree_level' => 'thac_si']))
            ->assertSee('Rà soát THS')->assertDontSee('Rà soát DH');
    }

    public function test_all_report_types_render_on_web_and_ignore_unsupported_filters(): void
    {
        $user = $this->viewer();
        $program = $this->program('ALL');
        foreach (['academic', 'iso', 'programs', 'plo', 'diploma', 'blanks', 'academic_year_summary', 'annual_blanks'] as $type) {
            $this->actingAs($user)->get(route('reports.index', ['type' => $type, 'academic_year' => 2026, 'program_id' => $program->id, 'degree_level' => 'dai_hoc']))
                ->assertOk()->assertSee('Chi tiết báo cáo');
        }
        $this->get(route('reports.index', ['type' => 'academic_year_summary', 'academic_year' => 2026, 'program_id' => $program->id]))
            ->assertViewHas('filters', ['academic_year' => '2026'])
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 3);
        $this->get(route('reports.index', ['type' => 'plo', 'academic_year' => 2026]))
            ->assertViewHas('filters', []);
    }

    public function test_preview_has_independent_pagination_and_keeps_filters(): void
    {
        $user = $this->viewer();
        for ($index = 1; $index <= 26; $index++) {
            $this->program('P-'.$index);
        }

        $this->actingAs($user)->get(route('reports.index', ['type' => 'programs', 'academic_year' => 2026, 'report_page' => 2]))
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 26 && $preview->count() === 1 && $preview->currentPage() === 2)
            ->assertSee(e(route('reports.index', ['type' => 'programs', 'academic_year' => 2026, 'report_page' => 1])), false);
    }

    public function test_read_only_viewer_sees_own_history_without_download_controls(): void
    {
        $user = $this->viewer();
        $other = User::factory()->create();
        ReportHistory::create(['user_id' => $user->id, 'report_type' => 'programs', 'format' => 'csv', 'row_count' => 0]);
        ReportHistory::create(['user_id' => $other->id, 'report_type' => 'diploma', 'format' => 'pdf', 'row_count' => 0]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertViewHas('history', fn ($history) => $history->total() === 1 && $history->first()->user_id === $user->id)
            ->assertDontSee('Xuất báo cáo')
            ->assertSee('Xem số liệu hiện tại')
            ->assertSee('href="'.e(route('reports.index', ['type' => 'programs', 'academic_year' => ''])).'"', false);
        $this->get(route('reports.export', ['programs', 'csv']))->assertForbidden();
        $this->get(route('reports.index', ['type' => 'unknown']))->assertSessionHasErrors('type');
    }
}
