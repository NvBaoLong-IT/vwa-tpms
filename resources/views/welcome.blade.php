<!doctype html>
<html lang="vi">
<head><link rel="icon" type="image/png" href="{{ asset('images/hvpnvn-mark.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VWA-TPMS — Cổng quản trị đào tạo của Học viện Phụ nữ Việt Nam. Chương trình, học vụ, rà soát và văn bằng trong một không gian làm việc.">
    <title>VWA-TPMS | Cổng quản trị đào tạo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="vwa-welcome">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <header class="welcome-header">
        <div class="welcome-shell welcome-header-inner">
            <a class="welcome-brand" href="{{ url('/') }}" aria-label="Trang chủ VWA-TPMS">
                <img src="{{ asset('images/hvpnvn-mark.png') }}" alt="" width="44" height="44">
                <span><strong>VWA-TPMS</strong><small>Học viện Phụ nữ Việt Nam</small></span>
            </a>
            <nav aria-label="Điều hướng trang chủ">
                <a class="welcome-nav-link" href="#modules">Phân hệ nghiệp vụ</a>
                <a class="welcome-nav-link" href="#about">Về hệ thống</a>
                <a class="welcome-nav-link" href="{{ route('diplomas.public-lookup') }}">Tra cứu văn bằng</a>
                @auth
                    <a class="welcome-button welcome-button-small" href="{{ route('dashboard') }}">Vào hệ thống <span aria-hidden="true">↗</span></a>
                @else
                    <a class="welcome-button welcome-button-small" href="{{ route('login') }}">Đăng nhập <span aria-hidden="true">↗</span></a>
                @endauth
            </nav>
        </div>
    </header>
    <main id="main-content" tabindex="-1">
        <section class="welcome-hero">
            <div class="welcome-shell welcome-hero-grid">
                <div class="welcome-hero-copy">
                    <p class="welcome-eyebrow"><span></span> CỔNG QUẢN TRỊ ĐÀO TẠO</p>
                    <h1>Kết nối dữ liệu.<br><em>Chủ động</em><br>quản trị đào tạo.</h1>
                    <p class="welcome-lead">Một không gian làm việc cho chương trình đào tạo, tiến độ sinh viên, rà soát chất lượng và hồ sơ văn bằng.</p>
                    <div class="welcome-actions">
                        <a class="welcome-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Vào không gian làm việc <span aria-hidden="true">↗</span></a>
                        <a class="welcome-text-link" href="#modules">Khám phá các phân hệ <span aria-hidden="true">↓</span></a>
                    </div>
                    <div class="welcome-hero-notes"><span>Phân quyền theo vai trò</span><span>Theo dõi lịch sử thay đổi</span></div>
                </div>
                <div class="welcome-visual">
                    <figure class="welcome-campus">
                        <img src="{{ asset('images/official/campus-a1.png') }}" alt="Tòa nhà Học viện Phụ nữ Việt Nam" width="720" height="720" fetchpriority="high">
                        <figcaption><span>HỌC VIỆN PHỤ NỮ VIỆT NAM</span><strong>Tri thức kết nối.<br>Giá trị lan tỏa.</strong></figcaption>
                    </figure>
                    <div class="welcome-workspace-card">
                        <span class="welcome-card-icon" aria-hidden="true">✓</span>
                        <div><strong>Thông tin có liên kết</strong><p>Từ chương trình đến hồ sơ sinh viên</p></div>
                    </div>
                    <div class="welcome-visual-label"><span aria-hidden="true">✦</span> VWA · KHÔNG GIAN ĐÀO TẠO SỐ</div>
                </div>
            </div>
        </section>
        <section id="modules" class="welcome-modules welcome-shell">
            <div class="welcome-section-heading"><div><p class="welcome-eyebrow">NGHIỆP VỤ ĐÀO TẠO</p><h2>Đúng công việc.<br>Trong cùng một hệ thống.</h2></div><p>Bốn phân hệ kết nối dữ liệu, giúp cán bộ theo dõi và xử lý công việc theo quyền được cấp.</p></div>
            @php
                $modules = [
                    ['01', 'Chương trình đào tạo', 'Quản lý chương trình, học phần, chuẩn đầu ra và phiên bản theo khóa.', 'Chương trình · Học phần · PLO', 'programs.index', 'programs.view'],
                    ['02', 'Học vụ & sinh viên', 'Theo dõi tiến độ học tập, cảnh báo học vụ và phân công cố vấn.', 'Tiến độ · Cảnh báo · Báo cáo', 'academic.index', 'academic.view'],
                    ['03', 'Rà soát chất lượng', 'Theo dõi các bước rà soát, người phụ trách, thời hạn và hồ sơ phê duyệt.', 'Quy trình · Thời hạn · Hồ sơ', 'iso.index', 'iso.view'],
                    ['04', 'Văn bằng, chứng chỉ', 'Quản lý kho phôi, đề nghị cấp văn bằng, sổ gốc và lịch sử thay đổi.', 'Kho phôi · Cấp bằng · Sổ gốc', 'diplomas.index', 'diploma.view'],
                ];
            @endphp
            <div class="welcome-module-grid">
                @foreach($modules as [$number, $title, $description, $tags, $destination, $permission])
                    <a class="welcome-module-card" href="{{ auth()->check() ? (auth()->user()->can($permission) ? route($destination) : route('dashboard')) : route('login') }}">
                        <div class="welcome-module-top"><span class="welcome-module-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                @switch($number)
                                    @case('01')<path d="M12 5v15M3 5c3-1 6-1 9 1 3-2 6-2 9-1v14c-3-1-6-1-9 1-3-2-6-2-9-1Z"/>@break
                                    @case('02')<path d="m2 8 10-5 10 5-10 5Zm4 3v6c4 3 8 3 12 0v-6M22 8v8"/>@break
                                    @case('03')<rect x="4" y="4" width="16" height="17" rx="3"/><path d="M9 3h6v3H9zm-1 9 2 2 5-5m-7 9h8"/>@break
                                    @case('04')<circle cx="12" cy="9" r="6"/><path d="m8 14-2 7 6-3 6 3-2-7m-7-5 2 2 3-3"/>@break
                                @endswitch
                            </svg>
                        </span><small>PHÂN HỆ {{ $number }}</small><span aria-hidden="true">↗</span></div>
                        <h3>{{ $title }}</h3><p>{{ $description }}</p><small>{{ $tags }}</small>
                    </a>
                @endforeach
            </div>
        </section>
        <section id="academy" class="welcome-gallery welcome-shell" aria-labelledby="welcome-gallery-title">
            <div class="welcome-section-heading"><div><p class="welcome-eyebrow">MÔI TRƯỜNG & CỘNG ĐỒNG</p><h2 id="welcome-gallery-title">Sắc màu Học viện.<br>Kết nối và phát triển.</h2></div><p>Không gian học tập, hoạt động và thông tin từ Học viện Phụ nữ Việt Nam.</p></div>
            <div class="welcome-gallery-grid">
                <figure class="welcome-gallery-main"><img src="{{ asset('images/official/cong-thong-tin.jpg') }}" alt="Hình ảnh cộng đồng và hoạt động Học viện Phụ nữ Việt Nam" loading="lazy"><figcaption><span class="welcome-photo-tag">CỘNG ĐỒNG VWA</span><h3>Tri thức, con người<br>và những kết nối mới.</h3><p>Không gian học tập và hoạt động của Học viện.</p></figcaption></figure>
                <figure class="welcome-gallery-secondary"><img src="{{ asset('images/official/tuyen-sinh.jpg') }}" alt="Banner tuyển sinh của Học viện Phụ nữ Việt Nam" loading="lazy"><figcaption><span class="welcome-photo-tag">ĐÀO TẠO & HỌC TẬP</span><h3>Khởi đầu hành trình tri thức.</h3></figcaption></figure>
                <figure class="welcome-gallery-secondary welcome-gallery-career"><img src="{{ asset('images/official/tuyen-dung.jpg') }}" alt="Banner tuyển dụng của Học viện Phụ nữ Việt Nam" loading="lazy"><figcaption><span class="welcome-photo-tag">CƠ HỘI PHÁT TRIỂN</span><h3>Đồng hành cùng Học viện.</h3></figcaption></figure>
            </div>
            <a class="welcome-text-link welcome-gallery-link" href="https://hvpnvn.edu.vn" target="_blank" rel="noopener noreferrer">Xem thông tin trên Cổng Học viện <span aria-hidden="true">↗</span></a>
        </section>
        <section id="about" class="welcome-about">
            <div class="welcome-shell welcome-about-grid">
                <div><p class="welcome-eyebrow">THÔNG TIN PHỤC VỤ ĐIỀU HÀNH</p><h2>Từ dữ liệu tập trung<br>đến công việc rõ ràng.</h2><p class="welcome-about-lead">VWA-TPMS hỗ trợ Phòng Đào tạo quản lý hồ sơ và phối hợp công việc trên một nền tảng thống nhất.</p><a class="welcome-text-link" href="https://hvpnvn.edu.vn" target="_blank" rel="noopener noreferrer">Cổng thông tin Học viện <span aria-hidden="true">↗</span></a></div>
                <div class="welcome-principles">
                    <article><span>01</span><div><h3>Nhìn rõ việc cần theo dõi</h3><p>Tổng quan cảnh báo, tiến độ và các mốc công việc theo phạm vi đang xem.</p></div></article>
                    <article><span>02</span><div><h3>Thao tác đúng vai trò</h3><p>Quyền xem, xử lý và phê duyệt phù hợp với nhiệm vụ của từng tài khoản.</p></div></article>
                    <article><span>03</span><div><h3>Giữ lại dấu vết nghiệp vụ</h3><p>Lịch sử tiến độ, phiên bản chương trình và hồ sơ rà soát phục vụ đối chiếu.</p></div></article>
                </div>
            </div>
        </section>
        <section class="welcome-shell welcome-closing"><div><p class="welcome-eyebrow">KHÔNG GIAN LÀM VIỆC CỦA BẠN</p><h2>Sẵn sàng bắt đầu công việc?</h2><p>Đăng nhập bằng tài khoản được cấp để truy cập các phân hệ.</p></div><a class="welcome-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Vào hệ thống' : 'Đăng nhập hệ thống' }} <span aria-hidden="true">↗</span></a></section>
    </main>
    <footer class="welcome-footer"><div class="welcome-shell"><span><strong>VWA-TPMS</strong> · Học viện Phụ nữ Việt Nam</span><small>© {{ date('Y') }} · Cổng quản trị đào tạo</small><a href="#main-content">Về đầu trang ↑</a></div></footer>
<x-flash-notifications /></body>
</html>
