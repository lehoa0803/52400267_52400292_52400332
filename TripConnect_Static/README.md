# TripConnect – FrontEnd Demo (bản nâng cấp)

Demo được dựng từ tài liệu đề tài TripConnect/SRS đã cung cấp, nay đã được đọc lại và nâng cấp toàn bộ 9 trang.

## 9 trang
1. `index.html` – Trang chủ, tìm kiếm nhanh chuyển sang trang Khám phá kèm bộ lọc
2. `explore.html` – Khám phá: tìm kiếm + lọc theo địa điểm/số ngày/ngân sách hoạt động thật (JS), có trạng thái "không có kết quả"
3. `trip-detail.html` – Chi tiết lịch trình: timeline, **bản đồ Leaflet thật**, **biểu đồ ngân sách Chart.js thật**, like/bookmark/follow, bình luận thêm được trực tiếp
4. `create-trip.html` – Tạo lịch trình: thêm/xoá hoạt động theo từng ngày (nhiều ngày, chuyển tab ngày), xem trước ảnh bìa thật (FileReader), checklist tự động tick
5. `feed.html` – Newsfeed: lọc Tất cả/Đang theo dõi/Phổ biến hoạt động thật, bình luận mở rộng theo từng bài, like/bookmark/follow
6. `profile.html` – Hồ sơ: đã sửa lỗi thẻ lịch trình bị để trống (`{trip_cards}`), thêm nút "Thêm lịch trình mới"
7. `login.html` – Đăng nhập người dùng: đăng nhập/đăng ký/quên mật khẩu, hiện/ẩn mật khẩu, chuyển hướng sau đăng nhập
8. `admin-login.html` – Đăng nhập quản trị viên, đồng bộ theo bảng màu chung
9. `assets/style.css`, `assets/app.js` – **Bộ mã nguồn dùng chung còn thiếu ở bản trước đã được bổ sung**

## Những gì đã nâng cấp
- **Hệ thống thiết kế đồng nhất**: bảng màu "tide teal + sunset coral", font tiêu đề Fraunces + font UI Plus Jakarta Sans, spacing/shadow/radius nhất quán trên toàn bộ site (`assets/style.css`).
- **`assets/app.js` và `assets/style.css` được tạo mới** — hai file này được mọi trang tham chiếu nhưng chưa tồn tại trong bộ tải lên trước đó, khiến site không có style/tương tác khi mở.
- **Sửa lỗi nội dung**: `profile.html` từng hiển thị chuỗi placeholder thô `{trip_cards}` thay vì thẻ lịch trình.
- **Tương tác thật thay vì tĩnh**: bộ lọc Khám phá, tab Bảng tin, thêm hoạt động/timeline nhiều ngày, xem trước ảnh, thêm bình luận, toggle thích/lưu/theo dõi (dùng chung qua `app.js`), toast thông báo.
- **Trực quan hoá dữ liệu thật**: biểu đồ ngân sách (Chart.js doughnut + chú thích) và bản đồ hành trình (Leaflet + marker) ở `trip-detail.html`.
- **Khả năng tiếp cận & SEO cơ bản**: thêm `alt` cho ảnh, `label for`, `aria-label`, favicon, thẻ mô tả trang, trạng thái focus rõ ràng, tôn trọng `prefers-reduced-motion`.
- **Điều hướng**: liên kết nav tự động highlight trang hiện tại (`data-page` + `app.js`).

## Công nghệ
- HTML5 / CSS3, Bootstrap 5, Bootstrap Icons
- Vanilla JavaScript (không phụ thuộc framework)
- Chart.js (biểu đồ ngân sách)
- Leaflet.js (bản đồ hành trình — đã kết nối, không chỉ "chuẩn bị sẵn")
- Dữ liệu hiện là mock, chưa có PHP/MySQL

## Chạy
Mở `index.html` bằng trình duyệt, hoặc dùng Live Server trong VS Code. Toàn bộ trang cùng thư mục `assets/` cần được giữ nguyên cấu trúc để đường dẫn tương đối hoạt động.

## Bước Backend sau
Giữ nguyên giao diện và thay mock data bằng PHP MVC + MySQL theo SRS:
`users, preferences, user_preferences, locations, trips, trip_days, activities, tags, trip_tags, likes, comments, bookmarks, follows, activity_logs`.

Gợi ý điểm nối dữ liệu theo từng trang:
- `explore.html` / `index.html` → API tìm kiếm `trips` theo `locations`, `budget`, `trip_days`
- `trip-detail.html` → `trips` + `trip_days` + `activities` + `comments` + `likes` + `bookmarks` + `follows`
- `create-trip.html` → `POST/PUT trips`, `trip_days`, `activities`
- `feed.html` → nguồn dữ liệu theo `follows` (following) và mức độ tương tác (popular)
- `login.html` / `admin-login.html` → `users` (phân quyền admin riêng)
