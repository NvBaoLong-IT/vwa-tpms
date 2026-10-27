<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgramExcelPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên');
        $this->actingAs($user);
    }

    public function test_preview_writes_no_curriculum_and_confirmation_is_required_and_single_use(): void
    {
        $this->post(route('programs.import'), ['file' => $this->workbook()])->assertRedirect();
        $draft = ProgramExcelImport::firstOrFail();
        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('plos', 0);
        $this->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Các thay đổi dự kiến')->assertSee('HP01');
        $this->post(route('programs.import.confirm', $draft))->assertSessionHasErrors('confirm');
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('programs', 0);
        $this->approveAndApplyLatestExcel();
        $this->assertDatabaseHas('programs', ['code' => 'EXCEL01']);
        $this->assertDatabaseHas('program_excel_imports', ['id' => $draft->id, 'status' => 'imported']);
        $this->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Đã nhập thành công')->assertDontSee('Dữ liệu đã thay đổi sau khi xem trước');
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertStatus(422);
    }

    public function test_stale_preview_does_not_overwrite_newer_changes(): void
    {
        $program = Program::create(['code' => 'EXCEL01', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $this->post(route('programs.import'), ['file' => $this->workbook()])->assertRedirect();
        $draft = ProgramExcelImport::firstOrFail();
        $program->update(['name_vi' => 'Nội dung người khác vừa sửa']);
        $this->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Dữ liệu đã thay đổi');
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertSessionHasErrors('file');
        $this->assertSame('Nội dung người khác vừa sửa', $program->fresh()->name_vi);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_shared_course_changes_are_blocked_and_duplicate_codes_rejected(): void
    {
        $program = Program::create(['code' => 'OTHER', 'name_vi' => 'CT khác', 'degree_level' => 'dai_hoc', 'total_credits' => 4]);
        $course = Course::create(['course_code' => 'HP01', 'name_vi' => 'Học phần dùng chung', 'credits' => 4]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương']);
        $this->post(route('programs.import'), ['file' => $this->workbook()])->assertSessionHasErrors('file');
        $this->assertSame(4, (int) $course->fresh()->credits);
        $this->assertDatabaseCount('program_excel_imports', 0);
        $this->post(route('programs.import'), ['file' => $this->workbook(true)])->assertSessionHasErrors('file');
    }

    public function test_preview_is_owned_expires_and_requires_import_permission(): void
    {
        $this->post(route('programs.import'), ['file' => $this->workbook()])->assertRedirect();
        $draft = ProgramExcelImport::firstOrFail();
        $owner = auth()->user();
        $other = User::factory()->create();
        $other->assignRole('Chuyên viên');
        $this->actingAs($other)->get(route('programs.import.preview', $draft))->assertForbidden();
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertForbidden();
        $this->actingAs($owner);
        $this->travel(3)->hours();
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertStatus(422);
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->get(route('programs.import.preview', $draft))->assertForbidden();
        $this->assertDatabaseCount('programs', 0);
    }

    private function approveAndApplyLatestExcel(): void
    {
        $owner = auth()->user();
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $draft = ProgramExcelImport::latest('id')->firstOrFail();
        $this->actingAs($manager)->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Đồng ý bản nhập'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($owner)->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function workbook(bool $duplicate = false): UploadedFile
    {
        $book = new Spreadsheet;
        $courses = [['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy'], ['HP01', 'Học phần mới', 3, 'Đại cương', 1, 1]];
        if ($duplicate) {
            $courses[] = ['hp01', 'Trùng', 3, 'Đại cương', 1, 1];
        }
        foreach ([
            'CTDT' => [['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'], ['EXCEL01', 'CT từ Excel', 'dai_hoc', 3, 0]],
            'PLO' => [['MaPLO', 'MoTa'], ['PLO1', 'Chuẩn đầu ra']],
            'HocPhan' => $courses,
            'MaTran' => [['MaHocPhan', 'MaPLO', 'MucDongGop'], ['HP01', 'PLO1', 2]],
        ] as $name => $rows) {
            $sheet = $name === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'ctdt-preview');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'ctdt.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
