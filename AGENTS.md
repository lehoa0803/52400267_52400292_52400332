# TripConnect — Hướng dẫn điều phối phát triển

Áp dụng cho toàn bộ repository và các phiên Codex tiếp theo. Mục tiêu: hoàn thiện TripConnect — nền tảng chia sẻ và gợi ý lịch trình du lịch — theo SRS FR-01 đến FR-18, có chức năng hoạt động thật, dữ liệu chính xác, bảo mật, giao diện dễ dùng và mã nguồn phù hợp đồ án nhóm sinh viên.

## 1. Bối cảnh và nguồn yêu cầu

- Repository: `D:\LeHoa\WebCuoiKy\WEB`.
- Project: `TripConnect_Static/`.
- Bản chạy XAMPP: `C:\xampp\htdocs\52400267_52400292_52400332\TripConnect_Static`.
- Database: `tripconnect`.
- Công nghệ đã thống nhất: PHP thuần, MySQL/MariaDB, HTML/CSS/Bootstrap, JavaScript, Leaflet/OpenStreetMap; Wikimedia Commons cho ảnh gợi ý hiện tại; Chart.js khi phù hợp với Budget.
- Không tự chuyển sang Laravel, React, Next.js, Node.js hoặc framework khác.
- SRS là nguồn nghiệp vụ chính; README, schema, migration, mã nguồn và database thực tế là căn cứ đối chiếu. Nếu thiếu SRS hoặc nội dung cần thiết, báo rõ và chỉ dùng yêu cầu đã xác minh; không tự đặt quy định mới.
- Thông tin lịch sử, phải kiểm tra lại: GĐ1 phần lớn đã triển khai, branch `feature/phase1-stabilization`, commit đã push `6cf5824584794f332cf329050282ee2cbd023cd1`; có `TripConnect_Static/database/schema.sql` và migration 001–006. Database từng được xác nhận có `trips.cover_image VARCHAR(1000)`, `trips.moderation_status ENUM('visible','hidden')` và index `idx_trips_moderation_status`.
- Không coi báo cáo cũ, nút bấm, file PHP hoặc bảng MySQL là bằng chứng chức năng đã hoàn thành.

## 2. Hiểu lệnh và giới hạn phạm vi

| Lệnh người dùng | Hành động |
|---|---|
| `Bắt đầu Giai đoạn X` | Áp dụng quy trình mục 3; kiểm tra mọi giai đoạn trước, dependency và nguy cơ hồi quy; tái sử dụng phần đang hoạt động và triển khai phần thiếu của GĐ X. |
| `Tiếp tục Giai đoạn X` | Áp dụng quy trình mục 3; tự xác định phần thiếu/lỗi của GĐ X từ hiện trạng, sửa an toàn và kiểm thử trong cùng lượt. |
| `Hoàn thiện các lỗi còn lại của Giai đoạn X` | Chỉ xử lý tiêu chí chưa đạt hoặc lỗi đã xác minh của GĐ X; không làm lại phần đã đạt; kiểm thử hồi quy và báo cáo. |
| `Kiểm tra tiến độ toàn bộ TripConnect` | Chỉ đọc mã nguồn, schema và bằng chứng kiểm thử; không sửa code/database; báo cáo cả 6 giai đoạn, phần thiếu và thứ tự ưu tiên tiếp theo. |

Không yêu cầu người dùng liệt kê lại chức năng hoặc nhắc lại thông tin đã có. Không mặc định giai đoạn trước đạt 100% khi được yêu cầu bắt đầu giai đoạn mới. Khi chỉ tiếp nhận hướng dẫn/lộ trình, đọc, kiểm tra hiện trạng và xác nhận đã hiểu; không tự bắt đầu lập trình giai đoạn khi chưa có lệnh.

## 3. Quy trình bắt buộc cho mỗi lượt phát triển

