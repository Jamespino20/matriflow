(function () {
  const getBase = () => document.documentElement.dataset.baseUrl || '';
  const SHOW = getBase() + '/assets/images/password-show.svg';
  const HIDE = getBase() + '/assets/images/password-hide.svg';

  function setIcon(btn, isVisible) {
    const img = btn.querySelector('img');
    if (!img) return;
    img.src = isVisible ? HIDE : SHOW;
    img.alt = isVisible ? 'Hide password' : 'Show password';
  }

  function bind(btn) {
    const inputId = btn.getAttribute('data-target');
    const input = document.getElementById(inputId);
    if (!input) return;

    setIcon(btn, false);

    btn.addEventListener('click', function () {
      const makeVisible = input.type === 'password';
      input.type = makeVisible ? 'text' : 'password';
      btn.setAttribute('aria-pressed', makeVisible ? 'true' : 'false');
      setIcon(btn, makeVisible);
    });
  }

  document.querySelectorAll('[data-password-toggle]').forEach(bind);
})();

// Modal helpers (auth pages)
(function () {
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';

    // Clear any previous AJAX results when opening modal
    const ajaxResult = el.querySelector('.ajax-result');
    if (ajaxResult) {
      ajaxResult.remove();
    }

    // Clear any flash messages in forgot password modal
    if (id === 'modal-forgot') {
      const flashMsg = el.querySelector('.card');
      if (flashMsg && flashMsg.textContent.includes('Reset link')) {
        flashMsg.remove();
      }
    }
  }

  function closeModal(el) {
    el.classList.remove('show');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (e) {
    const openBtn = e.target.closest('[data-modal-open]');
    if (openBtn) {
      e.preventDefault();
      openModal(openBtn.getAttribute('data-modal-open'));
      return;
    }

    const closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) {
      e.preventDefault();
      const modal = closeBtn.closest('.modal-overlay');
      if (modal) closeModal(modal);
      return;
    }

    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
      closeModal(e.target);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    const open = document.querySelector('.modal-overlay.show');
    if (open) closeModal(open);
  });

  // auto-open via query flags
  const params = new URLSearchParams(window.location.search);
  if (params.get('forgot') === '1') openModal('modal-forgot');
  if (params.get('reset') === '1') {
    openModal('modal-reset');
    // Ensure token is set in the form if present in URL
    const token = params.get('token');
    if (token) {
      const tokenInput = document.querySelector('#modal-reset input[name="token"]');
      if (tokenInput) {
        tokenInput.value = token;
      }
    }
  }
  if (params.get('setup2fa') === '1') openModal('modal-setup2fa');
  if (params.get('verify2fa') === '1') openModal('modal-verify2fa');
})();

// AJAX submit for forgot/reset modals
(function () {
  async function handleModalFormSubmit(e) {
    const form = e.target;
    const modal = form.closest('.modal-overlay');
    if (!modal) return;
    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    const origText = submitBtn ? submitBtn.textContent : null;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Please wait...'; }

    try {
      const action = form.getAttribute('action') || window.location.href;
      const res = await fetch(action, {
        method: form.method || 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(form)
      });

      const json = await res.json();

      let container = form.querySelector('.ajax-result');
      if (!container) {
        container = document.createElement('div');
        container.className = 'card ajax-result';
        container.style.padding = '14px';
        container.style.marginTop = '12px';
        form.appendChild(container);
      }

      container.innerHTML = '';
      const help = document.createElement('div');
      help.className = 'help';
      help.textContent = json.message || (json.error || 'Unexpected response');
      container.appendChild(help);

      if (json.reset_link) {
        const linkDiv = document.createElement('div');
        linkDiv.style.marginTop = '8px';
        linkDiv.style.wordBreak = 'break-all';
        linkDiv.style.fontWeight = '800';
        linkDiv.textContent = json.reset_link;
        container.appendChild(linkDiv);
      }

      // clear sensitive fields on success (reset flow)
      const actionInput = form.querySelector('input[name="action"]');
      if (json.ok && actionInput && actionInput.value === 'resetpassword') {
        form.querySelectorAll('input[type="password"]').forEach(i => i.value = '');
        // Close modal and redirect after a short delay
        setTimeout(() => {
          const modal = form.closest('.modal-overlay');
          if (modal) {
            modal.classList.remove('show');
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
          }
          // Redirect after success (use root if login.php is gone)
          window.location.href = document.documentElement.dataset.baseUrl ? document.documentElement.dataset.baseUrl + '/../' : '/';
        }, 2000);
      }

    } catch (err) {
      let container = form.querySelector('.ajax-result');
      if (!container) {
        container = document.createElement('div');
        container.className = 'card ajax-result';
        container.style.padding = '14px';
        container.style.marginTop = '12px';
        form.appendChild(container);
      }
      container.innerHTML = '<div class="help">Network error. Please try again.</div>';
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origText; }
    }
  }

  const forgotForm = document.querySelector('#modal-forgot form');
  if (forgotForm) forgotForm.addEventListener('submit', handleModalFormSubmit);
  const resetForm = document.querySelector('#modal-reset form');
  if (resetForm) resetForm.addEventListener('submit', handleModalFormSubmit);
})();

// Handle postMessage from 2FA iframes
(function () {
  window.addEventListener('message', function (e) {
    if (e.data && e.data.type === '2fa_success') {
      // Close any open modals
      const openModal = document.querySelector('.modal-overlay.show');
      if (openModal) {
        openModal.classList.remove('show');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
      }
      // Redirect if specified
      if (e.data.redirect) {
        window.location.href = e.data.redirect;
      }
    }
  });
})();

// Session Heartbeat - Proactive session validation
// Pings server every 60 seconds to keep session alive and detect logout
(function () {
  const getBase = () => document.documentElement.dataset.baseUrl || '';
  
  // Only run on authenticated pages (pages with a dashboard layout)
  if (!document.querySelector('.dashboard-layout, .role-layout, [data-authenticated]')) {
    return;
  }

  function heartbeat() {
    fetch(getBase() + '/controllers/heartbeat-handler.php', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
      if (!data.ok) {
        // Session expired or user logged out - redirect to home
        window.location.href = getBase() + '/';
      }
    })
    .catch(() => {
      // Network error - don't redirect, just log
      console.warn('Heartbeat failed - network issue');
    });
  }

  // Initial heartbeat after 30 seconds, then every 60 seconds
  setTimeout(heartbeat, 30000);
  setInterval(heartbeat, 60000);
})();
