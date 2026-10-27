@if(in_array($documentImport->status, ['review','returned'], true) && $documentImport->uploaded_by === auth()->id())
<details class="program-management"><summary>Sửa dữ liệu bản nháp AI trước khi trình</summary>
<p>Có thể lưu khi dữ liệu còn thiếu hoặc có môn tạm bỏ qua; chưa được trình duyệt cho đến khi bổ sung đủ. Chỉ cập nhật bản nháp, chưa ghi CTĐT. Nội dung không có trong bản nhập vẫn được giữ theo cách gộp. Nhóm tự chọn và khối bắt buộc dưới đây thay thế các khai báo tương ứng khi nhập.</p>
<form method="POST" action="{{ route('programs.document-imports.draft', $documentImport) }}" class="space-y-4">@csrf @method('PUT')
<input type="hidden" name="payload_hash" value="{{ app(\App\Services\CurriculumSnapshotDiffer::class)->fingerprint($payload) }}">
<div class="grid gap-3 sm:grid-cols-2">
@foreach(['code'=>'Mã CTĐT','name_vi'=>'Tên chương trình','total_credits'=>'Tổng tín chỉ','elective_credits_required'=>'Tự chọn cần tích lũy'] as $key=>$label)<label>{{ $label }}<input name="draft_program[{{ $key }}]" value="{{ old('draft_program.'.$key, $payload['program'][$key] ?? ($program?->$key ?? '')) }}" class="block w-full rounded border-gray-300" @if(in_array($key,['total_credits','elective_credits_required'])) type="number" min="{{ $key === 'total_credits' ? 1 : 0 }}" max="999" @endif></label>@endforeach
</div>
<label class="block">Khối kiến thức bắt buộc (mỗi dòng một khối)<textarea name="blocks_text" class="block w-full rounded border-gray-300" rows="3">{{ old('blocks_text', implode("\n", $payload['knowledge_blocks'] ?? ($program?->required_knowledge_blocks ?? []))) }}</textarea></label>
<label class="block">Nhóm tự chọn (mỗi dòng: MÃ | TÊN | KHỐI | TÍN CHỈ)<textarea name="groups_text" class="block w-full rounded border-gray-300" rows="3" placeholder="TC_A | Nhóm A | Chuyên ngành | 3">{{ old('groups_text', collect($payload['elective_groups'] ?? ($program?->elective_groups ?? []))->map(fn($g)=>$g['code'].' | '.$g['name'].' | '.$g['knowledge_block'].' | '.$g['credits_required'])->implode("\n")) }}</textarea></label>
@foreach($payload['courses'] ?? [] as $index=>$course)
<fieldset class="border p-3"><legend>{{ $course['course_code'] ?? 'Chưa có mã' }} · {{ $course['name_vi'] ?? 'Chưa có tên' }}</legend><div class="grid gap-3 sm:grid-cols-2">
@foreach(['course_code'=>'Mã học phần','name_vi'=>'Tên học phần','credits'=>'Tín chỉ','knowledge_block'=>'Khối kiến thức','elective_group_code'=>'Mã nhóm tự chọn','suggested_semester'=>'Học kỳ gợi ý','prerequisite_course_code'=>'Mã học trước','corequisite_course_code'=>'Mã song hành'] as $key=>$label)
<label>{{ $label }}<input name="draft_courses[{{ $index }}][{{ $key }}]" value="{{ old('draft_courses.'.$index.'.'.$key, $course[$key] ?? '') }}" class="block w-full rounded border-gray-300" @if(in_array($key,['credits','suggested_semester'])) type="number" min="1" max="{{ $key === 'credits' ? 99 : 20 }}" @endif></label>
@endforeach
<label>Loại học phần<select name="draft_courses[{{ $index }}][is_mandatory]" class="block w-full rounded border-gray-300"><option value="" @selected(!isset($course['is_mandatory']))>Chưa xác định</option><option value="1" @selected(isset($course['is_mandatory']) && $course['is_mandatory'])>Bắt buộc</option><option value="0" @selected(isset($course['is_mandatory']) && !$course['is_mandatory'])>Tự chọn</option></select></label>
<label>Phạm vi tín chỉ<select name="draft_courses[{{ $index }}][counts_toward_total]" class="block w-full rounded border-gray-300"><option value="1" @selected($course['counts_toward_total'] ?? true)>Tính vào tổng tín chỉ</option><option value="0" @selected(!($course['counts_toward_total'] ?? true))>Ngoài tổng tín chỉ</option></select></label>
<label>Trạng thái dòng<select name="draft_courses[{{ $index }}][is_deferred]" class="block w-full rounded border-gray-300"><option value="0" @selected(!($course['is_deferred'] ?? false))>Đang sử dụng</option><option value="1" @selected($course['is_deferred'] ?? false)>Tạm bỏ qua, chờ bổ sung</option></select></label>
</div></fieldset>
@endforeach
<button class="academic-button academic-button-primary">Lưu sửa bản nháp và đối chiếu lại</button>
</form></details>
@endif
