document.getElementById("heroSearchForm").addEventListener("submit", function (e) {
  e.preventDefault();
  var q = document.getElementById("heroSearch").value.trim();
  var location = document.getElementById("heroLocation").value;
  var budget = document.getElementById("heroBudget").value;
  var params = new URLSearchParams();
  if (q) params.set("q", q);
  if (location) params.set("location", location);
  if (budget) params.set("budget", budget);
  window.location.href = "explore.php" + (params.toString() ? "?" + params.toString() : "");
});
