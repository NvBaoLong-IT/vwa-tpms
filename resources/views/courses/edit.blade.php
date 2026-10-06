<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Chỉnh sửa học phần</h1><p>{{ $course->name_vi }}</p></div></div></x-slot>
    <div class="academic-page editor-page app-shell"><p class="editor-intro">Các trường có dấu * là bắt buộc.</p><form method="POST" action="{{ route('courses.update', $course) }}">@method('PUT')@include('courses.form')</form></div>
</x-app-layout>
