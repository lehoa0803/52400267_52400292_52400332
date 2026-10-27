/* ==========================================================================
   TripConnect — shared front-end behaviour (database-backed actions)
   ========================================================================== */

(function () {
  "use strict";

  // Ảnh bìa hỏng dùng placeholder cục bộ, không gọi tìm ảnh khi tải trang.
  var coverFallback = new URL('images/trip-placeholder.svg', document.currentScript.src).href;
  function replaceBrokenCover(img) {
    if (!(img instanceof HTMLImageElement) || !img.matches('.trip-img, .cover-lg, [data-trip-cover]')) return;
    if (img.dataset.coverFallback) { img.style.visibility = 'hidden'; return; }
    img.dataset.coverFallback = 'true';
    img.alt = '';
    img.src = coverFallback;
  }
  document.addEventListener('error', function (event) { replaceBrokenCover(event.target); }, true);
  document.querySelectorAll('img.trip-img, img.cover-lg, img[data-trip-cover]').forEach(function (img) {
    if (img.complete && img.naturalWidth === 0) replaceBrokenCover(img);
  });

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

    /* ---------- Footer information links ---------- */
    document.querySelectorAll('[data-i18n="footer.news"], [data-i18n="footer.sitemap"]').forEach(function (link) {
      link.remove();
    });
    document.querySelectorAll('[data-i18n="footer.help"]').forEach(function (link) {
      link.href = "help.php";
    });
    document.querySelectorAll('[data-i18n="footer.privacy"]').forEach(function (link) {
      link.href = "privacy.php";
    });
    document.querySelectorAll('[data-i18n="footer.terms"]').forEach(function (link) {
      link.remove();
    });
    document.querySelectorAll('[data-i18n="footer.data_policy"]').forEach(function (link) {
      link.href = "personal-data-policy.php";
    });
    /* ---------- Active nav link ---------- */
    var currentPage = (location.pathname.split("/").pop() || "index.php");
    document.querySelectorAll(".nav-link[data-page]").forEach(function (link) {
      if (link.getAttribute("data-page") === currentPage) {
        link.classList.add("active");
        link.setAttribute("aria-current", "page");
      }
    });

    // Update the UI only after the server confirms the committed database state.
    document.querySelectorAll('[data-social-action]').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        if (btn.disabled) return;
        var action = btn.dataset.socialAction;
        var content = '';
        if (action === 'comment_edit') {
          content = window.prompt('Sửa bình luận:', btn.closest('.comment').querySelector('[data-comment-content]').textContent);
          if (content === null) return;
        }
        if (action === 'comment_delete' && !window.confirm('Xóa bình luận này?')) return;
        var body = new URLSearchParams({action: action, target: btn.dataset.target,
          active: btn.dataset.active === '1' ? '0' : '1', content: content,
          csrf_token: document.querySelector('meta[name="social-csrf"]')?.content || ''});
        btn.disabled = true;
        try {
          var response = await fetch('trip-action.php', {method: 'POST', body: body, headers: {Accept: 'application/json'}});
          var data = await response.json();
          if (response.status === 401) { window.location.assign('login.php?next=' + encodeURIComponent(location.pathname.split('/').pop() + location.search + location.hash)); return; }
          if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể lưu thao tác.');
          if (data.redirect) {
            var destination = new URL(data.redirect, window.location.href);
            if (destination.pathname === location.pathname && destination.search === location.search) {
              window.history.replaceState(null, '', destination.href);
              window.location.reload();
            } else window.location.assign(destination.href);
            return;
          }
          btn.dataset.active = data.active ? '1' : '0';
          btn.setAttribute('aria-pressed', String(data.active));
          var icon = btn.querySelector('i');
          if (action === 'like') {
            btn.classList.toggle('is-liked', data.active);
            if (icon) icon.className = data.active ? 'bi bi-heart-fill' : 'bi bi-heart';
            if (btn.querySelector('[data-like-count]')) btn.querySelector('[data-like-count]').textContent = data.count;
          }
          if (action === 'bookmark' && icon) icon.className = data.active ? 'bi bi-bookmark-fill' : 'bi bi-bookmark';
          if (action === 'bookmark' && !data.active) {
            btn.closest('[data-bookmark-card]')?.remove();
            var savedTrips = document.getElementById('savedTrips');
            var emptySaved = savedTrips?.querySelector('[data-bookmark-empty]');
            if (emptySaved) emptySaved.hidden = !!savedTrips.querySelector('[data-bookmark-card]');
          }
          var label = btn.querySelector('[data-social-label]');
          if (label) label.textContent = action === 'like' ? (data.active ? 'Đã thích' : 'Thích')
            : action === 'bookmark' ? (data.active ? 'Đã lưu' : 'Lưu') : (data.active ? 'Đang theo dõi' : 'Theo dõi');
          document.querySelectorAll('[data-social-action="'+action+'"][data-target="'+btn.dataset.target+'"]').forEach(function(other){
            other.dataset.active=btn.dataset.active;other.setAttribute('aria-pressed',String(data.active));
            var otherLabel=other.querySelector('[data-social-label]');if(otherLabel&&label)otherLabel.textContent=label.textContent;
            var otherIcon=other.querySelector('i');if(otherIcon&&icon)otherIcon.className=icon.className;
          });
          if(action==='follow'){
            document.querySelectorAll('[data-followers-count="'+btn.dataset.target+'"]').forEach(function(el){el.textContent=data.followers;});
            document.querySelectorAll('[data-following-count="'+data.actor+'"]').forEach(function(el){el.textContent=data.following;});
            if(!data.active){
              var followingRow=btn.closest('[data-own-following-row]');
              if(followingRow){var followingList=followingRow.closest('details');followingRow.remove();followingList.querySelector('[data-follow-list-empty]').hidden=!!followingList.querySelector('[data-own-following-row]');}
            }
          }
          if(action==='bookmark')document.querySelectorAll('[data-bookmark-total]').forEach(function(el){el.textContent=data.bookmarks;});
          var status=document.getElementById('socialStatus');if(status)status.textContent='';
          window.tcToast(data.message);
        } catch (error) {
          var status = document.getElementById('socialStatus');
          if (status) status.textContent = error.message || 'Không thể kết nối máy chủ.';
          window.tcToast(error.message || 'Không thể kết nối máy chủ.');
        } finally { btn.disabled = false; }
      });
    });
    function openFollowList(){var list=document.getElementById(location.hash.slice(1));if(list&&list.tagName==='DETAILS')list.open=true;}
    openFollowList();window.addEventListener('hashchange',openFollowList);
  });
})();
