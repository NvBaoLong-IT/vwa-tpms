<x-app-layout>
    <x-slot name="header">
        <div class="student-heading">
            <div><p class="student-eyebrow">Chỉnh sửa hồ sơ sinh viên</p><h1>{{ $student->full_name }}</h1><p class="student-heading-code">{{ $student->student_code }}</p></div>
            <a class="student-button student-button-secondary" href="{{ route('academic.student', $student) }}">Xem thông tin sinh viên</a>
        </div>
    </x-slot>

    <div class="student-page app-shell">
        @can('students.manage')
        <section class="student-section student-edit-section student-section-first" aria-labelledby="student-master-title">
            <header><h2 id="student-master-title">Thông tin hồ sơ</h2><p>Cập nhật thông tin nhận diện và phân loại sinh viên.</p></header>
            <form method="POST" action="{{ route('academic.student.update', $student) }}" class="student-form">
                @csrf @method('PUT')
                @include('academic.partials.student-fields', ['student' => $student])
                <div class="student-form-actions"><button type="submit" class="student-button student-button-primary">Lưu hồ sơ</button></div>
            </form>
        </section>
        @endcan
        <section class="student-section student-edit-section student-section-first" aria-labelledby="student-assignment-title">
            <header><h2 id="student-assignment-title">Cố vấn và khoa phụ trách</h2><p>Phân công nơi tiếp nhận cảnh báo học vụ.</p></header>
            <form method="POST" action="{{ route('academic.assignment.update', $student) }}" class="student-form">
                @csrf @method('PUT')
                <div class="student-form-fields">
                    <label for="student-advisor">Cố vấn học tập<select id="student-advisor" name="advisor_user_id"><option value="">Chưa gán cố vấn</option>@foreach($advisors as $advisor)<option value="{{ $advisor->id }}" @selected(old('advisor_user_id', $student->advisor_user_id) == $advisor->id)>{{ $advisor->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('advisor_user_id')" /></label>
                    <label for="student-faculty">Khoa nhận cảnh báo<input id="student-faculty" name="faculty_name" maxlength="150" value="{{ old('faculty_name', $student->faculty_name) }}"><x-input-error :messages="$errors->get('faculty_name')" /></label>
                </div>
                <div class="student-form-actions"><button type="submit" class="student-button student-button-primary">Lưu phân công</button></div>
            </form>
        </section>

        <section class="student-section student-edit-section" aria-labelledby="student-edit-progress-title">
            <header><h2 id="student-edit-progress-title">Cập nhật tiến độ học tập</h2><p>Kết quả được lưu theo học kỳ và ghi vào lịch sử tiến độ.</p></header>
            <form method="POST" action="{{ route('academic.progress.update', $student) }}" class="student-form">
                @csrf @method('PUT')
                <div class="student-form-fields">
                    <label for="student-semester">Học kỳ<input id="student-semester" name="semester" maxlength="30" placeholder="2026-1" value="{{ old('semester') }}" required><x-input-error :messages="$errors->get('semester')" /></label>
                    <label for="student-gpa">Điểm trung bình hệ 4<input id="student-gpa" type="number" step="0.01" name="gpa_4" min="0" max="4" value="{{ old('gpa_4') }}" required><x-input-error :messages="$errors->get('gpa_4')" /></label>
                    <label for="student-credits">Tín chỉ tích lũy<input id="student-credits" type="number" name="credits_accumulated" min="0" value="{{ old('credits_accumulated') }}" required><x-input-error :messages="$errors->get('credits_accumulated')" /></label>
                    <label for="student-required-credits">Tín chỉ yêu cầu<input id="student-required-credits" type="number" name="credits_required" min="0" value="{{ old('credits_required') }}" required><x-input-error :messages="$errors->get('credits_required')" /></label>
                </div>
                <div class="student-form-actions"><button type="submit" class="student-button student-button-primary">Lưu và quét cảnh báo</button></div>
            </form>
        </section>

        <section class="student-section student-edit-section" aria-labelledby="student-edit-milestones-title">
            <header><h2 id="student-edit-milestones-title">Mốc học vụ</h2><p>Trạng thái học tập và các ngày được ghi nhận trong hồ sơ.</p></header>
            <form method="POST" action="{{ route('academic.milestones.update', $student) }}" class="student-form">
                @csrf @method('PUT')
                <div class="student-form-fields">
                    <label for="student-status">Trạng thái học tập<select id="student-status" name="status">@foreach(['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','withdrawn'=>'Thôi học'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('status')" /></label>
                    @foreach(['expected_graduation_date'=>'Tốt nghiệp dự kiến','graduated_at'=>'Ngày tốt nghiệp thực tế','withdrawn_at'=>'Ngày thôi học'] as $field => $label)
                        <label for="student-{{ $field }}">{{ $label }}<input id="student-{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field, $student->$field?->format('Y-m-d')) }}"><x-input-error :messages="$errors->get($field)" /></label>
                    @endforeach
                    @if($student->degree_level !== 'dai_hoc')
                        @foreach(['proposal_deadline'=>'Hạn nộp đề cương','proposal_submitted_at'=>'Đã nộp đề cương','department_defense_deadline'=>'Hạn bảo vệ cấp cơ sở','department_defended_at'=>'Đã bảo vệ cấp cơ sở','academy_defense_deadline'=>'Hạn bảo vệ cấp Học viện','academy_defended_at'=>'Đã bảo vệ cấp Học viện','maximum_study_end_date'=>'Hạn đào tạo tối đa'] as $field => $label)
                            <label for="student-{{ $field }}">{{ $label }}<input id="student-{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field, $student->$field?->format('Y-m-d')) }}"><x-input-error :messages="$errors->get($field)" /></label>
                        @endforeach
                    @endif
                </div>
                <div class="student-form-actions"><button type="submit" class="student-button student-button-primary">Lưu mốc học vụ</button></div>
            </form>
        </section>
        <a class="student-back-link" href="{{ route('academic.student', $student) }}">Quay lại thông tin sinh viên <span aria-hidden="true">&rarr;</span></a>
    </div>
</x-app-layout>
