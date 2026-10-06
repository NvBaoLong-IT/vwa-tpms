<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Chi tiết đợt rà soát</p><h1>{{ $workflow->title }}</h1><p>{{ $workflow->program->name_vi }} · {{ $workflow->program->code }}</p></div>
            <div class="academic-tools"><a href="{{ route('iso.index') }}" class="academic-button academic-button-secondary">Danh sách rà soát</a><a href="{{ route('iso.kanban') }}" class="academic-button academic-button-secondary">Bảng tiến độ</a></div>
        </div>
    </x-slot>
    <div class="academic-page iso-detail-page app-shell">
        @php
            $stepLabels = ['not_started'=>'Chưa bắt đầu','in_progress'=>'Đang thực hiện','waiting_library'=>'Đợi Thư viện','waiting_approval'=>'Đợi phê duyệt','completed'=>'Hoàn thành'];
            $workflowLabels = ['draft'=>'Bản nháp','in_progress'=>'Đang thực hiện','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'];
            $completed = $workflow->steps->where('status', 'completed')->count();
            $total = $workflow->steps->count();
            $percent = $total ? round($completed / $total * 100) : 0;
            $overdue = $workflow->steps->filter(fn ($step) => $step->status !== 'completed' && $step->deadline?->lt(today()))->count();
        @endphp
        @if(session('success'))<div class="academic-success" role="status">{{ session('success') }}</div>@endif
        <section class="iso-detail-overview" aria-labelledby="iso-info-title">
            <div class="academic-section-heading"><h2 id="iso-info-title">Tổng quan đợt rà soát</h2><span class="academic-status iso-status-{{ $workflow->status }}">{{ $workflowLabels[$workflow->status] ?? $workflow->status }}</span></div>
            <dl class="student-facts">
                <div><dt>Loại rà soát</dt><dd>{{ ['new'=>'Xây dựng mới','major'=>'Chỉnh sửa lớn','minor'=>'Chỉnh sửa nhỏ'][$workflow->type] ?? $workflow->type }}</dd></div>
                <div><dt>Ngày bắt đầu</dt><dd>{{ $workflow->start_date->format('d/m/Y') }}</dd></div>
                <div><dt>Hạn hoàn thành</dt><dd>{{ $workflow->expected_end_date->format('d/m/Y') }}</dd></div>
                <div><dt>Tín chỉ thay đổi</dt><dd>{{ $workflow->changed_credits ?? 'Chưa ghi nhận' }}</dd></div>
            </dl>
            <div class="iso-detail-progress">
                <div class="iso-progress-label"><strong>{{ $completed }}/{{ $total }} bước hoàn thành</strong><span>{{ $percent }}%</span></div>
                @if($total)<progress class="iso-progress" value="{{ $completed }}" max="{{ $total }}" aria-label="Tiến độ đợt rà soát">{{ $percent }}%</progress>@endif
                <div class="iso-detail-progress-notes"><span>{{ $workflow->files->count() }} tệp hồ sơ</span>@if($overdue)<span class="iso-overdue">{{ $overdue }} bước chưa hoàn thành quá hạn</span>@else<span>Không có bước chưa hoàn thành quá hạn</span>@endif</div>
            </div>
            @if($workflow->note)<p class="iso-detail-note"><strong>Ghi chú:</strong> {{ $workflow->note }}</p>@endif
        </section>
        <nav class="program-detail-navigation" aria-label="Nội dung đợt rà soát"><a href="#iso-detail-steps">Các bước thực hiện</a><a href="#iso-detail-files">Hồ sơ rà soát</a><a href="#iso-detail-approval">Phê duyệt và hoàn tất</a></nav>

        <section id="iso-detail-steps" class="program-detail-section" aria-labelledby="iso-steps-title">
            <div class="academic-section-heading"><h2 id="iso-steps-title">Các bước thực hiện</h2><span>Theo thứ tự quy trình</span></div>
            <div class="iso-step-list">
                @forelse($workflow->steps->sortBy('step_number') as $step)
                    @php($isOverdue = $step->status !== 'completed' && $step->deadline?->lt(today()))
                    <article @class(['iso-step-card','iso-step-card-overdue'=>$isOverdue])>
                        <div class="iso-step-heading">
                            <span class="iso-step-number" aria-hidden="true">{{ $step->step_number }}</span>
                            <div><p class="academic-eyebrow">Giai đoạn {{ $step->step_number }}</p><h3>{{ str_replace('CTĐT', 'chương trình đào tạo', $step->step_name) }}</h3></div>
                            <span class="academic-status iso-step-status-{{ $step->status }}">{{ $stepLabels[$step->status] ?? $step->status }}</span>
                        </div>
                        <dl class="iso-step-facts">
                            <div><dt>Hạn thực hiện</dt><dd>{{ $step->deadline?->format('d/m/Y') ?? 'Chưa ghi nhận' }} @if($isOverdue)<span class="iso-overdue">Quá hạn</span>@endif</dd></div>
                            <div><dt>Người phụ trách</dt><dd>{{ $step->assignee?->name ?: 'Chưa phân công' }}</dd></div>
                            <div><dt>Cập nhật gần nhất</dt><dd>{{ $step->updated_at->format('d/m/Y H:i') }}</dd></div>
                        </dl>
                        @if($step->note)<p class="iso-detail-note"><strong>Ghi chú:</strong> {{ $step->note }}</p>@endif
                        @can('iso.update')
                            <details class="program-management">
                                <summary>Cập nhật trạng thái và ghi chú</summary>
                                <form method="POST" action="{{ route('iso.steps.update', [$workflow, $step->id]) }}" class="iso-step-form">
                                    @csrf @method('PATCH')
                                    <label class="program-form-field" for="iso-step-status-{{ $step->id }}">Trạng thái bước<select id="iso-step-status-{{ $step->id }}" name="status">@foreach($stepLabels as $value=>$label)<option value="{{ $value }}" @selected($step->status === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="program-form-field" for="iso-step-note-{{ $step->id }}">Ghi chú<textarea id="iso-step-note-{{ $step->id }}" name="note" rows="3">{{ $step->note }}</textarea></label>
                                    <button class="academic-button academic-button-primary" type="submit">Lưu cập nhật</button>
                                </form>
                            </details>
                        @endcan
                        @can('iso.assign')
                            <details class="program-management">
                                <summary>Phân công người phụ trách</summary>
                                <form method="POST" action="{{ route('iso.steps.assign', [$workflow, $step->id]) }}" class="iso-step-form">
                                    @csrf @method('PATCH')
                                    <label class="program-form-field" for="iso-step-assignee-{{ $step->id }}">Người phụ trách<select id="iso-step-assignee-{{ $step->id }}" name="assigned_to_user_id"><option value="">Chưa phân công</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($step->assigned_to_user_id == $user->id)>{{ $user->name }}</option>@endforeach</select></label>
                                    <button class="academic-button academic-button-secondary" type="submit">Lưu phân công</button>
                                </form>
                            </details>
                        @endcan
                        <details class="program-management">
                            <summary>Lịch sử thay đổi ({{ $step->histories->count() }})</summary>
                            <ul class="iso-step-history">
                                @forelse($step->histories as $history)
                                    <li><strong>{{ $history->user?->name ?: 'Người dùng đã xóa' }}</strong><span>{{ $history->created_at->format('d/m/Y H:i') }} · {{ ['updated'=>'Cập nhật','assigned'=>'Giao việc'][$history->action] ?? $history->action }}</span>
                                        @if(isset($history->new_values['status']))<p>{{ $stepLabels[$history->new_values['status']] ?? $history->new_values['status'] }}</p>
                                        @elseif(array_key_exists('assigned_to_user_id', $history->new_values ?? []))<p>Người phụ trách: {{ $history->new_values['assigned_to_user_id'] ? ($users->firstWhere('id', $history->new_values['assigned_to_user_id'])?->name ?? 'Đã thay đổi') : 'Chưa phân công' }}</p>@endif
                                    </li>
                                @empty<li>Chưa có thay đổi.</li>@endforelse
                            </ul>
                        </details>
                    </article>
                @empty<p class="student-empty">Chưa có bước thực hiện.</p>@endforelse
            </div>
        </section>

        <section id="iso-detail-files" class="program-detail-section" aria-labelledby="iso-files-title">
            <div class="academic-section-heading"><h2 id="iso-files-title">Hồ sơ rà soát</h2><span>{{ $workflow->files->count() }} tệp</span></div>
            @forelse($workflow->files as $file)
                <div class="iso-file-row"><div><strong>{{ $file->original_name }}</strong><small>{{ $file->uploader?->name ?: 'Người dùng đã xóa' }} · {{ $file->created_at->format('d/m/Y H:i') }}</small></div><a class="program-detail-link" href="{{ route('iso.files.download', [$workflow, $file]) }}">Tải xuống &darr;</a></div>
            @empty<p class="student-empty">Chưa có hồ sơ.</p>@endforelse
            @can('iso.upload')
                <details class="program-management"><summary>Bổ sung hồ sơ rà soát</summary><p>PDF, Word hoặc ảnh JPG/PNG; tối đa 20 MB mỗi tệp.</p>
                    <form method="POST" action="{{ route('iso.files.upload', $workflow) }}" enctype="multipart/form-data" class="iso-step-form">
                        @csrf
                        <label class="program-form-field" for="iso-file">Tệp hồ sơ<input id="iso-file" type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required></label>
                        <x-input-error :messages="$errors->get('file')" />
                        <button type="submit" class="academic-button academic-button-primary">Tải hồ sơ lên</button>
                    </form>
                </details>
            @endcan
        </section>

        <section id="iso-detail-approval" class="program-detail-section" aria-labelledby="iso-approval-title">
            <div class="academic-section-heading"><h2 id="iso-approval-title">Phê duyệt và hoàn tất</h2></div>
            <dl class="iso-step-facts"><div><dt>Ngày phê duyệt</dt><dd>{{ $workflow->approved_at?->format('d/m/Y H:i') ?? 'Chưa phê duyệt' }}</dd></div><div><dt>Ngày hoàn tất</dt><dd>{{ $workflow->closed_at?->format('d/m/Y H:i') ?? 'Chưa hoàn tất' }}</dd></div></dl>
            <div class="iso-approval-actions">
                @can('iso.approve')<details class="program-management"><summary>Phê duyệt đợt rà soát</summary><p>Kiểm tra các bước và hồ sơ trước khi ghi nhận phê duyệt.</p><form method="POST" action="{{ route('iso.approve', $workflow) }}">@csrf<button type="submit" class="academic-button academic-button-primary">Xác nhận phê duyệt</button></form></details>@endcan
                @can('iso.close')<details class="program-management"><summary>Hoàn tất đợt rà soát</summary><p>Cần có phê duyệt trước khi đóng đợt rà soát. Hãy đối chiếu tiến độ các bước và hồ sơ trước khi xác nhận.</p><form method="POST" action="{{ route('iso.close', $workflow) }}">@csrf<button type="submit" class="academic-button academic-button-secondary">Xác nhận hoàn tất</button></form></details>@endcan
            </div>
        </section>
    </div>
</x-app-layout>
