# TripConnect

**Nền tảng chia sẻ và gợi ý lịch trình du lịch**

TripConnect là website hỗ trợ người dùng tìm kiếm, tham khảo, xây dựng, tùy chỉnh và chia sẻ lịch trình du lịch. Bên cạnh việc lên kế hoạch theo từng ngày, TripConnect hướng tới một cộng đồng nơi mọi người có thể chia sẻ kinh nghiệm, sao chép lịch trình công khai và nhận gợi ý chuyến đi phù hợp với sở thích, thời gian và ngân sách.

Dự án tiếp tục phát triển bằng **PHP thuần và MySQL/MariaDB**. Đây là quyết định điều chỉnh công nghệ so với SRS ban đầu; **mục tiêu nghiệp vụ và các yêu cầu chức năng FR-01 đến FR-18 vẫn được giữ lại**.

## 1. Mục tiêu và phạm vi

- **Lập lịch trình:** tạo, chỉnh sửa, lưu nháp, xuất bản và quản lý chuyến đi; xây dựng timeline theo ngày, giờ và hoạt động.
- **Quản lý ngân sách:** nhập chi phí theo hoạt động, phân loại và tổng hợp chi phí dự kiến/thực tế.
- **Bản đồ:** hiển thị những địa điểm trong lịch trình cùng tọa độ có thể xác minh.
- **Chia sẻ và tái sử dụng:** xem lịch trình công khai, sao chép thành bản nháp cá nhân (Clone/Fork) và điều chỉnh theo nhu cầu.
- **Tìm kiếm và gợi ý:** lọc lịch trình theo điểm đến, thời lượng, ngân sách, thẻ chủ đề; hướng tới đề xuất theo sở thích và độ phổ biến.
- **Tương tác cộng đồng:** Like, Comment, Bookmark, Follow và Newsfeed.
- **Xuất dữ liệu và quản trị:** xuất lịch trình dạng PDF/hình ảnh; quản lý người dùng, nội dung và danh mục/thẻ.

Các nội dung trên thể hiện **phạm vi mục tiêu của đề tài**, không có nghĩa tất cả chức năng đã được kiểm thử hoặc hoàn thiện.

## 2. Công nghệ sử dụng

| Thành phần | Công nghệ / định hướng |
| --- | --- |
| Backend | PHP thuần; không sử dụng Laravel hoặc Node.js/Express |
| Frontend | HTML, CSS, JavaScript thuần, Bootstrap |
| Database | MySQL/MariaDB, kết nối bằng PHP PDO |
| Môi trường phát triển | XAMPP (Apache, PHP, MySQL/MariaDB) |
| Bản đồ | Leaflet và OpenStreetMap |
| Tra cứu địa điểm | Dữ liệu MySQL; Nominatim chỉ khi người dùng yêu cầu |
| Lưu trữ hình ảnh | **Định hướng theo SRS: Cloudinary**; cần tích hợp/kiểm thử trước khi xác nhận hoàn thành |
| Gợi ý ảnh bên ngoài | Wikimedia Commons là tính năng phụ tùy chọn, không bắt buộc để tạo/lưu lịch trình |
| Quản lý mã nguồn | Git/GitHub |
| Kiểm thử | PHP lint, kiểm tra JavaScript và thử nghiệm luồng sử dụng thực tế; có thể dùng Postman cho endpoint |

**Lưu ý:** SRS gốc dự kiến React/Next.js, Express và JWT. Phiên bản phát triển hiện tại lựa chọn PHP thuần, nên tài liệu kỹ thuật cần được nhóm thống nhất cập nhật theo cách xác thực thực tế của mã nguồn. Không mặc định website đang dùng JWT nếu chưa kiểm chứng.

## 3. Yêu cầu chức năng theo SRS

| Mã | Yêu cầu | Phạm vi |
| --- | --- | --- |
| FR-01 | Xác thực | Đăng ký, đăng nhập, bảo vệ mật khẩu và phân quyền |
| FR-02 | Hồ sơ và sở thích | Cập nhật thông tin, avatar, sở thích du lịch |
| FR-03 | Thông tin chuyến đi | Tên, địa điểm, số ngày, ngân sách, ảnh bìa |
| FR-04 | Timeline và bản đồ | Ngày, thời gian, địa điểm, tọa độ, mô tả, chi phí, hình ảnh hoạt động |
| FR-05 | Budget Tracker | Tổng hợp và phân loại chi phí; biểu đồ ngân sách |
| FR-06 | Clone/Fork | Sao chép lịch trình công khai thành bản nháp riêng |
| FR-07 | Export Offline | Xuất lịch trình sang PDF hoặc hình ảnh |
| FR-08 | Trạng thái lịch trình | Lưu nháp, xuất bản, chỉnh sửa, xóa |
| FR-09 | Search & Multi-Filter | Tìm theo từ khóa, địa điểm, ngân sách, số ngày, tags |
| FR-10 | Recommendation | Gợi ý theo sở thích và độ phổ biến của lịch trình |
| FR-11 | Like & Comment | Thả tim, bình luận/phản hồi |
| FR-12 | Bookmark/Collection | Lưu lịch trình yêu thích |
| FR-13 | Follow | Theo dõi và hủy theo dõi người dùng |
| FR-14 | Newsfeed | Bài đăng mới từ người dùng đang theo dõi |
| FR-15 | Admin Dashboard | Thống kê người dùng, lịch trình và tương tác |
| FR-16 | User Moderation | Quản lý, khóa/mở khóa tài khoản |
| FR-17 | Content Moderation | Kiểm duyệt và xử lý nội dung vi phạm |
| FR-18 | Categories & Tags | Quản lý danh mục địa điểm, tỉnh thành và thẻ chủ đề |

