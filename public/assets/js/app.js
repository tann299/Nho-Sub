/* NhoSub — JS thuần, không phụ thuộc thư viện. */
(function () {
  'use strict';

  /* Toast */
  function toast(message, type) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.className = 'toast';
    el.textContent = message;
    if (type === 'error') { el.style.background = '#DC2626'; }
    if (type === 'success') { el.style.background = '#16A34A'; }
    wrap.appendChild(el);
    setTimeout(function () { el.remove(); }, 3200);
  }

  /* Copy vào clipboard */
  function copy(text, doneMessage) {
    var finish = function () { toast(doneMessage || 'Đã sao chép', 'success'); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(finish);
      return;
    }
    // Fallback cho hosting http
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); finish(); } catch (e) { toast('Không sao chép được', 'error'); }
    ta.remove();
  }

  /* Sidebar mobile + thu gọn desktop */
  function initSidebar() {
    var body = document.body;
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var overlay = document.querySelector('.sidebar-overlay');
    var collapseBtn = document.querySelector('[data-sidebar-collapse]');

    // Nhiều nút cùng mở menu: nút trên topbar (desktop/mobile) + nút "Menu" ở thanh dưới.
    Array.prototype.forEach.call(toggles, function (toggle) {
      toggle.addEventListener('click', function () {
        if (window.innerWidth < 1024) {
          body.classList.toggle('sidebar-open');
        } else {
          body.classList.toggle('sidebar-collapsed');
          try {
            localStorage.setItem('nhosub.sidebar', body.classList.contains('sidebar-collapsed') ? '1' : '0');
          } catch (e) { /* chế độ riêng tư: bỏ qua */ }
        }
      });
    });

    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        body.classList.toggle('sidebar-collapsed');
        try {
          localStorage.setItem('nhosub.sidebar', body.classList.contains('sidebar-collapsed') ? '1' : '0');
        } catch (e) { /* bỏ qua */ }
      });
    }

    if (overlay) {
      overlay.addEventListener('click', function () { body.classList.remove('sidebar-open'); });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { body.classList.remove('sidebar-open'); }
    });

    try {
      if (localStorage.getItem('nhosub.sidebar') === '1' && window.innerWidth >= 1024) {
        body.classList.add('sidebar-collapsed');
      }
    } catch (e) { /* bỏ qua */ }
  }

  /* Dropdown */
  function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach(function (root) {
      var trigger = root.querySelector('[data-dropdown-trigger]');
      var menu = root.querySelector('[data-dropdown-menu]');
      if (!trigger || !menu) { return; }

      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('is-open');
        menu.style.display = open ? 'block' : 'none';
      });
    });

    document.addEventListener('click', function () {
      document.querySelectorAll('[data-dropdown-menu].is-open').forEach(function (m) {
        m.classList.remove('is-open');
        m.style.display = 'none';
      });
    });
  }

  /* Chống bấm 2 lần nút submit (lớp bảo vệ UX, server vẫn có chốt riêng) */
  function initSubmitGuard() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('[type="submit"]');
        if (btn && !btn.disabled) {
          setTimeout(function () {
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = 'Đang xử lý…';
          }, 0);
        }
      });
    });
  }

  /* Xác nhận hành động nguy hiểm */
  function initConfirm() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el) { return; }
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }

  /* Copy nội dung chuyển khoản */
  function initCopy() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-copy]');
      if (!el) { return; }
      e.preventDefault();
      copy(el.getAttribute('data-copy'), el.getAttribute('data-copy-message'));
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initDropdowns();
    initSubmitGuard();
    initConfirm();
    initCopy();
  });

  window.NhoSub = { toast: toast, copy: copy };
})();
