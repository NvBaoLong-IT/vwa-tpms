<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cổng quản trị đào tạo VWA-TPMS của Học viện Phụ nữ Việt Nam.">
    <title>VWA-TPMS | Quản trị đào tạo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .home-hero { background-image: linear-gradient(90deg, rgba(7, 42, 55, .92) 0%, rgba(7, 42, 55, .76) 42%, rgba(7, 42, 55, .18) 100%), url('{{ asset('images/official/tuyen-sinh.jpg') }}'); background-position: center; background-size: cover; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <a href="#main-content" class="skip-link">Bỏ qua đến nội dung chính</a>
    <header class="absolute inset-x-0 top-0 z-10 border-b border-white/20 bg-slate-950/20 text-white backdrop-blur-sm">
        <div class="app-shell flex min-h-20 items-center justify-between gap-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Trang chủ VWA-TPMS">
                <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-white p-1"><img src="{{ asset('images/hvpnvn-mark.png') }}" alt="" class="h-full w-full object-contain"></span>
                <span class="hidden sm:block"><strong class="block text-lg tracking-tight">VWA-TPMS</strong><span class="block text-xs text-cyan-100">Quản trị đào tạo số</span></span>
            </a>
            <nav aria-label="Điều hướng trang chủ" class="flex items-center gap-2 sm:gap-5">
                <a href="#modules" class="hidden text-sm font-medium text-white/85 hover:text-white sm:inline">Phân hệ</a>
                <a href="#about" class="hidden text-sm font-medium text-white/85 hover:text-white sm:inline">Về Học viện</a>
                @if (Route::has('login'))<a href="{{ route('login') }}" class="btn border border-white/50 bg-white/10 text-white hover:bg-white hover:text-slate-900">Đăng nhập</a>@endif
            </nav>
        </div>
    </header>

    <main id="main-content">
        <section class="home-hero flex min-h-[680px] items-end pt-28 text-white">
            <div class="app-shell w-full pb-20 sm:pb-28">
                <div class="max-w-3xl">
                    <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-200/40 bg-cyan-900/30 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-cyan-100">Học viện Phụ nữ Việt Nam</p>
                    <h1 class="max-w-3xl text-4xl font-bold leading-tight tracking-tight sm:text-6xl">Kết nối dữ liệu, nâng chất lượng đào tạo</h1>
                    <p class="mt-6 max-w-2xl text-base leading-7 text-slate-100 sm:text-lg">VWA-TPMS giúp Phòng Đào tạo quản lý chương trình, học vụ, quy trình rà soát và văn bằng chứng chỉ trên một nền tảng thống nhất.</p>
                    <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('login') }}" class="btn btn-primary bg-cyan-600 hover:bg-cyan-500">Vào hệ thống <span aria-hidden="true">→</span></a><a href="#modules" class="btn border border-white/45 bg-white/10 text-white hover:bg-white hover:text-slate-900">Xem các phân hệ</a></div>
                </div>
            </div>
        </section>

        <section id="modules" class="border-b border-slate-200 bg-white py-16 sm:py-20">
            <div class="app-shell"><div class="max-w-2xl"><p class="text-sm font-bold uppercase tracking-[.16em] text-cyan-700">Không gian làm việc</p><h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Một cổng vào cho các nghiệp vụ đào tạo</h2><p class="mt-4 leading-7 text-slate-600">Các phân hệ được tổ chức theo vai trò, giúp cán bộ tìm đúng công việc và dữ liệu cần xử lý.</p></div><div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><a href="{{ route('login') }}" class="group border-l-4 border-cyan-600 bg-slate-50 p-5 hover:bg-cyan-50"><span class="text-sm font-semibold text-cyan-700">01</span><h3 class="mt-8 text-xl font-bold">Chương trình đào tạo</h3><p class="mt-2 text-sm leading-6 text-slate-600">Chương trình, học phần, chuẩn đầu ra, mức đáp ứng và học liệu.</p><span class="mt-5 block text-sm font-semibold text-cyan-700">Mở phân hệ →</span></a><a href="{{ route('login') }}" class="group border-l-4 border-amber-500 bg-slate-50 p-5 hover:bg-amber-50"><span class="text-sm font-semibold text-amber-700">02</span><h3 class="mt-8 text-xl font-bold">Học vụ</h3><p class="mt-2 text-sm leading-6 text-slate-600">Tiến độ sinh viên, cảnh báo, cố vấn và báo cáo học vụ.</p><span class="mt-5 block text-sm font-semibold text-amber-700">Mở phân hệ →</span></a><a href="{{ route('login') }}" class="group border-l-4 border-indigo-600 bg-slate-50 p-5 hover:bg-indigo-50"><span class="text-sm font-semibold text-indigo-700">03</span><h3 class="mt-8 text-xl font-bold">Rà soát chương trình</h3><p class="mt-2 text-sm leading-6 text-slate-600">Các bước công việc, bảng tiến độ, hồ sơ và người phụ trách.</p><span class="mt-5 block text-sm font-semibold text-indigo-700">Mở phân hệ →</span></a><a href="{{ route('login') }}" class="group border-l-4 border-rose-600 bg-slate-50 p-5 hover:bg-rose-50"><span class="text-sm font-semibold text-rose-700">04</span><h3 class="mt-8 text-xl font-bold">Văn bằng, chứng chỉ</h3><p class="mt-2 text-sm leading-6 text-slate-600">Kho phôi, sổ gốc, lịch sử thay đổi và tra cứu văn bằng.</p><span class="mt-5 block text-sm font-semibold text-rose-700">Mở phân hệ →</span></a></div></div>
        </section>

        <section class="bg-slate-50 py-16 sm:py-20"><div class="app-shell"><div class="flex flex-wrap items-end justify-between gap-5"><div><p class="text-sm font-bold uppercase tracking-[.16em] text-cyan-700">Hình ảnh từ Học viện</p><h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Không gian học tập và cộng đồng VWA</h2></div><p class="max-w-md text-sm leading-6 text-slate-600">Các banner được lấy từ website chính thức của Học viện và lưu tại local để trang chủ tải ổn định.</p></div><div class="mt-8 grid gap-5 lg:grid-cols-[1.35fr_1fr]"><figure class="overflow-hidden rounded-xl bg-white shadow-sm"><img src="{{ asset('images/official/cong-thong-tin.jpg') }}" alt="Banner hoạt động và hình ảnh Học viện Phụ nữ Việt Nam" class="h-full min-h-64 w-full object-cover"><figcaption class="border-t border-slate-200 px-5 py-4 text-sm font-medium text-slate-700">Cộng đồng, hoạt động và hình ảnh Học viện</figcaption></figure><figure class="overflow-hidden rounded-xl bg-white shadow-sm"><img src="{{ asset('images/official/tuyen-dung.jpg') }}" alt="Banner thông báo tuyển dụng của Học viện Phụ nữ Việt Nam" class="h-full min-h-64 w-full object-cover"><figcaption class="border-t border-slate-200 px-5 py-4 text-sm font-medium text-slate-700">Thông tin tuyển dụng và cơ hội phát triển</figcaption></figure></div></div></section>

        <section id="about" class="bg-slate-900 py-16 text-white sm:py-20"><div class="app-shell grid gap-10 lg:grid-cols-[1fr_auto] lg:items-center"><div class="max-w-3xl"><p class="text-sm font-bold uppercase tracking-[.16em] text-cyan-300">Học viện Phụ nữ Việt Nam</p><h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Dữ liệu đào tạo phục vụ quyết định đúng lúc</h2><p class="mt-5 max-w-2xl leading-7 text-slate-300">VWA-TPMS tập trung hóa các mốc rà soát, tiến độ, cảnh báo và hồ sơ để công việc đào tạo minh bạch, có người phụ trách và có lịch sử.</p></div><div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><div class="border border-white/15 p-4"><strong class="text-3xl text-cyan-300">4</strong><span class="mt-2 block text-xs uppercase tracking-wide text-slate-400">Phân hệ chính</span></div><div class="border border-white/15 p-4"><strong class="text-3xl text-cyan-300">1</strong><span class="mt-2 block text-xs uppercase tracking-wide text-slate-400">Nguồn dữ liệu</span></div><div class="border border-white/15 p-4"><strong class="text-3xl text-cyan-300">24/7</strong><span class="mt-2 block text-xs uppercase tracking-wide text-slate-400">Tra cứu</span></div></div></div></section>
    </main>
    <footer class="bg-slate-950 py-6 text-sm text-slate-400"><div class="app-shell flex flex-wrap justify-between gap-3"><span>VWA-TPMS · Học viện Phụ nữ Việt Nam</span><a href="https://hvpnvn.edu.vn" target="_blank" rel="noreferrer" class="text-cyan-300 hover:text-white">Website Học viện</a></div></footer>
</body>
</html>
