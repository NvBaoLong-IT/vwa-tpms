<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CurriculumCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $role = Role::findOrCreate(implode('-', $permissions), 'web');
        $role->syncPermissions(collect($permissions)->map(fn ($permission) => Permission::findOrCreate($permission, 'web'))->all());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_credit_warning_and_scoped_plo_levels(): void
    {
        $officer = $this->userWith(['programs.view', 'programs.update', 'courses.assign']);
        $program = Program::create(['code' => 'P1', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 10, 'elective_credits_required' => 4]);
        $course = Course::create(['course_code' => 'C1', 'name_vi' => 'Học phần', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Mô tả']);
        $otherPlo = Program::create(['code' => 'P2', 'name_vi' => 'Khác', 'degree_level' => 'dai_hoc', 'total_credits' => 10])->plos()->create(['code' => 'PLO1', 'description_vi' => 'Khác']);

        $this->actingAs($officer)->get(route('programs.show', $program))->assertOk()->assertSee('Tổng tín chỉ không khớp')->assertSee('thiếu 4 tín chỉ');
        $this->get(route('programs.matrix', $program))->assertOk()->assertSee('Mức đáp ứng chuẩn đầu ra')->assertSee('PLO1');
        $this->put(route('programs.matrix.update', $program), ['levels' => [$course->id => [$plo->id => 3]]])->assertRedirect();
        $this->assertDatabaseHas('course_plo_matrix', ['course_id' => $course->id, 'plo_id' => $plo->id, 'contribution_level' => 3]);
        $this->put(route('programs.matrix.update', $program), ['levels' => [$course->id => [$otherPlo->id => 2]]])->assertStatus(422);
        $this->put(route('programs.matrix.update', $program), ['levels' => [$course->id => [$plo->id => 4]]])->assertSessionHasErrors();
    }

    public function test_material_catalog_keeps_legacy_history_without_library_workflow(): void
    {
        $officer = $this->userWith(['programs.view', 'courses.view', 'courses.update', 'courses.delete']);
        $course = Course::create(['course_code' => 'C2', 'name_vi' => 'Học phần', 'credits' => 3]);
        $this->actingAs($officer)->post(route('courses.materials.store', $course), ['title' => 'Giáo trình A', 'material_type' => 'textbook'])->assertRedirect();
        $material = $course->materials()->firstOrFail();
        $material->confirmations()->create(['user_id' => $officer->id, 'status' => 'confirmed', 'note' => 'Phản hồi cũ']);
        $material->update(['library_status' => 'confirmed']);
        $this->post("/courses/{$course->id}/materials/{$material->id}/request-review")->assertNotFound();
        $this->post("/courses/{$course->id}/materials/{$material->id}/confirm", ['status' => 'unavailable'])->assertNotFound();
        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Phản hồi Thư viện đã lưu (1)')
            ->assertDontSee('Gửi Thư viện xác nhận')
            ->assertDontSee('Xác nhận học liệu');
        $this->actingAs($officer)->put(route('courses.materials.update', [$course, $material]), ['title' => 'Giáo trình B', 'material_type' => 'textbook'])->assertRedirect();
        $this->assertDatabaseHas('course_materials', ['id' => $material->id, 'library_status' => 'confirmed']);
        $this->delete(route('courses.materials.destroy', [$course, $material]))->assertSessionHasErrors('material');
        $this->delete(route('courses.destroy', $course))->assertSessionHasErrors('course');
        $this->assertDatabaseCount('course_material_confirmations', 1);
    }

    public function test_excel_import_creates_curriculum_and_rolls_back_invalid_matrix(): void
    {
        $officer = $this->userWith(['programs.view', 'programs.import']);
        $this->actingAs($officer)->get(route('programs.import.form'))->assertOk();
        $this->get(route('programs.import.template'))->assertOk();
        $this->actingAs($officer)->post(route('programs.import'), ['file' => $this->workbook('PLO1')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('programs', ['code' => 'PX', 'elective_credits_required' => 3]);
        $this->assertDatabaseHas('program_course', ['knowledge_block' => 'Cơ sở ngành', 'is_mandatory' => 0]);
        $this->assertDatabaseHas('course_plo_matrix', ['contribution_level' => 2]);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program', 'action' => 'imported']);

        $this->post(route('programs.import'), ['file' => $this->workbook('UNKNOWN', 'PY')])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('programs', ['code' => 'PY']);
    }

    private function workbook(string $matrixPlo, string $programCode = 'PX'): UploadedFile
    {
        $book = new Spreadsheet;
        $sheets = [
            'CTDT' => [['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'], [$programCode, 'Chương trình mới', 'dai_hoc', 3, 3]],
            'PLO' => [['MaPLO', 'MoTa'], ['PLO1', 'Chuẩn đầu ra']],
            'HocPhan' => [['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy'], ['CX', 'Học phần mới', 3, 'Cơ sở ngành', 0, 1]],
            'MaTran' => [['MaHocPhan', 'MaPLO', 'MucDongGop'], ['CX', $matrixPlo, 2]],
        ];
        foreach ($sheets as $index => $rows) {
            $sheet = $index === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($index);
            $sheet->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'ctdt');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'ctdt.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
