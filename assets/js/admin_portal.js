(() => {
  // Handle sidebar collapse toggle and persist state
  const layout = document.getElementById("appLayout");
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebarStorageKey = "mangLechonAdminSidebarCollapsed";

  const setSidebarCollapsed = (collapsed) => {
    if (!layout || !sidebarToggle) {
      return;
    }

    layout.classList.toggle("sidebar-collapsed", collapsed);
    sidebarToggle.setAttribute("aria-expanded", String(!collapsed));
    sidebarToggle.setAttribute(
      "aria-label",
      collapsed ? "Expand sidebar" : "Collapse sidebar",
    );
    sidebarToggle.setAttribute(
      "title",
      collapsed ? "Expand sidebar" : "Collapse sidebar",
    );
    sidebarToggle.textContent = collapsed ? "›" : "‹";

    try {
      window.localStorage.setItem(sidebarStorageKey, String(collapsed));
    } catch (error) {
      // Keep default behavior if storage is unavailable
    }
  };

  if (sidebarToggle && layout) {
    let initiallyCollapsed = false;

    try {
      initiallyCollapsed =
        window.localStorage.getItem(sidebarStorageKey) === "true";
    } catch (error) {
      // Use expanded state by default
    }

    setSidebarCollapsed(initiallyCollapsed);

    sidebarToggle.addEventListener("click", () => {
      setSidebarCollapsed(!layout.classList.contains("sidebar-collapsed"));
    });
  }

  // Handle topbar mail and account dropdown menus
  const mailTrigger = document.getElementById("adminTopbarMailTrigger");
  const mailMenu = document.getElementById("adminTopbarMailMenu");
  const accountTrigger = document.getElementById("adminTopbarAccountTrigger");
  const accountMenu = document.getElementById("adminTopbarAccountMenu");

  const closeAllMenus = () => {
    if (mailMenu && mailTrigger) {
      mailMenu.classList.remove("open");
      mailTrigger.setAttribute("aria-expanded", "false");
    }
    if (accountMenu && accountTrigger) {
      accountMenu.classList.remove("open");
      accountTrigger.setAttribute("aria-expanded", "false");
    }
  };

  if (mailTrigger && mailMenu) {
    mailTrigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const isOpen = mailMenu.classList.contains("open");
      closeAllMenus();

      if (!isOpen) {
        mailMenu.classList.add("open");
        mailTrigger.setAttribute("aria-expanded", "true");
      }
    });
  }

  if (accountTrigger && accountMenu) {
    accountTrigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const isOpen = accountMenu.classList.contains("open");
      closeAllMenus();

      if (!isOpen) {
        accountMenu.classList.add("open");
        accountTrigger.setAttribute("aria-expanded", "true");
      }
    });
  }

  document.addEventListener("click", (event) => {
    const insideMail =
      mailMenu &&
      mailTrigger &&
      (mailMenu.contains(event.target) || mailTrigger.contains(event.target));
    const insideAccount =
      accountMenu &&
      accountTrigger &&
      (accountMenu.contains(event.target) ||
        accountTrigger.contains(event.target));

    if (!insideMail && !insideAccount) {
      closeAllMenus();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeAllMenus();
    }
  });

  // Update topbar live date and clock every second
  const dateElement = document.getElementById("liveDate");
  const timeElement = document.getElementById("liveTime");

  const updateClock = () => {
    const now = new Date();

    if (dateElement) {
      dateElement.textContent = now.toLocaleDateString(undefined, {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    }

    if (timeElement) {
      timeElement.textContent = now.toLocaleTimeString(undefined, {
        hour: "numeric",
        minute: "2-digit",
        second: "2-digit",
      });
    }
  };

  updateClock();
  window.setInterval(updateClock, 1000);
})();

// Populate Staff Management modals dynamically from action buttons
const permissionsModal = document.getElementById("staffPermissionsModal");
if (permissionsModal) {
  permissionsModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (!btn) return;

    document.getElementById("permStaffId").value = btn.dataset.staffId || "";
    document.getElementById("permStaffName").textContent =
      btn.dataset.staffName || "";
    document.getElementById("permPositionInput").value =
      btn.dataset.position || "";
    document.getElementById("permPrimaryCategory").value =
      btn.dataset.primaryCategory || "0";

    const permitted = (btn.dataset.permittedCategories || "")
      .split(",")
      .map((id) => id.trim())
      .filter(Boolean);

    permissionsModal
      .querySelectorAll('input[name="permitted_categories[]"]')
      .forEach((checkbox) => {
        checkbox.checked = permitted.includes(checkbox.value);
      });
  });
}

const statusModal = document.getElementById("staffStatusModal");
if (statusModal) {
  const statusSelect = document.getElementById("statusActionSelect");
  const dateRow = document.getElementById("statusDateRow");
  const leaveEndCol = document.getElementById("leaveEndCol");

  const toggleStatusFields = () => {
    if (!statusSelect || !dateRow || !leaveEndCol) return;
    const val = statusSelect.value;
    const needsDate =
      val === "present" || val === "absent" || val === "on_leave";
    dateRow.style.display = needsDate ? "flex" : "none";
    leaveEndCol.style.display = val === "on_leave" ? "block" : "none";
  };

  if (statusSelect) {
    statusSelect.addEventListener("change", toggleStatusFields);
  }

  statusModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (!btn) return;

    document.getElementById("statusStaffId").value = btn.dataset.staffId || "";
    document.getElementById("statusStaffName").textContent =
      btn.dataset.staffName || "";

    if (statusSelect && btn.dataset.defaultAction) {
      statusSelect.value = btn.dataset.defaultAction;
    }
    toggleStatusFields();
  });
}

