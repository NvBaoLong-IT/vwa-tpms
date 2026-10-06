<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Thêm chương trình đào tạo</h1><p>Nhập thông tin theo từng nhóm bên dưới.</p></div></div></x-slot>
    <div class="academic-page editor-page app-shell"><p class="editor-intro">Các trường có dấu * là bắt buộc.</p><form method="POST" action="{{ route('programs.store') }}">@include('programs.form')</form></div>
</x-app-layout>
