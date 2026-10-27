<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DiplomaBlank;
use App\Models\DiplomaLedger;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeletedRecordTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['programs'])]
    #[TestWith(['courses'])]
    #[TestWith(['students'])]
    #[TestWith(['diploma_ledgers'])]
    #[TestWith(['diploma_blanks'])]
    public function test_only_manager_can_list_and_restore_deleted_records(string $type): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole(Role::findOrCreate('Lãnh đạo phòng', 'web'));
        $record = match ($type) {
            'programs' => Program::create(['code' => 'RESTORE', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 3]),
            'courses' => Course::create(['course_code' => 'RESTORE', 'name_vi' => 'Học phần', 'credits' => 3]),
            'students' => Student::create(['student_code' => 'RESTORE', 'full_name' => 'Người học', 'degree_level' => 'dai_hoc']),
            'diploma_blanks' => DiplomaBlank::create(['serial_number' => 'RESTORE', 'type' => 'dai_hoc', 'status' => 'destroyed', 'import_date' => '2026-10-01']),
            'diploma_ledgers' => DiplomaLedger::create([
                'student_id' => Student::create(['student_code' => 'HOLDER', 'full_name' => 'Tên văn bằng', 'degree_level' => 'dai_hoc'])->id,
                'reg_number' => 'RESTORE', 'serial_number' => 'SERIAL', 'diploma_type' => 'dai_hoc', 'issue_date' => '2026-10-01', 'status' => 'cancelled',
            ]),
        };
        $originalStatus = $record->refresh()->status;
        $record->delete();
        $this->assertSoftDeleted($record);
        $this->assertNull($record::find($record->id));
        $url = route('records.restore', [$type, $record->id]);

        $this->get(route('records.deleted'))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('records.deleted'))->assertForbidden();
        $this->post($url)->assertForbidden();
        $this->actingAs($manager)->get(route('records.deleted', ['type' => $type]))->assertOk()->assertSee('Khôi phục');
        $this->post($url)->assertRedirect()->assertSessionHasNoErrors();

        $restored = $record::findOrFail($record->id);
        $this->assertSame($originalStatus, $restored->status);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $manager->id, 'action' => 'restore', 'successful' => true]);
        $this->post($url)->assertNotFound();
    }
}
