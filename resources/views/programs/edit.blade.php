<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Chỉnh sửa chương trình đào tạo</h1><p>{{ $program->name_vi }}</p></div></div></x-slot>
    <div class="academic-page editor-page app-shell"><p class="editor-intro">Các trường có dấu * là bắt buộc.</p><form method="POST" action="{{ route('programs.update', $program) }}">@method('PUT')@include('programs.form')</form></div>
</x-app-layout>
