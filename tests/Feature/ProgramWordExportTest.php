<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ProgramWordExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_export_is_a_valid_docx_with_versioned_curriculum(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $program = Program::create(['code' => 'WORD-01', 'name_vi' => 'Kinh tế số', 'name_en' => 'Digital Economics', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'WORD101', 'name_vi' => 'Nhập môn Kinh tế số', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]);
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Vận dụng kiến thức']);
        $plo->matrix()->create(['course_id' => $course->id, 'contribution_level' => 2]);
        $version = app(ProgramVersionService::class)->capture($program, 2026, '604/QĐ-HVPNVN', '2026-06-16', $viewer->id);

        $response = $this->actingAs($viewer)->get(route('programs.versions.word', [$program, $version]));
        $response->assertOk()->assertDownload('CTDT-WORD-01-K2026-lan-1.docx');
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $response->getContent());

        $path = tempnam(sys_get_temp_dir(), 'word-test-');
        file_put_contents($path, $response->getContent());
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $document = $zip->getFromName('word/document.xml');
            $styles = $zip->getFromName('word/styles.xml');
            $this->assertNotFalse($document);
            $this->assertNotFalse($styles);
            $dom = new DOMDocument;
            $this->assertTrue($dom->loadXML($document));
            $this->assertStringContainsString('CHƯƠNG TRÌNH ĐÀO TẠO', $dom->textContent);
            $this->assertStringContainsString('Kinh tế số', $dom->textContent);
            $this->assertStringContainsString('WORD101', $dom->textContent);
            $this->assertStringContainsString('PLO1', $dom->textContent);
            $this->assertStringContainsString('Times New Roman', $styles);
            $zip->close();
        } finally {
            unlink($path);
        }

        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'Khác', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $this->get(route('programs.versions.word', [$other, $version]))->assertNotFound();

    }
}
