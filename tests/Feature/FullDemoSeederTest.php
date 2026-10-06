<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DiplomaBlank;
use App\Models\DiplomaLedger;
use App\Models\DiplomaRequest;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\FullDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_populates_web_modules_and_creates_downloadable_files(): void
    {
        Storage::fake('local');
        $this->seed(FullDemoSeeder::class);

        foreach (['programs', 'courses', 'plos', 'course_plo_matrix', 'course_materials', 'program_versions', 'students', 'student_progress', 'student_progress_histories', 'warning_logs', 'academic_imports', 'academic_settings', 'iso_workflows', 'iso_steps', 'iso_histories', 'iso_files', 'diploma_requests', 'diploma_blanks', 'diploma_ledgers', 'diploma_audit_logs', 'diploma_blank_audits', 'diploma_registry_sequences', 'curriculum_audits', 'notifications', 'report_histories', 'login_logs'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(1, DiplomaRequest::where('status', 'pending')->count());
        $this->assertSame(1, DiplomaRequest::where('status', 'rejected')->count());
        $this->assertGreaterThan(0, DB::table('course_material_confirmations')->count());
        $this->assertSame(1, DB::table('web_scheduled_runs')->where('run_key', 'demo:academic:completed')->count());
        foreach (DiplomaLedger::all() as $ledger) {
            $this->assertSame('used', DiplomaBlank::where('serial_number', $ledger->serial_number)->first()->status);
        }
        foreach (DB::table('iso_files')->pluck('path') as $path) {
            Storage::disk('local')->assertExists($path);
            $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
        }
        $manager = User::where('email', 'long.demo@vwa.local')->firstOrFail();
        foreach (['dashboard', 'programs.index', 'courses.index', 'academic.students', 'academic.index', 'iso.index', 'diplomas.index', 'reports.index', 'users.index'] as $route) {
            $this->actingAs($manager)->get(route($route))->assertOk();
        }
    }

    public function test_rerunning_preserves_existing_changes_and_does_not_duplicate_records(): void
    {
        Storage::fake('local');
        $this->seed(FullDemoSeeder::class);
        $manager = User::where('email', 'long.demo@vwa.local')->firstOrFail();
        $manager->update(['password' => Hash::make('changed-password'), 'name' => 'Tên đã cập nhật']);
        $course = Course::where('course_code', 'KTS101')->firstOrFail();
        $course->update(['name_vi' => 'Tên học phần đã cập nhật']);
        $program = Program::where('code', 'KTS-2026')->firstOrFail();
        $program->update(['name_vi' => 'Chương trình đã cập nhật']);
        $tables = ['users', 'programs', 'courses', 'program_course', 'program_versions', 'students', 'student_progress', 'warning_logs', 'iso_workflows', 'iso_steps', 'iso_files', 'diploma_requests', 'diploma_ledgers', 'diploma_blank_audits', 'diploma_registry_sequences', 'report_histories', 'notifications'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->seed(FullDemoSeeder::class);

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertTrue(Hash::check('changed-password', $manager->fresh()->password));
        $this->assertSame('Tên đã cập nhật', $manager->fresh()->name);
        $this->assertSame('Tên học phần đã cập nhật', $course->fresh()->name_vi);
        $this->assertSame('Chương trình đã cập nhật', $program->fresh()->name_vi);
    }
}
