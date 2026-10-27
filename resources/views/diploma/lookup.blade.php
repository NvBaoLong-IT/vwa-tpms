<x-guest-layout>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold">Tra cứu văn bằng</h1>
        <p>Nhập chính xác số vào sổ hoặc số phôi. Mỗi địa chỉ mạng được tra cứu tối đa 5 lần mỗi phút.</p>
        <form method="GET" action="{{ route('diplomas.public-lookup') }}" class="space-y-3">
            <label for="code" class="block font-medium">Số vào sổ hoặc số phôi</label>
            <input id="code" name="code" value="{{ request('code') }}" required minlength="3" maxlength="100" class="w-full rounded border-gray-300">
            <x-input-error :messages="$errors->get('code')" />
            <x-primary-button>Tra cứu</x-primary-button>
        </form>
        @isset($result)
            <div role="status" class="rounded border p-4 space-y-2">
                @if(isset($result['holder_name']))
                    <p><strong>Họ tên:</strong> {{ $result['holder_name'] }}</p>
                    <p><strong>Ngày cấp:</strong> {{ $result['issue_date'] }}</p>
                    <p><strong>Loại văn bằng:</strong> {{ ['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'][$result['diploma_type']] ?? 'Văn bằng' }}</p>
                    <p><strong>Hiệu lực:</strong> {{ $result['valid'] ? 'Còn hiệu lực' : 'Đã hủy / không còn hiệu lực' }}</p>
                @else
                    <p>Không tìm thấy văn bằng phù hợp.</p>
                @endif
            </div>
        @endisset
    </div>
</x-guest-layout>
