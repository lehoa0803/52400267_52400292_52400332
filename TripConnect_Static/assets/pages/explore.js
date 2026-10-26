(function () {
  'use strict';

  // Lọc và phân trang được xử lý ở backend để không lọc sai tập kết quả
  // đã phân trang. JavaScript chỉ giữ vai trò hỗ trợ giao diện.
  var form = document.getElementById('filterForm');
  if (!form) return;

  form.addEventListener('submit', function () {
    var page = form.querySelector('input[name="page"]');
    if (page) page.remove();
  });
}());
