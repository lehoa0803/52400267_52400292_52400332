(function () {
  var items = Array.prototype.slice.call(document.querySelectorAll(".trip-item"));
  var grid = document.getElementById("tripGrid");
  var empty = document.getElementById("exploreEmpty");
  var countEl = document.getElementById("resultCount");
  var form = document.getElementById("filterForm");
  function normalize(s) { return (s || "").toLowerCase(); }
  function updateResultCount(count) {
    var suffix = localStorage.getItem("tripconnect_lang") === "en" ? "itineraries" : "lịch trình";
    countEl.innerHTML = count + ' <span data-count-suffix>' + suffix + '</span>';
  }
  function applyFilters() {
    var q = normalize(document.getElementById("tripSearch").value);
    var loc = document.getElementById("filterLocation").value;
    var days = document.getElementById("filterDays").value;
    var budget = document.getElementById("filterBudget").value;
    var visible = 0;
    items.forEach(function (item) {
      var matchesQ = !q || normalize(item.dataset.name).indexOf(q) !== -1;
      var matchesLoc = !loc || item.dataset.location === loc;
      var matchesDays = !days || item.dataset.days === days;
      var matchesBudget = !budget || item.dataset.budget === budget;
      var show = matchesQ && matchesLoc && matchesDays && matchesBudget;
      item.classList.toggle("is-hidden", !show);
      if (show) visible++;
    });
    updateResultCount(visible);
    empty.classList.toggle("is-visible", visible === 0);
    grid.style.display = visible === 0 ? "none" : "";
  }
  var params = new URLSearchParams(window.location.search);
  if (params.get("q")) document.getElementById("tripSearch").value = params.get("q");
  if (params.get("location")) document.getElementById("filterLocation").value = params.get("location");
  if (params.get("budget")) {
    var raw = params.get("budget");
    var map = { "< 3 triệu": "low", "3–5 triệu": "mid", "> 5 triệu": "high" };
    document.getElementById("filterBudget").value = map[raw] || "";
  }
  form.addEventListener("submit", function (e) { e.preventDefault(); applyFilters(); });
  document.getElementById("tripSearch").addEventListener("input", applyFilters);
  document.getElementById("filterLocation").addEventListener("change", applyFilters);
  document.getElementById("filterDays").addEventListener("change", applyFilters);
  document.getElementById("filterBudget").addEventListener("change", applyFilters);
  applyFilters();
  document.addEventListener("tripconnect:languagechange", function (event) {
    updateResultCount(visibleCount());
  });
  function visibleCount() {
    return items.filter(function (item) { return !item.classList.contains("is-hidden"); }).length;
  }
})();
