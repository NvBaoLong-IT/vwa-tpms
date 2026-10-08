<?php

namespace App\Services;

use App\Exceptions\CurriculumExtractionException;
use Symfony\Component\Process\Process;

class ScannedPdfPreparer
{
    public function pages(string $path): array
    {
        $python = config('services.modelapi.pdf_python');
        if (! is_string($python) || $python === '') {
            return [];
        }
        $process = new Process([$python, app_path('Services/prepare_scanned_pdf.py'), $path]);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new CurriculumExtractionException('Không chuẩn bị được PDF scan. Hãy kiểm tra công cụ Python/pypdf/Pillow hoặc dùng DOCX.');
        }
        $result = json_decode($process->getOutput(), true);
        if (! is_array($result) || ! isset($result['pages'])) {
            throw new CurriculumExtractionException('Công cụ đọc PDF scan trả về kết quả không hợp lệ.');
        }

        return $result['pages'];
    }
}
