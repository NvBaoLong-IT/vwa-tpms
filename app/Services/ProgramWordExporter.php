<?php

namespace App\Services;

use App\Models\ProgramVersion;
use XMLWriter;
use ZipArchive;

class ProgramWordExporter
{
    private XMLWriter $xml;

    public function render(ProgramVersion $version): string
    {
        $this->xml = new XMLWriter;
        $this->xml->openMemory();
        $this->xml->startDocument('1.0', 'UTF-8', 'yes');
        $this->xml->startElement('w:document');
        $this->xml->writeAttribute('xmlns:w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $this->xml->startElement('w:body');

        $snapshot = $version->snapshot;
        $program = $snapshot['program'];
        $degreeLabel = ['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'][$program['degree_level']] ?? $program['degree_level'];
        $degree = mb_strtoupper($degreeLabel);
        $this->table([], [
            ["HỘI LIÊN HIỆP PHỤ NỮ VIỆT NAM\nHỌC VIỆN PHỤ NỮ VIỆT NAM", "CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM\nĐộc lập - Tự do - Hạnh phúc"],
        ], [4680, 4680], false);
        $this->paragraph(in_array($version->status, ['published', 'archived'], true) ? 'BẢN LƯU CHƯƠNG TRÌNH ĐÀO TẠO' : 'DỰ THẢO TRÌNH KÝ', 'Subtitle', 'center');
        $this->paragraph('CHƯƠNG TRÌNH ĐÀO TẠO', 'Title', 'center');
        $this->paragraph('NGÀNH '.mb_strtoupper($program['name_vi']).' TRÌNH ĐỘ '.$degree, 'Title', 'center');
        $this->paragraph('Khóa tuyển sinh '.$version->cohort_year.' - Quyết định số '.$version->decision_number.' ngày '.$version->decision_date->format('d/m/Y'), 'Normal', 'center');

        $this->paragraph('1. Giới thiệu chung về chương trình đào tạo', 'Heading1');
        $this->table([], [
            ['Tên tiếng Việt', $program['name_vi']],
            ['Tên tiếng Anh', $program['name_en'] ?: ''],
            ['Mã chương trình', $program['code']],
            ['Trình độ', $degreeLabel],
            ['Hình thức đào tạo', $program['delivery_mode'] ?: ''],
            ['Khóa tuyển sinh', (string) $version->cohort_year],
            ['Quyết định', $version->decision_number.' ngày '.$version->decision_date->format('d/m/Y')],
            ['Trạng thái', $version->statusLabel()],
            ['Hiệu lực từ', $version->effective_from?->format('d/m/Y') ?? 'Chưa xác định'],
            ['Hiệu lực đến hết', $version->effective_until?->format('d/m/Y') ?? 'Chưa xác định'],
        ], [2700, 6660], false);

        $this->paragraph('2. Cấu trúc và khối lượng kiến thức', 'Heading1');
        $this->paragraph('Tổng số tín chỉ của chương trình: '.$program['total_credits'].'. Tín chỉ tự chọn cần tích lũy: '.$program['elective_credits_required'].'.');
        $blocks = collect($snapshot['courses'])->groupBy('knowledge_block');
        $blockRows = [];
        foreach ($blocks as $name => $courses) {
            $blockRows[] = [
                $name,
                (string) $courses->filter(fn ($course) => $course['counts_toward_total'] ?? true)->where('is_mandatory', true)->sum('credits'),
                (string) $courses->filter(fn ($course) => $course['counts_toward_total'] ?? true)->where('is_mandatory', false)->sum('credits'),
                (string) $courses->filter(fn ($course) => ! ($course['counts_toward_total'] ?? true))->sum('credits'),
            ];
        }
        $this->table(['Khối kiến thức', 'Bắt buộc trong tổng', 'Tự chọn đang mở trong tổng', 'Ngoài tổng'], $blockRows, [3660, 1900, 1900, 1900]);
        if (! empty($snapshot['elective_groups'])) {
            $this->paragraph('Chỉ tiêu tự chọn theo nhóm', 'Heading2');
            $this->table(['Mã nhóm', 'Tên nhóm', 'Khối kiến thức', 'Tín chỉ cần tích lũy'], collect($snapshot['elective_groups'])->map(fn ($group) => [$group['code'], $group['name'], $group['knowledge_block'], (string) $group['credits_required']])->all(), [1400, 2700, 3500, 1760]);
        }

        $this->paragraph('3. Khung chương trình đào tạo', 'Heading1');
        $number = 1;
        foreach ($blocks as $name => $courses) {
            $this->paragraph($name, 'Heading2');
            $rows = [];
            foreach ($courses as $course) {
                $rows[] = [(string) $number++, $course['course_code'], $course['name_vi'].(($course['counts_toward_total'] ?? true) ? '' : ' (Ngoài tổng tín chỉ)').($course['is_mandatory'] ? '' : ' (Tự chọn'.(! empty($course['elective_group_code']) ? ' - '.$course['elective_group_code'] : '').')'), (string) $course['credits']];
            }
            $this->table(['TT', 'Mã học phần', 'Tên học phần', 'Số tín chỉ'], $rows, [560, 1600, 5900, 1300]);
        }
        if ($blocks->isEmpty()) {
            $this->paragraph('Chưa có học phần.');
        }

        $this->paragraph('4. Kế hoạch học kỳ', 'Heading1');
        $semesters = collect($snapshot['courses'])->filter(fn ($course) => $course['suggested_semester'] !== null)->groupBy('suggested_semester')->sortKeys();
        foreach ($semesters as $semester => $courses) {
            $this->paragraph('Học kỳ '.$semester, 'Heading2');
            $rows = [];
            foreach ($courses->values() as $index => $course) {
                $rows[] = [(string) ($index + 1), $course['course_code'], $course['name_vi'], (string) $course['credits']];
            }
            $this->table(['TT', 'Mã học phần', 'Tên học phần', 'Số tín chỉ'], $rows, [560, 1600, 5900, 1300]);
        }
        if ($semesters->isEmpty()) {
            $this->paragraph('Chưa phân bổ học kỳ.');
        }

        $this->paragraph('5. Chuẩn đầu ra', 'Heading1');
        $this->table(['Mã', 'Mô tả'], collect($snapshot['plos'])->map(fn ($plo) => [$plo['code'], $plo['description_vi']])->all(), [1500, 7860]);
        $this->paragraph('6. Mức đáp ứng chuẩn đầu ra', 'Heading1');
        $this->table(['Học phần', 'Chuẩn đầu ra', 'Mức đóng góp'], collect($snapshot['matrix'])->map(fn ($entry) => [$entry['course_code'], $entry['plo_code'], (string) $entry['contribution_level']])->all(), [3120, 3120, 3120]);
        if (! empty($snapshot['substitutions'])) {
            $this->paragraph('7. Quy tắc thay thế học phần', 'Heading1');
            $this->paragraph('Tín chỉ công nhận đáp ứng học phần đích, không cộng thêm vào tổng tín chỉ CTĐT.');
            $this->table(['Nguồn', 'Học phần được thay', 'Học phần đích', 'TC công nhận', 'Căn cứ và điều kiện'], collect($snapshot['substitutions'])->map(fn ($rule) => [$rule['source_program_code'].' K'.$rule['source_cohort_year'].' lần '.$rule['source_revision'], $rule['source_course_code'], $rule['target_course_code'], (string) $rule['recognized_credits'], $rule['decision_number'].' - '.$rule['note']])->all(), [1800, 1500, 1500, 1060, 3500]);
        }

        $this->paragraph(' ', 'Normal');
        $this->table([], [['TRƯỞNG PHÒNG ĐÀO TẠO', 'GIÁM ĐỐC'], ['(Ký, ghi rõ họ tên)', '(Ký, ghi rõ họ tên)'], [' ', ' '], [' ', ' ']], [4680, 4680], false);
        $this->sectionProperties();
        $this->xml->endElement();
        $this->xml->endElement();
        $document = $this->xml->outputMemory();

        $path = tempnam(sys_get_temp_dir(), 'ctdt-docx-');
        if ($path === false) {
            throw new \RuntimeException('Không tạo được tệp Word tạm.');
        }
        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Không mở được tệp Word tạm.');
            }
            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('word/document.xml', $document);
            $zip->addFromString('word/styles.xml', $this->styles());
            $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationships());
            $zip->close();

            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Không đọc được tệp Word.');
            }

            return $bytes;
        } finally {
            @unlink($path);
        }
    }

    private function paragraph(string $text, string $style = 'Normal', ?string $alignment = null, bool $bold = false, bool $inTable = false): void
    {
        $this->xml->startElement('w:p');
        $this->xml->startElement('w:pPr');
        $this->xml->startElement('w:pStyle');
        $this->xml->writeAttribute('w:val', $style);
        $this->xml->endElement();
        if (in_array($style, ['Title', 'Subtitle', 'Heading1', 'Heading2'], true) || ($bold && $inTable)) {
            $this->xml->writeElement('w:keepNext');
        }
        if ($alignment !== null) {
            $this->xml->startElement('w:jc');
            $this->xml->writeAttribute('w:val', $alignment);
            $this->xml->endElement();
        }
        $this->xml->startElement('w:spacing');
        $this->xml->writeAttribute('w:after', $inTable ? '0' : '120');
        $this->xml->writeAttribute('w:line', '300');
        $this->xml->endElement();
        $this->xml->endElement();
        $this->xml->startElement('w:r');
        if ($bold) {
            $this->xml->startElement('w:rPr');
            $this->xml->writeElement('w:b');
            $this->xml->endElement();
        }
        foreach (explode("\n", $text) as $index => $line) {
            if ($index > 0) {
                $this->xml->writeElement('w:br');
            }
            $this->xml->startElement('w:t');
            $this->xml->writeAttribute('xml:space', 'preserve');
            $this->xml->text($line);
            $this->xml->endElement();
        }
        $this->xml->endElement();
        $this->xml->endElement();
    }

    private function table(array $headers, array $rows, array $widths, bool $borders = true): void
    {
        if ($headers !== [] && $rows === []) {
            $this->paragraph('Chưa có dữ liệu.');

            return;
        }
        $this->xml->startElement('w:tbl');
        $this->xml->startElement('w:tblPr');
        $this->xml->startElement('w:tblW');
        $this->xml->writeAttribute('w:w', (string) array_sum($widths));
        $this->xml->writeAttribute('w:type', 'dxa');
        $this->xml->endElement();
        if ($borders) {
            $this->xml->startElement('w:tblBorders');
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
                $this->xml->startElement('w:'.$side);
                $this->xml->writeAttribute('w:val', 'single');
                $this->xml->writeAttribute('w:sz', '4');
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }
        $this->xml->endElement();
        $this->xml->startElement('w:tblGrid');
        foreach ($widths as $width) {
            $this->xml->startElement('w:gridCol');
            $this->xml->writeAttribute('w:w', (string) $width);
            $this->xml->endElement();
        }
        $this->xml->endElement();
        if ($headers !== []) {
            $this->tableRow($headers, $widths, true);
        }
        foreach ($rows as $index => $row) {
            $this->tableRow($row, $widths, ! $borders && $index === 0, ! $borders);
        }
        $this->xml->endElement();
    }

    private function tableRow(array $cells, array $widths, bool $header = false, bool $center = false): void
    {
        $this->xml->startElement('w:tr');
        $this->xml->startElement('w:trPr');
        $this->xml->writeElement('w:cantSplit');
        if ($header) {
            $this->xml->writeElement('w:tblHeader');
        }
        $this->xml->endElement();
        foreach ($cells as $index => $cell) {
            $this->xml->startElement('w:tc');
            $this->xml->startElement('w:tcPr');
            $this->xml->startElement('w:tcW');
            $this->xml->writeAttribute('w:w', (string) $widths[$index]);
            $this->xml->writeAttribute('w:type', 'dxa');
            $this->xml->endElement();
            $this->xml->endElement();
            $this->paragraph((string) $cell, 'Normal', $center ? 'center' : null, $header, true);
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    private function sectionProperties(): void
    {
        $this->xml->startElement('w:sectPr');
        $this->xml->startElement('w:pgSz');
        $this->xml->writeAttribute('w:w', '11906');
        $this->xml->writeAttribute('w:h', '16838');
        $this->xml->endElement();
        $this->xml->startElement('w:pgMar');
        foreach (['top' => 1400, 'right' => 1200, 'bottom' => 1400, 'left' => 1700, 'header' => 700, 'footer' => 700, 'gutter' => 0] as $name => $value) {
            $this->xml->writeAttribute('w:'.$name, (string) $value);
        }
        $this->xml->endElement();
        $this->xml->endElement();
    }

    private function styles(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('w:styles');
        $xml->writeAttribute('xmlns:w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach (['Normal' => [26, false], 'Title' => [32, true], 'Subtitle' => [24, true], 'Heading1' => [28, true], 'Heading2' => [26, true]] as $name => [$size, $bold]) {
            $xml->startElement('w:style');
            $xml->writeAttribute('w:type', 'paragraph');
            $xml->writeAttribute('w:styleId', $name);
            $xml->startElement('w:name');
            $xml->writeAttribute('w:val', $name);
            $xml->endElement();
            $xml->startElement('w:rPr');
            $xml->startElement('w:rFonts');
            $xml->writeAttribute('w:ascii', 'Times New Roman');
            $xml->writeAttribute('w:hAnsi', 'Times New Roman');
            $xml->writeAttribute('w:eastAsia', 'Times New Roman');
            $xml->endElement();
            $xml->startElement('w:sz');
            $xml->writeAttribute('w:val', (string) $size);
            $xml->endElement();
            if ($bold) {
                $xml->writeElement('w:b');
            }
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();

        return $xml->outputMemory();
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }
}
