<section class="program-detail-section" id="program-elective-groups" aria-labelledby="elective-groups-heading">
    <div class="academic-section-heading"><h2 id="elective-groups-heading">Chỉ tiêu tự chọn theo nhóm / khối</h2></div>
    <p class="version-snapshot-note">Mỗi học phần tự chọn chỉ thuộc một nhóm. Một khối có thể có nhiều nhóm. Tổng chỉ tiêu nhóm phải bằng tín chỉ tự chọn cần tích lũy của CTĐT; số tín chỉ học phần trong mỗi nhóm phải đáp ứng chỉ tiêu riêng.</p>
    @if(count($reconciliation['elective_groups']))
        <div class="program-detail-table-wrap"><table class="program-detail-table"><thead><tr><th>Mã nhóm</th><th>Tên nhóm</th><th>Khối kiến thức</th><th>Cần tích lũy</th><th>Đang mở</th><th>Còn thiếu</th><th></th></tr></thead><tbody>
            @foreach($reconciliation['elective_groups'] as $group)
                <tr><td data-label="Mã nhóm">{{ $group['code'] }}</td><td data-label="Tên nhóm">{{ $group['name'] }}</td><td data-label="Khối kiến thức">{{ $group['knowledge_block'] }}</td><td data-label="Cần tích lũy">{{ $group['credits_required'] }}</td><td data-label="Đang mở">{{ $group['credits_offered'] }}</td><td data-label="Còn thiếu">{{ $group['credits_missing'] }}</td><td>
                    @if($editable)@can('programs.update')
                        <details><summary>Chỉnh nhóm {{ $group['code'] }}</summary><form method="POST" action="{{ route('programs.elective-groups.store', $program) }}" class="space-y-2">@csrf
                            <input type="hidden" name="code" value="{{ $group['code'] }}">
                            <label>Tên nhóm<input class="block w-full rounded border-gray-300" name="name" value="{{ $group['name'] }}" required maxlength="150"></label>
                            <label>Khối kiến thức<input class="block w-full rounded border-gray-300" name="knowledge_block" value="{{ $group['knowledge_block'] }}" required maxlength="100"></label>
                            <label>Chỉ tiêu<input class="block w-full rounded border-gray-300" type="number" name="credits_required" value="{{ $group['credits_required'] }}" min="1" max="999" required></label>
                            <button class="academic-button academic-button-primary">Lưu nhóm</button>
                        </form></details>
                        <form method="POST" action="{{ route('programs.elective-groups.destroy', [$program, $group['code']]) }}">@csrf @method('DELETE')<button class="academic-button academic-button-secondary">Bỏ nhóm {{ $group['code'] }}</button></form>
                    @endcan @endif
                </td></tr>
            @endforeach
        </tbody></table></div>
    @else<p>Chưa khai báo nhóm tự chọn; hiện đối chiếu theo tổng chỉ tiêu toàn chương trình.</p>@endif
    @if($editable)@can('programs.update')
        <details class="program-management"><summary>Thêm nhóm tự chọn</summary><form method="POST" action="{{ route('programs.elective-groups.store', $program) }}" class="flex flex-wrap items-end gap-3">@csrf
            <label>Mã nhóm<input name="code" class="block rounded border-gray-300" required maxlength="30" pattern="[A-Z0-9_-]+" placeholder="VD: TC_A"></label>
            <label>Tên nhóm<input name="name" class="block rounded border-gray-300" required maxlength="150"></label>
            <label>Khối kiến thức<input name="knowledge_block" class="block rounded border-gray-300" required maxlength="100"></label>
            <label>Tín chỉ cần tích lũy<input name="credits_required" class="block rounded border-gray-300" type="number" min="1" max="999" required></label>
            <button class="academic-button academic-button-primary">Thêm nhóm</button>
        </form></details>
    @endcan @endif
</section>
