<?php
// Nội dung biên soạn cho demo, không phải trải nghiệm của người dùng thật.
// Toàn bộ 33 chuyến có Timeline; nguồn ảnh bổ sung ở demo_wikimedia.json.
$profiles = [
    ['thuy.linh', 'Thùy Linh', ['Ẩm thực', 'Văn hóa', 'Tiết kiệm'], 'Thích những chuyến đi chậm, ghé chợ và tìm hiểu món ăn địa phương.'],
    ['van.huy', 'Trần Văn Huy', ['Biển', 'Tự túc'], 'Hay lên kế hoạch đi biển cùng bạn, dành thời gian cho những cung đường ven bờ.'],
    ['minh.anh', 'Nguyễn Minh Anh', ['Núi', 'Check-in'], 'Thích khí hậu mát, đi bộ nhẹ và chụp ảnh cảnh quan.'],
    ['gia.bao', 'Hoàng Gia Bảo', ['Khám phá', 'Văn hóa'], 'Quan tâm kiến trúc đô thị và những câu chuyện lịch sử ở mỗi nơi.'],
    ['phuong.thao', 'Phương Thảo', ['Du lịch cùng gia đình', 'Nghỉ dưỡng'], 'Ưu tiên lịch trình thoáng, có giờ nghỉ và dễ điều chỉnh khi đi cùng gia đình.'],
    ['nhat.minh', 'Lê Nhật Minh', ['Biển', 'Nghỉ dưỡng'], 'Thường dành vài ngày ở một vùng biển thay vì chạy qua nhiều điểm.'],
    ['ngoc.han', 'Ngọc Hân', ['Văn hóa', 'Tự túc'], 'Thích di sản và những khu phố có thể tự đi bộ khám phá.'],
    ['khanh.vy', 'Trần Khánh Vy', ['Núi', 'Khám phá'], 'Quan tâm cảnh quan miền núi, luôn dành thêm thời gian dự phòng cho đường đèo.'],
    ['minh.quan', 'Minh Quân', ['Mạo hiểm', 'Tự túc'], 'Thích hoạt động ngoài trời, chỉ chọn cung đường phù hợp thể lực.'],
    ['ha.phuong', 'Hà Phương', ['Nghỉ dưỡng', 'Du lịch cùng gia đình'], 'Thích chuyến đi ít đổi chỗ ở và những buổi sáng không vội.'],
    ['duc.hai', 'Đỗ Đức Hải', ['Biển', 'Ẩm thực'], 'Thích chợ hải sản, nhưng luôn hỏi giá trước khi gọi món.'],
    ['mai.chi', 'Vũ Mai Chi', ['Tiết kiệm', 'Du lịch một mình'], 'Ưu tiên phương tiện công cộng và các điểm tham quan gần nhau.'],
    ['quoc.an', 'Phạm Quốc An', ['Văn hóa', 'Khám phá'], 'Quan tâm bảo tàng, làng nghề và di tích lịch sử.'],
    ['tu.uyen', 'Tú Uyên', ['Check-in', 'Ẩm thực'], 'Thích ngồi cà phê và ngắm nhịp sống ở những thành phố nhỏ.'],
    ['bao.ngoc', 'Trần Bảo Ngọc', ['Biển', 'Sang trọng'], 'Thích kỳ nghỉ có dịch vụ tốt, ngân sách được dự trù trước.'],
    ['hoai.nam', 'Nguyễn Hoài Nam', ['Núi', 'Tiết kiệm'], 'Hay đi cùng nhóm bạn, chia rõ tiền phòng và tiền ăn.'],
    ['lan.anh', 'Lê Lan Anh', ['Du lịch cùng gia đình', 'Văn hóa'], 'Thích những điểm có không gian rộng và hoạt động cho nhiều lứa tuổi.'],
    ['thanh.tung', 'Thanh Tùng', ['Khám phá', 'Tự túc'], 'Thích miền sông nước và những hành trình ngắn cuối tuần.'],
];

// Nguồn xác nhận điểm đến, không phải nguồn báo giá hay tọa độ.
$sources = [
    'hanoi' => 'https://vietnam.travel/node/187',
    'danang' => 'https://vietnam.travel/places-to-go/south-central-coast-central-highlands/da-nang',
    'dalat' => 'https://en.sggp.org.vn/pedestrian-street-along-xuan-huong-lake-opens-every-three-final-days-of-week-post110436.html',
    'hcm' => 'https://vietnam.travel/things-to-do/7-must-see-attractions-hcmc/',
    'cantho' => 'https://vietnam.travel/node/147',
    'phuquoc' => 'https://dulich.phuquoc.gov.vn/en/choanthoi',
];
// Metadata Commons công khai, lấy từ kết quả tìm kiếm hiện có đã được Admin duyệt.
// Seeder không đọc/sửa trip cũ và không gọi API khi chạy trang công khai.
$covers = [
    'hanoi' => ['1/17/Hanoi_Montage.jpg', 'Hanoi_Montage.jpg', 'Cheong. Original uploader was Cheong Kok Chun at en.wikipedia. Later versions uploaded by Gryffindor.', 'CC BY-SA 2.0', 'by-sa/2.0'],
    'danang' => ['3/38/2024-12-04_Da_Nang_at_night.jpg', '2024-12-04_Da_Nang_at_night.jpg', 'Alexkom000', 'CC BY 4.0', 'by/4.0'],
    'dalat' => ['2/2a/Da_Lat_-_Park.jpg', 'Da_Lat_-_Park.jpg', 'P. Hughes', 'CC BY 4.0', 'by/4.0'],
    'hcm' => ['e/ec/Independence_Palace_or_Reunification_Palace_%2812110625233%29.jpg', 'Independence_Palace_or_Reunification_Palace_(12110625233).jpg', 'Clay Gilliland', 'CC BY-SA 2.0', 'by-sa/2.0'],
    'cantho' => ['b/b5/Can_Tho_City_-_Stadt_aus_Sicht_vom_Boot.jpg', 'Can_Tho_City_-_Stadt_aus_Sicht_vom_Boot.jpg', 'Franzfoto', 'CC BY-SA 3.0', 'by-sa/3.0'],
    'phuquoc' => ['8/8d/An_Thoi_fishing_harbour_Sunset_Town_Sun_World_Phu_Quoc_Vietnam.jpg', 'An_Thoi_fishing_harbour_Sunset_Town_Sun_World_Phu_Quoc_Vietnam.jpg', 'Vivu Vietnam', 'CC BY-SA 4.0', 'by-sa/4.0'],
];
foreach ($covers as $key => $c) {
    $covers[$key] = ['image_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/' . $c[0] . '/960px-' . basename($c[0]),
        'source_url' => 'https://commons.wikimedia.org/wiki/File:' . $c[1], 'attribution' => $c[2], 'license' => $c[3],
        'license_url' => 'https://creativecommons.org/licenses/' . $c[4], 'reviewed_at' => '2026-10-24'];
}

