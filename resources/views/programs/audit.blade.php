<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Nhật ký CTĐT</h2></x-slot>
    <div class="py-8 max-w-7xl mx-auto px-4 space-y-4">
        <form method="GET" action="{{ route('programs.audit') }}" class="flex flex-wrap items-end gap-3">
            <label class="text-sm">Chương trình<select name="program_id" class="block rounded border-gray-300"><option value="">Tất cả</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></label>
            <label class="text-sm">Đối tượng<select name="type" class="block rounded border-gray-300"><option value="">Tất cả</option>@foreach(['program' => 'Chương trình', 'course' => 'Học phần', 'plo' => 'Chuẩn đầu ra', 'program_course' => 'Học phần trong CTĐT', 'plo_matrix' => 'Ma trận PLO', 'program_version' => 'Phiên bản'] as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="btn btn-secondary">Lọc</button>
        </form>
        <div class="overflow-x-auto border bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50"><tr><th class="p-3">Thời điểm</th><th class="p-3">Người thao tác</th><th class="p-3">Đối tượng</th><th class="p-3">Thao tác</th><th class="p-3">Thay đổi</th></tr></thead>
                <tbody>
                    @forelse($audits as $audit)
                        <tr class="border-t align-top">
                            <td class="p-3 whitespace-nowrap">{{ $audit->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3">{{ $audit->actor_name ?: 'Hệ thống' }}</td>
                            <td class="p-3">{{ ['program' => 'Chương trình', 'course' => 'Học phần', 'plo' => 'Chuẩn đầu ra', 'program_course' => 'Học phần trong CTĐT', 'plo_matrix' => 'Ma trận PLO', 'program_version' => 'Phiên bản'][$audit->subject_type] ?? $audit->subject_type }} #{{ $audit->subject_id }}</td>
                            <td class="p-3">{{ ['created' => 'Tạo', 'updated' => 'Sửa', 'deleted' => 'Xóa', 'attached' => 'Gắn', 'detached' => 'Bỏ', 'imported' => 'Nhập Excel', 'published' => 'Công bố', 'archived' => 'Thay thế'][$audit->action] ?? $audit->action }}</td>
                            <td class="p-3 min-w-72">
                                <details><summary class="cursor-pointer text-indigo-700">Xem thay đổi</summary>
                                    @if($audit->old_values !== null)<strong class="block mt-2">Trước</strong><pre class="whitespace-pre-wrap break-all max-h-80 overflow-auto text-xs">{{ json_encode($audit->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                                    @if($audit->new_values !== null)<strong class="block mt-2">Sau</strong><pre class="whitespace-pre-wrap break-all max-h-80 overflow-auto text-xs">{{ json_encode($audit->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-5 text-gray-500">Chưa có thao tác trong phạm vi này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $audits->links() }}
    </div>
</x-app-layout>
