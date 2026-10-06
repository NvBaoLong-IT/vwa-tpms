<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ModelApiCurriculumExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(UploadedFile $file): array
    {
        $apiKey = config('services.modelapi.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Chưa cấu hình MODELAPI_API_KEY.');
        }

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $content = [['type' => 'text', 'text' => $this->prompt()]];
        if ($mimeType === 'application/pdf') {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                throw new RuntimeException('Không đọc được tệp PDF.');
            }
            $content[] = ['type' => 'file', 'file' => ['filename' => $file->getClientOriginalName(), 'file_data' => 'data:'.$mimeType.';base64,'.base64_encode($contents)]];
        } else {
            $text = $this->docxText($file->getRealPath());
            if ($text === '') {
                throw new RuntimeException('Không trích xuất được nội dung Word.');
            }
            $content[] = ['type' => 'text', 'text' => "NỘI DUNG TÀI LIỆU:\n\n".$text];
        }

        $url = rtrim((string) config('services.modelapi.base_url'), '/').'/chat/completions';
        try {
            $response = Http::connectTimeout(10)
                ->timeout(120)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($url, ['model' => config('services.modelapi.model'), 'messages' => [['role' => 'user', 'content' => $content]], 'response_format' => ['type' => 'json_object']]);
        } catch (RequestException $exception) {
            throw new RuntimeException('API trung gian không phản hồi: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('API trung gian trả về lỗi HTTP '.$response->status().'.');
        }

        $text = (string) $response->json('choices.0.message.content', '');
        $text = trim(Str::of($text)->replaceStart('```json', '')->replaceEnd('```', '')->trim()->toString());
        $payload = json_decode($text, true);
        if (! is_array($payload) || ! isset($payload['program'])) {
            throw new RuntimeException('API trung gian không trả về đúng cấu trúc dữ liệu CTĐT.');
        }

        return $payload;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Bạn là trợ lý nhập chương trình đào tạo của Phòng Đào tạo. Đọc tài liệu được cung cấp và trả về DUY NHẤT JSON hợp lệ, không markdown, theo cấu trúc:
{"program":{"code":null,"name_vi":null,"name_en":null,"degree_level":null,"delivery_mode":null,"total_credits":null,"elective_credits_required":null,"decision_number":null,"decision_date":null},"knowledge_blocks":[],"plos":[{"code":"PLO1","description_vi":""}],"courses":[{"course_code":"","name_vi":"","credits":null,"knowledge_block":"","is_mandatory":null,"suggested_semester":null}],"matrix":[{"course_code":"","plo_code":"","contribution_level":null}],"warnings":[{"severity":"high|medium|low","message":"","source":""}],"source_notes":[]}
Quy tắc: không đoán dữ liệu bị thiếu; dùng null và thêm cảnh báo. degree_level chỉ được là dai_hoc, thac_si hoặc tien_si khi xác định rõ. is_mandatory là true/false khi xác định rõ, nếu không dùng null. contribution_level là số nguyên từ 1 đến 3 khi tài liệu có ma trận. Giữ nguyên mã, tên và số liệu gốc; không tự sửa chính tả. Nếu tổng tín chỉ không khớp tổng học phần, thêm cảnh báo mức high. source phải ghi trang hoặc mô tả vị trí nếu xác định được.
PROMPT;
    }

    private function docxText(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Không mở được tệp Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml)) {
            return '';
        }
        $text = preg_replace('/<w:tab\b[^>]*\/>/i', "\t", $xml) ?? $xml;
        $text = preg_replace('/<w:(br|p)\b[^>]*\/>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode($text)) ?? '');
    }
}
