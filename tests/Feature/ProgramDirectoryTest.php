<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgramDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(bool $canExport = false): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('programs.view', 'web'));
        if ($canExport) {
            $user->givePermissionTo(Permission::findOrCreate('reports.export', 'web'));
        }

        return $user;
    }

    private function program(string $code, array $attributes = []): Program
    {
        return Program::create($attributes + ['code' => $code, 'name_vi' => 'Chương trình '.$code, 'degree_level' => 'dai_hoc', 'total_credits' => 120, 'is_active' => true]);
    }

    public function test_search_matches_code_vietnamese_and_english_names_with_other_filters(): void
    {
        $user = $this->viewer();
        $matching = $this->program('DIGITAL-01', ['name_vi' => 'Kinh tế số', 'name_en' => 'Digital Economics']);
        $this->program('OTHER', ['name_vi' => 'Không phù hợp', 'is_active' => false, 'name_en' => 'Digital Studies']);

        $this->actingAs($user)->get(route('programs.index', ['q' => 'Digital', 'is_active' => '1', 'degree_level' => 'dai_hoc']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$matching->id])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2 && $summary['active'] === 1);
        $this->get(route('programs.index', ['q' => 'Kinh tế số']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$matching->id]);
        $this->get(route('programs.index', ['q' => 'DIGITAL-01']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$matching->id]);
        $this->get(route('programs.index', ['is_active' => '0']))
            ->assertViewHas('programs', fn ($programs) => $programs->total() === 1 && ! $programs->first()->is_active);
    }

    public function test_completeness_filters_and_summary_match_relationships(): void
    {
        $user = $this->viewer();
        $empty = $this->program('EMPTY');
        $complete = $this->program('COMPLETE');
        $course = Course::create(['course_code' => 'DIR101', 'name_vi' => 'Học phần', 'credits' => 3]);
        $complete->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $complete->versions()->create(['cohort_year' => 2026, 'revision' => 1, 'decision_number' => 'QĐ01', 'decision_date' => '2026-01-01', 'snapshot' => []]);

        $this->actingAs($user)->get(route('programs.index', ['completeness' => 'missing_courses']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$empty->id])
            ->assertViewHas('summary', fn ($summary) => $summary['missing_courses'] === 1 && $summary['missing_versions'] === 1);
        $this->get(route('programs.index', ['completeness' => 'missing_versions']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$empty->id]);
    }

    public function test_sorting_pagination_and_export_link_keep_filters(): void
    {
        $user = $this->viewer(true);
        for ($index = 1; $index <= 16; $index++) {
            $this->program(sprintf('PAGE-%02d', $index));
        }
        $filters = ['q' => 'PAGE', 'sort' => 'code', 'per_page' => 15, 'page' => 2];

        $this->actingAs($user)->get(route('programs.index', $filters))
            ->assertViewHas('programs', fn ($programs) => $programs->perPage() === 15 && $programs->currentPage() === 2 && $programs->first()->code === 'PAGE-16')
            ->assertSee('href="'.e(route('programs.export.csv', ['q' => 'PAGE', 'sort' => 'code', 'per_page' => 15])).'"', false);
        $this->get(route('programs.index', ['per_page' => 30]))
            ->assertViewHas('programs', fn ($programs) => $programs->perPage() === 30 && $programs->count() === 16);
        $this->get(route('programs.index', ['sort' => 'newest']))
            ->assertViewHas('programs', fn ($programs) => $programs->total() === 16);
    }

    public function test_csv_exports_all_filtered_rows_and_neutralizes_spreadsheet_formulas(): void
    {
        $user = $this->viewer(true);
        $this->program('EXPORT-01', ['name_vi' => '=1+1']);
        for ($index = 2; $index <= 16; $index++) {
            $this->program(sprintf('EXPORT-%02d', $index));
        }
        $this->program('EXCLUDED');
        $response = $this->actingAs($user)->get(route('programs.export.csv', ['q' => 'EXPORT', 'sort' => 'code', 'per_page' => 15, 'page' => 2]));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertDownload();
        $csv = $response->streamedContent();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
        $this->assertCount(17, $rows);
        $this->assertSame('EXPORT-01', $rows[1][0]);
        $this->assertSame("'=1+1", $rows[1][1]);
        $this->assertSame('EXPORT-16', $rows[16][0]);
        $this->assertStringNotContainsString('EXCLUDED', $csv);
    }

    public function test_viewer_cannot_export_and_does_not_receive_mutation_links(): void
    {
        $user = $this->viewer();
        $program = $this->program('READ-ONLY');
        $this->actingAs($user)->get(route('programs.index'))
            ->assertSee('href="'.route('programs.show', $program).'#program-courses"', false)
            ->assertDontSee('href="'.route('programs.create').'"', false)
            ->assertDontSee('href="'.route('programs.edit', $program).'"', false)
            ->assertDontSee('href="'.route('programs.import.form').'"', false)
            ->assertDontSee('Xuất danh sách CSV');
        $this->get(route('programs.export.csv'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('programs.export.csv'))->assertForbidden();
    }

    public function test_unsupported_sort_and_page_size_are_rejected(): void
    {
        $user = $this->viewer(true);

        $this->actingAs($user)->get(route('programs.index', ['sort' => 'name_vi desc', 'per_page' => 100000]))
            ->assertSessionHasErrors(['sort', 'per_page']);
        $this->get(route('programs.export.csv', ['sort' => 'name_vi desc']))
            ->assertSessionHasErrors('sort');
    }

    public function test_newest_and_updated_sorts_use_the_requested_date(): void
    {
        $user = $this->viewer();
        $older = $this->program('OLDER');
        $newer = $this->program('NEWER');
        $older->forceFill(['created_at' => '2026-01-01', 'updated_at' => '2026-03-01'])->save();
        $newer->forceFill(['created_at' => '2026-02-01', 'updated_at' => '2026-02-01'])->save();

        $this->actingAs($user)->get(route('programs.index', ['sort' => 'newest']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$newer->id, $older->id]);
        $this->get(route('programs.index', ['sort' => 'updated']))
            ->assertViewHas('programs', fn ($programs) => $programs->pluck('id')->all() === [$older->id, $newer->id]);
    }
}
