# Vận hành, sao lưu và khôi phục VWA-TPMS

Ngày lập: 20/10/2026. Tài liệu áp dụng cho thay đổi P0–P2 trong checkout hiện tại.

## 1. Trạng thái kiểm chứng và điều kiện đưa vào sử dụng

- Đã diễn tập thực tế trên MySQL Laragon: tạo hai database riêng với tên ngẫu nhiên, dump một bảng dữ liệu tổng hợp bằng `mysqldump`, tạo ZIP mã hóa AES, nhập SQL sang database mới và khôi phục ba tệp mẫu (biên bản, hồ sơ ISO, tệp công khai). Nội dung database đúng; cả ba tệp khớp SHA-256. Hai database diễn tập được xóa khi kết thúc; không nhập đè database ứng dụng.
- Lệnh tái hiện trên máy Windows hiện tại:

  ```powershell
  php artisan backup:restore-drill --binary-path=C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin --no-interaction
  ```

  Cần tài khoản MySQL có quyền tạo/xóa database diễn tập. Tệp và ZIP tổng hợp được giữ dưới `storage/framework/backup-drills/<id>/`; mật khẩu ZIP diễn tập là `drill-<id>`, chỉ dành cho dữ liệu tổng hợp. Lệnh không phục hồi dữ liệu thật và không thay cho nghiệm thu nghiệp vụ trên staging.
- Đã sửa lỗi cấu hình Laragon `=quick` thành `quick` tại `C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini`; bản trước sửa nằm ở `my.ini.before-backup-drill-20261020`. Không sửa cấu hình này trên máy chủ khác nếu không gặp cùng lỗi.
- Chưa xác nhận cron, worker, email cảnh báo, bản sao ngoài máy chủ, tài khoản hệ điều hành và khôi phục dữ liệu thật trên máy triển khai. Những việc này phải được nghiệm thu trước go-live.
- **Điểm chặn PHP:** PHP CLI hiện tại là 8.5.1, trong khi PhpSpreadsheet 1.30.7 yêu cầu PHP `<8.5`; lock còn có Symfony yêu cầu PHP `>=8.4.1`. Chọn PHP 8.4.x phù hợp cho cả CLI và web, hoặc xử lý nâng thư viện Excel ở một thay đổi riêng. Không dùng `--ignore-platform-reqs` để triển khai. Lần thêm laravel-backup 10.3.3 này chỉ bỏ qua giới hạn PHP phía trên bằng `--ignore-platform-req=php+`, không nâng các dependency cũ.

## 2. Cấu hình sao lưu

Đã cài `spatie/laravel-backup` 10.3.3. `config/backup.php` sao lưu database mặc định và toàn bộ `storage/app`, gồm dữ liệu riêng tư và công khai. Không đưa `.env`, mã nguồn, vendor, node_modules, session dạng tệp hoặc cache vào ZIP. Bản sao và tệp tạm backup được loại khỏi nguồn để tránh sao lưu lồng nhau. Phôi dạng dữ liệu nằm trong database; biên bản và tệp ISO nằm trong storage.

Thiết lập trên máy triển khai (giá trị đường dẫn là ví dụ, thay theo máy thực tế):

```dotenv
BACKUP_NAME=vwa-tpms
BACKUP_ROOT=/var/backups/vwa-tpms
BACKUP_ARCHIVE_PASSWORD=<mat-khau-manh-luu-rieng>
BACKUP_MAIL_TO=<email-nguoi-truc>
DB_DUMP_BINARY_PATH=/usr/bin
```

`BACKUP_ROOT` phải là thư mục riêng, không được web server phục vụ. Khi bỏ trống, mặc định là `storage/app/backups`; đây chỉ là bản sao cùng máy, chưa đáp ứng khả năng mất toàn bộ máy chủ. Phải có bản sao trên máy/ổ lưu trữ khác, kiểm tra SHA-256 sau truyền và diễn tập lấy lại từ vị trí đó. Mount thư mục riêng hoặc đồng bộ bằng công cụ vận hành của đơn vị; repo chưa tự cấu hình dịch vụ lưu trữ ngoài máy.

Lưu bản sao `.env`, `APP_KEY`, mật khẩu ZIP và cấu hình hạ tầng trong kho bí mật riêng, cùng thông tin phiên bản mã nguồn/`composer.lock`. Không đổi `APP_KEY` khi phục hồi dữ liệu đã mã hóa. Cấu hình SMTP thật để `BACKUP_MAIL_TO` nhận cảnh báo; khi chưa đặt email, package vẫn ghi log nhưng không gửi mail.

Lịch theo `config/web_schedule.php`:

| Công việc | Giờ chạy |
| --- | --- |
| Quét tác vụ nghiệp vụ hiện có | Mỗi 15 phút |
| Backup DB + storage/app | 01:00 mỗi ngày |
| Kiểm tra tuổi/dung lượng bản sao | 03:00 mỗi ngày |
| Dọn bản sao cũ | 03:30 mỗi ngày |

Backup có khóa chống chạy chồng trong 180 phút, kiểm tra ZIP sau tạo. Giới hạn này phải điều chỉnh nếu thực tế backup lâu hơn. Chính sách mặc định: giữ toàn bộ trong 7 ngày, tiếp đến bản ngày 16 ngày, tuần 8 tuần, tháng 4 tháng, năm 2 năm; giới hạn 5.000 MB có thể dọn sớm bản cũ. Chọn dung lượng theo số liệu thật trước go-live. Sao lưu theo ngày có thể mất dữ liệu phát sinh từ lần sao lưu gần nhất; cần thống nhất mức mất dữ liệu và thời gian phục hồi chấp nhận được.

Chạy thử thủ công sau cấu hình:

```bash
php artisan config:clear
php artisan backup:run
php artisan backup:list
php artisan backup:monitor
php artisan schedule:list
```

Xem `storage/logs/backup.log` và `storage/logs/laravel.log`; kiểm tra ZIP và bản sao ngoài máy, không chỉ dựa vào thông báo lệnh hoàn tất.

## 3. Quy trình khôi phục dữ liệu thật

Thực hiện trên **database và thư mục ứng dụng mới** trước khi chuyển lưu lượng. Các đường dẫn và tên database dưới đây là ví dụ. Không nhập SQL vào database đang sử dụng.

1. Xác định thời điểm bản sao, phiên bản mã nguồn và mật khẩu; đối chiếu SHA-256 với bản lưu bên ngoài. Dừng ghi dữ liệu ở hệ thống nguồn khi chốt thời điểm chuyển. Giữ bản sao hiện trạng trước thao tác.
2. Chuẩn bị thư mục ứng dụng mới với cùng phiên bản code/lock. Chạy `composer install --no-dev --optimize-autoloader` trên PHP tương thích. Cấu hình `.env` trỏ database mới; dùng `APP_KEY` gốc, cache riêng và `QUEUE_CONNECTION=sync` trong lúc kiểm tra.
3. Giải nén ZIP vào thư mục trung gian bằng công cụ hỗ trợ AES, ví dụ `7z x /duong-dan/backup.zip -o/duong-dan/restore-stage`; nhập mật khẩu khi được hỏi. Kiểm tra có `db-dumps/*.sql`, `storage/app/private` và các thư mục storage đã sao lưu. Không giải nén trực tiếp vào thư mục ứng dụng đang chạy.
4. Tạo database mới. Lệnh phải lỗi nếu tên đã tồn tại; không dùng `IF NOT EXISTS` để tránh vô tình nhập vào dữ liệu cũ:

   ```bash
   mysql -u <tai-khoan-quan-tri> -p -e "CREATE DATABASE vwa_tpms_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql --default-character-set=utf8mb4 -u <tai-khoan-phuc-hoi> -p vwa_tpms_restore < /duong-dan/restore-stage/db-dumps/<tep-dump>.sql
   ```

5. Chép `storage/app` đã giải nén vào ứng dụng mới, giữ đúng đường dẫn tương đối và quyền sở hữu. Không chép đè `storage/framework`, log hay `.env`. Chạy `php artisan migrate --force` chỉ trên database mới khi phiên bản code yêu cầu migration bổ sung; chạy `php artisan storage:link` cho tệp công khai. Xóa config cache cũ bằng `php artisan config:clear`.
6. Trên **database mới**, thu hồi phiên và token đã phục hồi, đồng thời dừng các tác vụ chờ từ thời điểm cũ:

   ```sql
   DELETE FROM sessions;
   UPDATE users SET session_version = session_version + 1, remember_token = NULL;
   DELETE FROM jobs;
   UPDATE document_imports SET status = 'failed', error_message = 'Tác vụ dừng do khôi phục dữ liệu; vui lòng gửi lại.' WHERE status = 'processing';
   UPDATE academic_imports SET status = 'failed', failure_message = 'Tác vụ dừng do khôi phục dữ liệu; vui lòng gửi lại.' WHERE status IN ('queued', 'processing');
   ```

   Không đưa tự động các job cũ trở lại chạy. Nếu bản backup quá cũ chưa có các bảng/cột trên, migrate trước rồi mới thực hiện. Chỉ xóa cache có namespace/database riêng của ứng dụng phục hồi.
