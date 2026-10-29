(function () {
  var buttons = document.querySelectorAll("#feedFilters [data-filter]");
  var posts = document.querySelectorAll("[data-feed]");
  var empty = document.getElementById("feedEmpty");
  buttons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      buttons.forEach(function (b) { b.classList.remove("btn-primary", "active"); b.classList.add("btn-light"); });
      btn.classList.remove("btn-light"); btn.classList.add("btn-primary", "active");
      var filter = btn.getAttribute("data-filter");
      var visible = 0;
      posts.forEach(function (post) {
        var show = filter === "all" || post.getAttribute("data-feed") === filter;
        post.classList.toggle("is-hidden", !show);
        if (show) visible++;
      });
      empty.style.display = visible === 0 ? "block" : "none";
    });
  });
  document.querySelectorAll(".comment-form").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var input = form.querySelector("input");
      var text = input.value.trim();
      if (!text) return;
      var list = document.getElementById(form.getAttribute("data-target"));
      var item = document.createElement("div");
      item.className = "comment";
      item.innerHTML = '<div class="d-flex gap-2"><img class="avatar" src="assets/images/default-avatar.png" alt="Ảnh đại diện của bạn"><div><strong>Bạn</strong><div class="small text-muted">Vừa xong</div><div class="mt-1"></div></div></div>';
      item.querySelector(".mt-1").textContent = text;
      list.appendChild(item);
      input.value = "";
    });
  });
})();
