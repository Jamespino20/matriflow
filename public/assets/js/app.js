(function () {
  const getBase = () => document.documentElement.dataset.baseUrl || "";
  const SHOW = getBase() + "/assets/images/password-show.svg";
  const HIDE = getBase() + "/assets/images/password-hide.svg";

  document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-password-toggle]");
    if (!btn) return;

    e.preventDefault();
    const inputId = btn.getAttribute("data-target");
    const input = document.getElementById(inputId);
    if (!input) return;

    const makeVisible = input.type === "password";

    // Toggle Input Type
    input.type = makeVisible ? "text" : "password";

    // Toggle ARIA
    btn.setAttribute("aria-pressed", makeVisible ? "true" : "false");
    btn.setAttribute(
      "aria-label",
      makeVisible ? "Hide password" : "Show password",
    );

    // Toggle Icon
    const img = btn.querySelector("img");
    if (img) {
      img.src = makeVisible ? HIDE : SHOW;
      img.alt = makeVisible ? "Hide password" : "Show password";
    }
  });
})();

// Modal helpers (auth pages)
(function () {
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add("show");
    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";

    // Clear any previous AJAX results when opening modal
    const ajaxResult = el.querySelector(".ajax-result");
    if (ajaxResult) {
      ajaxResult.remove();
    }

    // Clear any flash messages in forgot password modal
    if (id === "modal-forgot") {
      const flashMsg = el.querySelector(".card");
      if (flashMsg && flashMsg.textContent.includes("Reset link")) {
        flashMsg.remove();
      }
    }
  }

  function closeModal(el) {
    el.classList.remove("show");
    document.documentElement.style.overflow = "";
    document.body.style.overflow = "";
  }

  document.addEventListener("click", function (e) {
    const openBtn = e.target.closest("[data-modal-open]");
    if (openBtn) {
      e.preventDefault();
      openModal(openBtn.getAttribute("data-modal-open"));
      return;
    }

    const closeBtn = e.target.closest("[data-modal-close]");
    if (closeBtn) {
      e.preventDefault();
      const modal = closeBtn.closest(".modal-overlay");
      if (modal) closeModal(modal);
      return;
    }

    if (e.target.classList && e.target.classList.contains("modal-overlay")) {
      closeModal(e.target);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    const open = document.querySelector(".modal-overlay.show");
    if (open) closeModal(open);
  });

  // auto-open via query flags
  const params = new URLSearchParams(window.location.search);
  if (params.get("forgot") === "1") openModal("modal-forgot");
  if (params.get("reset") === "1") {
    openModal("modal-reset");
    // Ensure token is set in the form if present in URL
    const token = params.get("token");
    if (token) {
      const tokenInput = document.querySelector(
        '#modal-reset input[name="token"]',
      );
      if (tokenInput) {
        tokenInput.value = token;
      }
    }
  }
  if (params.get("setup2fa") === "1") openModal("modal-setup2fa");
  if (params.get("verify2fa") === "1") openModal("modal-verify2fa");
})();

// AJAX submit for forgot/reset modals
(function () {
  async function handleModalFormSubmit(e) {
    const form = e.target;
    const modal = form.closest(".modal-overlay");
    if (!modal) return;
    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    const origText = submitBtn ? submitBtn.textContent : null;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Please wait...";
    }

    try {
      const action = form.getAttribute("action") || window.location.href;
      const res = await fetch(action, {
        method: form.method || "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        body: new FormData(form),
      });

      const json = await res.json();

      let container = form.querySelector(".ajax-result");
      if (!container) {
        container = document.createElement("div");
        container.className = "card ajax-result";
        container.style.padding = "14px";
        container.style.marginTop = "12px";
        form.appendChild(container);
      }

      container.innerHTML = "";
      const help = document.createElement("div");
      help.className = "help";
      help.textContent = json.message || json.error || "Unexpected response";
      container.appendChild(help);

      if (json.reset_link) {
        const linkDiv = document.createElement("div");
        linkDiv.style.marginTop = "8px";
        linkDiv.style.wordBreak = "break-all";
        linkDiv.style.fontWeight = "800";
        linkDiv.textContent = json.reset_link;
        container.appendChild(linkDiv);
      }

      // clear sensitive fields on success (reset flow)
      const actionInput = form.querySelector('input[name="action"]');
      if (json.ok && actionInput && actionInput.value === "resetpassword") {
        form
          .querySelectorAll('input[type="password"]')
          .forEach((i) => (i.value = ""));
        // Close modal and redirect after a short delay
        setTimeout(() => {
          const modal = form.closest(".modal-overlay");
          if (modal) {
            modal.classList.remove("show");
            document.documentElement.style.overflow = "";
            document.body.style.overflow = "";
          }
          // Redirect after success (use root if login.php is gone)
          window.location.href = document.documentElement.dataset.baseUrl
            ? document.documentElement.dataset.baseUrl + "/../"
            : "/";
        }, 2000);
      }
    } catch (err) {
      let container = form.querySelector(".ajax-result");
      if (!container) {
        container = document.createElement("div");
        container.className = "card ajax-result";
        container.style.padding = "14px";
        container.style.marginTop = "12px";
        form.appendChild(container);
      }
      container.innerHTML =
        '<div class="help">Network error. Please try again.</div>';
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = origText;
      }
    }
  }

  const forgotForm = document.querySelector("#modal-forgot form");
  if (forgotForm) forgotForm.addEventListener("submit", handleModalFormSubmit);
  const resetForm = document.querySelector("#modal-reset form");
  if (resetForm) resetForm.addEventListener("submit", handleModalFormSubmit);
})();