1. **Kiểm tra hiện trạng:** đọc SRS/tài liệu yêu cầu, README, schema/migration, PHP/JS/CSS liên quan và phần đã triển khai ở các giai đoạn trước; kiểm tra branch, `git status`, `git diff`; truy vấn database chỉ đọc khi có kết nối. Không dựa hoàn toàn vào báo cáo cũ.
2. **Đánh giá trước khi làm:** rà soát cả 6 giai đoạn theo tiêu chí kiểm tra được và cách tính ở mục 4; phân biệt đã triển khai với đã xác minh.
3. **Đánh giá ảnh hưởng:** xác định chức năng ổn định, dependency, tương thích schema, vùng rủi ro và danh sách kiểm thử hồi quy; bảo toàn ảnh bìa, Timeline, Explore, Admin và mọi chức năng đã kiểm thử đạt. Sửa trước lỗi nghiêm trọng ở giai đoạn trước nếu trực tiếp cản trở công việc hiện tại; chỉ ghi nhận lỗi nhỏ không liên quan, không refactor toàn hệ thống.
4. **Chọn phần thiếu:** xác định chức năng chưa làm, chưa đúng SRS, lỗi đã xác minh và phần cần kiểm thử. Ưu tiên: (1) sai nghiệp vụ/mất dữ liệu; (2) chức năng SRS bắt buộc còn thiếu; (3) phân quyền/bảo mật; (4) giao diện cản thao tác; (5) trải nghiệm/tối ưu.
5. **Tự triển khai:** kiểm tra → sửa → kiểm thử → xử lý lỗi → hồi quy → cập nhật tiến độ. Ưu tiên sửa file hiện có, không viết lại phần tốt hoặc chỉ để thống nhất phong cách. Không thêm tính năng ngoài SRS khi yêu cầu chính còn thiếu; không tạo hàng loạt migration, preflight, backup, report hoặc helper không cần thiết. Không hỏi từng bước lập trình thông thường khi có thể làm an toàn. Nếu phạm vi quá lớn, hoàn tất và kiểm thử nhóm công việc ưu tiên cao nhất, báo phần còn lại; không tuyên bố hoàn thành thiếu bằng chứng. Không tự chạy tác vụ nền hoặc hứa tiếp tục sau khi lượt kết thúc.
6. **Kiểm thử:** thực hiện các kiểm tra ở mục 6, sửa lỗi phát hiện được trong phạm vi cho phép.
7. **Đồng bộ và báo cáo:** khi thay đổi code chạy ứng dụng, kiểm tra diff rồi đồng bộ an toàn sang bản XAMPP theo mục 7; báo cáo tiếng Việt theo mục 8. Không tạo file báo cáo riêng trong repository.

## 4. Tiêu chí và cách tính tiến độ

- Mỗi giai đoạn có danh sách tiêu chí độc lập, tách các ý ghép trong checklist thành điều kiện kiểm tra cụ thể. Mặc định trọng số mỗi tiêu chí bằng nhau; chỉ điều chỉnh khi SRS có ưu tiên rõ và phải giải thích.
- Ghi trạng thái từng tiêu chí: **Chưa làm (0%)**; **Có triển khai nhưng chưa đáp ứng yêu cầu** (nêu ca kiểm thử đạt/chưa đạt); **Đã triển khai và vượt kiểm tra kỹ thuật**; **Đã kiểm thử thực tế và xác nhận đạt**. Thiếu bằng chứng ghi **Chưa xác minh**.
- Với tổng trọng số tiêu chí của giai đoạn là `W`, `% đã triển khai = 100 × tổng trọng số tiêu chí có triển khai đáp ứng điều kiện yêu cầu / W`; `% đã xác minh = 100 × tổng trọng số tiêu chí đã nghiệm thu bằng kiểm thử phù hợp / W`. Phần triển khai dở chỉ tính theo các điều kiện con đã xác định và có bằng chứng; không gán tỷ lệ cảm tính.
- Tiêu chí chỉ hoàn thành khi đủ điều kiện nghiệm thu. Ghi riêng bằng chứng kiểm tra tĩnh/kỹ thuật và kiểm thử thao tác thực tế; kiểm tra tĩnh không chứng minh luồng sử dụng đã đạt. Không công bố chức năng chưa thử thực tế là đạt 100%.
- Không cộng trùng tiến độ giữa các giai đoạn. Với yêu cầu xuất hiện lại, phân biệt phạm vi cơ bản, nâng cao và kiểm thử hồi quy; không đếm lại cùng kết quả triển khai như công việc mới.
- Không đủ dữ liệu để xác định tiêu chí/mẫu số/bằng chứng thì ghi **Chưa đủ bằng chứng**, giải thích; không bịa phần trăm. Luôn báo cả tỷ lệ triển khai và xác minh, trước và sau lượt.

