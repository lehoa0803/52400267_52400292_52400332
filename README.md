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
4. Với máy mới, import `database/schema.sql` một lần trong phpMyAdmin để tạo database `tripconnect` và các bảng. Không chạy tiếp migration 001–006: schema đã có các thay đổi đó. Với database đang có dữ liệu, không import lại schema; dùng bước kiểm tra nâng cấp bên dưới.
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

Trước khi dùng Seeder trên máy mới, cần danh mục điểm đến và tags: schema chỉ tạo bảng rỗng, còn Seeder không tự tạo danh mục. Nhóm có thể xuất riêng **dữ liệu danh mục đã duyệt của `locations` và `tags`** từ phpMyAdmin trên máy đã thiết lập, rồi nhập vào database mới còn rỗng; không xuất `users`, lịch trình hoặc tương tác. Có thể bổ sung danh mục bằng giao diện Admin thay thế. Bộ demo cần 24 tên vùng khớp trường `area` trong `demo_dataset.php` và sáu tags hoạt động có slug `am-thuc`, `tu-tuc`, `bien`, `check-in`, `nui-rung`, `tiet-kiem`. Seeder tra ID bằng tên/slug; không cần sao chép ID tài khoản. Với database đã có dữ liệu, đối chiếu danh mục trước, không import đè các bảng.

Tài khoản mới được tạo qua form đăng ký; để quản trị trên máy mới, người phụ trách cấp role `admin` cho đúng tài khoản đã đăng ký trên database của máy đó. Không có tài khoản/mật khẩu Admin mặc định trong repository. Các tài khoản cũ chưa dùng `password_hash` cần được chuyển đổi hoặc đặt lại mật khẩu trong một đợt bảo trì có phê duyệt trước khi triển khai công khai; mã nguồn vẫn giữ khả năng đăng nhập cũ để không khóa người dùng hiện tại. Đăng nhập/đăng ký và đăng xuất sử dụng CSRF; đăng xuất chỉ nhận POST từ nút của website.

## 7. Quy trình làm việc nhóm

### File phát triển và triển khai hosting

- Giữ `database/schema.sql`, migrations và preflight để cài đặt/đối chiếu schema; migration Chat `001` chỉ là lịch sử, không chạy mặc định. Giữ `seed_demo.php`, `demo_dataset.php`, `demo_routes.php`, `demo_wikimedia.json` để nhóm bổ sung dữ liệu có kiểm soát qua CLI.
- Giữ các test vì phạm vi khác nhau: Timeline/Budget thuần, DOM form (race tìm địa điểm/tọa độ/thứ tự), lưu chỉnh sửa/rollback, tags, bảo trì ảnh, dữ liệu demo SQLite/MySQL/HTTP, kiểm tra dữ liệu đã nhập chỉ SELECT và tích hợp GĐ2–5/ngôn ngữ. Không dùng test demo để kiểm tra database chính.
- Các test PHP chỉ chạy qua CLI; `trip_timeline_form_test.html` có thể mở bằng `file://` trong Chrome để kiểm tra DOM không dùng database. `phase23_mysql_test.php --phase5 TEN_DB_THU` kiểm tra Admin và luồng in Timeline/PNG hiện tại; `--locale` kiểm tra cả hai ngôn ngữ và PDF A4 thực tế. Các chế độ ghi chỉ chấp nhận database localhost có tiền tố test riêng.
- Hosting chỉ cần mã ứng dụng và assets. Không đưa `tests/`, `database/`, `.git/`, `AGENTS.md`, tài liệu nội bộ, backup, log, cache hoặc `.env` lên vùng web công khai. `config/` vẫn cần cho PHP include, phải chặn truy cập trực tiếp và cấp cấu hình riêng qua môi trường; không đưa credential thật vào Git.
- Các `.htaccess` chặn HTTP vào `tests/`, `database/`, `config/`, file ẩn và bản sao cấu hình trên Apache 2.4 có `AllowOverride` phù hợp. Giữ các file này khi triển khai. Nginx và PHP development server không áp dụng `.htaccess`: cần chặn các đường dẫn tương đương ở web server hoặc đặt chúng ngoài document root; không dùng PHP development server cho hosting công khai. Kiểm tra HTTP trả 403/404 cho các đường dẫn nội bộ trước khi mở website.
- Nếu toàn bộ repository được đặt trong document root, giữ cả `.htaccess` ở repository root để chặn `.git/`, tài liệu nội bộ và thư mục backup cũ. Khi ứng dụng là junction như bản XAMPP hiện tại, thư mục cha XAMPP cần có bản quy tắc này riêng; `.htaccess` trong ứng dụng không thể bảo vệ thư mục cha.
- `.gitignore` ở repository root bảo vệ cả backup nằm ngoài thư mục ứng dụng; chỉ cho phép schema/migrations SQL nguồn, loại dump và dữ liệu runtime. `.gitignore` không gỡ file đã được Git theo dõi: kiểm tra danh sách staged trước commit.