// Handle postMessage from 2FA iframes
(function () {
  window.addEventListener("message", function (e) {
    if (e.data && e.data.type === "2fa_success") {
      // Close any open modals
      const openModal = document.querySelector(".modal-overlay.show");
      if (openModal) {
        openModal.classList.remove("show");
        document.documentElement.style.overflow = "";
        document.body.style.overflow = "";
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
  const getBase = () => document.documentElement.dataset.baseUrl || "";

  // Only run on authenticated pages (pages with a dashboard layout)
  if (
    !document.querySelector(
      ".dashboard-layout, .role-layout, [data-authenticated]",
    )
  ) {
    return;
  }

  function heartbeat() {
    fetch(getBase() + "/controllers/heartbeat-handler.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) {
          // Session expired or user logged out - redirect to home
          window.location.href = getBase() + "/";
        }
      })
      .catch(() => {
        // Network error - don't redirect, just log
        console.warn("Heartbeat failed - network issue");
      });
  }

  // Initial heartbeat after 30 seconds, then every 60 seconds
  setTimeout(heartbeat, 30000);
  setInterval(heartbeat, 60000);

  // Theme Management (Session-scoped)
  (function () {
    function applyTheme(theme) {
      if (theme === "dark") {
        document.documentElement.classList.add("dark-theme");
      } else {
        document.documentElement.classList.remove("dark-theme");
      }
      sessionStorage.setItem("matriflow_theme", theme);
      // Also update cookie for SSR
      document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax`;
    }

    document.addEventListener("click", (e) => {
      const toggle = e.target.closest("[data-theme-toggle]");
      if (!toggle) return;

      const current = sessionStorage.getItem("matriflow_theme") || "light";
      applyTheme(current === "light" ? "dark" : "light");
    });
  })();

  // Toast System Implementation
  function showToast(message, type = "info") {
    const container = document.getElementById("toast-container");
    if (!container) return;

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;

    const iconMap = {
      success: "check_circle",
      error: "error",
      warning: "warning",
      info: "info",
    };

    toast.innerHTML = `
    <span class="material-symbols-outlined" style="font-size:20px;">${iconMap[type] || "info"}</span>
    <div class="toast-content">${message}</div>
    <span class="material-symbols-outlined toast-close" onclick="this.parentElement.classList.add('fade-out'); setTimeout(() => this.parentElement.remove(), 300)">close</span>
  `;

    container.appendChild(toast);

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (toast.parentElement) {
        toast.classList.add("fade-out");
        setTimeout(() => toast.remove(), 300);
      }
    }, 5000);
  }

  // Intercept window.alert for a smoother UX
  window.originalAlert = window.alert;
  window.alert = function (msg) {
    showToast(msg, "info");
  };

  // Global Multi-Select Dropdown Handler
  function setupMultiSelect() {
    window.updateSingleDropdown = function (
      dropdownId,
      displayId,
      defaultText,
    ) {
      const dropdown = document.getElementById(dropdownId);
      const checkboxes = dropdown.querySelectorAll(
        'input[type="checkbox"]:checked',
      );
      const display = document.getElementById(displayId).querySelector("span");

      if (checkboxes.length === 0) {
        display.textContent = defaultText;
        display.style.color = "var(--text-secondary)";
      } else {
        const labels = Array.from(checkboxes).map((cb) =>
          cb.parentNode.textContent.trim(),
        );
        const text = labels.join(", ");
        display.textContent = text;
        display.style.color = "var(--text-primary)";

        // If text is too long, show count
        if (display.scrollWidth > display.clientWidth) {
          display.textContent = checkboxes.length + " Items Selected";
        }
      }
    };
  }

  document.addEventListener("DOMContentLoaded", setupMultiSelect);
})();