## 5. Lộ trình và checklist nghiệm thu

Các mục dưới đây là yêu cầu cần kiểm chứng, không phải xác nhận đã hoàn thành.

### GĐ1 — Nền tảng và ổn định

Mục tiêu: website cơ bản ổn định, hỗ trợ tài khoản, lịch trình và quản lý nội dung.

- [ ] **1.1 — FR-01, tài khoản:** đăng ký, đăng nhập, đăng xuất; bảo vệ mật khẩu bằng hàm băm phù hợp; quản lý session; kiểm tra đầu vào; phân quyền User/Admin; chặn tài nguyên riêng khi chưa đăng nhập và hành động Admin từ tài khoản thường.
- [ ] **1.2 — FR-02, hồ sơ:** xem/sửa hồ sơ, avatar và thông tin cơ bản; xem lịch trình bản thân; thiết lập/lưu sở thích; dữ liệu hồ sơ lưu chính xác.
- [ ] **1.3 — FR-03, FR-08, chuyến đi cơ bản:** tạo, sửa chuyến đi của mình, lưu nháp, xuất bản, xóa đúng thiết kế; kiểm tra sở hữu; upload ảnh hoặc chọn Wikimedia; lưu/hiển thị đúng ảnh bìa sau tải lại; không mất dữ liệu khi sửa.
- [ ] **1.4 — FR-09, Explore cơ bản:** danh sách công khai, tìm từ khóa, lọc cơ bản, trạng thái không kết quả, xóa bộ lọc; loại nội dung Admin đã ẩn.
- [ ] **1.5 — FR-15, FR-16, FR-17, Admin cơ bản:** đăng nhập Admin, dashboard, danh sách người dùng, quản lý lịch trình, ẩn/khôi phục; phân quyền/CSRF; không công khai lịch trình bị ẩn.
- [ ] **1.6 — Ổn định/bảo mật:** không lỗi PHP nghiêm trọng; SQL và đầu vào an toàn; không lộ mật khẩu, secret, stack trace; cấu hình DB phù hợp; trang chính hoạt động; README/schema đủ chia sẻ mã nguồn.

### GĐ2 — Timeline, Budget Tracker và địa điểm

Mục tiêu: tạo và quản lý chuyến đi nhiều ngày theo SRS.

- [ ] **2.1 — FR-03, thông tin chuyến đi:** tên, mô tả, điểm đến, ngày bắt đầu/kết thúc khi phù hợp, số ngày, số người, ngân sách dự kiến, tags/chủ đề, ảnh bìa; từ chối dữ liệu sai và giữ dữ liệu khi chỉnh sửa.
- [ ] **2.2 — FR-04, Timeline:** nhiều ngày; thêm/sửa/xóa/sắp xếp hoạt động từng ngày; giờ bắt đầu/kết thúc và hoạt động qua đêm; địa điểm/mô tả; giữ hoạt động cũ khi sửa; lưu nháp Timeline chưa hoàn chỉnh; trang chi tiết hiển thị đúng; transaction/rollback an toàn.
- [ ] **2.3 — FR-05, Budget:** nhập ngân sách dự kiến và chi phí hoạt động; phân loại; tổng hợp theo ngày, danh mục, toàn chuyến; chi phí mỗi người/cả nhóm; so sánh ngân sách, cảnh báo vượt và biểu đồ phân bổ; phân biệt dự kiến/thực tế; không nhân đôi hoặc chia sai chi phí.
- [ ] **2.4 — FR-04, địa điểm:** nhập/tìm/chọn địa điểm có tọa độ; lưu latitude/longitude; hiển thị lại sau tải trang; tương thích location catalog; không tọa độ giả; đổi địa điểm không mất dữ liệu; xử lý hợp lý hoạt động không tọa độ.
- [ ] **2.5 — Bản đồ:** truyền đúng tọa độ tới Leaflet; marker đúng địa điểm thực, không marker giả; tile lỗi không làm hỏng chức năng; giữ nguyên phần HTTPS/SSL OpenStreetMap đang tạm hoãn.
- [ ] **2.6 — Chuẩn bị cá nhân hóa:** lưu nhất quán địa điểm, số ngày, ngân sách, tags, loại hình du lịch; không triển khai Recommendation mới trong GĐ2.

