<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Chuẩn đầu ra - {{ $program->name_vi }}</h2></x-slot>
    <div class="py-8 max-w-4xl mx-auto px-4 space-y-5">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        <x-input-error :messages="$errors->all()" />
        @can('programs.update')
            <form method="POST" action="{{ route('programs.plos.store', $program) }}" class="flex flex-wrap gap-3 items-end border-b pb-5">
                @csrf
                <label class="text-sm">Mã PLO<input name="code" class="block rounded border-gray-300" required></label>
                <label class="text-sm flex-1">Mô tả<input name="description_vi" class="block w-full rounded border-gray-300" required></label>
                <button class="btn btn-primary">Thêm</button>
            </form>
        @endcan
        <div class="divide-y border bg-white">
            @forelse($plos as $plo)
                <div class="p-4">
                    @can('programs.update')
                        <form method="POST" action="{{ route('programs.plos.update', [$program, $plo]) }}" class="flex flex-wrap gap-3 items-end">
                            @csrf @method('PUT')
                            <label class="text-sm">Mã PLO<input name="code" value="{{ $plo->code }}" class="block w-28 rounded border-gray-300" required></label>
                            <label class="text-sm flex-1">Mô tả<input name="description_vi" value="{{ $plo->description_vi }}" class="block w-full rounded border-gray-300" required></label>
                            <button class="text-indigo-700 font-medium">Lưu</button>
                        </form>
                    @else
                        <strong>{{ $plo->code }}</strong> - {{ $plo->description_vi }}
                    @endcan
                </div>
            @empty
                <p class="p-4 text-gray-500">Chưa có chuẩn đầu ra.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
