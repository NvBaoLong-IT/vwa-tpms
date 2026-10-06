# Tổng quan hệ thống VWA TPMS

## Mục đích

VWA TPMS là hệ thống web hỗ trợ Phòng Đào tạo Học viện Phụ nữ Việt Nam quản lý chương trình đào tạo, tiến độ rà soát chương trình, tiến trình học tập và hồ sơ văn bằng chứng chỉ. Hệ thống tập trung dữ liệu vốn nằm trong Excel, hồ sơ rời và các lần nhắc việc thủ công; từ đó giúp cán bộ theo dõi trạng thái, tìm việc quá hạn, ghi nhận người xử lý và lập báo cáo.

Người dùng vận hành hiện tại gồm Lãnh đạo phòng, Chuyên viên Phòng Đào tạo và Xem. Một số tên vai trò cũ như chuyên viên chương trình đào tạo, chuyên viên học vụ, chuyên viên văn bằng chứng chỉ và cán bộ Thư viện còn trong seeder/test để tương thích dữ liệu cũ, không phải vai trò vận hành hiện tại. Đây là hệ thống hỗ trợ nghiệp vụ phòng; phần mềm đăng ký tín chỉ, xếp thời khóa biểu, thu học phí và nhập điểm đại trà không nằm trong phạm vi hiện tại.

## Quy trình sử dụng điển hình

1. Chuyên viên nhập hoặc cập nhật chương trình đào tạo, học phần, chuẩn đầu ra và học liệu.
2. Phòng mở đợt rà soát chương trình, giao người phụ trách, lưu hồ sơ và theo dõi hạn từng bước.
3. Chuyên viên Phòng Đào tạo nhập kết quả tích lũy theo học kỳ; hệ thống đối chiếu ngưỡng và tạo danh sách cảnh báo cần xử lý.
4. Cán bộ phụ trách xem cảnh báo, ghi nhận cách xử lý, theo dõi tiến độ và xuất báo cáo.
5. Bộ phận được phân quyền quản lý phôi, cấp số vào sổ, lưu lịch sử thao tác và tra cứu nội bộ trong `/diplomas`.
6. Lãnh đạo xem bảng điều hành và các báo cáo tổng hợp từ dữ liệu đã nhập.

## Chức năng và công dụng