// Tuple: giờ đầu/cuối, tên, địa điểm, loại, tiền VND, phạm vi, ghi chú.
// +1 ở giờ cuối = qua đêm. Giá đều là dự toán demo, không phải bảng giá niêm yết.
$trips = [
 ['key'=>'hanoi-phoco', 'author'=>'thuy.linh', 'area'=>'Hà Nội', 'cover'=>'hanoi', 'title'=>'Hà Nội 3 ngày, ăn quanh phố cổ và ghé Văn Miếu', 'people'=>2, 'budget'=>5500000, 'tags'=>['am-thuc','tu-tuc'],
  'description'=>'Lịch trình dành cho hai người đã có mặt tại Hà Nội, ưu tiên đi bộ và gọi xe cho chặng xa. Buổi đầu dành cho khu Hồ Hoàn Kiếm, ngày tiếp theo đổi sang Văn Miếu và Hoàng thành. Ngày cuối để nhẹ nhàng quanh Hồ Tây trước khi kết thúc chuyến đi.',
  'days'=>[
   ['Hồ Hoàn Kiếm và phố cổ',
    ['08:00','08:30','Ăn sáng trước khi dạo hồ','Khu vực Hồ Hoàn Kiếm','food',50000,'per_person','Dự trù một bát phở và nước; chọn quán tại chỗ, không gán tên quán chưa xác minh.'],
    ['09:00','11:00','Đi bộ quanh Hồ Hoàn Kiếm','Hồ Hoàn Kiếm','sightseeing',0,'fixed','Chỉ đi không gian công cộng ven hồ; không bao gồm vé đền Ngọc Sơn.'],
    ['11:30','13:00','Ăn trưa và nghỉ chân trong phố cổ','Khu vực phố cổ Hà Nội','food',100000,'per_person','Ước tính bún chả và đồ uống, nghỉ tránh nắng trước buổi chiều.'],
    ['14:00','17:00','Dạo phố cổ, cà phê và bữa tối sớm','Khu vực Hàng Đào – Hàng Buồm','food',180000,'per_person','Dự trù cà phê, bữa tối và nước; không tính mua sắm tùy ý.'],
    ['18:00','07:00+1','Nghỉ đêm gần phố cổ','Khu vực phố cổ Hà Nội','lodging',650000,'fixed','Dự toán một phòng đôi một đêm, chưa đặt khách sạn cụ thể.'],
   ],
   ['Văn Miếu và Hoàng thành',
    ['07:30','08:30','Ăn sáng và đi xe tới Văn Miếu','Khu vực Quốc Tử Giám','food',70000,'per_person','Tiền ăn sáng; tiền xe gộp riêng ở hoạt động di chuyển cuối chuyến.'],
    ['09:00','11:00','Tham quan Văn Miếu','Văn Miếu – Quốc Tử Giám','sightseeing',100000,'per_person','Dự phòng vé, xác nhận biểu phí và giờ mở cửa trước khi đi.'],
    ['11:30','13:00','Ăn trưa và nghỉ ở khu Điện Biên Phủ','Khu vực đường Điện Biên Phủ, Hà Nội','food',100000,'per_person','Chừa thời gian ăn và nghỉ, không di chuyển sang điểm xa trong giờ trưa.'],
    ['14:00','16:30','Tham quan khu trung tâm Hoàng thành','Hoàng thành Thăng Long','sightseeing',100000,'per_person','Dự phòng tiền vé; hỏi khu vực mở tham quan, không giả định mọi công trình đều mở.'],
    ['17:30','19:00','Ăn tối gần chỗ nghỉ','Khu vực phố cổ Hà Nội','food',150000,'per_person','Chọn bữa tối theo khẩu vị, hỏi giá trước.'],
    ['19:30','07:00+1','Nghỉ đêm thứ hai','Khu vực phố cổ Hà Nội','lodging',650000,'fixed','Cùng phòng đôi đêm trước, không tính lại tiền đặt cọc.'],
   ],
   ['Buổi sáng bên Hồ Tây',
    ['07:30','08:30','Ăn sáng, trả phòng và gửi hành lý','Khu vực phố cổ Hà Nội','food',60000,'per_person','Thỏa thuận gửi hành lý trước; không tính thêm đêm lưu trú.'],
    ['09:00','11:00','Dạo ven Hồ Tây','Đường Thanh Niên, Hồ Tây','free_time',0,'fixed','Dạo không gian công cộng, không đặt hoạt động dưới nước.'],
    ['11:30','13:00','Ăn trưa trước khi kết thúc','Khu vực Trúc Bạch','food',120000,'per_person','Dự toán bữa trưa cho hai người, không khẳng định giá của quán cụ thể.'],
    ['13:30','14:30','Lấy hành lý và tổng kết tiền xe nội thành','Khu vực phố cổ Hà Nội','transport',500000,'fixed','Khoản xe cả nhóm cho ba ngày, tính đúng một lần; chuyến kết thúc tại Hà Nội, không gồm vé đến/đi Hà Nội.'],
   ],
  ]],
 ['key'=>'danang-banbe', 'author'=>'van.huy', 'area'=>'Đà Nẵng', 'cover'=>'danang', 'title'=>'Đà Nẵng 3 ngày cùng bạn, biển Mỹ Khê và sông Hàn', 'people'=>4, 'budget'=>8500000, 'tags'=>['bien','am-thuc','tu-tuc'],
  'description'=>'Nhóm bốn người bắt đầu chuyến đi khi đã đến Đà Nẵng. Lịch dành nhiều thời gian cho biển và các điểm trong thành phố, không ghép thêm Hội An để tránh chạy xe quá nhiều. Những khoảng nghỉ giữa trưa có thể đổi theo thời tiết và tình trạng biển.',
  'days'=>[
   ['Làm quen thành phố biển',
    ['08:00','09:00','Ăn sáng sau khi đến Đà Nẵng','Khu vực Mỹ Khê','food',60000,'per_person','Ước tính mì Quảng và nước; hành trình không bao gồm vé máy bay.'],
    ['09:30','11:00','Đi dạo bãi biển','Bãi biển Mỹ Khê','free_time',0,'fixed','Đi bộ ven biển, chỉ xuống nước khi có cứu hộ và điều kiện an toàn.'],
    ['11:30','13:00','Ăn trưa và nghỉ mát','Khu vực Phước Mỹ','food',100000,'per_person','Dự toán cơm hoặc bún cho nhóm, tránh di chuyển ngoài nắng trưa.'],
    ['15:00','18:00','Dạo sông Hàn và ăn tối','Bờ đông sông Hàn, khu vực cầu Rồng','food',180000,'per_person','Kinh phí bữa tối và nước, đi bộ ven sông miễn phí; không cam kết lịch phun lửa.'],
    ['19:00','07:00+1','Nghỉ hai phòng gần biển','Khu vực Mỹ Khê','lodging',1100000,'fixed','Hai phòng đôi cho cả nhóm trong một đêm, chưa chọn khách sạn.'],
   ],
   ['Văn hóa Chăm và nhịp chợ',
    ['07:30','08:30','Ăn sáng trước khi qua sông','Khu vực Mỹ Khê','food',60000,'per_person','Bữa sáng đơn giản, tiền xe tính ở gói nội thành cuối chuyến.'],
    ['09:00','11:00','Xem điêu khắc Chăm','Bảo tàng Điêu khắc Chăm Đà Nẵng','sightseeing',100000,'per_person','Mức dự phòng vé, cần tra lại giờ và giá chính thức trước ngày đi.'],
    ['11:30','13:30','Ăn trưa và nghỉ ở trung tâm','Khu vực đường Trần Phú, Đà Nẵng','food',110000,'per_person','Để khoảng nghỉ dài trước khi dạo chợ.'],
    ['14:30','18:00','Dạo chợ Hàn rồi ăn tối','Chợ Hàn và khu vực lân cận','food',180000,'per_person','Tiền ăn tối và đồ uống; mua quà là khoản tùy chọn ngoài dự toán.'],
    ['19:00','07:00+1','Nghỉ đêm thứ hai gần biển','Khu vực Mỹ Khê','lodging',1100000,'fixed','Giữ hai phòng để cả nhóm không phải chuyển hành lý.'],
   ],
   ['Buổi sáng thong thả',
    ['07:30','08:30','Ăn sáng và chuẩn bị hành lý','Khu vực Mỹ Khê','food',60000,'per_person','Sắp đồ sớm, thống nhất giờ trả phòng.'],
    ['09:00','10:30','Cà phê nhìn ra biển','Khu vực đường Võ Nguyên Giáp','food',70000,'per_person','Chưa chọn quán, dự toán đồ uống, không dựng tên thương hiệu.'],
    ['11:00','12:30','Ăn trưa trước khi rời thành phố','Khu vực Mỹ Khê','food',120000,'per_person','Bữa cuối gọn nhẹ, tránh đặt tham quan sát giờ kết thúc.'],
    ['13:00','14:00','Trả phòng và tổng kết tiền xe','Khu vực Mỹ Khê','transport',1000000,'fixed','Tổng xe nội thành ba ngày cho bốn người, không nhân tiếp theo số người và không gồm vé liên tỉnh.'],
   ],
  ]],
 ['key'=>'dalat-cham', 'author'=>'minh.anh', 'area'=>'Đà Lạt', 'cover'=>'dalat', 'title'=>'Đà Lạt 3 ngày 2 đêm, đi chậm quanh hồ Xuân Hương', 'people'=>2, 'budget'=>6000000, 'tags'=>['check-in','tu-tuc'],
  'description'=>'Chuyến đi hai người tập trung ở trung tâm Đà Lạt, phù hợp khi muốn nghỉ hơn là săn thật nhiều điểm. Hồ Xuân Hương và quảng trường được dành hai thời điểm khác nhau để cảm nhận nhịp phố. Lịch không có săn mây sáng sớm hoặc tour xa, và đã chừa thời gian trú mưa.',
  'days'=>[
   ['Một vòng trung tâm',
    ['08:00','09:00','Ăn sáng khi đã tới Đà Lạt','Khu vực chợ Đà Lạt','food',60000,'per_person','Bánh mì hoặc món nước, chưa tính xe liên tỉnh.'],
    ['09:30','11:00','Đi bộ một đoạn ven hồ','Hồ Xuân Hương','free_time',0,'fixed','Chọn đoạn ngắn phù hợp thời tiết, không cố đi hết hồ.'],
    ['11:30','13:30','Ăn trưa và nghỉ tránh mưa','Khu vực trung tâm Đà Lạt','food',100000,'per_person','Ước tính một bữa cơm, chừa thời gian nghỉ.'],
    ['15:00','18:00','Quảng trường Lâm Viên và bữa tối sớm','Quảng trường Lâm Viên và khu vực lân cận','food',180000,'per_person','Quảng trường công cộng miễn phí; tiền dành cho bữa tối và nước.'],
    ['19:00','07:00+1','Nghỉ phòng đôi gần trung tâm','Khu vực trung tâm Đà Lạt','lodging',750000,'fixed','Dự trù một đêm, không xác nhận dịch vụ của nơi lưu trú cụ thể.'],
   ],
   ['Buổi sáng ở ga và chiều cà phê',
    ['07:30','08:30','Ăn sáng gần chỗ nghỉ','Khu vực trung tâm Đà Lạt','food',60000,'per_person','Ăn sáng trước khi di chuyển, không tính trùng với tiền phòng.'],
    ['09:00','11:00','Tham quan kiến trúc nhà ga','Ga Đà Lạt','sightseeing',100000,'per_person','Dự phòng vé vào khu tham quan, không bao gồm vé tàu và không giả định có chuyến tàu.'],
    ['11:30','13:30','Ăn trưa, quay về nghỉ','Khu vực đường Quang Trung, Đà Lạt','food',110000,'per_person','Ăn gần tuyến tham quan để giảm vòng xe.'],
    ['15:00','18:00','Cà phê rồi ăn tối trong trung tâm','Khu vực chợ Đà Lạt','food',200000,'per_person','Chọn chỗ ngồi khi đến, tránh khẳng định tên quán hay giá niêm yết.'],
    ['19:00','07:00+1','Nghỉ đêm thứ hai','Khu vực trung tâm Đà Lạt','lodging',750000,'fixed','Giữ nguyên phòng để có một buổi chiều nghỉ thoải mái.'],
   ],
   ['Trước khi về',
    ['07:30','08:30','Ăn sáng và sắp hành lý','Khu vực trung tâm Đà Lạt','food',60000,'per_person','Kiểm tra đồ và giờ trả phòng.'],
    ['09:00','10:30','Ngắm hồ buổi sáng','Hồ Xuân Hương','free_time',0,'fixed','Không tổ chức hoạt động thuê thuyền hoặc mất phí.'],
    ['11:00','12:30','Ăn trưa trước khi kết thúc','Khu vực chợ Đà Lạt','food',120000,'per_person','Dự trù bữa ăn cuối, mua đặc sản không thuộc chi phí bắt buộc.'],
    ['13:00','14:00','Lấy hành lý, tổng hợp chi phí xe','Khu vực trung tâm Đà Lạt','transport',650000,'fixed','Tiền xe nội thành ba ngày cho cả hai người; không gồm vé đến hoặc rời Đà Lạt.'],
   ],
  ]],
 ['key'=>'saigon-kientruc', 'author'=>'gia.bao', 'area'=>'Hồ Chí Minh', 'cover'=>'hcm', 'title'=>'Hai ngày ở Sài Gòn, ghé Dinh Độc Lập và dạo trung tâm', 'people'=>1, 'budget'=>2800000, 'tags'=>['check-in','tu-tuc'],
  'description'=>'Lịch ngắn dành cho một người đã có mặt tại TP.HCM và muốn tìm hiểu khu trung tâm. Buổi sáng dành cho di tích, buổi chiều giảm nhịp để tránh nắng và mưa. Chuyến kết thúc sau bữa trưa ngày thứ hai, không ghép Củ Chi vì sẽ cần thêm một buổi riêng.',
  'days'=>[
   ['Dinh Độc Lập và phố trung tâm',
    ['07:30','08:30','Ăn sáng gần Dinh Độc Lập','Khu vực Nam Kỳ Khởi Nghĩa','food',60000,'per_person','Dự toán món nước và đồ uống, dành thời gian đi bộ tới cổng.'],
    ['09:00','11:00','Tham quan Dinh Độc Lập','Dinh Độc Lập','sightseeing',120000,'per_person','Khoản dự phòng vé, cần xem lại loại vé và giờ mở cửa chính thức.'],
    ['11:30','13:30','Ăn trưa và nghỉ trong trung tâm','Khu vực đường Pasteur, TP.HCM','food',120000,'per_person','Không lên lịch ngoài trời vào giờ nắng mạnh.'],
    ['14:30','18:00','Dạo đường sách, phố đi bộ và ăn tối','Đường sách Nguyễn Văn Bình – phố đi bộ Nguyễn Huệ','food',200000,'per_person','Tiền ăn tối, cà phê và nước; đi bộ không mất vé, không tính tiền mua sách.'],
    ['19:00','07:00+1','Nghỉ một đêm gần trung tâm','Khu vực trung tâm TP.HCM','lodging',800000,'fixed','Dự trù một phòng cho một người, chưa chỉ định khách sạn.'],
   ],
   ['Nhịp chợ và bờ sông',
    ['07:30','08:30','Ăn sáng rồi trả phòng','Khu vực trung tâm TP.HCM','food',60000,'per_person','Gửi hành lý theo thỏa thuận trước khi đi tiếp.'],
    ['09:00','10:30','Dạo chợ Bến Thành','Chợ Bến Thành','free_time',0,'fixed','Chỉ tham quan, mua sắm cá nhân ngoài dự toán.'],
    ['11:00','12:30','Ăn trưa gần khu bến Bạch Đằng','Khu vực bến Bạch Đằng','food',150000,'per_person','Dự toán bữa trưa, không đặt dịch vụ tàu trên sông.'],
    ['13:00','14:00','Nhận hành lý và tổng kết tiền xe','Khu vực trung tâm TP.HCM','transport',350000,'fixed','Tổng tiền xe hai ngày cho một người, không gồm vé liên tỉnh.'],
   ],
  ]],
 ['key'=>'cantho-songnuoc', 'author'=>'phuong.thao', 'area'=>'Cần Thơ', 'cover'=>'cantho', 'title'=>'Cần Thơ cuối tuần, dạo Ninh Kiều và đi chợ nổi sớm', 'people'=>3, 'budget'=>5800000, 'tags'=>['am-thuc','tu-tuc'],
  'description'=>'Gia đình ba người bắt đầu tại Cần Thơ, nghỉ một đêm để có thể đi chợ nổi vào sáng hôm sau. Ngày đầu giữ lịch nhẹ ở Ninh Kiều, không ghép vườn trái cây cách xa trung tâm. Chuyến thuyền cần đặt trước với đơn vị có áo phao và xác nhận tình hình chợ, thời tiết.',
  'days'=>[
   ['Dành chiều cho Ninh Kiều',
    ['09:00','10:00','Ăn sáng muộn tại trung tâm','Khu vực Ninh Kiều, Cần Thơ','food',60000,'per_person','Gia đình đã tới thành phố; vé xe đến Cần Thơ không nằm trong dự toán.'],
    ['10:30','11:30','Dạo bến Ninh Kiều','Bến Ninh Kiều','free_time',0,'fixed','Đi bộ, không mua tour ngay khi chưa kiểm tra điều kiện an toàn.'],
    ['12:00','14:00','Ăn trưa và nghỉ mát','Khu vực Ninh Kiều, Cần Thơ','food',100000,'per_person','Ưu tiên bữa ăn dễ dùng cho cả gia đình.'],
    ['16:00','18:30','Dạo bờ sông và ăn tối','Bến Ninh Kiều và khu vực lân cận','food',180000,'per_person','Dự toán bữa tối, nước; về sớm để chuẩn bị dậy đi chợ nổi.'],
    ['19:00','04:30+1','Nghỉ phòng gia đình','Khu vực Ninh Kiều, Cần Thơ','lodging',1000000,'fixed','Một phòng ba người hoặc phương án tương đương, cần xác nhận sức chứa khi đặt.'],
   ],
   ['Chợ nổi buổi sớm',
    ['05:00','08:00','Đi thuyền tham quan chợ nổi','Bến Ninh Kiều – chợ nổi Cái Răng','transport',1000000,'fixed','Ước tính thuê thuyền riêng cả nhóm, xác nhận giá và áo phao; không khẳng định mật độ thuyền thực tế.'],
    ['08:30','09:30','Ăn sáng sau khi lên bờ','Khu vực Ninh Kiều, Cần Thơ','food',70000,'per_person','Ăn trên bờ, không tính trùng với tour thuyền không kèm ăn.'],
    ['10:00','11:30','Nghỉ lại và thu xếp hành lý','Khu vực Ninh Kiều, Cần Thơ','rest',0,'fixed','Sử dụng phòng đã trả tiền đêm trước trong giờ trả phòng thông thường; xác nhận với nơi lưu trú.'],
    ['12:00','13:30','Ăn trưa trước khi về','Khu vực Ninh Kiều, Cần Thơ','food',120000,'per_person','Chọn bữa trưa gọn, kết thúc hành trình tại thành phố.'],
    ['14:00','14:30','Tổng kết xe nội thành','Khu vực Ninh Kiều, Cần Thơ','transport',400000,'fixed','Khoản xe trên bờ cho hai ngày, không trùng tiền thuyền hoặc vé liên tỉnh.'],
   ],
  ]],
 ['key'=>'phuquoc-nghiduong', 'author'=>'nhat.minh', 'area'=>'Phú Quốc', 'cover'=>'phuquoc', 'title'=>'Phú Quốc 4 ngày, nghỉ ở Dương Đông và dành một ngày xuống An Thới', 'people'=>2, 'budget'=>14000000, 'tags'=>['bien','am-thuc'],
  'description'=>'Chuyến hai người bắt đầu khi đã đến Phú Quốc, ở một chỗ trong ba đêm để không phải chuyển hành lý. Hai ngày đầu dành cho vùng Dương Đông, chỉ gom chuyến đi nam đảo vào ngày thứ ba. Không có tour đảo hoặc cáp treo bắt buộc, nên lịch dễ đổi khi thời tiết xấu.',
  'days'=>[
   ['Đến Dương Đông và nghỉ ngơi',
    ['09:00','10:00','Ăn sáng sau khi đến đảo','Khu vực Dương Đông, Phú Quốc','food',90000,'per_person','Chưa bao gồm vé máy bay hoặc phà đến đảo.'],
    ['10:30','11:30','Dạo ven biển gần chỗ ở','Khu vực Bãi Trường, Phú Quốc','free_time',0,'fixed','Chỉ chọn đoạn bờ biển công cộng mở cửa, không vào khu riêng của resort.'],
    ['12:00','14:00','Ăn trưa và nghỉ','Khu vực Dương Đông, Phú Quốc','food',180000,'per_person','Dự toán bữa trưa, xác nhận giá trước khi gọi món.'],
    ['16:00','19:00','Dạo trung tâm rồi ăn tối','Khu vực chợ đêm Phú Quốc, Dương Đông','food',300000,'per_person','Kinh phí bữa tối và nước, không phải bảng giá hải sản.'],
    ['20:00','07:30+1','Nghỉ đêm đầu ở Dương Đông','Khu vực Dương Đông, Phú Quốc','lodging',1800000,'fixed','Một phòng đôi, giá dự kiến chưa đặt; không khẳng định hạng sao.'],
   ],
   ['Một ngày không chạy xa',
    ['08:00','09:00','Ăn sáng thong thả','Khu vực Dương Đông, Phú Quốc','food',90000,'per_person','Chọn giá phòng chưa gồm bữa sáng để tránh tính trùng.'],
    ['09:30','11:00','Đi bộ ven biển','Khu vực Bãi Trường, Phú Quốc','free_time',0,'fixed','Không thuê thiết bị thể thao biển; quan sát biển báo an toàn.'],
    ['11:30','14:00','Ăn trưa và nghỉ dài','Khu vực Dương Đông, Phú Quốc','food',180000,'per_person','Để trống thời gian nghỉ, không ghép điểm bắc đảo.'],
    ['16:00','19:00','Cà phê, ngắm chiều và ăn tối','Khu vực Dương Đông, Phú Quốc','food',350000,'per_person','Dự toán đồ uống và bữa tối, ngắm cảnh tùy thời tiết.'],
    ['20:00','07:00+1','Nghỉ đêm thứ hai','Khu vực Dương Đông, Phú Quốc','lodging',1800000,'fixed','Giữ phòng, không tính tiền thuê cả nhóm theo đầu người.'],
   ],
   ['Dành trọn ngày cho nam đảo',
    ['07:30','08:30','Ăn sáng trước khi xuống An Thới','Khu vực Dương Đông, Phú Quốc','food',90000,'per_person','Chuẩn bị nước và dành khoảng di chuyển riêng phía sau.'],
    ['09:00','10:30','Đi xe từ Dương Đông xuống An Thới','Dương Đông – An Thới, Phú Quốc','transport',900000,'fixed','Dự toán xe cả ngày khứ hồi cho hai người, gồm chiều về, không thu tiền xe lần nữa.'],
    ['11:00','13:00','Dạo chợ An Thới và ăn trưa','Chợ An Thới, Phú Quốc','food',250000,'per_person','Địa điểm chợ có nguồn du lịch địa phương, tiền ăn chỉ là dự toán.'],
    ['14:00','15:30','Quan sát nhịp cảng từ khu công cộng','Khu vực cảng An Thới, Phú Quốc','free_time',0,'fixed','Không vào khu hạn chế, không lên tàu, không mua vé đảo hay cáp treo.'],
    ['16:00','19:00','Trở về Dương Đông và ăn tối','An Thới – Dương Đông, Phú Quốc','food',250000,'per_person','Chừa khoảng 90 phút đường về; chi phí ở đây chỉ tính bữa tối, xe đã tính buổi sáng.'],
    ['20:00','07:30+1','Nghỉ đêm cuối','Khu vực Dương Đông, Phú Quốc','lodging',1800000,'fixed','Đêm thứ ba của cùng phòng đôi.'],
   ],
   ['Buổi sáng trước khi kết thúc',
    ['08:00','09:00','Ăn sáng và sắp đồ','Khu vực Dương Đông, Phú Quốc','food',90000,'per_person','Dành giờ kiểm tra hành lý, không xếp thêm điểm xa.'],
    ['09:30','11:00','Cà phê gần chỗ nghỉ','Khu vực Dương Đông, Phú Quốc','food',80000,'per_person','Chưa chọn quán cụ thể; để linh hoạt theo giờ trả phòng.'],
    ['11:30','13:00','Ăn trưa rồi trả phòng','Khu vực Dương Đông, Phú Quốc','food',180000,'per_person','Xác nhận giờ trả phòng hoặc gửi hành lý, không mặc định được trả muộn miễn phí.'],
    ['13:30','14:30','Tổng kết tiền xe tại Dương Đông','Khu vực Dương Đông, Phú Quốc','transport',600000,'fixed','Xe quanh Dương Đông các ngày còn lại, không trùng xe nam đảo; không bao gồm vé rời đảo.'],
   ],
  ]],
];

