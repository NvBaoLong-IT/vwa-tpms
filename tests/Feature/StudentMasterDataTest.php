<?php

namespace Tests\Feature;

use App\Imports\StudentImport;
use App\Jobs\ImportStudentsJob;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('students.manage', 'web'));

        return $user;
    }

    public function test_manager_can_create_and_update_a_student_profile(): void
    {
        $user = $this->manager();
        $program = Program::create(['code' => 'SV-01', 'name_vi' => 'Ngành thử nghiệm', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);

        $this->actingAs($user)->post(route('academic.student.store'), [
            'student_code' => 'SV-001', 'full_name' => 'Nguyễn Văn A', 'program_id' => $program->id,
            'degree_level' => 'dai_hoc', 'status' => 'active', 'class_code' => 'K45A', 'enrollment_year' => 2022,
        ])->assertRedirect();
        $student = Student::where('student_code', 'SV-001')->firstOrFail();

        $this->actingAs($user)->put(route('academic.student.update', $student), [
            'student_code' => 'SV-001', 'full_name' => 'Nguyễn Văn B', 'program_id' => $program->id,
            'degree_level' => 'dai_hoc', 'status' => 'active', 'class_code' => 'K45B', 'enrollment_year' => 2022,
        ])->assertRedirect();
        $this->assertSame('Nguyễn Văn B', $student->fresh()->full_name);
        $this->assertSame('K45B', $student->fresh()->class_code);
    }

    public function test_manager_can_queue_full_student_import(): void
    {
        Queue::fake();
        $user = $this->manager();
        $csv = "MaSV,HoTen,NgaySinh,MaNganh,Lop,Khoa,NamNhapHoc,TrinhDo,Email,TrangThai\nSV-002,Nguyen Van B,01/09/2004,,,,2022,dai_hoc,b@example.test,active\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->actingAs($user)->post(route('academic.students.import'), ['file' => $file])->assertRedirect(route('academic.students'));
        Queue::assertPushed(ImportStudentsJob::class);
        $this->assertDatabaseHas('academic_imports', ['kind' => 'student_master', 'original_name' => 'students.csv']);
    }

    public function test_student_import_writes_master_fields_and_reports_bad_rows(): void
    {
        $program = Program::create(['code' => 'SV-02', 'name_vi' => 'Ngành nhập', 'degree_level' => 'thac_si', 'total_credits' => 60]);
        $csv = "MaSV,HoTen,NgaySinh,MaNganh,Lop,Khoa,NamNhapHoc,TrinhDo,Email,TrangThai\nSV-003,Nguyen Van C,01/09/2004,SV-02,K46B,Khoa B,2023,,c@example.test,active\nSV-004,,01/01/2004,SV-02,K46B,Khoa B,2023,thac_si,,active\n";
        $path = tempnam(sys_get_temp_dir(), 'students');
        file_put_contents($path, $csv);
        HeadingRowFormatter::default('none');
        $import = new StudentImport;
        Excel::import($import, $path);

        $this->assertSame(1, $import->imported);
        $this->assertCount(1, $import->errors);
        $this->assertDatabaseHas('students', ['student_code' => 'SV-003', 'program_id' => $program->id, 'class_code' => 'K46B', 'degree_level' => 'thac_si']);
    }

    public function test_viewer_cannot_manage_student_profiles(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('students.view', 'web'));

        $this->actingAs($user)->get(route('academic.student.create'))->assertForbidden();
    }
}