### GĐ3 — Tương tác và chia sẻ

Mục tiêu: chia sẻ lịch trình cộng đồng với dữ liệu và thao tác thật.

- [ ] **3.1 — FR-06, Clone/Fork:** sao chép lịch trình công khai cùng Timeline/hoạt động vào bản sao thuộc người dùng hiện tại; không sửa bản gốc; bản sao chỉnh sửa độc lập; xử lý nguồn thiếu dữ liệu hoặc bị ẩn.
- [ ] **3.2 — FR-11, Like/Comment:** Like/Unlike thật, số thích đúng DB; gửi/hiển thị bình luận; quyền sửa/xóa theo SRS; xác thực quyền và CSRF; không chỉ thay đổi DOM giả thao tác thành công.
- [ ] **3.3 — FR-12, Bookmark:** lưu/bỏ lưu, danh sách đã lưu; không trùng; chỉ tài khoản đăng nhập được thao tác.
- [ ] **3.4 — FR-13, Follow:** theo dõi/bỏ theo dõi tác giả, trạng thái đúng; không tự Follow hoặc tạo quan hệ trùng.
- [ ] **3.5 — FR-14, Newsfeed:** lịch trình mới từ người đang Follow; loại nháp/đã xóa/bị ẩn; thứ tự hợp lý; trạng thái chưa Follow ai; kiểm tra phân trang khi cần.

### GĐ4 — Tìm kiếm và gợi ý theo sở thích

Mục tiêu: khám phá lịch trình phù hợp nhu cầu.

- [ ] **4.1 — FR-02, sử dụng sở thích:** chọn, lưu, sửa; sử dụng đúng dữ liệu đã lưu, không tự suy đoán khi chưa có sở thích.
- [ ] **4.2 — FR-09, tìm kiếm nâng cao:** từ khóa, điểm đến, số ngày, ngân sách, tags, loại hình; kết hợp bộ lọc; không kết quả; chỉ trả lịch trình công khai.
- [ ] **4.3 — FR-10, Recommendation:** đánh giá sở thích, điểm đến, loại hình, ngân sách, số ngày, tags và độ phổ biến từ dữ liệu thật; chấm điểm rõ ràng/dễ giải thích, xếp hạng hợp lý; loại nội dung bị ẩn; có phương án cho tài khoản mới chưa có sở thích và dữ liệu thiếu.

Ưu tiên tái sử dụng `TripConnect_Static/config/trip_recommendation.php`. Không bắt buộc AI API hoặc machine learning khi SRS không yêu cầu.

### GĐ5 — Xuất lịch trình và quản trị hoàn chỉnh

Mục tiêu: hoàn thiện các chức năng SRS còn thiếu để dùng thực tế.

