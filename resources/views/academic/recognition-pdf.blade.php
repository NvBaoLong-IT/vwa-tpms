<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><style>
@page { margin: 22mm 18mm 20mm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; color: #111; }
h1 { font-size: 17pt; text-align: center; margin: 12pt 0 4pt; }
h2 { font-size: 11pt; margin: 16pt 0 7pt; page-break-after: avoid; }
p { margin: 5pt 0; } .center { text-align: center; } .small { font-size: 8pt; color: #444; }
.status { border: 1px solid #333; padding: 7pt; margin: 10pt 0; font-weight: bold; text-align: center; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 7pt 0; }
th, td { border: 1px solid #888; padding: 5pt; vertical-align: top; word-wrap: break-word; }
th { background: #eee; text-align: left; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.section { page-break-inside: avoid; } .meta td { border: 0; padding: 3pt 0; }
</style></head><body>
<p class="center small">PHÒNG ĐÀO TẠO · MẪU ĐỐI CHIẾU NỘI BỘ</p>
<h1>HỒ SƠ CÔNG NHẬN TÍN CHỈ</h1><p class="center">Mã hồ sơ #{{ $recognition->id }} · Xuất ngày {{ now()->format('d/m/Y H:i') }}</p>
<div class="status">{{ mb_strtoupper($status) }}@if($recognition->status !== 'approved') · KHÔNG TÍNH VÀO TÍN CHỈ ĐƯỢC CÔNG NHẬN @endif</div>
<table class="meta"><tr><td><strong>Mã sinh viên:</strong> {{ $student->student_code }}</td><td><strong>Họ tên hiện tại:</strong> {{ $student->full_name }}</td></tr><tr><td><strong>CTĐT đích:</strong> {{ $recognition->version->snapshot['program']['code'] }}</td><td><strong>Khóa / lần sửa:</strong> K{{ $recognition->version->cohort_year }} / {{ $recognition->version->revision }}</td></tr></table>
<p><strong>Tên CTĐT theo bản lưu:</strong> {{ $recognition->version->snapshot['program']['name_vi'] }}</p>
<p><strong>Quyết định CTĐT:</strong> {{ $recognition->version->decision_number }} ngày {{ $recognition->version->decision_date->format('d/m/Y') }}</p>
<p><strong>Căn cứ công nhận:</strong> {{ $recognition->decision_number }}</p><p><strong>Giải trình / điều kiện:</strong> {{ $recognition->note }}</p>
<h2>1. Kết quả học phần nguồn tại thời điểm trình</h2>
<table><thead><tr><th style="width:10%">Mã kết quả</th><th style="width:13%">Phiên bản nguồn</th><th style="width:16%">Học phần</th><th style="width:12%">Kỳ học</th><th style="width:8%">TC</th><th style="width:13%">Kết quả</th><th style="width:28%">Căn cứ</th></tr></thead><tbody>@foreach($recognition->allocation['results'] as $row)<tr><td>#{{ $row['id'] }}</td><td>@php($sourceVersion = $sourceVersions->get($row['program_version_id']))@if($sourceVersion){{ $sourceVersion->snapshot['program']['code'] }}<br>K{{ $sourceVersion->cohort_year }} · L{{ $sourceVersion->revision }}@else#{{ $row['program_version_id'] }}@endif</td><td>{{ $row['course_code'] }}</td><td>{{ $row['semester'] }}</td><td>{{ $row['credits'] }}</td><td>{{ ['passed'=>'Đạt','exempt'=>'Miễn theo căn cứ'][$row['outcome']] ?? $row['outcome'] }}</td><td>{{ $row['evidence'] }}</td></tr>@endforeach</tbody></table>
<h2>2. Phân bổ theo học phần đích</h2>
<table><thead><tr><th>Học phần đích</th><th>Tên học phần theo CTĐT</th><th style="width:18%">TC công nhận</th></tr></thead><tbody>@foreach($recognition->allocation['targets'] as $row)<tr><td>{{ $row['course_code'] }}</td><td>{{ collect($recognition->version->snapshot['courses'])->firstWhere('course_code', $row['course_code'])['name_vi'] ?? '' }}</td><td>{{ $row['credits'] }}</td></tr>@endforeach</tbody></table>
<p><strong>Tổng phân bổ của hồ sơ:</strong> {{ array_sum(array_column($recognition->allocation['targets'], 'credits')) }} tín chỉ. Chỉ tính vào tiến độ khi hồ sơ có trạng thái “Đã công nhận”.</p>
@if($recognition->allocation['rule_id'] ?? null)@php($rule = collect($recognition->version->snapshot['substitutions'] ?? [])->firstWhere('id', $recognition->allocation['rule_id']))<p><strong>Quy tắc thay thế:</strong> {{ $rule ? \App\Services\CurriculumSubstitutionRules::label($rule) : $recognition->allocation['rule_id'] }}@if($rule) · {{ $rule['decision_number'] }}@endif</p>@endif
<div class="section"><h2>3. Xét duyệt và lịch sử xử lý</h2><p><strong>Người trình:</strong> {{ $recognition->submitter?->name }} · {{ $recognition->created_at->format('d/m/Y H:i') }}</p><p><strong>Người xét duyệt:</strong> {{ $recognition->reviewer?->name ?? 'Chưa xét duyệt' }} · {{ $recognition->reviewed_at?->format('d/m/Y H:i') ?? '' }}</p><p><strong>Ý kiến:</strong> {{ $recognition->review_note ?? 'Chưa có' }}</p></div>
<table><thead><tr><th style="width:23%">Thời điểm</th><th style="width:15%">Thao tác</th><th style="width:24%">Người thao tác</th><th>Ý kiến / lý do</th></tr></thead><tbody>@foreach($history as $event)<tr><td>{{ $event->created_at->format('d/m/Y H:i') }}</td><td>{{ ['recognition_submitted'=>'Trình','recognition_approved'=>'Duyệt','recognition_returned'=>'Trả về','recognition_revoked'=>'Thu hồi'][$event->action] ?? $event->action }}</td><td>{{ $event->actor_name }}</td><td>{{ $event->new_values['reason'] ?? $event->new_values['review_note'] ?? $event->new_values['note'] ?? '' }}</td></tr>@endforeach</tbody></table>
<p class="small">Bản đối chiếu được xuất từ trạng thái hiện tại của hồ sơ. Việc công nhận không tự cập nhật GPA, số tín chỉ học vụ đã nhập hoặc quyết định tốt nghiệp.</p>
</body></html>


