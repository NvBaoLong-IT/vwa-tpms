<section class="program-detail-section" id="program-substitutions" aria-labelledby="substitutions-title">
    <h2 id="substitutions-title">Quy tắc thay thế / tương đương học phần</h2>
    <p class="version-snapshot-note">Quy tắc gắn phiên bản nguồn với học phần đích. Tín chỉ công nhận là phần đáp ứng học phần đích, không cộng thêm vào tổng tín chỉ CTĐT. Hỗ trợ nhiều học phần nguồn và nhiều học phần đích. Công nhận cho từng sinh viên được thực hiện tại hồ sơ sinh viên, qua bước trình và duyệt riêng.</p>
    <div class="program-detail-table-wrap"><table class="program-detail-table"><thead><tr><th>Phiên bản nguồn</th><th>Học phần được thay</th><th>Học phần đích</th><th>Tín chỉ công nhận</th><th>Căn cứ</th><th>Ghi chú</th><th></th></tr></thead><tbody>
        @forelse($rules as $rule)
            <tr><td data-label="Phiên bản nguồn">{{ $rule['source_program_code'] }} · K{{ $rule['source_cohort_year'] }} · Lần {{ $rule['source_revision'] }}</td><td data-label="Học phần được thay">{{ $rule['source_course_code'] }}</td><td data-label="Học phần đích">{{ $rule['target_course_code'] }}</td><td data-label="Tín chỉ công nhận">{{ $rule['recognized_credits'] }}@if(isset($rule['target_courses']))<small class="block">@foreach($rule['target_courses'] as $allocation){{ $allocation['course_code'] }}: {{ $allocation['credits'] }}; @endforeach</small>@endif</td><td data-label="Căn cứ">{{ $rule['decision_number'] }}</td><td data-label="Ghi chú">{{ ($rule['kind'] ?? 'replacement') === 'equivalent' ? 'Tương đương' : 'Thay thế' }} · {{ $rule['note'] }}</td><td>
                @if($editable)@can('programs.update')<form method="POST" action="{{ route('programs.substitutions.destroy', [$program, $rule['id']]) }}">@csrf @method('DELETE')<button class="academic-button academic-button-secondary">Bỏ quy tắc</button></form>@endcan @endif
            </td></tr>
        @empty<tr><td colspan="7">Chưa có quy tắc thay thế học phần.</td></tr>@endforelse
    </tbody></table></div>
    @if($editable)@can('programs.update')
        <details class="program-management"><summary>Thêm quy tắc thay thế</summary>
            <form method="POST" action="{{ route('programs.substitutions.store', $program) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                <label>Loại quy tắc<select class="block w-full rounded border-gray-300" name="kind"><option value="replacement">Thay thế</option><option value="equivalent">Tương đương (theo chiều nguồn → đích)</option></select></label>
                <label>Phiên bản nguồn<select class="block w-full rounded border-gray-300" name="source_version_id" required><option value="">Chọn phiên bản đã công bố</option>@foreach($sourceVersions as $source)<option value="{{ $source->id }}">{{ $source->snapshot['program']['code'] }} · K{{ $source->cohort_year }} · Lần {{ $source->revision }}</option>@endforeach</select></label>
                <label>Mã học phần nguồn (cách nhau bằng dấu phẩy)<textarea class="block w-full rounded border-gray-300" name="source_codes" required placeholder="HP01, HP02">{{ old('source_codes') }}</textarea></label>
                <label>Phân bổ học phần đích (mỗi dòng MÃ=TÍN_CHỈ)<textarea class="block w-full rounded border-gray-300" name="target_lines" required placeholder="HP03=3&#10;HP04=2">{{ old('target_lines') }}</textarea></label>
                <label>Quyết định/căn cứ cho phép<input class="block w-full rounded border-gray-300" name="decision_number" required maxlength="100" value="{{ old('decision_number') }}"></label>
                <label>Điều kiện và phạm vi áp dụng<textarea class="block w-full rounded border-gray-300" name="note" required maxlength="2000">{{ old('note') }}</textarea></label>
                <button class="academic-button academic-button-primary">Lưu quy tắc vào bản đang soạn</button>
            </form>
        </details>
    @endcan @endif
</section>