- [ ] **5.1 — FR-07, Export:** PDF; hình ảnh nếu SRS yêu cầu; có thông tin chuyến đi/Timeline, thời gian/địa điểm/chi phí hợp lý, tiếng Việt đúng; không lộ dữ liệu riêng tư trong file công khai; tải file hợp lệ.
- [ ] **5.2 — FR-08, vòng đời lịch trình:** kiểm tra đầy đủ nháp/xuất bản/chỉnh sửa/xóa, quyền sở hữu; không công khai bản nháp; đổi trạng thái không mất dữ liệu.
- [ ] **5.3 — FR-15, Dashboard:** số người dùng, lịch trình, nội dung công khai/ẩn và thống kê lấy từ dữ liệu thật; không số liệu giả.
- [ ] **5.4 — FR-16, quản lý người dùng:** danh sách, tìm kiếm, xử lý tài khoản theo SRS (khóa/mở khóa nếu yêu cầu); quyền Admin, CSRF và xác thực thao tác.
- [ ] **5.5 — FR-17, kiểm duyệt:** danh sách cần xử lý; ẩn/khôi phục lịch trình; xử lý nội dung/bình luận vi phạm theo SRS; chuyển trạng thái đúng; nội dung bị ẩn không lộ qua endpoint khác.
- [ ] **5.6 — FR-18, danh mục:** quản lý tags, danh mục du lịch, địa điểm nếu thuộc SRS; kiểm tra dữ liệu liên quan; không xóa thiếu an toàn danh mục đang dùng.

### GĐ6 — Kiểm thử tổng thể và bàn giao

Mục tiêu: xác minh chất lượng toàn hệ thống và khả năng bàn giao.

- [ ] **6.1 — Chức năng FR-01–FR-18:** kiểm thử luồng đăng ký → đăng nhập → tạo lịch trình → xuất bản → khám phá; Like/Bookmark/Follow; Clone/Fork; Recommendation; Admin.
- [ ] **6.2 — Bảo mật:** authentication, authorization, CSRF, SQL injection, XSS, session, upload ảnh, truy cập URL trực tiếp, IDOR/quyền sở hữu tài nguyên.
- [ ] **6.3 — Database:** schema import được trên máy mới; khóa ngoại/index hợp lý; sửa không mất dữ liệu; không chạy migration trùng; không đưa dữ liệu cá nhân lên GitHub.
- [ ] **6.4 — Giao diện:** responsive, không vỡ layout màn hình nhỏ; thông báo lỗi/thành công rõ; form dễ dùng; không nút giả; trạng thái loading/empty/error.
- [ ] **6.5 — Hiệu năng:** tránh truy vấn thừa, phân trang khi cần, không gọi API ngoài liên tục; cache ảnh/gợi ý hợp lý; không tối ưu quá mức khi chưa có số đo.
- [ ] **6.6 — Bàn giao:** README cài đặt hoàn chỉnh, SQL schema hợp lệ, hướng dẫn XAMPP, dữ liệu demo an toàn nếu cần, mã nguồn đầy đủ và checklist kiểm thử cho nhóm demo giảng viên.

### Mở rộng — Tự động tạo lịch trình

Chỉ triển khai khi các giai đoạn bắt buộc đã hoàn thành **và** người dùng yêu cầu. Nhận điểm đến, số ngày, ngân sách, sở thích để tạo bản nháp Timeline đề xuất; có thể dùng quy tắc từ địa điểm/hoạt động hiện có. Không tự tích hợp OpenAI API, Gemini hoặc dịch vụ tính phí. Đây không phải điều kiện hoàn thành FR-10.

## 6. Kiểm thử và bằng chứng

Tối thiểu cho thay đổi ứng dụng liên quan:

- PHP lint; JavaScript syntax check nếu có công cụ; `git diff --check`.
- Kiểm tra query/schema, quyền sở hữu, xác thực, CSRF và đầu vào trống/sai.
- HTTP GET và kiểm thử hồi quy các chức năng bị ảnh hưởng.
- Có thể kiểm thử thao tác ghi trong môi trường độc lập; không tự gửi POST/DELETE làm thay đổi database thật để thử nghiệm.

Báo rõ từng nhóm: **Đã kiểm thử thực tế**, **Đã kiểm tra kỹ thuật** (kể cả kiểm tra tĩnh), **Chưa kiểm thử**, **Kiểm thử thất bại**. Nêu giới hạn công cụ/môi trường; lint hoặc HTTP 200 không đủ xác nhận toàn bộ nghiệp vụ.

