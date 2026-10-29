document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.footer').forEach(function (footer) {
    var company = footer.querySelector('.col-lg-4');
    if (!company) return;
    var contact = company.querySelector('.footer-contact');
    if (contact) {
      contact.querySelectorAll('li').forEach(function (item) {
        if (item.querySelector('a[href^="tel:"]')) item.remove();
      });
    }
    if (contact && !contact.querySelector('[data-footer-social-label]')) {
      var socialLabel = document.createElement('li');
      socialLabel.innerHTML = '<i class="bi bi-globe2"></i><span>Theo dõi chúng tôi trên:</span>';
      socialLabel.setAttribute('data-footer-social-label', 'true');
      contact.appendChild(socialLabel);
    }
    if (!company.querySelector('.footer-social')) {
      var social = document.createElement('div');
      social.className = 'footer-social';
      social.innerHTML = '<span aria-label="Instagram"><i class="bi bi-instagram"></i></span><span aria-label="Facebook"><i class="bi bi-facebook"></i></span><span aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></span><span aria-label="TikTok"><i class="bi bi-tiktok"></i></span><span aria-label="Messenger"><i class="bi bi-messenger"></i></span>';
      company.appendChild(social);
    }
    if (!company.querySelector('.btn-hotline')) {
      var hotline = document.createElement('a');
      hotline.className = 'btn-hotline';
      hotline.href = 'tel:1800646888';
      hotline.innerHTML = '<i class="bi bi-telephone-fill"></i> 1800 646 888';
      company.appendChild(hotline);
      var note = document.createElement('div');
      note.className = 'footer-note';
      note.textContent = 'Tổng đài miễn phí 24/7';
      company.appendChild(note);
    }
    footer.querySelectorAll('.footer a').forEach(function (link) {
      if (link.textContent.trim() === 'Thỏa thuận sử dụng') link.remove();
      if (link.textContent.trim() === 'Chính sách riêng tư') link.href = 'privacy.php';
      if (link.textContent.trim() === 'Chính sách bảo vệ dữ liệu cá nhân') link.href = 'personal-data-policy.php';
      if (link.textContent.trim() === 'Trợ giúp') link.href = 'help.php';
    });
  });
});
