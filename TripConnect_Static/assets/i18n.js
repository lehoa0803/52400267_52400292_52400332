// assets/i18n.js
const translations = {
  vi: {
    "nav.home": "Trang chủ",
    "nav.explore": "Khám phá",
    "nav.feed": "Bảng tin",
    "nav.profile": "Hồ sơ",
    "nav.login": "Đăng nhập",
    "nav.create_trip": "Tạo lịch trình",
    "nav.language": "Ngôn ngữ",
    
    "hero.badge": "Cộng đồng du lịch & lập lịch trình",
    "hero.title": "Đi đâu cũng được,<br>miễn là có lịch trình.",
    "hero.desc": "Khám phá lịch trình thực tế, lưu lại chuyến đi yêu thích và tự tạo hành trình của riêng bạn.",
    "hero.placeholder": "Bạn muốn đi đâu?",
    "hero.location": "Địa điểm",
    "hero.budget": "Ngân sách",
    "hero.search_btn": "Tìm kiếm",
    "hero.featured_title": "Trip nổi bật hôm nay",
    "hero.trip_name_1": "Đà Nẵng – Hội An tự túc",
    "hero.trip_desc_1": "4 ngày 3 đêm · ~4,5 triệu · 248 lượt thích",
    
    "kpi.community": "Cộng đồng",
    "kpi.community_desc": "thành viên du lịch",
    "kpi.trips": "Lịch trình",
    "kpi.trips_desc": "hành trình đã chia sẻ",
    "kpi.locations": "Địa điểm",
    "kpi.locations_desc": "điểm đến nổi bật",
    "kpi.interactions": "Tương tác",
    "kpi.interactions_desc": "lượt thích & lưu",

    "section.suggest": "Gợi ý cho bạn",
    "section.title": "Lịch trình nổi bật",
    "section.view_all": "Xem tất cả",

    "trip.danang_badge": "4 ngày 3 đêm",
    "trip.danang_title": "Đà Nẵng – Hội An tự túc",
    "trip.danang_place": "Đà Nẵng, Hội An",
    "trip.danang_desc": "Lịch trình biển, phố cổ, ẩm thực và các điểm check-in nổi bật.",
    "trip.danang_detail": "Xem chi tiết",

    "trip.dalat_badge": "3 ngày 2 đêm",
    "trip.dalat_title": "Đà Lạt chill cuối tuần",
    "trip.dalat_place": "Đà Lạt, Lâm Đồng",
    "trip.dalat_desc": "Cà phê, săn mây, homestay và lịch trình nhẹ nhàng cho nhóm bạn.",
    "trip.dalat_detail": "Xem chi tiết",

    "trip.phuquoc_badge": "4 ngày",
    "trip.phuquoc_title": "Phú Quốc – nghỉ dưỡng",
    "trip.phuquoc_place": "Phú Quốc, Kiên Giang",
    "trip.phuquoc_desc": "Biển xanh, resort, hoàng hôn và các trải nghiệm thư giãn.",
    "trip.phuquoc_detail": "Xem chi tiết",

    "card.create_title": "Tạo lịch trình",
    "card.create_desc": "Xây dựng timeline theo từng ngày, địa điểm, thời gian và chi phí.",
    "card.create_link": "Bắt đầu tạo →",

    "card.budget_title": "Theo dõi ngân sách",
    "card.budget_desc": "Tổng hợp chi phí ăn uống, di chuyển, lưu trú và trải nghiệm.",
    "card.budget_link": "Xem demo →",

    "card.community_title": "Kết nối cộng đồng",
    "card.community_desc": "Like, comment, bookmark, follow và cập nhật từ những người bạn theo dõi.",
    "card.community_link": "Mở bảng tin →",

    "footer.desc": "Chia sẻ trải nghiệm, khám phá lịch trình và tạo chuyến đi theo cách của bạn.",
    "footer.explore_title": "Khám phá",
    "footer.explore_1": "Lịch trình nổi bật",
    "footer.explore_2": "Bảng tin",
    "footer.info_title": "Thông tin",
    "footer.info_desc": "Demo FrontEnd — dữ liệu hiện đang là mock data, sẵn sàng nối PHP/MySQL ở bước Backend."
  },
  en: {
    "nav.home": "Home",
    "nav.explore": "Explore",
    "nav.feed": "Feed",
    "nav.profile": "Profile",
    "nav.login": "Login",
    "nav.create_trip": "Create Trip",
    "nav.language": "Language",

    
    "hero.badge": "Travel Community & Itinerary Planner",
    "hero.title": "Go anywhere,<br>as long as you have a plan.",
    "hero.desc": "Discover real travel itineraries, save your favorite trips, and create your own journey.",
    "hero.placeholder": "Where do you want to go?",
    "hero.location": "Location",
    "hero.budget": "Budget",
    "hero.search_btn": "Search",
    "hero.featured_title": "Featured Trip Today",
    "hero.trip_name_1": "Da Nang – Hoi An Self-sufficient",
    "hero.trip_desc_1": "4 days 3 nights · ~4.5M VND · 248 likes",
    
    "kpi.community": "Community",
    "kpi.community_desc": "travel members",
    "kpi.trips": "Itineraries",
    "kpi.trips_desc": "shared journeys",
    "kpi.locations": "Locations",
    "kpi.locations_desc": "featured spots",
    "kpi.interactions": "Interactions",
    "kpi.interactions_desc": "likes & bookmarks",

    "section.suggest": "Recommended for you",
    "section.title": "Featured Itineraries",
    "section.view_all": "View all",

    "trip.danang_badge": "4 days 3 nights",
    "trip.danang_title": "Da Nang – Hoi An Self-sufficient",
    "trip.danang_place": "Da Nang, Hoi An",
    "trip.danang_desc": "Beach schedule, ancient town, culinary and top check-in spots.",
    "trip.danang_detail": "View details",

    "trip.dalat_badge": "3 days 2 nights",
    "trip.dalat_title": "Da Lat Weekend Chill",
    "trip.dalat_place": "Da Lat, Lam Dong",
    "trip.dalat_desc": "Coffee, cloud hunting, homestays and relaxing trips for friends.",
    "trip.dalat_detail": "View details",

    "trip.phuquoc_badge": "4 days",
    "trip.phuquoc_title": "Phu Quoc – Resort Getaway",
    "trip.phuquoc_place": "Phu Quoc, Kien Giang",
    "trip.phuquoc_desc": "Blue sea, resorts, sunset and relaxing experiences.",
    "trip.phuquoc_detail": "View details",

    "card.create_title": "Create Itinerary",
    "card.create_desc": "Build daily timelines, locations, times and estimated costs.",
    "card.create_link": "Start creating →",

    "card.budget_title": "Track Budget",
    "card.budget_desc": "Synthesize expenses for food, transportation, accommodation and experiences.",
    "card.budget_link": "View demo →",

    "card.community_title": "Community Connection",
    "card.community_desc": "Like, comment, bookmark, follow and stay updated from people you follow.",
    "card.community_link": "Open feed →",

    "footer.desc": "Share experiences, explore itineraries and create trips your own way.",
    "footer.explore_title": "Explore",
    "footer.explore_1": "Featured Itineraries",
    "footer.explore_2": "Newsfeed",
    "footer.info_title": "Information",
    "footer.info_desc": "FrontEnd Demo — mock data, ready for PHP/MySQL backend integration."
  }
};

function changeLanguage(lang) {
  localStorage.setItem("tripconnect_lang", lang);
  document.querySelectorAll("[data-i18n]").forEach(element => {
    const key = element.getAttribute("data-i18n");
    if (translations[lang] && translations[lang][key]) {
      if (element.tagName === "INPUT" || element.tagName === "TEXTAREA") {
        element.placeholder = translations[lang][key];
      } else {
        element.innerHTML = translations[lang][key];
      }
    }
  });

  document.querySelectorAll("[data-lang-btn]").forEach(btn => {
    if (btn.getAttribute("data-lang-btn") === lang) {
      btn.classList.add("active", "fw-bold");
    } else {
      btn.classList.remove("active", "fw-bold");
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  const savedLang = localStorage.getItem("tripconnect_lang") || "vi";
  changeLanguage(savedLang);

  document.querySelectorAll("[data-lang-btn]").forEach(btn => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const lang = btn.getAttribute("data-lang-btn");
      changeLanguage(lang);
    });
  });
});