## 7. An toàn Git, MySQL và XAMPP

- **Git:** kiểm tra branch/status/diff trước sửa; bảo toàn thay đổi chưa commit, không ghi đè thay đổi chưa rõ nguồn gốc; không tự chuyển nhánh gây mất/xung đột thay đổi. Không sửa trực tiếp `main` cho giai đoạn mới khi chưa được đồng ý. Không tự stage/commit/push/merge nếu chưa có yêu cầu cụ thể; không dùng `git reset --hard`, `git clean`, force push.
- **MySQL:** khi kiểm tra chỉ dùng `SELECT` và `INFORMATION_SCHEMA`. Không tự xóa/thay đổi dữ liệu người dùng, không tự `ALTER`/`DROP`/`DELETE`/`UPDATE` database chính hoặc thực hiện ghi dữ liệu thật để kiểm thử. Chỉ thay đổi database thật sau khi được phê duyệt. Nếu cần schema mới, trình bày SQL tối thiểu và lý do; tiếp tục phần code độc lập an toàn trong lúc chờ. Không chạy lại migration đã áp dụng.
- **XAMPP:** không tự Start/Stop/Restart Apache, MySQL hoặc XAMPP. Khi đồng bộ code ứng dụng, so sánh diff nguồn/đích, chỉ chép phần thay đổi đã kiểm tra, bảo toàn cấu hình và uploads cục bộ; không ghi đè khác biệt chưa rõ nguồn gốc. Tuân thủ quyền ghi của môi trường, xin quyền khi cần. File hướng dẫn ở repository root không cần đồng bộ vào thư mục chạy ứng dụng.
- **Phạm vi khác:** không tự sửa HTTPS/SSL OpenStreetMap khi chưa được yêu cầu; không tạo thêm backup/report trong repository hoặc file phụ không cần thiết; không đưa mật khẩu/secret/dữ liệu cá nhân lên GitHub.
- **Khi cần xin phép:** nguy cơ mất dữ liệu, sửa schema database chính, thay đổi cấu hình hệ thống, ghi đè thay đổi Git chưa rõ nguồn gốc hoặc hành động không thể hoàn tác. Các bước lập trình an toàn thông thường thực hiện trong phạm vi đã được giao; tôn trọng giới hạn quyền của môi trường.

## 8. Báo cáo sau lượt phát triển/kiểm tra tiến độ

Báo cáo ngắn gọn bằng tiếng Việt trong hội thoại, không tạo file báo cáo riêng. Dùng bảng cho cả 6 giai đoạn; mỗi ô trước/sau thể hiện cặp `% triển khai / % xác minh` hoặc `Chưa đủ bằng chứng`.

| Giai đoạn | Trước lượt này (triển khai / xác minh) | Sau lượt này (triển khai / xác minh) | Mức xác minh | Trạng thái / còn thiếu |
|---|---|---|---|---|
| GĐ1 | … | … | … | … |
| GĐ2 | … | … | … | … |
| GĐ3 | … | … | … | … |
| GĐ4 | … | … | … | … |
| GĐ5 | … | … | … | … |
| GĐ6 | … | … | … | … |

Sau bảng, nêu:

1. Công việc đã hoàn thành: chức năng thực sự vừa sửa/xây dựng hoặc kết quả rà soát.
2. File đã thay đổi: chỉ file thực sự chỉnh sửa.
3. Kết quả kiểm thử: phân biệt kỹ thuật/tĩnh với thao tác thực tế, chưa thử và thất bại.
4. Công việc còn lại: tiêu chí chưa đạt theo ưu tiên.
5. Việc người dùng cần làm: chỉ kiểm thử trực tiếp hoặc quyết định quan trọng thật sự cần.
6. Lệnh đề xuất tiếp theo, ví dụ `Tiếp tục Giai đoạn 2` hoặc `Bắt đầu Giai đoạn 3`.

Với lượt chỉ cập nhật hướng dẫn, không tự bắt đầu giai đoạn hoặc đưa ra tỷ lệ tiến độ chưa được đánh giá.
