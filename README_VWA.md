# VWA-TPMS

Nền tảng Laravel cho hệ thống quản trị chương trình đào tạo và tiến trình học vụ số của Phòng Đào tạo.

## Giai đoạn 1 đã hoàn thành

- Laravel 12.69.2 trên PHP 8.5.
- MySQL/MariaDB database `vwa_tpms` ở `127.0.0.1:3307`.
- Cấu hình `.env.example` đã dùng port 3307.
- RBAC với Spatie Laravel Permission 8.x.
- Ba vai trò vận hành hiện tại: Lãnh đạo phòng, Chuyên viên Phòng Đào tạo và Xem. Các tên vai trò cũ vẫn được giữ trong một số seeder/test để tương thích dữ liệu cũ, không phải vai trò vận hành hiện tại.
- Các tài khoản demo được seed khi chạy lại database.
- Endpoint kiểm tra nền tảng: `/health`.
- Laravel Boost 2.9.1 đã được cấu hình cho Codex, gồm guidelines, skills và MCP.
- Danh mục mẫu CTĐT gồm chương trình Kinh tế số và 4 học phần liên kết qua MySQL.
- Laravel Breeze đã tạo luồng đăng nhập, đăng xuất, đổi mật khẩu và xác thực email.
- Middleware RBAC đã được đăng ký cho `role`, `permission` và `role_or_permission`.

## Chạy local

```powershell
php artisan serve
```

Tài khoản demo:

- `long.demo@vwa.local` / `password` — Lãnh đạo phòng
- `daotao.demo@vwa.local` / `password` — Chuyên viên Phòng Đào tạo
- `baolonglocal` / `password` — Lãnh đạo phòng (có thể đăng nhập bằng tên tài khoản hoặc email `baolonglocal@vwa.local`)

Không dùng các tài khoản này trên môi trường thật. Đổi mật khẩu trước khi bàn giao.

Các route kiểm tra quyền cũ (endpoint JSON, không phải màn hình nghiệp vụ):

- `/modules/curriculum`, `/modules/academic`, `/modules/diploma` chỉ trả JSON `authorized`; có thể xóa sau khi các test không còn phụ thuộc.
- Màn hình nghiệp vụ tương ứng là `/programs`, `/academic` và `/diplomas`.
- `/iso` — màn hình rà soát chương trình.

Tra cứu văn bằng hiện chỉ là tra cứu nội bộ trong `/diplomas`. Chưa có route công khai hoạt động cho `/tra-cuu-van-bang` hoặc GET `/diplomas/lookup`.

Môi trường kiểm thử: một lần chạy trước đây dùng SQLite chưa migrate đầy đủ và phát sinh lỗi thiếu bảng `student_progresses`. DB test đã được migrate/tạo lại riêng; bộ test hiện tại chạy độc lập. Chưa có bằng chứng thao tác đó ảnh hưởng DB vận hành.

Lưu ý triển khai: `composer check-platform-reqs` vẫn báo `phpoffice/phpspreadsheet` yêu cầu PHP `< 8.5`, trong khi môi trường đang dùng PHP 8.5.1. Đây là blocker triển khai dù test local có thể chạy với package đã cài bỏ qua ràng buộc nền tảng.

## Laravel Boost

Boost được bật qua `boost.json`. Các tài liệu hướng dẫn nằm trong `AGENTS.md`, skills của dự án nằm trong `.agents/skills` và cấu hình MCP của Codex nằm trong `.codex/config.toml`.

Khi cần cập nhật hướng dẫn và skills:

```powershell
php artisan boost:update --no-discover
```

## Làm mới dữ liệu mẫu

```powershell
php artisan migrate:fresh --seed
```

Lệnh này xóa toàn bộ bảng trong database được cấu hình ở `.env`, chỉ dùng cho môi trường phát triển.
