<x-app-layout>
    <x-slot name="header">
        <div class="dashboard-heading">
            <div class="dashboard-heading-main">
                <div class="dashboard-heading-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 3 7.5 12 12l9-4.5L12 3Z" stroke="currentColor" stroke-width="1.6"/><path d="M3 12 12 16.5 21 12" stroke="currentColor" stroke-width="1.6"/><path d="M3 16.5 12 21l9-4.5" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="7.5" r="1.4" fill="currentColor"/></svg>
                </div>
                <div>
                    <p class="dashboard-eyebrow">Học viện Phụ nữ Việt Nam &middot; Phòng Đào tạo</p>
                    <h1>Bảng điều hành đào tạo</h1>
                    <p class="dashboard-heading-subtitle">Tổng quan sinh viên, chất lượng đào tạo và tiến độ công việc.</p>
                </div>
            </div>
            <div class="dashboard-heading-meta">
                <span class="dashboard-date">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M7 3v3M13 3v3M3 8h14" stroke="currentColor" stroke-width="1.4"/></svg>
                    {{ now()->format('d/m/Y') }}
                </span>
                <span class="dashboard-role">{{ auth()->user()->getRoleNames()->map(fn ($role) => config('role_labels')[$role] ?? $role)->join(', ') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="dashboard-page app-shell">
        @php
            $studentFilters = array_filter(['program_id' => request('program_id'), 'degree_level' => request('degree_level'), 'enrollment_year' => request('year')], fn ($value) => $value !== null && $value !== '');
            $warningFilters = array_merge($studentFilters, ['status' => 'open']);
            $gradRate = $stats['graduation_rate'];
            $hasGrad = $gradRate !== null;
            $gradPct = $hasGrad ? (float) $gradRate : 0;
            // Circumference of the graduation progress ring (r = 36).
            $circ = 226.19;
            $dash = round($circ * $gradPct / 100, 1);
        @endphp

        {{-- Filters --}}
        <form method="GET" action="{{ route('dashboard') }}" class="dashboard-filters" aria-label="Bộ lọc bảng điều hành">
            <div class="dashboard-filter">
                <label for="dashboard-program">Ngành đào tạo</label>
                <select id="dashboard-program" name="program_id"><option value="">Tất cả ngành</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select>
            </div>
            <div class="dashboard-filter">
                <label for="dashboard-degree">Trình độ</label>
                <select id="dashboard-degree" name="degree_level"><option value="">Tất cả trình độ</option><option value="dai_hoc" @selected(request('degree_level') === 'dai_hoc')>Đại học</option><option value="thac_si" @selected(request('degree_level') === 'thac_si')>Thạc sĩ</option><option value="tien_si" @selected(request('degree_level') === 'tien_si')>Tiến sĩ</option></select>
            </div>
            <div class="dashboard-filter dashboard-filter-year">
                <label for="dashboard-year">Năm nhập học</label>
                <input id="dashboard-year" type="number" min="2000" max="2100" name="year" value="{{ request('year') }}" placeholder="Tất cả năm">
            </div>
            <div class="dashboard-filter-actions">
                <button type="submit" class="dashboard-action-primary">Áp dụng lọc</button>
                @if(request()->hasAny(['program_id', 'degree_level', 'year']))<a href="{{ route('dashboard') }}" class="dashboard-action-clear">Xóa lọc</a>@endif
            </div>
        </form>

        <div class="dashboard-context" role="status">
            <span class="dashboard-context-dot" aria-hidden="true"></span>
            <span>Đang xem:</span>
            <strong>{{ request('program_id') ? ($programs->firstWhere('id', request('program_id'))?->name_vi ?? 'Ngành đã chọn') : 'Tất cả ngành' }}</strong>
            <span class="dashboard-context-sep">·</span>
            <span>{{ ['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'][request('degree_level')] ?? 'Tất cả trình độ' }}</span>
            <span class="dashboard-context-sep">·</span>
            <span>{{ request('year') ? 'Khóa '.request('year') : 'Tất cả khóa' }}</span>
        </div>

        {{-- KPI grid --}}
        <section class="dashboard-section" aria-labelledby="dashboard-overview-title">
            <div class="dashboard-section-heading">
                <h2 id="dashboard-overview-title">Tổng quan nhanh</h2>
                <span>Số liệu theo phạm vi đang xem</span>
            </div>
            <div class="dashboard-metrics">
                @canany(['students.view', 'academic.view'])
                    <a class="dashboard-metric dashboard-metric-students" href="{{ route('academic.students', $studentFilters) }}">
                        <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="1.7"/><path d="M4 18c0-2.8 2.7-5 8-5s8 2.2 8 5v1H4v-1Z" stroke="currentColor" stroke-width="1.7"/></svg></span><span class="dashboard-metric-badge">Sinh viên</span></div>
                        <p>Tổng sinh viên theo lọc</p><strong>{{ number_format($stats['students']) }}</strong><span class="dashboard-metric-detail">Xem danh sách &rarr;</span>
                    </a>
                @else
                    <div class="dashboard-metric dashboard-metric-students"><div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="1.7"/><path d="M4 18c0-2.8 2.7-5 8-5s8 2.2 8 5v1H4v-1Z" stroke="currentColor" stroke-width="1.7"/></svg></span><span class="dashboard-metric-badge">Sinh viên</span></div><p>Tổng sinh viên theo lọc</p><strong>{{ number_format($stats['students']) }}</strong></div>
                @endcanany

                @can('academic.view')
                    <a class="dashboard-metric dashboard-metric-warnings" href="{{ route('academic.index', $warningFilters) }}">
                        <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 8v6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1.2" fill="currentColor"/><path d="M12 3 3 18h18L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-warn">Cần xử lý</span></div>
                        <p>Cảnh báo đang mở</p><strong>{{ number_format($stats['warnings_open']) }}</strong><span class="dashboard-metric-detail">Xử lý cảnh báo &rarr;</span>
                    </a>
                @else
                    <div class="dashboard-metric dashboard-metric-warnings"><div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 8v6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1.2" fill="currentColor"/><path d="M12 3 3 18h18L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-warn">Cần xử lý</span></div><p>Cảnh báo đang mở</p><strong>{{ number_format($stats['warnings_open']) }}</strong></div>
                @endcan

                @can('iso.view')
                    <a class="dashboard-metric dashboard-metric-iso" href="{{ route('iso.index', ['status' => 'in_progress', 'overdue' => 1]) }}">
                        <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="15" r="2.2" stroke="currentColor" stroke-width="1.5"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-iso">ISO</span></div>
                        <p>Rà soát quá hạn</p><strong>{{ number_format($stats['iso_overdue']) }}</strong><span class="dashboard-metric-subdetail">Toàn hệ thống</span><span class="dashboard-metric-detail">Mở danh sách rà soát &rarr;</span>
                    </a>
                @else
                    <div class="dashboard-metric dashboard-metric-iso"><div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="15" r="2.2" stroke="currentColor" stroke-width="1.5"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-iso">ISO</span></div><p>Rà soát quá hạn</p><strong>{{ number_format($stats['iso_overdue']) }}</strong><span class="dashboard-metric-subdetail">Toàn hệ thống</span></div>
                @endcan

                @can('programs.view')
                <a class="dashboard-metric dashboard-metric-programs" href="{{ route('programs.index') }}">
                    <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 6.5A2.5 2.5 0 0 1 7.5 4H19v14H7.5A2.5 2.5 0 0 0 5 20.5V6.5Z" stroke="currentColor" stroke-width="1.6"/><path d="M5 6.5h10M5 10h10M5 13.5h7" stroke="currentColor" stroke-width="1.4"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-ct">CTĐT</span></div>
                    <p>Chương trình đào tạo</p><strong>{{ number_format($stats['programs']) }}</strong><span class="dashboard-metric-detail">Quản lý CTĐT &rarr;</span>
                </a>
                @else
                <div class="dashboard-metric dashboard-metric-programs">
                    <div class="dashboard-metric-top"><span class="dashboard-metric-badge">CTĐT</span></div>
                    <p>Chương trình đào tạo</p><strong>{{ number_format($stats['programs']) }}</strong>
                </div>
                @endcan

                <div class="dashboard-metric dashboard-metric-imports {{ $stats['import_errors'] > 0 ? 'dashboard-metric-attention' : '' }}">
                    <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 15V5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M8 9 12 5l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="16" width="16" height="4" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg></span><span class="dashboard-metric-badge {{ $stats['import_errors'] > 0 ? 'dashboard-metric-badge-bad' : 'dashboard-metric-badge-ok' }}">{{ $stats['import_errors'] > 0 ? 'Có lỗi' : 'Ổn định' }}</span></div>
                    <p>Lần nhập có lỗi</p><strong>{{ number_format($stats['import_errors']) }}</strong><span class="dashboard-metric-subdetail">Toàn hệ thống</span>
                </div>

                <div class="dashboard-metric dashboard-metric-graduation">
                    <div class="dashboard-metric-top"><span class="dashboard-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 5 4 9l8 4 8-4-8-4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 13 12 17l8-4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M20 9v6" stroke="currentColor" stroke-width="1.6"/></svg></span><span class="dashboard-metric-badge dashboard-metric-badge-ok">Tốt nghiệp</span></div>
                    <p>Tốt nghiệp đúng hạn</p>
                    <strong @class(['dashboard-metric-value-empty' => !$hasGrad])>{{ $hasGrad ? number_format($gradRate, 1).'%' : 'Chưa đủ dữ liệu' }}</strong>
                    <span class="dashboard-metric-subdetail">{{ $stats['graduated_on_time'] }}/{{ $stats['graduation_eligible'] }} sinh viên đến hạn</span>
                </div>
            </div>
        </section>

        <section class="dashboard-section" aria-labelledby="dashboard-work-title">
            <div class="dashboard-section-heading">
                <h2 id="dashboard-work-title">Việc cần theo dõi</h2>
                <span>Cảnh báo học vụ theo bộ lọc; rà soát và nhập dữ liệu trên toàn hệ thống</span>
            </div>
            <div class="dashboard-work-list">
                @if($stats['warnings_open'] > 0)
                    <div class="dashboard-work-row">
                        <span class="dashboard-work-count">{{ number_format($stats['warnings_open']) }}</span>
                        <div><strong>Cảnh báo học vụ đang mở</strong><small>Cần rà soát kết quả học tập và tình trạng sinh viên.</small></div>
                        @can('academic.view')<a href="{{ route('academic.index', $warningFilters) }}">Xử lý cảnh báo <span aria-hidden="true">&rarr;</span></a>@endcan
                    </div>
                @endif
                @if($stats['iso_overdue'] > 0)
                    <div class="dashboard-work-row">
                        <span class="dashboard-work-count">{{ number_format($stats['iso_overdue']) }}</span>
                        <div><strong>Đợt rà soát chương trình quá hạn</strong><small>Có bước chưa hoàn tất sau thời hạn được giao.</small></div>
                        @can('iso.view')<a href="{{ route('iso.index', ['status' => 'in_progress', 'overdue' => 1]) }}">Xem đợt rà soát <span aria-hidden="true">&rarr;</span></a>@endcan
                    </div>
                @endif
                @if($stats['import_errors'] > 0)
                    <div class="dashboard-work-row">
                        <span class="dashboard-work-count">{{ number_format($stats['import_errors']) }}</span>
                        <div><strong>Lần nhập dữ liệu có lỗi</strong><small>Kiểm tra kết quả nhập và các dòng dữ liệu bị lỗi.</small></div>
                        @can('academic.import')<a href="{{ route('academic.import.form') }}">Kiểm tra dữ liệu <span aria-hidden="true">&rarr;</span></a>@endcan
                    </div>
                @endif
                @if($stats['warnings_open'] === 0 && $stats['iso_overdue'] === 0 && $stats['import_errors'] === 0)
                    <p class="dashboard-all-clear">Không có cảnh báo học vụ đang mở, đợt rà soát quá hạn hoặc lần nhập dữ liệu có lỗi trong phạm vi thống kê.</p>
                @endif
            </div>
        </section>

        {{-- Graduation insight and role-aware quick actions. --}}
        <div class="dashboard-content-grid">
            <section class="dashboard-panel dashboard-panel-feature" aria-labelledby="graduation-title">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="dashboard-panel-kicker">Tiến độ tốt nghiệp</p>
                        <h2 id="graduation-title">Tỷ lệ đúng hạn</h2>
                    </div>
                    <span class="dashboard-panel-hint">{{ $stats['graduation_eligible'] }} đến hạn</span>
                </div>

                @if($hasGrad)
                    <div class="dashboard-grad">
                        <div class="dashboard-grad-ring" aria-hidden="true">
                            <svg viewBox="0 0 80 80" width="88" height="88">
                                <circle cx="40" cy="40" r="36" fill="none" stroke="#e6eef2" stroke-width="7"/>
                                <circle cx="40" cy="40" r="36" fill="none" stroke="#176b50" stroke-width="7" stroke-linecap="round"
                                    stroke-dasharray="{{ $dash }} {{ $circ }}" transform="rotate(-90 40 40)"/>
                            </svg>
                            <span class="dashboard-grad-ring-label">{{ number_format($gradRate, 1) }}<small>%</small></span>
                        </div>
                        <div class="dashboard-grad-body">
                            <p class="dashboard-grad-main">{{ $stats['graduated_on_time'] }}<span>/{{ $stats['graduation_eligible'] }}</span> sinh viên</p>
                            <p class="dashboard-grad-desc">Tốt nghiệp đúng hạn so với ngày dự kiến trong hồ sơ sinh viên.</p>
                            <div class="dashboard-grad-bar" aria-hidden="true"><div style="width: {{ min(100, $gradPct) }}%"></div></div>
                            <p class="dashboard-grad-note">Phạm vi: ngành, trình độ và khóa đang chọn.</p>
                        </div>
                    </div>
                @else
                    <div class="dashboard-empty dashboard-empty-compact">
                        <p>Chưa đủ dữ liệu để tính tỷ lệ đúng hạn.</p>
                        <span>Cần có sinh viên đã đến hạn tốt nghiệp (ngày dự kiến ≤ hôm nay) và ngày tốt nghiệp thực tế.</span>
                    </div>
                @endif
            </section>

            <section class="dashboard-panel" aria-labelledby="quick-links-title">
                <div class="dashboard-panel-heading">
                    <div><p class="dashboard-panel-kicker">Thao tác nhanh</p><h2 id="quick-links-title">Đi tới công việc</h2></div>
                </div>
                <div class="dashboard-quick">
                    @can('programs.view')<a href="{{ route('programs.index') }}"><span class="dashboard-quick-icon dq-ct"><svg viewBox="0 0 24 24" fill="none"><path d="M6 7h10M6 12h10M6 17h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span><span><strong>Chương trình đào tạo</strong><small>CTĐT, học phần, PLO &amp; ma trận</small></span><span class="dashboard-quick-arrow" aria-hidden="true">&rarr;</span></a>@endcan
                    @can('academic.view')<a href="{{ route('academic.index', $warningFilters) }}"><span class="dashboard-quick-icon dq-warn"><svg viewBox="0 0 24 24" fill="none"><path d="M12 7v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="17" r="1.2" fill="currentColor"/></svg></span><span><strong>Cảnh báo học vụ</strong><small>Rà soát, phân công &amp; xử lý</small></span><span class="dashboard-quick-arrow" aria-hidden="true">&rarr;</span></a>@endcan
                    @canany(['students.view', 'academic.view'])<a href="{{ route('academic.students', $studentFilters) }}"><span class="dashboard-quick-icon dq-stu"><svg viewBox="0 0 24 24" fill="none"><path d="M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="1.6"/><path d="M4 18c0-2.6 2.7-4.6 8-4.6s8 2 8 4.6v1H4v-1Z" stroke="currentColor" stroke-width="1.6"/></svg></span><span><strong>Sinh viên &amp; cố vấn</strong><small>Danh sách, lớp, khoa</small></span><span class="dashboard-quick-arrow" aria-hidden="true">&rarr;</span></a>@endcanany
                    @can('iso.view')<a href="{{ route('iso.index') }}"><span class="dashboard-quick-icon dq-iso"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M4 10h16" stroke="currentColor" stroke-width="1.6"/></svg></span><span><strong>Rà soát CTĐT</strong><small>Đợt, bước &amp; hồ sơ</small></span><span class="dashboard-quick-arrow" aria-hidden="true">&rarr;</span></a>@endcan
                    @canany(['reports.view','dashboard.view'])<a href="{{ route('reports.index') }}"><span class="dashboard-quick-icon dq-rep"><svg viewBox="0 0 24 24" fill="none"><path d="M7 4h7l3 3v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6"/><path d="M14 4v3h3" stroke="currentColor" stroke-width="1.6"/><path d="M8 13h8M8 17h6" stroke="currentColor" stroke-width="1.6"/></svg></span><span><strong>Báo cáo</strong><small>Xuất CSV / Excel / PDF</small></span><span class="dashboard-quick-arrow" aria-hidden="true">&rarr;</span></a>@endcanany
                </div>

            </section>
        </div>

        <p class="dashboard-footnote">Số liệu tính theo bộ lọc hiện tại (trừ ISO quá hạn và lần nhập lỗi — luôn tính toàn hệ thống). Dữ liệu mẫu cần đối chiếu quy chế trước khi dùng với hồ sơ thật.</p>
    </div>
</x-app-layout>
