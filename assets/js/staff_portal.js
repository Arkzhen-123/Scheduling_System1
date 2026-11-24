(() => {
    // Handle sidebar collapse toggle and persist state
    const layout = document.getElementById('appLayout');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarStorageKey = 'mangLechonStaffSidebarCollapsed';

    const setSidebarCollapsed = (collapsed) => {
        if (!layout || !sidebarToggle) {
            return;
        }

        layout.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarToggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarToggle.textContent = collapsed ? '›' : '‹';

        try {
            window.localStorage.setItem(sidebarStorageKey, String(collapsed));
        } catch (error) {
            // Keep default behavior if storage is unavailable
        }
    };

    if (sidebarToggle && layout) {
        let initiallyCollapsed = false;

        try {
            initiallyCollapsed = window.localStorage.getItem(sidebarStorageKey) === 'true';
        } catch (error) {
            // Use expanded state by default
        }

        setSidebarCollapsed(initiallyCollapsed);

        sidebarToggle.addEventListener('click', () => {
            setSidebarCollapsed(!layout.classList.contains('sidebar-collapsed'));
        });
    }

    // Handle topbar staff account dropdown menu
    const accountTrigger = document.getElementById('staffTopbarAccountTrigger');
    const accountMenu = document.getElementById('staffTopbarAccountMenu');

    const closeAccountMenu = () => {
        if (!accountTrigger || !accountMenu) {
            return;
        }

        accountMenu.classList.remove('open');
        accountTrigger.setAttribute('aria-expanded', 'false');
    };

    if (accountTrigger && accountMenu) {
        accountTrigger.addEventListener('click', (event) => {
            event.stopPropagation();

            if (accountMenu.classList.contains('open')) {
                closeAccountMenu();
            } else {
                accountMenu.classList.add('open');
                accountTrigger.setAttribute('aria-expanded', 'true');
            }
        });

        document.addEventListener('click', (event) => {
            if (!accountMenu.contains(event.target) && !accountTrigger.contains(event.target)) {
                closeAccountMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAccountMenu();
                accountTrigger.focus();
            }
        });
    }

    // Update topbar live date and clock every second
    const dateElement = document.getElementById('liveDate');
    const timeElement = document.getElementById('liveTime');

    const updateClock = () => {
        const now = new Date();

        if (dateElement) {
            dateElement.textContent = now.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        }

        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString(undefined, {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
            });
        }
    };

    updateClock();
    window.setInterval(updateClock, 1000);
})();

// Schedule modal helpers (date-range sync, category hints, and edit modal)
    const assignStartInput = document.getElementById('assignStartDate');
    const assignEndInput = document.getElementById('assignEndDate');
    const weekdaySelectorRow = document.getElementById('weekdaySelectorRow');

    const syncDateRangeUI = () => {
        if (!assignStartInput || !assignEndInput) return;
        if (assignStartInput.value) {
            assignEndInput.min = assignStartInput.value;
            if (!assignEndInput.value || assignEndInput.value < assignStartInput.value) {
                assignEndInput.value = assignStartInput.value;
            }
        }
        if (weekdaySelectorRow) {
            const isMultiDay = assignStartInput.value && assignEndInput.value && assignEndInput.value > assignStartInput.value;
            weekdaySelectorRow.style.display = isMultiDay ? 'block' : 'none';
        }
    };

    if (assignStartInput && assignEndInput) {
        assignStartInput.addEventListener('change', syncDateRangeUI);
        assignEndInput.addEventListener('change', syncDateRangeUI);
        syncDateRangeUI();
    }

    const assignStaffSelect = document.getElementById('assignStaffSelect');
    const assignCategorySelect = document.getElementById('assignCategorySelect');
    const assignPermHint = document.getElementById('assignPermissionHint');

    const updateAssignCategoryOptions = () => {
        if (!assignStaffSelect || !assignCategorySelect) return;
        const selectedOpt = assignStaffSelect.options[assignStaffSelect.selectedIndex];
        const permittedCsv = selectedOpt ? (selectedOpt.dataset.permitted || '') : '';
        const primaryCat = selectedOpt ? (selectedOpt.dataset.primary || '') : '';
        const permittedIds = permittedCsv.split(',').filter(Boolean);

        Array.from(assignCategorySelect.options).forEach((opt) => {
            if (!opt.value) return;
            const baseName = opt.dataset.baseName || opt.textContent;
            if (!selectedOpt || !selectedOpt.value) {
                opt.textContent = baseName;
                return;
            }
            const allowed = permittedIds.includes(opt.value);
            opt.textContent = allowed ? `${baseName} (Authorized)` : `${baseName} (Requires override)`;
        });

        if (primaryCat && permittedIds.includes(primaryCat)) {
            assignCategorySelect.value = primaryCat;
        }

        if (assignPermHint) {
            assignPermHint.textContent = selectedOpt && selectedOpt.value
                ? 'Authorized shift categories for this employee are labeled above.'
                : '';
        }
    };

    if (assignStaffSelect) {
        assignStaffSelect.addEventListener('change', updateAssignCategoryOptions);
    }

    const editScheduleModal = document.getElementById('editScheduleModal');
    if (editScheduleModal) {
        editScheduleModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (!btn) return;

            document.getElementById('editScheduleId').value = btn.dataset.scheduleId || '';
            document.getElementById('editStaffSelect').value = btn.dataset.staffId || '';
            document.getElementById('editCategorySelect').value = btn.dataset.categoryId || '';
            document.getElementById('editScheduleDate').value = btn.dataset.scheduleDate || '';
            document.getElementById('editStartTime').value = btn.dataset.startTime || '';
            document.getElementById('editEndTime').value = btn.dataset.endTime || '';
            document.getElementById('editWorkLocation').value = btn.dataset.workLocation || '';
            document.getElementById('editTaskDescription').value = btn.dataset.taskDescription || '';
            document.getElementById('editScheduleNotes').value = btn.dataset.notes || '';
            document.getElementById('editScheduleStatus').value = btn.dataset.status || 'scheduled';
        });
    }