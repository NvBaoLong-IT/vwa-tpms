<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class CurriculumBrowserDemoSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        $testingRoot = str_replace('\\', '/', storage_path('framework/testing/'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, $testingRoot) || ! str_contains(basename($database), 'curriculum-browser-')) {
            throw new RuntimeException('Chỉ tạo dữ liệu trình duyệt trong SQLite kiểm thử riêng ở storage/framework/testing.');
        }
        $this->call(OfficeRolesSeeder::class);
        foreach (['officer' => 'Chuyên viên', 'manager' => 'Lãnh đạo phòng', 'viewer' => 'Xem'] as $code => $role) {
            $user = User::factory()->create(['name' => 'DEMO - '.$role, 'email' => $code.'@curriculum.test', 'is_active' => true, 'must_change_password' => false]);
            $user->assignRole($role);
        }
        $program = Program::create(['code' => 'DEMO-CTDT', 'name_vi' => 'DEMO - Chương trình kiểm soát đào tạo', 'degree_level' => 'dai_hoc', 'total_credits' => 9, 'elective_credits_required' => 6, 'review_cycle_years' => 3, 'required_knowledge_blocks' => ['Đại cương', 'Chuyên ngành'], 'elective_groups' => [
            ['code' => 'A', 'name' => 'Nhóm phương pháp', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3],
            ['code' => 'B', 'name' => 'Nhóm ứng dụng', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3],
        ]]);
        foreach ([['BB01', 'Học phần bắt buộc', true, 'Đại cương', null], ['TC01', 'Phương pháp nghiên cứu', false, 'Chuyên ngành', 'A'], ['TC02', 'Ứng dụng nghiệp vụ', false, 'Chuyên ngành', 'A']] as [$code, $name, $mandatory, $block, $group]) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $name, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => $block, 'is_mandatory' => $mandatory, 'elective_group_code' => $group, 'suggested_semester' => 1]);
        }
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Vận dụng kiến thức trong thực tiễn']);
        $plo->matrix()->create(['course_id' => $program->courses()->firstOrFail()->id, 'contribution_level' => 2]);
        $book = new Spreadsheet;
        foreach ([
            'CTDT' => [['MaCTDT', 'TenCTDT', 'TrinhDo', 'TongTinChi', 'TinChiTuChon'], ['DEMO-EXCEL', 'DEMO - CTĐT nhập Excel', 'dai_hoc', 6, 3]],
            'PLO' => [['MaPLO', 'MoTa'], ['PLO1', 'Chuẩn đầu ra nhập Excel']],
            'HocPhan' => [['MaHocPhan', 'TenHocPhan', 'TinChi', 'KhoiKienThuc', 'BatBuoc', 'HocKy', 'MaNhomTuChon'], ['EXBB', 'Bắt buộc mẫu', 3, 'Đại cương', 1, 1, null], ['EXTC', 'Tự chọn mẫu', 3, 'Chuyên ngành', 0, 2, 'EXA']],
            'MaTran' => [['MaHocPhan', 'MaPLO', 'MucDongGop'], ['EXBB', 'PLO1', 2], ['EXTC', 'PLO1', 3]],
            'NhomTuChon' => [['MaNhom', 'TenNhom', 'KhoiKienThuc', 'TinChiCanDat'], ['EXA', 'Nhóm Excel', 'Chuyên ngành', 3]],
        ] as $name => $rows) {
            $sheet = $name === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1', true);
        }
        (new Xlsx($book))->save(storage_path('framework/testing/curriculum-browser-import.xlsx'));
        $book->disconnectWorksheets();
    }
}
