<?php

namespace App\Http\Controllers;

use App\Models\CreditRecognition;
use App\Models\CurriculumAudit;
use App\Models\ProgramVersion;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CreditRecognitionExportController extends Controller
{
    public function __invoke(Student $student, CreditRecognition $recognition, string $format): Response
    {
        abort_unless($recognition->student_id === $student->id, 404);
        Validator::make(['format' => $format], ['format' => 'required|in:pdf,csv'])->validate();
        $recognition->load(['version', 'submitter', 'reviewer']);
        $sourceVersions = ProgramVersion::whereIn('id', array_column($recognition->allocation['results'], 'program_version_id'))->get()->keyBy('id');
        $history = CurriculumAudit::where('subject_type', 'credit_recognition')->where('subject_id', $recognition->id)->orderBy('id')->get();
        $status = ['submitted' => 'Chờ phê duyệt', 'approved' => 'Đã công nhận', 'returned' => 'Trả về bổ sung', 'revoked' => 'Đã thu hồi'][$recognition->status] ?? $recognition->status;
        $filename = 'ho-so-cong-nhan-'.$recognition->id.'-'.$recognition->status;
        if ($format === 'pdf') {
            $response = Pdf::loadView('academic.recognition-pdf', compact('student', 'recognition', 'history', 'status', 'sourceVersions'))
                ->setPaper('a4')->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])->download($filename.'.pdf');
        } else {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $values) use ($stream): void {
                $values = array_map(function ($value): string {
                    $text = (string) $value;

                    return preg_match('/^[=+@\-]/', ltrim($text)) ? "'".$text : $text;
                }, $values);
                fputcsv($stream, $values, ',', '"', '');
            };
            $write(['Hồ sơ', $recognition->id, 'Trạng thái', $status]);
            $write(['Mã sinh viên', $student->student_code, 'Họ tên hiện tại', $student->full_name]);
            $write(['CTĐT đích', $recognition->version->snapshot['program']['code'], 'Khóa', $recognition->version->cohort_year, 'Lần sửa', $recognition->version->revision]);
            $write(['Quyết định CTĐT', $recognition->version->decision_number, 'Căn cứ công nhận', $recognition->decision_number]);
            $write(['Người trình', $recognition->submitter?->name, 'Người xét duyệt', $recognition->reviewer?->name, 'Ngày xét duyệt', $recognition->reviewed_at?->format('d/m/Y H:i')]);
            $write(['Giải trình', $recognition->note]);
            $write(['Ý kiến duyệt', $recognition->review_note]);
            $write(['Nguồn', 'Mã kết quả', 'Phiên bản nguồn', 'Mã học phần', 'Kỳ học', 'Tín chỉ', 'Kết quả', 'Căn cứ']);
            foreach ($recognition->allocation['results'] as $row) {
                $source = $sourceVersions->get($row['program_version_id']);
                $sourceLabel = $source ? $source->snapshot['program']['code'].' K'.$source->cohort_year.' lần '.$source->revision.' - '.$source->decision_number : '#'.$row['program_version_id'];
                $write(['Nguồn', $row['id'], $sourceLabel, $row['course_code'], $row['semester'], $row['credits'], ['passed' => 'Đạt', 'exempt' => 'Miễn theo căn cứ'][$row['outcome']] ?? $row['outcome'], $row['evidence']]);
            }
            $write(['Phân bổ', 'Mã học phần đích', 'Tín chỉ công nhận']);
            foreach ($recognition->allocation['targets'] as $row) {
                $write(['Phân bổ', $row['course_code'], $row['credits']]);
            }
            $write(['Lịch sử', 'Thời điểm', 'Thao tác', 'Người thao tác', 'Ý kiến / lý do']);
            foreach ($history as $event) {
                $write(['Lịch sử', $event->created_at->format('d/m/Y H:i'), ['recognition_submitted' => 'Trình', 'recognition_approved' => 'Duyệt', 'recognition_returned' => 'Trả về', 'recognition_revoked' => 'Thu hồi'][$event->action] ?? $event->action, $event->actor_name, $event->new_values['reason'] ?? $event->new_values['review_note'] ?? $event->new_values['note'] ?? '']);
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
            $response = response($content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"']);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