// Phân bổ gốc cho 27 chuyến; ghép với tuyến đầy đủ phía dưới trước khi trả dữ liệu.
$planned = [
 ['ngoc.han','Ninh Bình','Ninh Bình cuối tuần, Tràng An và Hang Múa',2],
 ['khanh.vy','Quảng Ninh','Hạ Long 3 ngày cùng gia đình',3],
 ['minh.quan','Lào Cai','Sa Pa 3 ngày, dành thời gian cho bản làng',3],
 ['hoai.nam','Hà Giang','Hà Giang 5 ngày cùng nhóm bạn có lái xe địa phương',5],
 ['khanh.vy','Sơn La','Mộc Châu 3 ngày, ngắm đồi chè và nghỉ ở bản',3],
 ['lan.anh','Hoà Bình','Mai Châu 2 ngày, đạp xe và nghỉ ở bản',2],
 ['quoc.an','Thừa Thiên Huế','Huế 3 ngày, Đại Nội và những bữa cơm nhà',3],
 ['ngoc.han','Quảng Nam','Hội An 3 ngày, phố cổ và một sáng ở biển',3],
 ['minh.quan','Quảng Bình','Phong Nha 3 ngày, chọn hang động phù hợp thể lực',3],
 ['duc.hai','Bình Định','Quy Nhơn 3 ngày, biển và món ăn buổi sáng',3],
 ['tu.uyen','Phú Yên','Phú Yên 3 ngày cùng bạn, đi chậm ven biển',3],
 ['bao.ngoc','Khánh Hòa','Nha Trang 4 ngày nghỉ dưỡng cùng gia đình',4],
 ['ha.phuong','Bình Thuận','Mũi Né 3 ngày, nghỉ biển và ngắm đồi cát',3],
 ['duc.hai','Bà Rịa - Vũng Tàu','Cuối tuần Vũng Tàu, ăn ngon và ngắm biển',2],
 ['quoc.an','Tây Ninh','Tây Ninh 2 ngày, Tòa Thánh và núi Bà Đen',2],
 ['thanh.tung','Bến Tre','Bến Tre 2 ngày, đi vườn và nghỉ bên sông',2],
 ['thanh.tung','An Giang','Châu Đốc 3 ngày, núi Sam và rừng tràm',3],
 ['bao.ngoc','Bà Rịa - Vũng Tàu','Côn Đảo 4 ngày, dành thời gian cho biển và di tích',4],
 ['mai.chi','Hà Nội','Hà Nội 2 ngày một mình với ngân sách sinh viên',2],
 ['gia.bao','Hồ Chí Minh','Sài Gòn 3 ngày, bảo tàng và khu Chợ Lớn',3],
 ['phuong.thao','Cần Thơ','Cần Thơ 3 ngày cùng gia đình, thêm một ngày Cồn Sơn',3],
 ['nhat.minh','Phú Quốc','Phú Quốc 3 ngày hai người, ưu tiên bãi biển',3],
 ['minh.anh','Đà Lạt','Đà Lạt 4 ngày cùng bạn, dành riêng một ngày ngoại ô',4],
 ['van.huy','Đà Nẵng','Đà Nẵng 2 ngày cho nhóm bạn sinh viên',2],
 ['ha.phuong','Ninh Bình','Ninh Bình 3 ngày cùng gia đình, không đi quá gấp',3],
 ['tu.uyen','Thừa Thiên Huế','Cuối tuần ở Huế, đi ăn và dạo sông Hương',2],
 ['hoai.nam','Đắk Lắk','Buôn Ma Thuột 3 ngày, cà phê và văn hóa Tây Nguyên',3],
];
$sources += [
 'ninhbinh'=>'https://dsvh.gov.vn/danh-lam-thang-canh-trang-an-tam-coc-bich-dong-2946',
 'halong'=>'https://whc.unesco.org/en/list/672/',
 'sapa'=>'https://www.vietnam.travel/places-to-go/northern-vietnam/sapa',
 'hagiang'=>'https://vietnam.travel/things-to-do/ha-giang-loop-four-day-road-trip',
 'mocchau'=>'https://vietnam.travel/things-to-do/moc-chau-eco-traveller-dream-destination',
 'maichau'=>'https://vietnam.travel/node/197',
 'hue'=>'https://vietnam.travel/things-to-do/hue-itinerary',
 'hoian'=>'https://vietnam.travel/things-to-do/top-things-to-do-hoi-an',
 'phongnha'=>'https://www.vietnam.travel/things-to-do/cave-country-phong-nha',
 'quynhon'=>'https://csdl.vietnamtourism.gov.vn/dest/?item=917',
 'phuyen'=>'https://vietnam.travel/node/1475',
 'nhatrang'=>'https://vietnam.travel/node/1339',
 'muine'=>'https://vietnam.travel/vi/things-to-do/7-ways-have-fun-mui-ne',
 'vungtau'=>'https://vietnam.travel/things-to-do/vung-tau-sightseeing-wonderland',
 'tayninh'=>'https://vietnamtourism.gov.vn/post/65957',
 'bentre'=>'https://conphung.vn/',
 'chaudoc'=>'https://vietnam.travel/node/149',
 'condao'=>'https://vietnam.travel/node/1420',
 'buonmathuot'=>'https://vietnamtourism.vn/en/index.php/news/items/18518',
];
$covers += json_decode(file_get_contents(__DIR__ . '/demo_wikimedia.json'), true, 512, JSON_THROW_ON_ERROR);
$routes = require __DIR__ . '/demo_routes.php';
if (count($routes) !== count($planned)) throw new LogicException('Route plan mismatch.');
foreach ($routes as $index=>$route) {
    [$author,$area,$title,$expectedDays] = $planned[$index];
    if (count($route['days']) !== $expectedDays) throw new LogicException('Incomplete route: ' . $route['key']);
    $days = []; $previousHub = $route['hub'];
    foreach ($route['days'] as $dayIndex=>$recipe) {
        $dayTitle = array_shift($recipe);
        $stops = array_values(array_filter($recipe, 'is_array'));
        $strings = array_values(array_filter($recipe, 'is_string'));
        $nightHub = $strings ? end($strings) : $route['hub'];
        // Một tuyến cả ngày có ăn trưa: không tạo thêm chi phí bữa trưa lần nữa.
        $wholeDay = count($stops) === 1;
        $day = [$dayTitle];
        $meal = $route['meal'];
        $day[] = ['07:30','08:15','Ăn sáng trước buổi ' . mb_strtolower($dayTitle),$previousHub,'food',$meal,'per_person','Dự toán bữa sáng, giá phòng chưa bao gồm ăn. Chưa chọn quán hoặc khẳng định giá niêm yết.' . ($dayIndex === $expectedDays-1 ? ' Trả phòng đúng giờ, gửi hành lý theo thỏa thuận; các giờ nghỉ sau đó ở khu vực chung, không mặc định giữ phòng tới tối miễn phí.' : '')];
        foreach ($stops as $stopIndex=>[$place,$activity,$cost,$note]) {
            // Vary half-day routes; preserve enough time between stops for lunch/travel.
            $start = $wholeDay ? '09:00' : ($stopIndex === 0 ? ($dayIndex % 2 ? '08:45' : '09:00') : '14:30');
            $end = $wholeDay ? '16:30' : ($stopIndex === 0 ? '11:30' : '16:30');
            if ($route['cover'] === 'hagiang' && $stopIndex === 0) {
                $start='08:30'; $end=$wholeDay?'17:00':'12:00';
            }
            $category = 'sightseeing';
            if (preg_match('/cà phê|đồ uống|bữa phụ|thử trà/iu', $activity)) $category='food';
            elseif (preg_match('/nghỉ|sắp đồ|hành lý/iu', $activity)) $category='rest';
            elseif ($cost === 0 && preg_match('/đi xe|di chuyển|trở về hà giang/iu', $activity)) $category='transport';
            elseif ($cost === 0 && preg_match('/đi bộ|dạo/iu', $activity)) $category='free_time';
            if ($category === 'rest' && $cost > 0) {
                $category='food';
                $note .= ' Khoản dự toán dành cho ăn/uống khi nghỉ, không phải phí nghỉ chân hoặc giữ phòng.';
            }
            if ($route['cover'] === 'hagiang' && $wholeDay) $category='food'; // Tiền ở chặng về là bữa trưa, xe thuộc gói trọn chuyến.
            // Chặng xe có hướng dẫn và trải nghiệm vẫn giữ mô tả riêng; tiền xe trọn chuyến ở cuối.
            $day[] = [$start,$end,$activity,$place,$category,$cost,'per_person',$note];
            if (!$wholeDay && $stopIndex === 0) {
                $day[] = ['12:00','13:30','Ăn trưa và nghỉ sau buổi ' . mb_strtolower($dayTitle),$place,'food',round($meal*1.4),'per_person','Ăn ở khu vực điểm vừa tham quan, chừa thời gian nghỉ và đi xe sang điểm chiều. Khoản dự toán, chưa chỉ định nhà hàng.'];
            }
        }
        $day[] = ['17:30','19:00','Ăn tối và nghỉ chân',$nightHub,'food',round($meal*1.8),'per_person','Dự toán một bữa tối và nước, hỏi giá trước. Không bao gồm mua sắm hoặc dịch vụ giải trí tùy chọn.'];
        if ($dayIndex < $expectedDays-1) {
            $day[] = ['19:30','07:00+1','Nghỉ đêm ' . ($dayIndex+1),$nightHub,'lodging',$route['room'],'fixed','Tiền phòng cho cả nhóm một đêm, chưa đặt cơ sở cụ thể. Giá chưa bao gồm bữa ăn, xác nhận sức chứa và giờ nhận/trả phòng.'];
        } else {
            $day[] = ['19:30','20:00','Tổng kết gói xe tại điểm đến',$nightHub,'transport',$route['transport'],'fixed','Khoản xe cho cả nhóm trong toàn chuyến, chỉ tính một lần, không phải một chuyến xe riêng lúc tổng kết. Gồm các chặng đường bộ đã mô tả; không gồm vé liên tỉnh, tour thuyền/cáp treo đã tính riêng. Lịch kết thúc buổi tối, cần bố trí chuyến về sau đó hoặc nghỉ thêm ngoài dự toán.'];
        }
        $days[] = $day; $previousHub = $nightHub;
    }
    $tags = ['tu-tuc'];
    if (in_array($route['cover'], ['sapa','hagiang','mocchau','maichau','phongnha','buonmathuot'], true)) $tags[]='nui-rung';
    elseif (in_array($route['cover'], ['halong','quynhon','phuyen','nhatrang','muine','vungtau','condao','phuquoc','danang'], true)) $tags[]='bien';
    else $tags[]='am-thuc';
    if (str_contains($route['key'],'sinhvien')) $tags[]='tiet-kiem';
    $trips[] = ['key'=>$route['key'],'author'=>$author,'area'=>$area,'cover'=>$route['cover'],'title'=>$title,
        'people'=>$route['people'],'budget'=>$route['budget'],'tags'=>$tags,'description'=>$route['description'],'days'=>$days];
}
$planned = []; // 27 kế hoạch đã có nội dung, không còn placeholder/chuyến dự kiến rỗng.
return compact('profiles', 'sources', 'covers', 'trips', 'planned');
