document.addEventListener("DOMContentLoaded", function () {
  var budgetData = [
    { label: "Ăn uống", value: 1400000, color: "#e85a45" },
    { label: "Di chuyển", value: 900000, color: "#eda247" },
    { label: "Lưu trú", value: 1500000, color: "#0f6e66" },
    { label: "Trải nghiệm", value: 700000, color: "#128077" }
  ];
  var ctx = document.getElementById("budgetChart");
  if (ctx && window.Chart) {
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: budgetData.map(function (d) { return d.label; }),
        datasets: [{ data: budgetData.map(function (d) { return d.value; }), backgroundColor: budgetData.map(function (d) { return d.color; }), borderWidth: 0 }]
      },
      options: { plugins: { legend: { display: false } }, cutout: "68%" }
    });
  }
  var legend = document.getElementById("budgetLegend");
  budgetData.forEach(function (d) {
    var li = document.createElement("li");
    li.className = "d-flex justify-content-between align-items-center mb-1";
    li.innerHTML = '<span><span class="budget-swatch"></span>' + d.label + '</span><span class="text-muted"></span>';
    li.querySelector(".text-muted").textContent = d.value.toLocaleString("vi-VN") + "đ";
    legend.appendChild(li);
  });
  var mapEl = document.getElementById("tripMap");
  if (mapEl && window.L) {
    var map = L.map(mapEl, { scrollWheelZoom: false }).setView([16.047, 108.22], 12);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap contributors" }).addTo(map);
    var stops = [
      { name: "Homestay Sơn Trà", coords: [16.115, 108.28] },
      { name: "Chùa Linh Ứng", coords: [16.101, 108.293] },
      { name: "Trung tâm Đà Nẵng", coords: [16.047, 108.22] },
      { name: "Phố cổ Hội An", coords: [15.8801, 108.338] }
    ];
    stops.forEach(function (s) { L.marker(s.coords).addTo(map).bindPopup(s.name); });
  }
  var commentForm = document.getElementById("commentForm");
  commentForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var input = document.getElementById("commentInput");
    var text = input.value.trim();
    if (!text) return;
    var list = document.getElementById("commentList");
    var item = document.createElement("div");
    item.className = "comment";
    item.innerHTML = '<div class="d-flex gap-2"><img class="avatar" src="https://i.pravatar.cc/80?img=68" alt="Ảnh đại diện của bạn"><div><strong>Bạn</strong><div class="small text-muted">Vừa xong</div><div class="mt-1"></div></div></div>';
    item.querySelector(".mt-1").textContent = text;
    list.appendChild(item);
    input.value = "";
    var countEl = document.getElementById("commentCount");
    countEl.textContent = parseInt(countEl.textContent, 10) + 1;
  });
  document.getElementById("cloneBtn").addEventListener("click", function () { window.tcToast("Đã tạo bản nháp riêng từ lịch trình này"); });
  document.getElementById("exportBtn").addEventListener("click", function () { window.tcToast("Đang chuẩn bị file PDF (demo)"); });
});