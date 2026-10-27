<div class="program-detail-table-wrap mt-4">
    <table class="program-detail-table">
        <thead><tr><th>Nhóm</th><th>Nội dung</th><th>Thay đổi</th><th>Trước</th><th>Sau</th></tr></thead>
        <tbody>
            @forelse($changes as $change)
                <tr><td data-label="Nhóm">{{ $change['section'] }}</td><td data-label="Nội dung">{{ ['cohort_year'=>'Khóa tuyển sinh','revision'=>'Lần sửa','decision_number'=>'Số quyết định','decision_date'=>'Ngày quyết định','effective_from'=>'Hiệu lực từ ngày','effective_until'=>'Hiệu lực đến hết ngày','code'=>'Mã chương trình','name_vi'=>'Tên tiếng Việt','name_en'=>'Tên tiếng Anh','degree_level'=>'Trình độ','delivery_mode'=>'Hình thức đào tạo','total_credits'=>'Tổng tín chỉ','elective_credits_required'=>'Tự chọn cần tích lũy','review_cycle_years'=>'Chu kỳ rà soát'][$change['key']] ?? $change['key'] }}</td><td data-label="Thay đổi">{{ $change['action'] }}</td>
                    @foreach(['before' => 'Trước', 'after' => 'Sau'] as $side => $label)
                        <td data-label="{{ $label }}">
                            @if(is_array($change[$side]))
                                <dl>
                                    @foreach($change[$side] as $field => $value)
                                        <div><dt class="font-medium">{{ ['source_course_codes'=>'Các học phần nguồn','target_courses'=>'Phân bổ học phần đích','elective_group_code'=>'Mã nhóm tự chọn','credits_required'=>'Tín chỉ cần tích lũy','name'=>'Tên nhóm','course_code'=>'Mã học phần','name_vi'=>'Tên tiếng Việt','name_en'=>'Tên tiếng Anh','credits'=>'Tín chỉ','knowledge_block'=>'Khối kiến thức','is_mandatory'=>'Bắt buộc','suggested_semester'=>'Học kỳ gợi ý','sort_order'=>'Thứ tự','prerequisite_course_id'=>'Mã nội bộ học phần tiên quyết','corequisite_course_id'=>'Mã nội bộ học phần song hành','code'=>'Mã','description_vi'=>'Mô tả tiếng Việt','description_en'=>'Mô tả tiếng Anh','plo_code'=>'Mã chuẩn đầu ra','contribution_level'=>'Mức đáp ứng','kind'=>'Loại quy tắc', 'id'=>'Mã quy tắc', 'source_version_id'=>'Mã phiên bản nguồn', 'recognized_credits'=>'Tín chỉ công nhận', 'source_cohort_year'=>'Khóa nguồn', 'source_program_code'=>'Mã CTĐT nguồn', 'source_revision'=>'Lần sửa nguồn', 'source_course_code'=>'Học phần được thay','target_course_code'=>'Học phần thay thế','decision_number'=>'Quyết định','note'=>'Ghi chú'][$field] ?? $field }}</dt><dd>@if(is_array($value))@foreach($value as $part){{ is_array($part) ? (($part['course_code'] ?? '') . ': ' . ($part['credits'] ?? '') . ' tín chỉ') : $part }}; @endforeach @else{{ is_bool($value) ? ($value ? 'Có' : 'Không') : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?? '—')) }}@endif</dd></div>
                                    @endforeach
                                </dl>
                            @else
                                {{ is_bool($change[$side]) ? ($change[$side] ? 'Có' : 'Không') : (['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'][$change[$side] ?? ''] ?? ($change[$side] ?? '—')) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5">Không có thay đổi nội dung.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>





