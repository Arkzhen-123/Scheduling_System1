"use strict";

/*
|--------------------------------------------------------------------------
| Password visibility toggle
|--------------------------------------------------------------------------
| This is the only password visibility handler.
| It changes the input type and SVG icon, never the button text.
|--------------------------------------------------------------------------
*/

function initializePasswordToggles() {
  const toggleButtons = document.querySelectorAll("[data-password-toggle]");

  toggleButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      const targetId = button.getAttribute("data-target");
      const passwordInput = targetId ? document.getElementById(targetId) : null;

      if (!passwordInput || passwordInput.tagName !== "INPUT") {
        return;
      }

      const currentlyHidden = passwordInput.type === "password";
      const shouldShow = currentlyHidden;

      passwordInput.type = shouldShow ? "text" : "password";

      button.setAttribute(
        "aria-label",
        shouldShow ? "Hide password" : "Show password",
      );

      button.setAttribute(
        "title",
        shouldShow ? "Hide password" : "Show password",
      );

      button.setAttribute("aria-pressed", shouldShow ? "true" : "false");

      const icon = button.querySelector(".eye-icon");

      if (!icon) {
        return;
      }

      if (shouldShow) {
        // Crossed-eye icon: password is currently visible.
        icon.innerHTML = `
                    <path d="M3 3l18 18"/>
                    <path d="M10.6 10.6a2 2 0 002.8 2.8"/>
                    <path d="M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a15.7 15.7 0 01-3 3.9"/>
                    <path d="M6.6 6.6C3.7 8.5 2 12 2 12s3.6 7 10 7a10.7 10.7 0 004.1-.8"/>
                `;
      } else {
        // Eye icon: password is currently hidden.
        icon.innerHTML = `
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                `;
      }
    });
  });
}

/*
|--------------------------------------------------------------------------
| Forgot-password popup
|--------------------------------------------------------------------------
*/

function initializeForgotPasswordPanel() {
  const overlay = document.getElementById("forgot-overlay");
  const openButton = document.getElementById("open-forgot-password");
  const closeButton = document.getElementById("close-forgot-password");

  if (!overlay || !openButton || !closeButton) {
    return;
  }

  const staffForm = document.getElementById("staff-help-form");
  const adminForm = document.getElementById("admin-help-form");

  if (!staffForm || !adminForm) {
    return;
  }

  function setFormEnabled(form, enabled) {
    form.querySelectorAll("input").forEach(function (input) {
      input.disabled = !enabled;
    });
  }

  function hideForms() {
    staffForm.hidden = true;
    adminForm.hidden = true;

    setFormEnabled(staffForm, false);
    setFormEnabled(adminForm, false);
  }

  function selectHelpType(type) {
    const showStaff = type === "staff";
    const selectedForm = showStaff ? staffForm : adminForm;

    staffForm.hidden = !showStaff;
    adminForm.hidden = showStaff;

    setFormEnabled(staffForm, showStaff);
    setFormEnabled(adminForm, !showStaff);

    const firstField = selectedForm.querySelector('input:not([type="hidden"])');

    if (firstField) {
      firstField.focus();
    }
  }

  function openPanel() {
    overlay.hidden = false;
    hideForms();
    closeButton.focus();
  }

  function closePanel() {
    overlay.hidden = true;
    hideForms();
    openButton.focus();
  }

  document.querySelectorAll("[data-help-type]").forEach(function (button) {
    button.addEventListener("click", function () {
      selectHelpType(button.getAttribute("data-help-type"));
    });
  });

  openButton.addEventListener("click", openPanel);
  closeButton.addEventListener("click", closePanel);

  overlay.addEventListener("click", function (event) {
    if (event.target === overlay) {
      closePanel();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !overlay.hidden) {
      closePanel();
    }
  });

  hideForms();
}

/*
|--------------------------------------------------------------------------
| Optional confirmation dialogs
|--------------------------------------------------------------------------
| Add data-confirm="Your message" to a form or link to request confirmation.
|--------------------------------------------------------------------------
*/

function initializeConfirmButtons() {
  document.querySelectorAll("[data-confirm]").forEach(function (element) {
    element.addEventListener("click", function (event) {
      const message = element.getAttribute("data-confirm");

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
}

/*
|--------------------------------------------------------------------------
| Optional automatic alert dismissal
|--------------------------------------------------------------------------
| Alerts with data-auto-dismiss="true" disappear after five seconds.
|--------------------------------------------------------------------------
*/

function initializeAutoDismissAlerts() {
  document
    .querySelectorAll('[data-auto-dismiss="true"]')
    .forEach(function (alert) {
      window.setTimeout(function () {
        alert.remove();
      }, 5000);
    });
}

/*
|--------------------------------------------------------------------------
| Initialize page interactions
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", function () {
  initializePasswordToggles();
  initializeForgotPasswordPanel();
  initializeConfirmButtons();
  initializeAutoDismissAlerts();
});
