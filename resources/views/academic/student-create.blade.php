<x-app-layout>
    <x-slot name="header"><div class="student-heading"><div><p class="student-eyebrow">Hồ sơ người học</p><h1>Thêm sinh viên</h1><p>Nhập hồ sơ cơ bản để bắt đầu theo dõi học vụ.</p></div></div></x-slot>
    <div class="student-page app-shell"><section class="student-section student-section-first"><form method="POST" action="{{ route('academic.student.store') }}" class="student-form">@csrf @include('academic.partials.student-fields', ['student' => null])<div class="student-form-actions"><button class="student-button student-button-primary">Lưu hồ sơ</button><a class="student-button student-button-secondary" href="{{ route('academic.students') }}">Hủy</a></div></form></section></div>
</x-app-layout>
