// auth.js - Handle Modal Authentication Logic

(function () {
  const getBase = () => document.documentElement.dataset.baseUrl || "";
  const getRoot = () => getBase().replace(/\/public$/, "") || "/";
  // --- Helper Functions ---
  function clearErrors(form) {
    const existingAlerts = form.querySelectorAll(
      ".alert-danger, .alert-success",
    );
    existingAlerts.forEach((el) => el.remove());
  }

  function showError(form, message) {
    clearErrors(form);
    const alert = document.createElement("div");
    alert.className = "alert alert-danger";
    alert.textContent = message;
    const firstRow = form.querySelector(".form-row, .form-grid-2");
    if (firstRow) {
      firstRow.parentNode.insertBefore(alert, firstRow);
    } else {
      form.prepend(alert);
    }
  }

  function openModal(id) {
    const m = document.getElementById(id);
    if (m) {
      m.classList.add("show");
      // Accessibility focus
      const input = m.querySelector("input");
      if (input) input.focus();
    }
  }

  function closeModal(el) {
    const m = el.closest ? el.closest(".modal-overlay") : el;
    if (m) m.classList.remove("show");
  }

  // --- Password Strength Meter ---
  // --- Password Strength Meter ---
  function debounce(func, wait) {
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  function updatePasswordStrength(input) {
    const password = input.value;
    const container = input.closest(".input-group") || input.parentNode;
    let meter = container.parentNode.querySelector(".password-strength-meter");

    // Create if missing
    if (!meter) {
      meter = document.createElement("div");
      meter.className = "password-strength-meter";
      meter.innerHTML = `
            <div class="meter-bar-bg"><div class="meter-bar-fill"></div></div>
            <div class="meter-text"></div>
            <ul class="meter-reasons"></ul>
        `;
      container.parentNode.appendChild(meter);
    }

    if (password.length === 0) {
      meter.style.display = "none";
      return;
    }

    meter.style.display = "block";
    const bar = meter.querySelector(".meter-bar-fill");
    const text = meter.querySelector(".meter-text");
    const reasonsList = meter.querySelector(".meter-reasons");

    let strength = 0;
    let reasons = [];

    if (password.length >= 10) strength++;
    else reasons.push("Must be at least 10 characters");

    if (/[A-Z]/.test(password)) strength++;
    else reasons.push("Must contain an uppercase letter");

    if (/[a-z]/.test(password)) strength++;
    else reasons.push("Must contain a lowercase letter");

    if (/[0-9]/.test(password)) strength++;
    else reasons.push("Must contain a number");

    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else reasons.push("Must contain a special character");

    // Penalize repeating/sequential (logic from before)
    if (/(.)\1{3,}/.test(password)) strength = Math.max(0, strength - 1);

    let color = "#ef4444"; // Red
    let label = "Weak";
    let width = "20%";

    if (strength >= 5) {
      color = "#22c55e"; // Green
      label = "Strong";
      width = "100%";
    } else if (strength >= 3) {
      color = "#f59e0b"; // Amber
      label = "Medium";
      width = "60%";
    }

    bar.style.width = width;
    bar.style.backgroundColor = color;
    text.textContent = label;
    text.style.color = color;

    // Show reasons if not strong
    if (strength < 5) {
      reasonsList.innerHTML = reasons.map((r) => `<li>${r}</li>`).join("");
      reasonsList.style.display = "block";
    } else {
      reasonsList.style.display = "none";
    }
  }

  function initPasswordStrength() {
    // Inject CSS
    if (!document.getElementById("password-strength-css")) {
      const style = document.createElement("style");
      style.id = "password-strength-css";
      style.textContent = `
        .password-strength-meter { 
            margin-top: 12px; 
            margin-bottom: 12px;
            font-size: 12px; 
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .meter-bar-bg { background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
        .meter-bar-fill { height: 100%; transition: width 0.3s ease, background-color 0.3s ease; width: 0; }
        .meter-text { font-weight: 700; text-transform: uppercase; font-size: 11px; margin-bottom: 4px; }
        .meter-reasons { margin: 0; padding-left: 20px; color: #64748b; line-height: 1.4; }
        .meter-reasons li { margin-bottom: 2px; }
      `;
      document.head.appendChild(style);
    }

    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach((input) => {
      const isTarget =
        input.name === "password" || input.name === "password_new";
      if (!isTarget) return;

      // Debounced listener
      input.addEventListener(
        "input",
        debounce(() => updatePasswordStrength(input), 150),
      );
    });
  }

  // Call init
  document.addEventListener("DOMContentLoaded", initPasswordStrength);
  // Also re-init if modals are opened or content changes
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.addedNodes.length) initPasswordStrength();
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // --- Event Listeners for Toggles ---
  document.addEventListener("click", function (e) {
    // Open
    const openBtn = e.target.closest("[data-modal-open]");
    if (openBtn) {
      e.preventDefault();
      const id = openBtn.getAttribute("data-modal-open");
      openModal(id);
    }

    // Close
    const closeBtn = e.target.closest("[data-modal-close]");
    if (closeBtn) {
      e.preventDefault();
      closeModal(closeBtn);
    }

    // Switch (e.g. "Forgot Password?" -> Close Login, Open Forgot)
    const switchBtn = e.target.closest("[data-modal-switch]");
    if (switchBtn) {
      e.preventDefault();
      closeModal(switchBtn);
      const id = switchBtn.getAttribute("data-modal-switch");
      setTimeout(() => openModal(id), 150);
    }

    // Close on background click
    if (e.target.classList.contains("modal-overlay")) {
      e.target.classList.remove("show");
    }

    // Password Toggle
    // Password Toggle handled in app.js
  });

  // --- Form Handlers ---

  // 1. Login Form
  const loginForm = document.querySelector("#modal-login form");
  if (loginForm) {
    loginForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearErrors(loginForm);

      const btn = loginForm.querySelector('button[type="submit"]');
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Signing in...";

      try {
        const formData = new FormData(loginForm);
        const res = await fetch(getBase() + "/controllers/auth-handler.php", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
          const data = await res.json();
          if (data.success) {
            if (data.require_2fa) {
              closeModal(loginForm);

              if (
                data.redirect.includes("setup-2fa") ||
                data.redirect.includes("registered=true")
              ) {
                // Prioritize 2FA Setup if it's new user setup
                const setupModal = document.getElementById("modal-setup2fa");
                if (setupModal) {
                  setupModal.classList.add("show");
                  const iframe = setupModal.querySelector("iframe");
                  if (iframe)
                    iframe.src = getBase() + "/setup-2fa.php?modal=1&newuser=1";
                } else {
                  // Fallback to registered success if no setup modal (unlikely)
                  const regModal = document.getElementById(
                    "modal-registered-success",
                  );
                  if (regModal) regModal.classList.add("show");
                }
              } else {
                const verifyModal = document.getElementById("modal-verify2fa");
                if (verifyModal) {
                  verifyModal.classList.add("show");
                  const iframe = verifyModal.querySelector("iframe");
                  if (iframe)
                    iframe.src = getBase() + "/verify-2fa.php?modal=1";
                }
              }
            } else {
              window.location.href = data.redirect;
            }
          } else {
            const msg = Array.isArray(data.errors)
              ? data.errors.join(" ")
              : data.errors || "Login failed.";
            showError(loginForm, msg);
          }
        } else {
          // Fallback if not JSON
          window.location.href = getBase() + "/dashboard.php";
        }
      } catch (err) {
        console.error(err);
        showError(loginForm, "An unexpected error occurred.");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  }

  // 2. Register Form
  const registerForm = document.querySelector("#modal-register form");
  if (registerForm) {
    registerForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearErrors(registerForm);

      const btn = registerForm.querySelector('button[type="submit"]');
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Creating Account...";

      try {
        const formData = new FormData(registerForm);
        const res = await fetch(getBase() + "/controllers/auth-handler.php", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
          const data = await res.json();
          if (data.success) {
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              closeModal(registerForm);
              const loginModal = document.getElementById("modal-login");
              if (loginModal) {
                loginModal.classList.add("show");
                const successAlert = document.createElement("div");
                successAlert.className = "alert alert-success";
                successAlert.textContent =
                  "Registration successful! Please log in.";
                const firstRow = loginModal.querySelector(".form-row");
                if (firstRow)
                  firstRow.parentNode.insertBefore(successAlert, firstRow);
              }
            }
          } else {
            const msg = Array.isArray(data.errors)
              ? data.errors.join(" ")
              : data.errors || "Registration failed.";
            showError(registerForm, msg);
          }
        } else {
          showError(registerForm, "Received invalid response.");
        }
      } catch (err) {
        console.error(err);
        showError(registerForm, "Network error. Please try again.");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  }

  // 3. Forgot Password Form
  const forgotForm = document.querySelector("#modal-forgot form");
  if (forgotForm) {
    forgotForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearErrors(forgotForm);

      const btn = forgotForm.querySelector('button[type="submit"]');
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Generating...";

      try {
        const formData = new FormData(forgotForm);
        const res = await fetch(getBase() + "/controllers/auth-handler.php", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
          const data = await res.json();
          if (data.success) {
            const alert = document.createElement("div");
            alert.className = "alert alert-success";
            alert.textContent = data.message;

            // Show the link as clickable
            if (data.reset_link) {
              const linkDiv = document.createElement("div");
              linkDiv.style.marginTop = "12px";
              linkDiv.style.wordBreak = "break-all";
              linkDiv.style.fontSize = "13px";
              // Make it a clickable link
              linkDiv.innerHTML =
                '<a href="' +
                data.reset_link +
                '" class="btn-text" style="font-weight:700">Click here to reset password</a> or copy:<br><span style="user-select:all">' +
                data.reset_link +
                "</span>";
              alert.appendChild(linkDiv);
            }

            const firstRow = forgotForm.querySelector(".form-row");
            if (firstRow) firstRow.parentNode.insertBefore(alert, firstRow);
          } else {
            const msg = Array.isArray(data.errors)
              ? data.errors.join(" ")
              : data.errors || data.message || "Failed to generate reset link.";
            showError(forgotForm, msg);
          }
        } else {
          showError(forgotForm, "Received invalid response.");
        }
      } catch (err) {
        console.error(err);
        showError(forgotForm, "Network error.");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  }

  // 4. Reset Password Form
  const resetForm = document.querySelector("#modal-reset form");
  if (resetForm) {
    resetForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearErrors(resetForm);

      const btn = resetForm.querySelector('button[type="submit"]');
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Updating...";

      try {
        const formData = new FormData(resetForm);
        const res = await fetch(getRoot() + "/", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
          const data = await res.json();
          if (data.ok) {
            closeModal(resetForm);
            const loginModal = document.getElementById("modal-login");
            if (loginModal) {
              loginModal.classList.add("show");
              const successAlert = document.createElement("div");
              successAlert.className = "alert alert-success";
              successAlert.textContent =
                data.message || "Password updated successfully!";
              const firstRow = loginModal.querySelector(".form-row");
              if (firstRow)
                firstRow.parentNode.insertBefore(successAlert, firstRow);
            }
          } else {
            showError(resetForm, data.message || "Password reset failed.");
          }
        } else {
          showError(resetForm, "Received invalid response.");
        }
      } catch (err) {
        console.error(err);
        showError(resetForm, "Network error.");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  }

  // --- URL Parameter Handling (Auto-open) ---
  const urlParams = new URLSearchParams(window.location.search);

  if (urlParams.get("action") === "reset" && urlParams.get("token")) {
    const m = document.getElementById("modal-reset");
    if (m) {
      m.classList.add("show");
      const tokenInput = m.querySelector('input[name="token"]');
      if (tokenInput) tokenInput.value = urlParams.get("token");
    }
  }

  if (urlParams.get("setup2fa") === "1") {
    const m = document.getElementById("modal-setup2fa");
    if (m) {
      m.classList.add("show");
      const iframe = m.querySelector("iframe");
      const isNewUser = urlParams.get("newuser") === "1";
      if (iframe)
        iframe.src =
          getBase() +
          "/setup-2fa.php?modal=1" +
          (isNewUser ? "&newuser=1" : "");
      window.history.replaceState({}, document.title, getRoot());
    }
  }

  if (urlParams.get("verify2fa") === "1") {
    const m = document.getElementById("modal-verify2fa");
    if (m) {
      m.classList.add("show");
      const iframe = m.querySelector("iframe");
      if (iframe) iframe.src = getBase() + "/verify-2fa.php?modal=1";
      window.history.replaceState({}, document.title, getRoot());
    }
  }

  // Generic Error from redirect
  if (urlParams.get("error")) {
    const m = document.getElementById("modal-login");
    if (m) {
      m.classList.add("show");
      const alert = document.createElement("div");
      alert.className = "alert alert-danger";
      alert.textContent = decodeURIComponent(urlParams.get("error"));
      m.querySelector(".modal-body").prepend(alert);
      window.history.replaceState({}, document.title, getRoot());
    }
  }

  // --- PostMessage Listener for Iframe 2FA ---
  window.addEventListener("message", function (event) {
    if (event.data && event.data.type === "2fa_success") {
      window.location.href = event.data.redirect;
    }
  });
})();