7. Đối chiếu số lượng và mẫu dữ liệu Program, Course, Student, sổ văn bằng, phôi, audit, quan hệ tiên quyết và `deleted_at` với mốc backup. Thử đăng nhập lại, kiểm tra ba vai trò, tải biên bản/ISO, mở lịch sử văn bằng và tra cứu public cả văn bằng còn hiệu lực lẫn đã hủy. Xác minh một tệp riêng tư không thể truy cập trực tiếp qua web. Không sửa trạng thái hủy/tiêu hủy chỉ để làm cho kết quả tra cứu hợp lệ.
8. Khi các kiểm tra đạt, cấu hình cron/worker theo phần dưới, chốt chuyển database và lưu lượng, chạy một bản backup mới rồi kiểm tra email cảnh báo. Ghi người thực hiện, tên bản sao, thời gian bắt đầu/kết thúc, số liệu đối chiếu và kết quả nghiệm thu.

Rollback ứng dụng không được xóa các cột `deleted_at`, `session_version` hoặc quan hệ học phần đang có dữ liệu. Ưu tiên sửa tiếp hoặc phục hồi cả code + DB + storage về cùng một mốc đã kiểm chứng.

## 4. Scheduler và queue

Local hiện vẫn dùng `QUEUE_CONNECTION=sync`; không cài Windows Service. Job AI và nhập tốt nghiệp chạy ngay trong cấu hình sync, vì vậy request local vẫn phải chờ. Để tránh chờ trên môi trường triển khai, dùng queue database và worker thực sự:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=3900
```

Timeout AI tối đa 3.600 giây, nhập tốt nghiệp 300 giây. `retry_after` phải lớn hơn timeout dài nhất; không giữ mặc định 90 giây. Cả hai job chỉ tự thử một lần, có trạng thái thất bại khi lỗi/quá hạn. Tệp tốt nghiệp được dọn sau xử lý; gửi lại tệp khi thất bại. Tệp AI giữ lại để đối chiếu. Worker kiểm tra lại trạng thái tài khoản và quyền của người gửi; xác nhận bản nháp AI vẫn là bước thủ công có kiểm tra quyền.

Ví dụ Supervisor trên Linux (thay đường dẫn và tài khoản chạy bằng giá trị thực tế):

```ini
[program:vwa-tpms-worker]
directory=/var/www/vwa-tpms
command=/usr/bin/php /var/www/vwa-tpms/artisan queue:work database --sleep=3 --tries=1 --timeout=3600 --max-time=7200
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3700
redirect_stderr=true
stdout_logfile=/var/www/vwa-tpms/storage/logs/worker.log
```

Sau cập nhật config: `supervisorctl reread`, `supervisorctl update`, `supervisorctl status`; sau deploy code: `php artisan queue:restart`. Theo dõi `php artisan queue:failed`, số hàng `jobs`, trạng thái bản nháp AI và bảng kết quả nhập tốt nghiệp. Không chạy nhiều worker nhập tốt nghiệp trước khi kiểm thử tải và cạnh tranh trên MySQL.

Cron chạy mỗi phút bằng tài khoản có quyền ghi storage/cache:

```cron
* * * * * cd /var/www/vwa-tpms && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Chỉ chọn một cơ chế scheduler trên một máy chủ, không đồng thời cron và timer trùng lịch. `schedule:list` chỉ xác nhận lịch trong code, không chứng minh cron đang chạy. Lãnh đạo phòng mở “Quản lý & hồ sơ → Tình trạng vận hành” (`/operations/status`) để xem 20 lượt nghiệp vụ gần nhất, số lượt nhập chờ/lỗi và thời điểm ZIP sao lưu. Lịch console ghi thêm hàng `scheduler-heartbeat` trong `web_scheduled_runs`; quá 35 phút chưa hoàn tất sẽ hiện cảnh báo. Lượt tick web không cập nhật hàng này nên không che việc cron ngừng hoạt động. Đối chiếu thêm `scheduler.log` và log backup. Backup của Spatie không ghi kết quả nghiệp vụ vào `web_scheduled_runs`; trang chỉ kiểm tra tuổi tệp ZIP, cần theo dõi riêng nội dung và lỗi bằng `backup:list`, `backup:monitor` và email/log.

Nút `schedule/tick` có throttle vẫn dùng để cứu hộ tác vụ nghiệp vụ. Nó phụ thuộc có người truy cập, không thay cron, không chạy backup và không xử lý queue. Trạng thái `queued` kéo dài thường là dấu hiệu worker chưa chạy hoặc sai connection.

## 5. Các thay đổi tài khoản, tra cứu và khôi phục bản ghi

