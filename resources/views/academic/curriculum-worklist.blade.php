<section class="dashboard-section" aria-labelledby="curriculum-work-title">
    <div class="dashboard-section-heading"><h2 id="curriculum-work-title">Kiểm soát CTĐT và công nhận tín chỉ</h2><span>Tối đa 5 việc lâu nhất mỗi nhóm · Số liệu theo quyền truy cập</span></div>
    @foreach($curriculumWork as $group)
        <div class="program-detail-overview mb-4">
            <div class="academic-section-heading"><h3>{{ $group['title'] }}</h3><strong>{{ $group['count'] }} việc</strong></div>
            <p class="version-snapshot-note">{{ $group['scope'] }}</p>
            <div class="dashboard-work-list">@forelse($group['items'] as $item)<div class="dashboard-work-row"><div><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></div><a href="{{ $item['url'] }}">Mở hồ sơ <span aria-hidden="true">&rarr;</span></a></div>@empty<p>Không có việc cần xử lý trong phạm vi này.</p>@endforelse</div>
        </div>
    @endforeach
</section>
