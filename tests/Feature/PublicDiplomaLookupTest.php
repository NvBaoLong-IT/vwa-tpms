<?php

namespace Tests\Feature;

use App\Models\DiplomaLedger;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDiplomaLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_lookup_exact_serial_or_registration_without_private_fields(): void
    {
        $student = Student::create(['student_code' => 'PRIVATE', 'full_name' => 'Tên hồ sơ hiện tại', 'degree_level' => 'dai_hoc']);
        $ledger = DiplomaLedger::create(['student_id' => $student->id, 'holder_name' => 'Tên trên văn bằng', 'reg_number' => 'REG/2026/001', 'serial_number' => 'SERIAL-001', 'diploma_type' => 'dai_hoc', 'issue_date' => '2026-10-01', 'status' => 'issued']);
        $this->get(route('diplomas.public-lookup'))->assertOk()->assertSee('Số vào sổ hoặc số phôi');
        foreach ([$ledger->serial_number, $ledger->reg_number] as $code) {
            $this->getJson(route('diplomas.public-lookup', ['code' => $code]))->assertOk()->assertExactJson([
                'holder_name' => 'Tên trên văn bằng', 'issue_date' => '2026-10-01', 'diploma_type' => 'dai_hoc', 'valid' => true,
            ])->assertHeader('Cache-Control', 'no-store, private');
        }
        $ledger->update(['status' => 'cancelled']);
        $this->getJson(route('diplomas.public-lookup', ['code' => $ledger->serial_number]))->assertJsonPath('valid', false);
        $this->getJson(route('diplomas.public-lookup', ['code' => 'SERIAL-%']))->assertExactJson(['valid' => false]);
    }

    public function test_invalid_input_is_rejected_and_requests_are_throttled_even_with_different_codes(): void
    {
        $this->getJson(route('diplomas.public-lookup'))->assertUnprocessable()->assertJsonValidationErrors('code');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->getJson(route('diplomas.public-lookup', ['code' => 'UNKNOWN-'.$attempt]))->assertOk()->assertExactJson(['valid' => false]);
        }
        $this->getJson(route('diplomas.public-lookup', ['code' => 'ANOTHER']))->assertStatus(429);
    }
}