const takeoverModal = document.getElementById("shiftTakeoverModal");
if (takeoverModal) {
  const scheduleSelect = document.getElementById("takeoverScheduleSelect");
  const replacementSelect = document.getElementById("takeoverNewStaffSelect");
  const categoryHint = document.getElementById("takeoverCategoryHint");

  const filterReplacementOptions = () => {
    if (!scheduleSelect || !replacementSelect) return;
    const selectedOption = scheduleSelect.options[scheduleSelect.selectedIndex];
    const currentStaffId = selectedOption ? selectedOption.dataset.staffId : "";
    const requiredCategory = selectedOption
      ? selectedOption.dataset.categoryId
      : "";
    const categoryName = selectedOption
      ? selectedOption.dataset.categoryName
      : "";

    if (categoryHint) {
      categoryHint.textContent = categoryName
        ? `Shift Category: ${categoryName} (authorized staff are marked with ★)`
        : "";
    }

    Array.from(replacementSelect.options).forEach((opt) => {
      if (!opt.value) return;
      const isSamePerson = opt.value === currentStaffId;
      opt.disabled = isSamePerson;
      opt.hidden = isSamePerson;

      const permitted = (opt.dataset.permitted || "").split(",");
      const baseLabel = opt.dataset.baseLabel || opt.textContent;
      const isAuthorized =
        requiredCategory && permitted.includes(requiredCategory);
      opt.textContent = isAuthorized
        ? `★ ${baseLabel} (Authorized)`
        : baseLabel;
    });

    if (replacementSelect.value === currentStaffId) {
      replacementSelect.value = "";
    }
  };

  if (scheduleSelect) {
    scheduleSelect.addEventListener("change", filterReplacementOptions);
  }

  takeoverModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (btn && btn.dataset.scheduleId && scheduleSelect) {
      scheduleSelect.value = btn.dataset.scheduleId;
    }
    filterReplacementOptions();
  });
}

const resetPwModal = document.getElementById("resetStaffPasswordModal");
if (resetPwModal) {
  resetPwModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById("resetPwStaffId").value = btn.dataset.staffId || "";
    document.getElementById("resetPwStaffName").textContent =
      btn.dataset.staffName || "";
  });
}

const deleteStaffModal = document.getElementById("deleteStaffModal");
if (deleteStaffModal) {
  deleteStaffModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById("deleteStaffId").value = btn.dataset.staffId || "";
    document.getElementById("deleteStaffName").textContent =
      btn.dataset.staffName || "";
    document.getElementById("deleteStaffEmpId").textContent =
      btn.dataset.employeeId || "";
  });
}

// Generate random temporary password inside modals
document.querySelectorAll("[data-generate-password]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const targetInput = document.getElementById(btn.dataset.generatePassword);
    if (!targetInput) return;
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    let token = "ML@";
    for (let i = 0; i < 6; i++) {
      token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    targetInput.value = token;
  });
});

// Auto-submit filter forms immediately on dropdown change or paused keyword typing
document.querySelectorAll("form.auto-filter-form").forEach((form) => {
  let searchTimer = null;

  form.querySelectorAll("select").forEach((selectEl) => {
    selectEl.addEventListener("change", () => {
      form.submit();
    });
  });

  const searchInput = form.querySelector('input[name="search"]');
  if (searchInput) {
    // Keep cursor at the end of the search box if a search query is active
    if (searchInput.value.trim() !== "") {
      searchInput.focus();
      const length = searchInput.value.length;
      searchInput.setSelectionRange(length, length);
    }

    searchInput.addEventListener("input", () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => {
        form.submit();
      }, 450);
    });

    // Submit immediately if the user clicks the browser's clear (×) button
    searchInput.addEventListener("search", () => {
      window.clearTimeout(searchTimer);
      form.submit();
    });
  }
});

// Populate Edit Attendance modal dynamically from three-dot menu
const editAttendanceModal = document.getElementById("editAttendanceModal");
if (editAttendanceModal) {
  editAttendanceModal.addEventListener("show.bs.modal", (event) => {
    const btn = event.relatedTarget;
    if (!btn) return;

    document.getElementById("attendanceScheduleId").value =
      btn.dataset.scheduleId || "";
    document.getElementById("attendanceStaffLabel").textContent =
      btn.dataset.staffName || "";
    document.getElementById("attendanceShiftLabel").textContent =
      btn.dataset.shiftLabel || "";
    document.getElementById("attendanceStatusSelect").value =
      btn.dataset.status || "present";
    document.getElementById("attendanceCheckInInput").value =
      btn.dataset.checkIn || "";
    document.getElementById("attendanceCheckOutInput").value =
      btn.dataset.checkOut || "";
  });
}
