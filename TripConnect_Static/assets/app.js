/* ==========================================================================
   TripConnect — shared front-end behaviour (mock, no backend yet)
   ========================================================================== */

(function () {
  "use strict";

  /* ---------- Toast helper (window.tcToast) ---------- */
  function ensureToastEl() {
    var el = document.querySelector(".tc-toast");
    if (!el) {
      el = document.createElement("div");
      el.className = "tc-toast";
      el.setAttribute("role", "status");
      el.setAttribute("aria-live", "polite");
      document.body.appendChild(el);
    }
    return el;
  }

  window.tcToast = function (message) {
    var el = ensureToastEl();
    el.textContent = message;
    el.classList.add("is-visible");
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(function () {
      el.classList.remove("is-visible");
    }, 2200);
  };

  document.addEventListener("DOMContentLoaded", function () {
    function isEnglish() {
      return localStorage.getItem("tripconnect_lang") === "en";
    }
    /* ---------- Active nav link ---------- */
    var currentPage = (location.pathname.split("/").pop() || "index.php");
    document.querySelectorAll(".nav-link[data-page]").forEach(function (link) {
      if (link.getAttribute("data-page") === currentPage) {
        link.classList.add("active");
        link.setAttribute("aria-current", "page");
      }
    });

    /* ---------- Like buttons ---------- */
    document.querySelectorAll("[data-like]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var icon = btn.querySelector("i");
        var liked = btn.classList.toggle("is-liked");
        if (icon) icon.className = liked ? "bi bi-heart-fill" : "bi bi-heart";
        var countEl = btn.querySelector("[data-like-count]");
        if (countEl) {
          var n = parseInt(countEl.textContent.replace(/[^\d]/g, ""), 10) || 0;
          countEl.textContent = liked ? n + 1 : n - 1;
        }
        var label = btn.querySelector("[data-like-label]");
        if (label) label.textContent = liked ? (isEnglish() ? "Liked" : "Đã thích") : (isEnglish() ? "Like" : "Thích");
      });
    });

    /* ---------- Bookmark buttons ---------- */
    document.querySelectorAll("[data-bookmark]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var icon = btn.querySelector("i");
        var saved = btn.classList.toggle("is-saved");
        if (icon) icon.className = saved ? "bi bi-bookmark-fill" : "bi bi-bookmark";
        var label = btn.querySelector("[data-bookmark-label]");
        if (label) label.textContent = saved ? (isEnglish() ? "Saved" : "Đã lưu") : (isEnglish() ? "Save" : "Lưu");
        window.tcToast(saved ? (isEnglish() ? "Saved to collection" : "Đã lưu vào bộ sưu tập") : (isEnglish() ? "Removed from collection" : "Đã bỏ lưu"));
      });
    });

    /* ---------- Follow buttons ---------- */
    document.querySelectorAll("[data-follow]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var following = btn.getAttribute("data-following") === "true";
        following = !following;
        btn.setAttribute("data-following", following ? "true" : "false");
        btn.textContent = following ? (isEnglish() ? "Following" : "Đang theo dõi") : (isEnglish() ? "Follow" : "Theo dõi");
        btn.classList.toggle("btn-primary", !following);
        btn.classList.toggle("btn-soft", following);
      });
    });
  });
})();