**Trạng thái triển khai:** Danh sách trên là yêu cầu từ SRS, không phải bảng xác nhận đã hoàn thành. Cần đối chiếu mã nguồn và kiểm thử từng FR trước khi đánh dấu trạng thái chính thức.

## 4. Nguyên tắc xử lý ảnh và địa điểm

- Người dùng có thể chọn hoặc tải ảnh bìa và ảnh cho hoạt động. Ảnh đã chọn phải được lưu thành công và hiển thị lại sau khi tải trang.
- **Cloudinary** là hướng lưu trữ ảnh theo SRS. Khi chuyển từ ảnh lưu nội bộ sang Cloudinary, phải bảo toàn URL/ảnh đã có và kiểm thử luồng upload, lưu, đọc lại trước khi chuyển dữ liệu.
- **Wikimedia Commons** chỉ là nguồn gợi ý ảnh bổ sung. Không yêu cầu API Wikimedia hoạt động mới cho phép lưu lịch trình; không tự dùng ảnh sai địa điểm. Khi dùng ảnh ngoài phải kiểm tra nguồn và điều kiện giấy phép.
- **Leaflet/OpenStreetMap** phục vụ bản đồ lịch trình. Nominatim hỗ trợ tra cứu địa điểm theo yêu cầu người dùng, không thay thế dữ liệu lịch trình do người dùng nhập.
- Nếu chưa có ảnh phù hợp, dùng placeholder trung tính; tuyệt đối không gán ảnh du lịch ngẫu nhiên.

## 5. Cấu trúc dự án

```text
TripConnect_Static/
├── admin/                 # Giao diện và nghiệp vụ quản trị
├── assets/                # CSS, JavaScript, hình ảnh giao diện
├── config/                # Kết nối database và hàm nghiệp vụ dùng chung
├── database/
│   └── migrations/        # Lịch sử thay đổi schema SQL
├── index.php              # Trang chủ
├── explore.php            # Khám phá và tìm kiếm lịch trình
├── feed.php               # Bảng tin
├── profile.php            # Hồ sơ người dùng
├── create-trip.php        # Tạo lịch trình
├── edit-trip.php          # Chỉnh sửa lịch trình
├── trip-detail.php        # Chi tiết lịch trình
└── README.md
```

*Sơ đồ chỉ liệt kê những thư mục và file tiêu biểu, không phải danh sách đầy đủ.* Giữ các module đang được sử dụng; không gộp hoặc xóa file chỉ để giảm số lượng file trong repository.

## 6. Chạy project bằng XAMPP

1. Cài XAMPP và kiểm tra các extension PHP cần thiết, đặc biệt PDO MySQL và cURL.
2. Đặt project dưới `htdocs` hoặc cấu hình Apache trỏ tới thư mục chứa project.
3. Khởi động Apache và MySQL từ XAMPP.
4. Trong phpMyAdmin, tạo database `tripconnect` nếu chưa có. **Các migration trong repository không phải schema khởi tạo đầy đủ**: chúng giả định các bảng nền như `users`, `trips`, `locations`, `trip_days`, `activities`, `likes`, `comments`, `bookmarks`, `follows`, `tags` và `trip_tags` đã tồn tại. Máy mới cần được cung cấp schema khởi tạo đã rà soát (chỉ cấu trúc và dữ liệu danh mục an toàn), sau đó mới chạy các migration còn thiếu.
5. Với database đã có dữ liệu, **sao lưu trước**, chạy preflight đúng tên file và chỉ áp dụng migration còn thiếu theo thứ tự phù hợp. Không chạy lại migration đã được áp dụng.
6. Nếu database đã có schema sau Giai đoạn 1, kiểm tra bằng `SHOW COLUMNS FROM trips` trước khi chạy `005` và `006`. Schema đang chạy cần có `cover_image VARCHAR(1000)`, `moderation_status` nhận `visible`/`hidden` và index `idx_trips_moderation_status`. Chỉ chạy migration còn thiếu trên bản sao lưu đã xác minh.
7. Truy cập địa chỉ tương ứng với thư mục triển khai, ví dụ:

   `http://localhost/52400267_52400292_52400332/TripConnect_Static/`

### Lưu ý về migration