| Nhóm chức năng | Hệ thống đang hỗ trợ | Công dụng trong công việc |
| --- | --- | --- |
| Tài khoản và phân quyền | Đăng nhập; quản lý tài khoản, vai trò và quyền riêng; khóa tài khoản; buộc đổi mật khẩu lần đầu; lưu lịch sử đăng nhập. | Mỗi cán bộ chỉ thực hiện các thao tác được giao; có căn cứ xem ai đã truy cập. |
| Chương trình đào tạo | Quản lý chương trình, trình độ, quyết định, tổng tín chỉ; gắn học phần theo khối kiến thức và học kỳ; phân biệt bắt buộc/tự chọn; đối chiếu tín chỉ khai báo với tín chỉ học phần. | Giảm việc đối chiếu nhiều bảng rời và nhận ra chênh lệch tín chỉ khi rà soát. |
| Chuẩn đầu ra và học liệu | Quản lý PLO; ma trận mức đóng góp PLO–học phần; quản lý giáo trình/học liệu và hiển thị lịch sử xác nhận đã có. | Theo dõi mối liên hệ giữa chuẩn đầu ra, học phần và tình trạng học liệu trước khi trình duyệt. |
| Nhập CTĐT | Cung cấp mẫu Excel nhiều sheet; nhập chương trình, PLO, học phần, khối kiến thức và ma trận; kiểm tra cấu trúc và dữ liệu đầu vào. | Chuyển dữ liệu CTĐT đang có trong bảng tính vào hệ thống. |
| Rà soát ISO | Tạo đợt rà soát và 4 bước theo khung 6 tháng; giao việc, cập nhật trạng thái, xem Kanban, đếm bước quá hạn, tải/lưu hồ sơ, ghi lịch sử, phê duyệt và đóng đợt. | Lãnh đạo và cán bộ biết hồ sơ đang ở bước nào, ai xử lý và còn bao lâu. |
| Theo dõi học vụ | Quản lý sinh viên, ngành/lớp/cố vấn, lịch sử tín chỉ và GPA theo học kỳ; nhập Excel/CSV; sửa tiến độ thủ công có lưu lịch sử. | Có dữ liệu tập trung để kiểm tra sinh viên theo ngành, lớp và học kỳ. |
| Cảnh báo học vụ | Thiết lập ngưỡng theo trình độ; quét GPA, số tín chỉ còn thiếu và tỷ lệ tiến độ; lọc, nhận xử lý, giải quyết hoặc bỏ qua cảnh báo; thông báo trong hệ thống. | Giúp phát hiện sớm hồ sơ có nguy cơ chậm tiến độ và theo dõi việc xử lý. |
| Nhật ký nhập dữ liệu | Theo dõi lần import đang chờ, xử lý, hoàn thành hoặc thất bại; ghi số dòng hợp lệ, lỗi từng dòng và cho tải danh sách lỗi. | Cán bộ biết file đã được xử lý đến đâu và sửa đúng những dòng sai. |
| Văn bằng chứng chỉ | Quản lý kho phôi và tình trạng phôi; nhập danh sách tốt nghiệp; cấp số vào sổ, cập nhật/hủy có ghi vết; xuất trích lục/bản sao; tra cứu nội bộ trong `/diplomas`. | Tập trung việc theo dõi phôi, sổ gốc và lịch sử cấp phát. |
| Bảng điều hành và báo cáo | Thống kê sinh viên, CTĐT, cảnh báo, ISO quá hạn; lọc và xem biểu đồ; xuất báo cáo học vụ, ISO, CTĐT/PLO, phôi và văn bằng ở CSV, Excel hoặc PDF; lưu lịch sử xuất báo cáo. | Giảm thời gian tổng hợp thủ công và hỗ trợ theo dõi công việc của phòng. |

## Mức độ hoàn thiện hiện tại

Các nhóm chức năng trên đã có mã nguồn và màn hình thao tác trong bản phát triển. Dự án dùng Laravel 12 và MySQL; dữ liệu mẫu giúp thử luồng sử dụng. Đây chưa phải xác nhận nghiệm thu nghiệp vụ hoặc xác nhận đã triển khai trên hạ tầng Học viện. Các phép tính, biểu mẫu, quyền truy cập và dữ liệu mẫu cần được đối chiếu với quy trình thực tế trước khi dùng với dữ liệu sinh viên và văn bằng thật.

Những điểm hiện là **giả định của bản phát triển**:

- Đợt ISO tự sinh 4 giai đoạn trong 6 tháng; ngày hạn tránh cuối tuần nhưng chưa có lịch ngày nghỉ riêng của Học viện.
- Ngưỡng cảnh báo có thể chỉnh trong giao diện; giá trị mẫu và cách tính cần đối chiếu quy chế của từng trình độ.
- Thông báo hiện được lưu trong hệ thống; chưa coi email/SMS gửi ra ngoài là đã vận hành.
- Số vào sổ được hệ thống tạo theo quy tắc demo; cần xác nhận cấu trúc, đánh số liên tục và quy trình kiểm tra trước khi cấp thật.
- Chưa có cổng tra cứu văn bằng công khai hoạt động; nếu triển khai sau này, cần duyệt nội dung hiển thị, quyền riêng tư và hình thức văn bản chính thức.

## Giá trị chuyển đổi số có thể trình bày

Sản phẩm tạo một nơi lưu dữ liệu và theo dõi công việc; tự tính/tổng hợp một số chỉ số; chuyển việc rà Excel và nhắc hạn thủ công thành danh sách cần xử lý; ghi lại thao tác và người chịu trách nhiệm. Hiệu quả thực tế cần được đo bằng dữ liệu sử dụng thật, chẳng hạn thời gian tổng hợp báo cáo, số hồ sơ quá hạn được phát hiện và tỷ lệ file nhập phải sửa.
