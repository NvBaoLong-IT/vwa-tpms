<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Thêm học phần</h1><p>Nhập thông tin theo từng nhóm bên dưới.</p></div></div></x-slot>
    <div class="academic-page editor-page app-shell"><p class="editor-intro">Các trường có dấu * là bắt buộc.</p><form method="POST" action="{{ route('courses.store') }}">@include('courses.form')</form></div>
</x-app-layout>
