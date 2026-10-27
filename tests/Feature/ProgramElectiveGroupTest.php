<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\User;
use App\Services\ProgramCreditReconciler;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgramElectiveGroupTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên');
        $this->actingAs($user);

        return $user;
    }

    private function program(): Program
    {
        return Program::create(['code' => 'GROUPS', 'name_vi' => 'CT nhóm tự chọn', 'degree_level' => 'dai_hoc', 'total_credits' => 6, 'elective_credits_required' => 6, 'required_knowledge_blocks' => ['Chuyên ngành']]);
    }

    public function test_two_independent_groups_in_one_block_are_snapshotted_and_changes_do_not_mutate_versions(): void
    {
        $user = $this->officer();
        $program = $this->program();
        foreach (['A', 'B'] as $code) {
            $this->post(route('programs.elective-groups.store', $program), ['code' => $code, 'name' => 'Nhóm '.$code, 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3])->assertSessionHasNoErrors()->assertRedirect();
            $course = Course::create(['course_code' => 'HP'.$code, 'name_vi' => 'Học phần '.$code, 'credits' => 3]);
            $this->post(route('programs.courses.attach', $program), ['course_id' => $course->id, 'knowledge_block' => 'Chuyên ngành', 'is_mandatory' => 0, 'elective_group_code' => $code])->assertSessionHasNoErrors()->assertRedirect();
        }
        $analysis = app(ProgramCreditReconciler::class)->forProgram($program->fresh());
        $this->assertSame([0, 0], array_column($analysis['elective_groups'], 'credits_missing'));
        $this->assertSame([], array_values(array_filter($analysis['issues'], fn ($issue) => $issue['severity'] === 'error')));
        $version = app(ProgramVersionService::class)->capture($program, 2026, '01/QĐ', '2026-01-01', $user->id);
        $this->assertCount(2, $version->snapshot['elective_groups']);
        $this->post(route('programs.elective-groups.store', $program), ['code' => 'A', 'name' => 'Nhóm A mới', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 4])->assertRedirect();
        $this->assertSame(3, $version->fresh()->snapshot['elective_groups'][0]['credits_required']);
        $this->get(route('programs.versions.show', [$program, $version]))->assertOk()->assertSee('Nhóm A')->assertSee('Nhóm B');
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program', 'subject_id' => $program->id, 'action' => 'elective_groups_updated']);
        $this->delete(route('programs.elective-groups.destroy', [$program, 'A']))->assertSessionHasErrors('elective_group');
    }

    public function test_sufficient_total_electives_cannot_hide_a_shortage_in_one_group(): void
    {
        $this->officer();
        $program = $this->program();
        $program->update(['elective_groups' => [
            ['code' => 'A', 'name' => 'Nhóm A', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3],
            ['code' => 'B', 'name' => 'Nhóm B', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3],
        ]]);
        $course = Course::create(['course_code' => 'ONLY-A', 'name_vi' => 'Học phần nhóm A', 'credits' => 6]);
        $program->courses()->attach($course, ['knowledge_block' => 'Chuyên ngành', 'is_mandatory' => false, 'elective_group_code' => 'A']);
        $this->get(route('programs.show', $program))->assertOk()->assertSee('Nhóm tự chọn Nhóm B thiếu 3 tín chỉ');
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2026, 'decision_number' => '01/QĐ', 'decision_date' => '2026-01-01'])->assertRedirect();
        $version = $program->versions()->firstOrFail();
        $this->post(route('programs.versions.submit', [$program, $version]), ['effective_from' => '2026-01-01'])->assertSessionHasErrors('version');
        $this->assertSame('draft', $version->fresh()->status);
        $this->put(route('programs.courses.update', [$program, $course]), ['knowledge_block' => 'Chuyên ngành', 'is_mandatory' => 1, 'elective_group_code' => 'A'])->assertSessionHasErrors('elective_group_code');
        $this->put(route('programs.courses.update', [$program, $course]), ['knowledge_block' => 'Khối khác', 'is_mandatory' => 0, 'elective_group_code' => 'A'])->assertSessionHasErrors('elective_group_code');
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->post(route('programs.elective-groups.store', $program), ['code' => 'C', 'name' => 'Nhóm C', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 1])->assertForbidden();
    }

    public function test_excel_preview_reads_group_sheet_and_assignments_without_writing_curriculum(): void
    {
        $this->officer();
        $book = new Spreadsheet;
        foreach ([
            'CTDT' => [['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'], ['GROUPS', 'CT nhập nhóm', 'dai_hoc', 3, 3]],
            'PLO' => [['MaPLO', 'MoTa'], ['PLO1', 'Chuẩn đầu ra']],
            'HocPhan' => [['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy', 'MaNhomTuChon'], ['TC1', 'Tự chọn A', 3, 'Chuyên ngành', 0, 1, 'A']],
            'MaTran' => [['MaHocPhan', 'MaPLO', 'MucDongGop'], ['TC1', 'PLO1', 2]],
            'NhomTuChon' => [['MaNhom', 'TenNhom', 'KhoiKienThuc', 'TinChiCanDat'], ['A', 'Nhóm tự chọn A', 'Chuyên ngành', 3]],
        ] as $name => $rows) {
            $sheet = $name === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'ctdt-groups');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $this->post(route('programs.import'), ['file' => new UploadedFile($path, 'groups.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])->assertSessionHasNoErrors()->assertRedirect();
        $draft = ProgramExcelImport::firstOrFail();
        $this->assertSame('A', $draft->payload['courses'][0]['elective_group_code']);
        $this->assertSame(3, $draft->payload['elective_groups'][0]['credits_required']);
        $this->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Nhóm tự chọn A');
        $this->assertDatabaseCount('programs', 0);
        $owner = auth()->user();
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertRedirect();
        $this->actingAs($manager)->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Đồng ý chỉ tiêu nhóm'])->assertRedirect();
        $this->actingAs($owner)->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $program = Program::where('code', 'GROUPS')->firstOrFail();
        $this->assertSame('A', $program->elective_groups[0]['code']);
        $this->assertDatabaseHas('program_course', ['program_id' => $program->id, 'elective_group_code' => 'A']);
    }
}
