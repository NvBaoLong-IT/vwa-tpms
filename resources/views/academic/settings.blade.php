<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Ngưỡng GPA cảnh báo</h2></x-slot>
    <div class="py-8 max-w-3xl mx-auto px-4">
        @if(session('success'))<p class="p-3 mb-4 bg-green-100 text-green-800">{{ session('success') }}</p>@endif
        <form method="POST" action="{{ route('academic.settings.update') }}">
            @csrf @method('PUT')
            <div class="border bg-white divide-y">
                @foreach(['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'] as $degree => $label)
                    @php($setting = $settings->firstWhere('degree_level', $degree))
                    @if($setting)
                        <label class="flex items-center justify-between gap-4 p-4 text-sm">
                            <span>{{ $label }}</span>
                            <input type="number" step="0.01" min="0" max="4" name="settings[{{ $setting->id }}]" value="{{ $setting->value }}" class="w-28 rounded border-gray-300" required>
                        </label>
                    @endif
                @endforeach
            </div>
            <button class="btn btn-primary mt-4">Lưu ngưỡng</button>
        </form>
    </div>
</x-app-layout>