- Khóa tài khoản hoặc đổi tập role sẽ tăng `users.session_version`, đổi token ghi nhớ và xóa các database session của người đó. Middleware so sánh phiên bản trên mỗi request; mở khóa không phục hồi phiên đã thu hồi. Cập nhật không đổi role giữ phiên đang hợp lệ. Thời hạn session 120 phút là thời gian không hoạt động, không phải trần 2 giờ kể từ lúc khóa.
- GET `/tra-cuu-van-bang` có giao diện khách và JSON khi gửi `Accept: application/json`, tham số `code` là số vào sổ hoặc số phôi chính xác. Throttle 5 request/phút theo địa chỉ IP, gồm cả lần tải form và input sai; chỉ trả họ tên trên văn bằng, ngày cấp, loại và `valid`. Không trả ID, ngày sinh, mã sinh viên hoặc số sổ khác. Văn bằng đã hủy có `valid=false`; không tìm thấy chỉ có `valid=false`. Response không lưu cache và yêu cầu không lập chỉ mục. Throttle không loại bỏ hoàn toàn việc quét phân tán; có thể bổ sung captcha khi có số liệu vận hành.
- Đã thêm `SoftDeletes` cho Program, Course, Student, DiplomaLedger và DiplomaBlank. Không thêm đường xóa sổ văn bằng; nghiệp vụ hủy vẫn đi qua quyết định và audit. Chỉ Lãnh đạo phòng mở được “Quản lý & hồ sơ → Khôi phục dữ liệu”. Không có thao tác xóa vĩnh viễn trên màn hình này. Khôi phục không đổi trạng thái hủy văn bằng/tiêu hủy phôi; mã duy nhất vẫn được giữ để tránh tái sử dụng.
- Audit xóa Course giữ từng program_id trước xóa. Import AI gộp audit tự động thành một bản ghi import có snapshot trước/sau, không tắt các model event khác. Học trước/học song hành dùng mã học phần trong payload; được liên kết sau khi đã tạo đủ học phần, không đổi quan hệ của học phần dùng chung ở chương trình khác. Theo cấu trúc dữ liệu hiện tại, mỗi học phần có tối đa một học trước và một học song hành; học song hành không tự biến thành quan hệ đối xứng. Tiên quyết dùng chung validator với cập nhật thủ công để chặn vòng lặp. Mã sai, tự tham chiếu hoặc vòng lặp sẽ rollback toàn bộ lượt nhập. Không có trường quan hệ trong bản nháp cũ thì giữ nguyên quan hệ đã có; null rõ ràng là bỏ quan hệ.

## 6. Kiểm tra triển khai

Kết quả kiểm tra checkout ngày 20/10/2026:

| Kiểm tra | Kết quả |
| --- | --- |
| `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --colors=never` | 201 tests, 1.310 assertions, đạt |
| `node --test tests/flash-notifications.test.mjs tests/web-schedule.test.mjs` | 8/8 đạt |
| `php vendor/bin/pint --dirty --format agent` | Đạt, đã định dạng PHP |
| `npm run build` | Đạt |
| `php artisan view:cache` và `php artisan schedule:list --no-interaction` | Đạt |
| `git diff --check` | Đạt; Git chỉ nhắc chuẩn hóa CRLF/LF |
| `composer validate --no-check-publish` | Hợp lệ; còn cảnh báo hai constraint phiên bản cố định có sẵn |
| `composer check-platform-reqs` | Chưa đạt do PHP 8.5.1 / PhpSpreadsheet `<8.5` nêu ở phần 1 |
| Diễn tập MySQL bằng `backup:restore-drill` | Đạt với dữ liệu tổng hợp, ZIP mã hóa và 3 tệp khớp SHA-256 |

Lần chạy test với giới hạn CLI mặc định 128 MB đã hết RAM ở Dompdf; tiến trình kiểm thử 512 MB chạy hết bộ test. Test nhập tốt nghiệp đã chạy cả hàng đợi database và `queue:work --once` trên SQLite kiểm thử; không chỉ dùng queue giả. Cookie ghi nhớ cũ bị từ chối sau thu hồi; cookie mới và đăng nhập lại vẫn hoạt động. Test khóa/mở khóa, đổi role, phiên file, người dùng không đủ quyền, throttle public, vòng lặp tiên quyết, audit và khôi phục dữ liệu đều đã chạy. Chưa thực hiện kiểm tra giao diện bằng trình duyệt thật, tải API AI thật hoặc xác minh cấu hình trên máy chủ triển khai.

Trước khi mở lưu lượng: dùng PHP tương thích, `composer check-platform-reqs`, `php artisan migrate --force`, build asset, kiểm tra quyền storage và chạy thử backup/restore trên staging. Các migration mới chỉ thêm cột; chưa chạy trên database ứng dụng thật trong phiên làm việc này. Phải đưa migration `session_version` vào trước khi code mới phục vụ request.

Không đưa `.env`, vendor, node_modules, storage local hoặc dữ liệu diễn tập lên máy chủ bằng cách chép toàn bộ checkout. Triển khai code và lock, cài dependency trên máy đích và giữ dữ liệu vận hành tại đó.
