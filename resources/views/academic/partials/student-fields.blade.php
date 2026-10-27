<div class="student-form-fields">
    <label>Mã sinh viên<input name="student_code" value="{{ old('student_code', $student?->student_code) }}" required maxlength="50"><x-input-error :messages="$errors->get('student_code')" /></label>
    <label>Họ và tên<input name="full_name" value="{{ old('full_name', $student?->full_name) }}" required maxlength="255"><x-input-error :messages="$errors->get('full_name')" /></label>
    <label>Ngày sinh<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student?->date_of_birth?->format('Y-m-d')) }}"><x-input-error :messages="$errors->get('date_of_birth')" /></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $student?->email) }}" maxlength="255"><x-input-error :messages="$errors->get('email')" /></label>
    <label>Ngành<select name="program_id"><option value="">Chưa phân ngành</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('program_id', $student?->program_id) == $program->id)>{{ $program->code }} - {{ $program->name_vi }}</option>@endforeach</select></label>
    <label>Trình độ<select name="degree_level"><option value="dai_hoc" @selected(old('degree_level', $student?->degree_level ?? 'dai_hoc') === 'dai_hoc')>Đại học</option><option value="thac_si" @selected(old('degree_level', $student?->degree_level) === 'thac_si')>Thạc sĩ</option><option value="tien_si" @selected(old('degree_level', $student?->degree_level) === 'tien_si')>Tiến sĩ</option></select></label>
    <label>Lớp<input name="class_code" value="{{ old('class_code', $student?->class_code) }}" maxlength="50"></label>
    <label>Khoa<input name="faculty_name" value="{{ old('faculty_name', $student?->faculty_name) }}" maxlength="150"></label>
    <label>Năm nhập học<input type="number" name="enrollment_year" min="2000" max="2100" value="{{ old('enrollment_year', $student?->enrollment_year) }}"></label>
    <label>Trạng thái<select name="status"><option value="active" @selected(old('status', $student?->status ?? 'active') === 'active')>Đang học</option><option value="graduated" @selected(old('status', $student?->status) === 'graduated')>Đã tốt nghiệp</option><option value="withdrawn" @selected(old('status', $student?->status) === 'withdrawn')>Thôi học</option></select></label>
</div>