### Bảo trì ảnh bìa lịch trình cũ (Admin)

Trong **Admin → Lịch trình → Bảo trì ảnh bìa hàng loạt** (`admin/admin-trip-covers.php`), chọn lịch trình cần kiểm tra rồi bấm **Kiểm tra & tự tìm ảnh**. Hệ thống dùng Wikimedia và cache hiện có để chọn ảnh có bằng chứng địa danh/giấy phép; thao tác này chưa ghi database. Xem ảnh, nguồn và giấy phép, sau đó bấm **Xác nhận cập nhật** để lưu một hoặc nhiều đề xuất. Mỗi trang tối đa 10 lịch trình; đề xuất hết hạn sau 30 phút, dữ liệu đã thay đổi phải duyệt lại.

Ảnh upload/ảnh Wikimedia do người dùng chủ động chọn được giữ nguyên nếu chưa có bằng chứng hỏng. Nếu chưa có ảnh phù hợp, lỗi mạng hoặc giới hạn truy vấn, hệ thống báo cần tìm lại và không cho xác nhận ghi placeholder. Chỉ trường ảnh bìa và metadata được cập nhật khi có ảnh thật, không đổi tác giả, Timeline, tương tác hay trạng thái. Trang công khai không gọi Wikimedia khi tải; ảnh tải lỗi vẫn chuyển sang placeholder cục bộ để bảo toàn bố cục.

Kiểm thử nghiệp vụ bảo trì không ghi MySQL thật: `C:\xampp\php\php.exe TripConnect_Static/tests/trip_cover_maintenance_test.php` (chạy từ repository root, dùng SQLite trong bộ nhớ).

### Bộ dữ liệu demo có kiểm soát

`database/demo_dataset.php` chứa **18 hồ sơ, 33 chuyến có Timeline (95 ngày, 522 hoạt động)**, phân bổ 24 mục tỉnh/thành trong catalog hiện có (25 điểm đến ảnh, tách riêng Côn Đảo và Vũng Tàu). `database/demo_routes.php` chứa 27 tuyến bổ sung và ghi chú riêng từng địa danh; `database/demo_wikimedia.json` lưu metadata 19 ảnh bổ sung lấy qua cơ chế Wikimedia hiện có, kèm kết quả HEAD ngày 24/10/2026. Sáu chuyến đầu được giữ nguyên. Không còn chuyến kế hoạch rỗng hoặc ảnh placeholder trong bộ seed. Tiểu sử và mô tả công khai ghi rõ đây là dữ liệu mẫu do nhóm biên soạn. Không tạo Like/Follow/Comment/Bookmark hay lượt xem giả.

Chạy từ repository root, dùng PHP CLI:

```powershell
# Không kết nối database: xem tên, phân bổ tác giả/địa phương, Timeline và Budget.
& 'C:\xampp\php\php.exe' TripConnect_Static/database/seed_demo.php --report
# Chỉ HEAD các ảnh Wikimedia có sẵn; lỗi/429/timeout trả trạng thái unknown, cần thử lại.
& 'C:\xampp\php\php.exe' TripConnect_Static/database/seed_demo.php --verify-images
# Chỉ SELECT: kiểm tra danh mục/va chạm và đếm bản ghi dự kiến thêm.
& 'C:\xampp\php\php.exe' TripConnect_Static/database/seed_demo.php --database=tripconnect
# Kiểm thử nhập, rollback, chạy lại, quan hệ và Budget trong SQLite :memory:.
& 'C:\xampp\php\php.exe' TripConnect_Static/tests/demo_seed_test.php
# MariaDB localhost thực tế: tạo database tripconnect_demo_test_<thời gian>_<ngẫu nhiên> riêng.
# Chỉ sao chép cấu trúc/danh mục; MySQL chính chỉ SELECT, không đổi cấu hình website.
& 'C:\xampp\php\php.exe' TripConnect_Static/tests/demo_seed_mysql_test.php --run
```

`--report` phân biệt `trips_with_cover=33`, `cover_count=25` và `shared_cover_uses=8`: tám lượt dùng chung ảnh không phải tám chuyến thiếu ảnh. Không cần thay 25 ảnh đạt chỉ để tạo URL riêng cho từng chuyến.

Kiểm thử MariaDB giữ database có tiền tố `tripconnect_demo_test_` để duyệt lại, không tự DROP hoặc xóa database nào. Test tạo 17 bảng **một lần trong database thử mới**, sau đó gọi CLI Seeder thật lên các bảng đã tồn tại; đối chiếu schema trước/sau, khóa ngoại, Budget, chạy lại và rollback khi lỗi. Không chạy `schema.sql` nguyên file vì phần đầu có `USE tripconnect`; test chỉ trích riêng các câu CREATE TABLE cho database thử. Seeder vận hành không chứa DDL và không cần import lại schema.

Để kiểm thử HTTP, chạy một PHP development server riêng trỏ vào database thử bằng biến môi trường **chỉ trong tiến trình thử**, không đổi Apache hoặc file kết nối website. Sau đó chạy `tests/demo_seed_ui_test.php TEN_DB_THU http://127.0.0.1:CONG/`. Test từ chối database không đúng tiền tố và xác minh database của server trước khi gửi đăng nhập; chỉ đặt mật khẩu tạm trong database thử rồi khôi phục hash gốc. Không in hoặc lưu mật khẩu. Tắt server thử sau khi kiểm tra. Test HTTP kiểm tra HTML của 33 trang, không tự khẳng định mọi ảnh/biểu đồ đã vẽ trong trình duyệt; cần kiểm tra Chrome riêng khi nghiệm thu giao diện.

**Chưa được chạy nhập MySQL chính hoặc xóa dữ liệu cũ khi chưa được chủ dự án phê duyệt.** Sau khi duyệt, chuẩn bị bản sao lưu có thể phục hồi ở ngoài repository và kiểm tra trên MySQL/MariaDB độc lập trước. Nhập bằng `seed_demo.php --database=TEN_DB --apply --confirm-database=TEN_DB`; cấu hình host/user/password qua các biến môi trường hiện có, không ghi credential vào file. Seeder không tạo database/schema/danh mục, yêu cầu bảng InnoDB và các điểm đến/tags hoạt động đã tồn tại. Trước khi ghi, cả 25 ảnh phải trả kết quả HEAD `ok`. Các thay đổi nằm trong một transaction, lỗi bất kỳ rollback toàn bộ.

Username `tc.<tên-không-dấu>`, email `.test` và marker `[TripConnect demo v1 author:...]`/`[TripConnect demo v1 trip:...]` nhận diện dữ liệu seed. ID luôn tra từ database, không dùng ID cố định. Chạy lại chỉ bỏ qua bản ghi đã seed, giữ nội dung sửa tay và trạng thái ẩn/xóa; va chạm với tài khoản không có marker đúng sẽ dừng. Không sửa các tài khoản/lịch trình đang có. Seeder không đọc hay sao chép tương tác từ lịch trình cũ.

