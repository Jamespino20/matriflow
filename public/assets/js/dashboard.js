// Dashboard JS: avatar cropping, theme toggling, and UI helpers
(function () {
  // Secure config getter
  const getConfig = (key) => document.documentElement.dataset[key] || "";

  document.addEventListener("DOMContentLoaded", function () {
    // --- Theme Toggling ---
    const themeToggle = document.querySelector("[data-theme-toggle]");
    if (themeToggle) {
      themeToggle.addEventListener("click", function () {
        const isDark = document.body.classList.toggle("dark-theme");
        localStorage.setItem("matriflow_theme", isDark ? "dark" : "light");
        // Set cookie for PHP to read (SSR)
        document.cookie =
          "theme=" +
          (isDark ? "dark" : "light") +
          "; path=/; max-age=" +
          365 * 24 * 60 * 60;
      });
    }

    // --- Global Avatar Cropper ---
    const avatarModal = document.getElementById("modal-avatar-upload");
    const canvas = document.getElementById("avatar-canvas");
    const zoomInput = document.getElementById("avatar-zoom");
    const fileInput = document.getElementById("avatar-file-input");
    const saveBtn = document.getElementById("btn-save-avatar");

    let ctx,
      img,
      scale = 1,
      offset = { x: 0, y: 0 },
      dragging = false,
      start = { x: 0, y: 0 };

    if (canvas) {
      ctx = canvas.getContext("2d");
      img = new Image();

      // Open modal when clicking any avatar change trigger
      document.addEventListener("click", function (e) {
        if (e.target.closest("[data-avatar-trigger]")) {
          avatarModal.style.display = "flex";
          if (!img.src) fileInput.click();
        }
      });

      if (fileInput) {
        fileInput.addEventListener("change", function (e) {
          const f = e.target.files[0];
          if (!f) return;
          const reader = new FileReader();
          reader.onload = function (ev) {
            img = new Image();
            img.onload = function () {
              // Initial fit
              scale = Math.max(0.5, Math.min(3, canvas.width / img.width));
              if (zoomInput) zoomInput.value = scale;
              offset = { x: 0, y: 0 };
              redraw();
            };
            img.src = ev.target.result;
          };
          reader.readAsDataURL(f);
        });
      }

      function redraw() {
        if (!img.width) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const w = img.width * scale;
        const h = img.height * scale;
        const x = offset.x + (canvas.width - w) / 2;
        const y = offset.y + (canvas.height - h) / 2;
        ctx.drawImage(img, x, y, w, h);

        // Draw circular mask guide
        ctx.strokeStyle = "rgba(255,255,255,0.5)";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(
          canvas.width / 2,
          canvas.height / 2,
          canvas.width / 2 - 2,
          0,
          Math.PI * 2
        );
        ctx.stroke();
      }

      canvas.addEventListener("pointerdown", function (e) {
        dragging = true;
        start = { x: e.clientX, y: e.clientY };
        canvas.setPointerCapture(e.pointerId);
      });
      canvas.addEventListener("pointermove", function (e) {
        if (!dragging || !img.width) return;
        const dx = e.clientX - start.x;
        const dy = e.clientY - start.y;
        offset.x += dx;
        offset.y += dy;
        start = { x: e.clientX, y: e.clientY };
        redraw();
      });
      canvas.addEventListener("pointerup", function (e) {
        dragging = false;
        canvas.releasePointerCapture(e.pointerId);
      });

      if (zoomInput) {
        zoomInput.addEventListener("input", function (e) {
          scale = parseFloat(e.target.value);
          redraw();
        });
      }

      if (saveBtn) {
        saveBtn.addEventListener("click", function () {
          const dataUrl = canvas.toDataURL("image/png");
          const fd = new FormData();
          fd.append("action", "update_avatar");
          fd.append("avatar_data", dataUrl);
          fd.append("csrf_token", getConfig("csrfToken"));
          fd.append("ajax", "1");

          const originalText = saveBtn.textContent;
          saveBtn.disabled = true;
          saveBtn.textContent = "Saving...";

          const baseUrl = getConfig("baseUrl") || "/public";
          fetch(baseUrl + "/controllers/profile-handler.php", {
            method: "POST",
            body: fd,
          })
            .then((res) => res.json())
            .then((json) => {
              if (json.ok) {
                showToast("Success", "Profile picture updated!", "success");
                // Refresh all avatars on page
                const avatarUrl =
                  json.avatar_url ||
                  baseUrl + "/assets/images/default-avatar.png?t=" + Date.now();
                document
                  .querySelectorAll(".header-avatar, .profile-avatar-img")
                  .forEach((el) => {
                    el.src = avatarUrl;
                  });
                avatarModal.style.display = "none";
              } else {
                showToast(
                  "Error",
                  json.message || "Failed to save avatar.",
                  "error"
                );
              }
            })
            .catch((err) => {
              console.error(err);
              showToast("Error", "Network error.", "error");
            })
            .finally(() => {
              saveBtn.disabled = false;
              saveBtn.textContent = originalText;
            });
        });
      }
    }

    // --- Toast Helper ---
    function showToast(title, msg, kind) {
      let container = document.getElementById("toast-container");
      if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        container.className = "toast-container";
        document.body.appendChild(container);
      }
      const t = document.createElement("div");
      t.className = "toast " + (kind || "info");
      t.innerHTML = `<div><div class="title">${title}</div><div class="msg">${msg}</div></div>`;
      container.appendChild(t);
      setTimeout(() => {
        t.classList.add("hide");
        setTimeout(() => t.remove(), 400);
      }, 4000);
    }

    window.showToast = showToast; // Export for global use

    // --- Inactivity Timer ---
    const inactivityTimeout = 10 * 60 * 1000; // 10 minutes
    let inactivityTimer;

    function resetInactivityTimer() {
      clearTimeout(inactivityTimer);
      inactivityTimer = setTimeout(showInactivityModal, inactivityTimeout);
    }

    function showInactivityModal() {
      const modal = document.getElementById("modal-inactivity");
      if (modal) {
        modal.style.display = "flex";
        // Optional: Automatically redirect after another minute if they don't click anything
        setTimeout(() => {
          const baseUrl = getConfig("baseUrl") || "/public";
          window.location.href = baseUrl + "/logout.php";
        }, 60000);
      }
    }

    // List of events that reset the timer
    const events = [
      "mousedown",
      "mousemove",
      "keypress",
      "scroll",
      "touchstart",
    ];
    events.forEach((name) => {
      document.addEventListener(name, resetInactivityTimer, true);
    });

    // Initialize timer
    resetInactivityTimer();
  });
})();
