<x-app-layout>
    <x-slot name="header">
        <div class="student-heading">
            <div><p class="student-eyebrow">Hồ sơ sinh viên</p><h1>{{ $student->full_name }}</h1><p class="student-heading-code">{{ $student->student_code }}</p></div>
            <div class="student-actions">@can('students.manage')<a class="student-button student-button-secondary" href="{{ route('academic.credits', $student) }}">Công nhận tín chỉ</a>@if(auth()->user()->hasRole('Lãnh đạo phòng'))<a class="student-button student-button-secondary" href="{{ route('academic.recognitions.queue') }}">Hồ sơ công nhận chờ duyệt</a>@endif @endcan
                <a class="student-button student-button-secondary" href="{{ route('academic.students') }}">Danh sách sinh viên</a>
                @if(auth()->user()->can('academic.view') && auth()->user()->can('academic.process'))
                    <a class="student-button student-button-primary" href="{{ route('academic.student.edit', $student) }}">Chỉnh sửa hồ sơ</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="student-page app-shell">
        <section class="student-section student-section-first" aria-labelledby="student-info-title">
            <div class="student-section-heading"><h2 id="student-info-title">Thông tin sinh viên</h2><span class="student-status student-status-{{ $student->status }}">{{ ['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','withdrawn'=>'Thôi học'][$student->status] ?? $student->status }}</span></div>
            <dl class="student-facts">
                <div><dt>Mã sinh viên</dt><dd>{{ $student->student_code }}</dd></div>
                <div><dt>Họ và tên</dt><dd>{{ $student->full_name }}</dd></div>
                <div><dt>Ngày sinh</dt><dd>{{ $student->date_of_birth?->format('d/m/Y') ?? 'Chưa có thông tin' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $student->email ?: 'Chưa có thông tin' }}</dd></div>
                <div><dt>Ngành đào tạo</dt><dd>{{ $student->program?->name_vi ?: 'Chưa phân ngành' }}</dd></div>
                <div><dt>Trình độ</dt><dd>{{ ['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'][$student->degree_level] ?? $student->degree_level }}</dd></div>
                <div><dt>Lớp</dt><dd>{{ $student->class_code ?: 'Chưa có thông tin' }}</dd></div>
                <div><dt>Năm nhập học</dt><dd>{{ $student->enrollment_year ?: 'Chưa có thông tin' }}</dd></div>
                <div><dt>Khoa nhận cảnh báo</dt><dd>{{ $student->faculty_name ?: 'Chưa được phân công' }}</dd></div>
                <div><dt>Cố vấn học tập</dt><dd>{{ $student->advisor?->name ?: 'Chưa được phân công' }}</dd></div>
            </dl>
        </section>

        @can('academic.view')
            @php($latestProgress = $student->progress->sortByDesc('semester')->first())
            <section class="student-section" aria-labelledby="student-results-title">
                <div class="student-section-heading"><h2 id="student-results-title">Kết quả học tập</h2>@if($latestProgress)<span>Học kỳ {{ $latestProgress->semester }}</span>@endif</div>
                @if($latestProgress)
                    <div class="student-results">
                        <div><p>Điểm trung bình hệ 4</p><strong>{{ $latestProgress->gpa_4 ?? 'Chưa có dữ liệu' }}</strong></div>
                        <div><p>Tín chỉ tích lũy</p><strong>{{ $latestProgress->credits_accumulated }}<small> / {{ $latestProgress->credits_required }} yêu cầu</small></strong></div>
                        <div><p>Cảnh báo chưa hoàn tất</p><strong>{{ $student->warnings->whereIn('status', ['open', 'processing'])->count() }}</strong></div>
                    </div>
                @else
                    <p class="student-empty">Chưa có dữ liệu tiến độ học tập.</p>
                @endif
            </section>

            <section class="student-section" aria-labelledby="student-milestones-title">
                <div class="student-section-heading"><h2 id="student-milestones-title">Mốc học vụ</h2></div>
                <dl class="student-facts student-milestones">
                    @foreach(['expected_graduation_date'=>'Tốt nghiệp dự kiến','graduated_at'=>'Ngày tốt nghiệp thực tế','withdrawn_at'=>'Ngày thôi học'] as $field => $label)
                        <div><dt>{{ $label }}</dt><dd>{{ $student->$field?->format('d/m/Y') ?? 'Chưa ghi nhận' }}</dd></div>
                    @endforeach
                    @if($student->degree_level !== 'dai_hoc')
                        @foreach(['proposal_deadline'=>'Hạn nộp đề cương','proposal_submitted_at'=>'Đã nộp đề cương','department_defense_deadline'=>'Hạn bảo vệ cấp cơ sở','department_defended_at'=>'Đã bảo vệ cấp cơ sở','academy_defense_deadline'=>'Hạn bảo vệ cấp Học viện','academy_defended_at'=>'Đã bảo vệ cấp Học viện','maximum_study_end_date'=>'Hạn đào tạo tối đa'] as $field => $label)
                            <div><dt>{{ $label }}</dt><dd>{{ $student->$field?->format('d/m/Y') ?? 'Chưa ghi nhận' }}</dd></div>
                        @endforeach
                    @endif
                </dl>
            </section>

            <div class="student-history-grid">
                <section class="student-section" aria-labelledby="student-progress-title">
                    <div class="student-section-heading"><h2 id="student-progress-title">Lịch sử tiến độ</h2></div>
                    <div class="student-history">
                        @forelse($student->progress->sortByDesc('semester') as $progress)
                            <article class="student-history-entry">
                                <h3>Học kỳ {{ $progress->semester }}</h3>
                                <p>{{ $progress->credits_accumulated }}/{{ $progress->credits_required }} tín chỉ &middot; Điểm trung bình {{ $progress->gpa_4 }}</p>
                                @foreach($progress->histories as $history)
                                    <small>{{ $history->created_at->format('d/m/Y H:i') }} &middot; {{ $history->user?->name ?: 'Hệ thống' }} &middot; {{ ['created'=>'Tạo mới','updated'=>'Cập nhật'][$history->action] ?? $history->action }}</small>
                                @endforeach
                            </article>
                        @empty
                            <p class="student-empty">Chưa có lịch sử tiến độ.</p>
                        @endforelse
                    </div>
                </section>
                <section class="student-section" aria-labelledby="student-warnings-title">
                    <div class="student-section-heading"><h2 id="student-warnings-title">Lịch sử cảnh báo</h2></div>
                    <div class="student-history">
                        @forelse($student->warnings as $warning)
                            <article class="student-history-entry">
                                <div class="student-warning-heading"><h3>{{ config('academic.warning_labels')[$warning->warning_type] ?? $warning->warning_type }}</h3><span class="student-status student-warning-{{ $warning->status }}">{{ ['open'=>'Chưa xử lý','processing'=>'Đang xử lý','resolved'=>'Đã xử lý','dismissed'=>'Đã bỏ qua'][$warning->status] ?? $warning->status }}</span></div>
                                <p>{{ str_replace('GPA', 'Điểm trung bình hệ 4', $warning->reason) }}</p>
                                <small>{{ $warning->issued_at?->format('d/m/Y H:i') ?? 'Chưa ghi nhận ngày' }}</small>
                            </article>
                        @empty
                            <p class="student-empty">Không có cảnh báo.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endcan
    </div>
</x-app-layout>
