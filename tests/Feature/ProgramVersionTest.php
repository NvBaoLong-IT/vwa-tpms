<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CoursePloMatrix;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgramVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfficeRolesSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_manual_program_form_saves_required_blocks_without_duplicates(): void
    {
        $specialist = $this->user('Chuyên viên');
        $this->actingAs($specialist)->post(route('programs.store'), [
            'code' => 'FORM-01', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc',
            'total_credits' => 120, 'review_cycle_years' => 3,
            'required_blocks' => "Đại cương\nCơ sở ngành\nđại cương",
        ])->assertRedirect(route('programs.index'));

        $this->assertSame(['Đại cương', 'Cơ sở ngành'], Program::where('code', 'FORM-01')->firstOrFail()->required_knowledge_blocks);
    }

    public function test_versions_keep_course_plo_and_matrix_snapshots_across_edits(): void
    {
        $specialist = $this->user('Chuyên viên');
        $program = Program::create(['code' => 'VERSION-01', 'name_vi' => 'Kinh tế số', 'degree_level' => 'dai_hoc', 'total_credits' => 5, 'elective_credits_required' => 2, 'required_knowledge_blocks' => ['Đại cương', 'Chuyên ngành']]);
        $mandatory = Course::create(['course_code' => 'OLD101', 'name_vi' => 'Học phần cũ', 'credits' => 3]);
        $elective = Course::create(['course_code' => 'OLD201', 'name_vi' => 'Tự chọn cũ', 'credits' => 2]);
        $program->courses()->attach($mandatory, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $program->courses()->attach($elective, ['knowledge_block' => 'Chuyên ngành', 'is_mandatory' => false]);
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Chuẩn cũ']);
        CoursePloMatrix::create(['course_id' => $mandatory->id, 'plo_id' => $plo->id, 'contribution_level' => 2]);

        $this->actingAs($specialist)->post(route('programs.versions.store', $program), ['cohort_year' => 2024, 'decision_number' => '123/QĐ', 'decision_date' => '2024-08-01'])->assertRedirect();
        $old = $program->versions()->firstOrFail();
        $this->assertSame(1, $old->revision);
        $this->assertSame('PLO1', $old->snapshot['matrix'][0]['plo_code']);
        $this->get(route('programs.versions.show', [$program, $old]))->assertOk()->assertSee('Học phần cũ')->assertSee('Chuẩn cũ');

        $program->update(['name_vi' => 'Kinh tế số mới', 'total_credits' => 4, 'elective_credits_required' => 0, 'required_knowledge_blocks' => ['Cơ sở ngành']]);
        $program->courses()->detach([$mandatory->id, $elective->id]);
        $newCourse = Course::create(['course_code' => 'NEW101', 'name_vi' => 'Học phần mới', 'credits' => 4]);
        $program->courses()->attach($newCourse, ['knowledge_block' => 'Cơ sở ngành', 'is_mandatory' => true]);
        $plo->update(['description_vi' => 'Chuẩn mới']);
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2025, 'decision_number' => '456/QĐ', 'decision_date' => '2025-08-01'])->assertRedirect();

        $this->assertSame(['OLD101', 'OLD201'], collect($old->fresh()->snapshot['courses'])->pluck('course_code')->all());
        $this->assertSame('Chuẩn cũ', $old->fresh()->snapshot['plos'][0]['description_vi']);
        $new = $program->versions()->where('cohort_year', 2025)->firstOrFail();
        $this->assertSame(['NEW101'], collect($new->snapshot['courses'])->pluck('course_code')->all());
        $this->assertSame('Kinh tế số mới', $new->snapshot['program']['name_vi']);
    }

    public function test_credit_reconciliation_warns_about_mismatch_and_missing_blocks(): void
    {
        $specialist = $this->user('Chuyên viên');
        $program = Program::create(['code' => 'CREDIT-01', 'name_vi' => 'Đối chiếu', 'degree_level' => 'dai_hoc', 'total_credits' => 10, 'elective_credits_required' => 4, 'required_knowledge_blocks' => ['Đại cương', 'Cơ sở ngành']]);
        $course = Course::create(['course_code' => 'GEN101', 'name_vi' => 'Đại cương', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);

        $this->actingAs($specialist)->get(route('programs.show', $program))
            ->assertOk()
            ->assertSee('Tổng tín chỉ không khớp')
            ->assertSee('Tổng tín chỉ học phần gắn là 3')
            ->assertSee('thiếu 4 tín chỉ')
            ->assertSee('Thiếu khối kiến thức: Cơ sở ngành');
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2024, 'decision_number' => '123/QĐ', 'decision_date' => '2024-08-01'])->assertRedirect();
        $version = $program->versions()->firstOrFail();
        $this->get(route('programs.versions.show', [$program, $version]))->assertOk()->assertSee('Thiếu khối kiến thức: Cơ sở ngành');
    }

    public function test_publish_requires_manager_and_archives_previous_revision(): void
    {
        $specialist = $this->user('Chuyên viên');
        $viewer = $this->user('Xem');
        $manager = $this->user('Lãnh đạo phòng');
        $program = Program::create(['code' => 'PUBLISH-01', 'name_vi' => 'Công bố', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $input = ['cohort_year' => 2024, 'decision_number' => '123/QĐ', 'decision_date' => '2024-08-01'];

        $this->actingAs($viewer)->post(route('programs.versions.store', $program), $input)->assertForbidden();
        $this->actingAs($specialist)->post(route('programs.versions.store', $program), $input)->assertRedirect();
        $first = $program->versions()->firstOrFail();
        $this->post(route('programs.versions.publish', [$program, $first]))->assertForbidden();
        $this->actingAs($manager)->post(route('programs.versions.publish', [$program, $first]))->assertRedirect();
        $this->assertSame('published', $first->fresh()->status);
        $this->post(route('programs.versions.publish', [$program, $first]))->assertStatus(422);
        $this->post(route('programs.versions.store', $program), $input)->assertRedirect();
        $second = $program->versions()->orderByDesc('revision')->firstOrFail();
        $this->assertSame(2, $second->revision);
        $this->post(route('programs.versions.publish', [$program, $second]))->assertRedirect();
        $this->assertSame('archived', $first->fresh()->status);
        $this->assertSame('published', $second->fresh()->status);
        $this->delete(route('programs.destroy', $program))->assertSessionHasErrors('program');
        $this->actingAs($viewer)->get(route('programs.versions.show', [$program, $second]))->assertOk();
        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'Khác', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $this->get(route('programs.versions.show', [$other, $second]))->assertNotFound();
    }

    public function test_excel_import_replaces_working_curriculum_but_preserves_older_version(): void
    {
        $specialist = $this->user('Chuyên viên');
        $this->actingAs($specialist)->post(route('programs.import'), ['file' => $this->workbook('OLD101', 'Đại cương', 3, 'PLO1')])->assertRedirect();
        $program = Program::where('code', 'IMPORT-01')->firstOrFail();
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2024, 'decision_number' => '123/QĐ', 'decision_date' => '2024-08-01'])->assertRedirect();
        $old = $program->versions()->firstOrFail();

        $this->post(route('programs.import'), ['file' => $this->workbook('NEW101', 'Chuyên ngành', 4, 'PLO2')])->assertRedirect();
        $this->assertSame(['NEW101'], $program->fresh()->courses->pluck('course_code')->all());
        $this->assertSame(['PLO2'], $program->fresh()->plos->pluck('code')->all());
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2025, 'decision_number' => '456/QĐ', 'decision_date' => '2025-08-01'])->assertRedirect();
        $new = $program->versions()->where('cohort_year', 2025)->firstOrFail();
        $this->assertSame(['OLD101'], collect($old->fresh()->snapshot['courses'])->pluck('course_code')->all());
        $this->assertSame(['NEW101'], collect($new->snapshot['courses'])->pluck('course_code')->all());
        $this->assertSame(['Chuyên ngành'], $new->snapshot['required_blocks']);
    }

    private function workbook(string $courseCode, string $block, int $credits, string $ploCode): UploadedFile
    {
        $book = new Spreadsheet;
        $sheets = [
            'CTDT' => [['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'], ['IMPORT-01', 'Chương trình nhập', 'dai_hoc', $credits, 0]],
            'PLO' => [['MaPLO', 'MoTa'], [$ploCode, 'Chuẩn đầu ra']],
            'HocPhan' => [['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy'], [$courseCode, 'Học phần nhập', $credits, $block, 1, 1]],
            'MaTran' => [['MaHocPhan', 'MaPLO', 'MucDongGop'], [$courseCode, $ploCode, 2]],
            'KhoiKienThuc' => [['TenKhoi'], [$block]],
        ];
        foreach ($sheets as $name => $rows) {
            $sheet = $name === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'ctdt');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'ctdt.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