Mỗi tài khoản dùng secret ngẫu nhiên riêng rồi `password_hash()`, secret được hủy, không in/lưu mật khẩu hay cung cấp đăng nhập công khai. Các hồ sơ này là tác giả nội dung demo; khi có Email Verification phải bảo toàn user/trip và phân biệt bằng marker/domain, **không** đánh dấu `.test` là đã xác minh. Nếu nhóm cần đăng nhập demo, tạo tài khoản riêng bằng email thật do nhóm kiểm soát và quy trình xác minh bình thường. Hiện sở thích hoạt động qua JSON `users.travel_interests`; hai bảng preferences/user_preferences đang trống không được tự thêm dữ liệu thay thế.

Đăng nhập tác giả demo chỉ chấp nhận mật khẩu khớp `password_verify()`, không áp dụng nhánh so sánh mật khẩu cũ dạng plaintext/hash. Không có mật khẩu đăng nhập được phát hành cho 18 hồ sơ seed; việc đăng nhập bằng mật khẩu tạm chỉ phục vụ kiểm thử độc lập. Hiện chưa có Email Verification; khả năng tương thích về sau là phương án thiết kế, chưa phải chức năng đã triển khai. Cần giữ tài khoản/tác giả khi bổ sung xác minh và không tạo bypass cho người dùng công khai.

Giá hoạt động là ước tính, ghi rõ đơn vị `fixed` (cả nhóm) hoặc `per_person`. Tiền xe trọn chuyến ghi một lần; phạm vi là chi phí tại điểm đến, không gồm vé liên tỉnh/mua sắm tùy ý. Gói tham quan cả ngày có bữa trưa không tạo thêm khoản ăn trưa; ngày cuối trả phòng đúng giờ và gửi hành lý, không mặc định được giữ phòng miễn phí tới tối. Hà Giang có nơi ngủ riêng từng chặng và buổi xe dài, không chạy xe đêm. Tọa độ cả 522 hoạt động để NULL vì chưa xác minh, không mạo danh địa điểm trong catalog. Có thể bổ sung catalog/Places sau qua giao diện sửa; không phải tạo lại chuyến. Nguồn điểm đến và nguồn/giấy phép Commons nằm ngay trong dữ liệu. Giờ dịch vụ, tọa độ và giá niêm yết chưa được xác minh đầy đủ. Không gọi API ảnh lúc tải trang.

Để gỡ riêng dữ liệu seed sau này: xuất danh sách ID bằng **đồng thời** marker, username/email `.test` và quan hệ tác giả; đối chiếu với nội dung đã sửa và các bảng likes/comments/bookmarks/follows, bản clone, catalog do người dùng tạo, conversation/message trước khi đề xuất xóa. Chỉ xóa đúng ID đã được duyệt trong transaction, theo khóa ngoại; nếu có dữ liệu người dùng mới liên quan thì dừng để duyệt lại. Seeder hiện **không có lệnh xóa**, không xóa hàng loạt theo miền email hoặc tên hiển thị. Khôi phục dùng bản sao lưu ngoài repository khi cần. Lịch trình cũ #1–#6 chỉ là ứng viên cần chủ dự án xác nhận; #7/#10/#13 và mọi tài khoản chưa rõ nguồn gốc được giữ nguyên.

SQLite test chuyển riêng cú pháp `GROUP_CONCAT ... SEPARATOR` và kiểu cột; không thay thế kiểm thử MySQL/MariaDB, ENUM/DECIMAL, đồng thời/concurrency hoặc nghiệm thu giao diện Trang chủ/Explore/Profile/Trip Detail trên database độc lập.

### Quy tắc Git của nhóm

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
