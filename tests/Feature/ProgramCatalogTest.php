<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_program_can_expose_its_course_catalog(): void
    {
        $program = Program::create([
            'code' => 'TEST-001',
            'name_vi' => 'Chương trình kiểm thử',
            'degree_level' => 'dai_hoc',
            'total_credits' => 120,
        ]);
        $course = Course::create([
            'course_code' => 'TEST101',
            'name_vi' => 'Học phần kiểm thử',
            'credits' => 3,
        ]);

        $program->courses()->attach($course, [
            'knowledge_block' => 'Đại cương',
            'suggested_semester' => 1,
        ]);

        $this->assertSame('Học phần kiểm thử', $program->fresh()->courses->first()->name_vi);
        $this->assertSame('Đại cương', $program->fresh()->courses->first()->pivot->knowledge_block);
    }
}