- `001_create_chat_tables.sql`: migration lịch sử của tính năng Chat đã ngừng sử dụng. **Không chạy khi cài phiên bản không có Chat**, trừ khi nhóm xác định có phụ thuộc và có chủ đích khôi phục tính năng.
- `002_trip_timeline_budget.sql`: thay đổi phục vụ Timeline và ngân sách.
- `003_location_catalog.sql`: dữ liệu/cấu trúc phục vụ địa điểm.
- `004_trip_cover_image_metadata.sql`: các cột nguồn, tác giả và giấy phép ảnh bìa.
- `005_trip_cover_image_length.sql`: mở rộng `trips.cover_image` lên `VARCHAR(1000)` để lưu URL ảnh ngoài dài hơn; dữ liệu hiện có được giữ nguyên.
- `006_trip_moderation.sql`: bổ sung `trips.moderation_status` với các giá trị `visible` và `hidden`, cùng index `idx_trips_moderation_status`; trạng thái xuất bản `draft`, `published`, `deleted` không bị thay đổi.
- Những file `*_preflight.sql` dùng để kiểm tra điều kiện trước migration tương ứng.
- Tên preflight tương ứng là `005_trip_cover_image_length_preflight.sql` và `006_trip_moderation_preflight.sql`.
- Migration `006` là lịch sử thay đổi ban đầu và khai báo cột dạng `VARCHAR(20)`; database hiện tại đã được chuẩn hóa thành `ENUM('visible','hidden')`. Không chạy lại `006` trên database hiện tại và không dùng migration này thay cho schema khởi tạo mới nếu chưa rà soát.
- Không xóa hoặc sửa ngược migration lịch sử đã từng áp dụng trên database của nhóm. Nếu cần thay đổi tiếp, thống nhất quy trình migration mới.

### Cấu hình database

Các biến môi trường được mã nguồn hỗ trợ:

```text
TRIPCONNECT_DB_HOST
TRIPCONNECT_DB_NAME
TRIPCONNECT_DB_USER
TRIPCONNECT_DB_PASSWORD
```

Trên XAMPP cá nhân, có thể dùng cấu hình localhost phù hợp môi trường máy; **không commit mật khẩu, API key, Cloudinary secret hoặc thông tin nhạy cảm**. Khi đưa lên máy khác cần cấu hình kết nối tương ứng.

### Cài database trên máy mới

File [database/schema.sql](database/schema.sql) là schema khởi tạo sạch, chỉ chứa cấu trúc hiện tại và không chứa dữ liệu người dùng. Thành viên mới nên tạo database `tripconnect`, import file này một lần trong phpMyAdmin, sau đó cấu hình kết nối PHP và tạo tài khoản thử nghiệm riêng trên máy đó.

Không chạy các migration 001–006 ngay sau `schema.sql`: schema đã bao gồm các cột Timeline, location catalog, metadata ảnh bìa, `cover_image VARCHAR(1000)` và `moderation_status ENUM('visible','hidden')`. Các migration chỉ giữ vai trò lịch sử/nâng cấp cho database cũ. Với database đã có dữ liệu, sao lưu trước, chạy preflight và chỉ áp dụng migration còn thiếu; không chạy lại `005` hoặc `006` nếu các cột/index tương ứng đã tồn tại.

Schema này không cung cấp dữ liệu mẫu thật, tài khoản, ảnh upload, cache hoặc dump database. Các file trong `assets/uploads/` bị loại khỏi Git; thành viên cần tự tạo dữ liệu thử an toàn trên máy cá nhân.

## 7. Quy trình làm việc nhóm

- Mỗi thành viên làm trên branch riêng, cập nhật mã nguồn từ nhánh chung trước khi sửa và tạo Pull Request để nhóm rà soát.
- Commit mã nguồn, cấu hình mẫu an toàn, tài liệu và migration cần thiết. Không commit backup, dump chứa dữ liệu thật, cache, log, `.DS_Store` hoặc ảnh upload cá nhân.
- Không thay đổi schema của database dùng chung khi chưa thống nhất và có bản backup.
- Kiểm tra `git diff --check`, PHP lint và cú pháp JavaScript trước khi tạo Pull Request.
- Kiểm thử chức năng bằng thao tác thực tế: tạo → lưu → tải lại → chỉnh sửa → xuất bản; không coi HTTP 200 hoặc lint thành công là đủ để xác nhận chức năng hoàn chỉnh.
- Cập nhật README/SRS khi nhóm thống nhất thay đổi kiến trúc hoặc phạm vi chức năng.

## 8. Thứ tự ưu tiên hoàn thiện

1. Ổn định đăng nhập, hồ sơ, tạo/chỉnh sửa, lưu nháp/xuất bản, ảnh bìa, Timeline, ngân sách và bản đồ.
2. Hoàn thiện Clone/Fork và Export PDF/Image.
3. Hoàn thiện Search/Multi-Filter, tags và Recommendation dựa trên sở thích.
4. Hoàn thiện Like, Comment, Bookmark, Follow và Newsfeed.
5. Hoàn thiện Admin, phân quyền, bảo mật, responsive và kiểm thử tổng thể.

**Nguyên tắc phát triển:** Ưu tiên các chức năng cốt lõi FR-01 đến FR-18. Không tiếp tục mở rộng các chức năng phụ như tự động thu thập hình ảnh khi việc đó làm trì hoãn tính năng chính hoặc gây bất ổn cho luồng lưu lịch trình.
