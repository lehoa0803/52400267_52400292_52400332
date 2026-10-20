document.addEventListener("DOMContentLoaded", function () {
  var currentDay = 1;
  var activitiesByDay = {};
  var dayBadge = document.getElementById("dayBadge");
  var dayTabs = document.getElementById("dayTabs");
  var activityList = document.getElementById("activityList");
  var totalDays = function () { return parseInt(document.getElementById("tripDays").value, 10) || 1; };

  function renderDayTabs() {
    dayTabs.innerHTML = "";
    var n = totalDays();
    for (var i = 1; i <= n; i++) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-sm " + (i === currentDay ? "btn-primary" : "btn-light");
      btn.textContent = "Ngày " + i;
      btn.addEventListener("click", function () {
        currentDay = parseInt(this.textContent.replace(/\D/g, ""), 10);
        dayBadge.textContent = "Day " + currentDay;
        renderDayTabs();
        renderActivities();
      });
      dayTabs.appendChild(btn);
    }
  }

  function renderActivities() {
    var list = activitiesByDay[currentDay] || [];
    activityList.innerHTML = "";
    if (!list.length) {
      activityList.innerHTML = '<p class="timeline-empty mb-0">Chưa có hoạt động nào cho ngày này. Thêm hoạt động đầu tiên ở form phía trên.</p>';
      return;
    }
    list.sort(function (a, b) { return a.time.localeCompare(b.time); });
    list.forEach(function (act, idx) {
      var item = document.createElement("div");
      item.className = "timeline-item";
      item.innerHTML = '<span class="timeline-dot"></span><button type="button" class="timeline-remove" aria-label="Xoá hoạt động"><i class="bi bi-x-lg"></i></button><div class="small-label"></div><h6 class="mb-1"></h6><div class="text-muted small"><i class="bi bi-geo-alt"></i> <span class="place"></span> · <span class="cost"></span></div>';
      item.querySelector(".small-label").textContent = act.time;
      item.querySelector("h6").textContent = act.name;
      item.querySelector(".place").textContent = act.place;
      item.querySelector(".cost").textContent = (act.cost || 0).toLocaleString("vi-VN") + "đ";
      item.querySelector(".timeline-remove").addEventListener("click", function () {
        activitiesByDay[currentDay].splice(idx, 1);
        renderActivities();
      });
      activityList.appendChild(item);
    });
  }

  document.getElementById("dayPlus").addEventListener("click", function () {
    if (currentDay < totalDays()) { currentDay++; dayBadge.textContent = "Day " + currentDay; renderDayTabs(); renderActivities(); }
  });
  document.getElementById("dayMinus").addEventListener("click", function () {
    if (currentDay > 1) { currentDay--; dayBadge.textContent = "Day " + currentDay; renderDayTabs(); renderActivities(); }
  });
  document.getElementById("tripDays").addEventListener("change", renderDayTabs);
  document.getElementById("activityForm").addEventListener("submit", function (e) {
    e.preventDefault();
    var time = document.getElementById("activityTime").value;
    var name = document.getElementById("activityName").value.trim();
    var place = document.getElementById("activityPlace").value.trim();
    var cost = parseInt(document.getElementById("activityCost").value, 10) || 0;
    if (!time || !name || !place) return;
    if (!activitiesByDay[currentDay]) activitiesByDay[currentDay] = [];
    activitiesByDay[currentDay].push({ time: time, name: name, place: place, cost: cost });
    renderActivities();
    document.getElementById("chkTimeline").checked = true;
    this.reset();
    document.getElementById("activityTime").focus();
  });
  document.getElementById("coverInput").addEventListener("change", function (e) {
    var file = e.target.files[0];
    var img = document.getElementById("coverPreview");
    var placeholder = document.getElementById("previewPlaceholder");
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
      img.src = ev.target.result;
      img.classList.remove("d-none");
      placeholder.classList.add("d-none");
    };
    reader.readAsDataURL(file);
  });
  document.getElementById("tripName").addEventListener("input", function () {
    document.getElementById("chkInfo").checked = this.value.trim().length > 0;
  });
  document.getElementById("tripBudget").addEventListener("input", function () {
    document.getElementById("chkBudget").checked = this.value.trim().length > 0;
  });
  document.getElementById("saveTripBtn").addEventListener("click", function () {
    var name = document.getElementById("tripName").value.trim();
    if (!name) {
      document.getElementById("tripName").focus();
      window.tcToast("Vui lòng nhập tên chuyến đi");
      return;
    }
    document.getElementById("createAlert").classList.remove("d-none");
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
  renderDayTabs();
  renderActivities();
});