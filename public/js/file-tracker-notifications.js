(function (window, document) {
  'use strict';

  // On-screen toast pop-ups for new notifications.
  // Set to false (leaving window.KLAES_ENABLE_NOTIFICATION_TOASTS unset) to
  // disable them again; the header bell, unread counters and the notifications
  // page are unaffected either way.
  const TOASTS_ENABLED = true;

  const SELECTORS = {
    root: '#file-tracker-header-notifications',
    toggle: '[data-notification-toggle]',
    panel: '[data-notification-panel]',
  };
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatRelativeTime(value) {
    if (!value) {
      return '';
    }
    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
      return '';
    }
    const diffSeconds = Math.floor((Date.now() - timestamp) / 1000);
    if (diffSeconds < 45) {
      return 'Just now';
    }
    if (diffSeconds < 3600) {
      return `${Math.floor(diffSeconds / 60)}m ago`;
    }
    if (diffSeconds < 86400) {
      return `${Math.floor(diffSeconds / 3600)}h ago`;
    }
    if (diffSeconds < 604800) {
      return `${Math.floor(diffSeconds / 86400)}d ago`;
    }
    return new Date(timestamp).toLocaleDateString();
  }

  // No actor photo reaches the client (the notification payload carries no user
  // image), so the avatar is derived from the notification type instead.
  const AVATARS = {
    accepted: { bg: '#dcfce7', fg: '#16a34a', path: 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z' },
    rejected: { bg: '#fee2e2', fg: '#dc2626', path: 'M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z' },
    digital: { bg: '#ede9fe', fg: '#7c3aed', path: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 7V3.5L18.5 9z' },
    file: { bg: '#e0e7ff', fg: '#4f46e5', path: 'M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8z' },
    attendance: { bg: '#fef3c7', fg: '#d97706', path: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' },
    default: { bg: '#f1f5f9', fg: '#64748b', path: 'M12 2a5.006 5.006 0 0 0-5 5v4.586l-.707.707A1 1 0 0 0 7 14h10a1 1 0 0 0 .707-1.707L17 11.586V7a5.006 5.006 0 0 0-5-5zm-2 13a2 2 0 0 0 4 0h-4z' },
  };

  function resolveAvatar(item) {
    const type = item.type || '';
    const module = item.data?.raw?.module || '';
    if (type.endsWith('.accepted')) return AVATARS.accepted;
    if (type.endsWith('.rejected')) return AVATARS.rejected;
    if (type === 'digital_request' || module === 'digital_request') return AVATARS.digital;
    // Sign-in / sign-out notices (attendance.login, attendance.logout). A clock,
    // so the two cards a member of staff collects every day are not mistaken for
    // file work at a glance.
    if (type.startsWith('attendance') || module === 'attendance') return AVATARS.attendance;
    if (type.startsWith('file_tracking') || module === 'file_tracking') return AVATARS.file;
    return AVATARS.default;
  }

  function buildAvatar(item) {
    const avatar = resolveAvatar(item);
    return `
      <span class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full" style="background:${avatar.bg}">
        <svg viewBox="0 0 24 24" fill="${avatar.fg}" class="w-5 h-5"><path d="${avatar.path}"/></svg>
      </span>
    `;
  }

  // Mirrors the reference design's "**Name** did something" line: the file
  // number carries the weight, the rest of the title is secondary.
  function buildHeadline(item) {
    const title = item.title || 'File tracker update';
    const fileNo = item.data?.fileNumber || item.data?.file_number;

    if (fileNo) {
      const at = title.toLowerCase().indexOf(String(fileNo).toLowerCase());
      if (at !== -1) {
        const lead = title.slice(0, at + String(fileNo).length);
        const rest = title.slice(at + String(fileNo).length).trim();
        return `<span class="font-semibold text-slate-900">${escapeHtml(lead)}</span>${
          rest ? ` <span class="text-slate-500">${escapeHtml(rest)}</span>` : ''
        }`;
      }
    }

    return `<span class="font-semibold text-slate-900">${escapeHtml(title)}</span>`;
  }

  function buildAssignmentActions(item) {
    const raw = item.data?.raw || {};
    const trackerId = raw.file_tracker_id || raw.file_tracking_id;
    const status = (raw.assignment_status || '').toLowerCase();
    const pendingStatuses = ['pending', 'pending_acceptance', 'awaiting_acceptance'];
    const isPending = !status || pendingStatuses.includes(status);
    const isFileTracker =
      item.type?.startsWith('file_tracking') ||
      raw.module === 'file_tracking';

    if (!trackerId || !isFileTracker) {
      return '';
    }

    if (!isPending) {
      return buildCloseButton(item, true);
    }

    return `
      <div class="mt-3 space-y-2">
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="notification-action bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-notification-action="accept"
            data-tracker-id="${trackerId}"
            data-notification-id="${item.id}"
          >
            Accept
          </button>
          <button
            type="button"
            class="notification-action bg-red-600 hover:bg-red-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-notification-action="reject"
            data-tracker-id="${trackerId}"
            data-notification-id="${item.id}"
          >
            Reject
          </button>
        </div>
        ${buildCloseButton(item, true)}
      </div>
    `;
  }

  // ── Digital Request: Approve / Reject from notification bell ────────────────
  function buildDigitalRequestActions(item) {
    const isDigital = item.type === 'digital_request' || item.data?.module === 'digital_request';
    const requestId = item.data?.request_id;

    if (!isDigital || !requestId) return '';

    return `
      <div class="mt-3 space-y-2">
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="dr-notification-action bg-violet-600 hover:bg-violet-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-dr-action="approve"
            data-dr-request-id="${requestId}"
            data-notification-id="${item.id}"
          >
            Approve
          </button>
          <button
            type="button"
            class="dr-notification-action bg-red-600 hover:bg-red-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-dr-action="reject"
            data-dr-request-id="${requestId}"
            data-notification-id="${item.id}"
          >
            Reject
          </button>
        </div>
        ${buildCloseButton(item, true)}
      </div>
    `;
  }

  function buildCloseButton(item, subtle = false) {
    if (!item?.id) {
      return '';
    }

    // Rows render on a white/violet-tinted card, so the subtle variant has to be
    // dark — it used to be text-white/80 and was invisible.
    const baseClasses = subtle
      ? 'text-[11px] font-semibold text-violet-600 hover:underline'
      : 'inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition';

    return `
      <button
        type="button"
        class="${baseClasses}"
        data-notification-close
        data-notification-id="${item.id}"
      >
        ${subtle ? 'Mark as read' : 'Close'}
      </button>
    `;
  }

  async function markNotificationRead(notificationId) {
    if (!notificationId) {
      return false;
    }

    try {
      const response = await fetch(`/file-tracker-dashboard/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        // Some call sites navigate away in the same tick (the toast body opens
        // /notifications); keepalive stops the browser cancelling the write.
        keepalive: true,
      });

      if (!response.ok) {
        throw new Error(`Unable to mark notification as read (status ${response.status}).`);
      }

      return true;
    } catch (error) {
      console.warn('Notification read acknowledgement failed', error);
      throw error;
    }
  }

  function buildEndpoints(root) {
    const endpoints = [];
    const primary = root.dataset.endpoint;
    const fallback = root.dataset.fallbackEndpoint;
    const custom = root.dataset.endpoints;

    [primary, fallback]
      .concat(
        custom
          ? custom
              .split(',')
              .map((value) => value.trim())
              .filter(Boolean)
          : []
      )
      .forEach((endpoint) => {
        if (endpoint && !endpoints.includes(endpoint)) {
          endpoints.push(endpoint);
        }
      });

    return endpoints;
  }

  function togglePanel(panel, button, open) {
    if (!panel || !button) {
      return;
    }
    const shouldOpen =
      typeof open === 'boolean' ? open : panel.classList.contains('hidden');
    if (shouldOpen) {
      panel.classList.remove('hidden');
      button.setAttribute('aria-expanded', 'true');
      return;
    }
    panel.classList.add('hidden');
    button.setAttribute('aria-expanded', 'false');
  }

  function initNotifications() {
    const root = document.querySelector(SELECTORS.root);
    if (!root || !window.PushNotificationCenter) {
      return;
    }

    const toggleBtn = root.querySelector(SELECTORS.toggle);
    const panel = root.querySelector(SELECTORS.panel);
    const list = root.querySelector('#header-notification-list');
    const listContainer = root.querySelector('#header-notification-list-container');
    const loadingState = root.querySelector('#header-notification-loading');
    const emptyState = root.querySelector('#header-notification-empty');
    const errorState = root.querySelector('#header-notification-error');
    const badge = root.querySelector('#header-notification-badge');
    const totalCountEl = root.querySelector('#header-notification-total');
    const refreshBtn = root.querySelector('#header-notification-refresh');
    const markAllBtn = root.querySelector('#header-notification-mark-all');
    const tabButtons = Array.from(root.querySelectorAll('[data-notification-tab]'));

    let activeTab = 'all';
    let lastItems = [];

    const endpoints = buildEndpoints(root);
    const pollInterval = Number(root.dataset.pollInterval || 20000);

    const notificationCenter = window.PushNotificationCenter.create({
      endpoints,
      pollInterval,
      soundUrl: root.dataset.soundUrl,
      iconUrl: root.dataset.iconUrl,
    });

    function renderList(items) {
      if (!list) {
        return;
      }

      const visible = activeTab === 'unread' ? items.filter((item) => !item.isRead) : items;

      if (!visible.length) {
        list.innerHTML = '';
        if (emptyState) {
          const label = emptyState.querySelector('p');
          if (label) {
            label.textContent =
              activeTab === 'unread' ? 'No unread notifications.' : "You're all caught up.";
          }
          emptyState.classList.remove('hidden');
        }
        return;
      }

      emptyState?.classList.add('hidden');

      const rows = visible
        .map((item) => {
          const createdLabel = formatRelativeTime(item.createdAt || item.created_at);
          const office = item.data?.officeName || item.data?.office_name;
          const primaryActions = buildDigitalRequestActions(item) || buildAssignmentActions(item);

          return `
            <li class="flex items-start gap-3 px-3 py-3 rounded-xl transition ${
              item.isRead ? 'bg-white hover:bg-slate-50' : 'bg-violet-50/70 hover:bg-violet-50'
            }">
              ${buildAvatar(item)}
              <div class="min-w-0 flex-1">
                <p class="text-sm leading-snug">${buildHeadline(item)}</p>
                ${
                  item.body
                    ? `<p class="mt-0.5 text-xs text-slate-500 leading-snug" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${escapeHtml(item.body)}</p>`
                    : ''
                }
                <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                  ${createdLabel ? `<span>${escapeHtml(createdLabel)}</span>` : ''}
                  ${office ? `<span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full">${escapeHtml(office)}</span>` : ''}
                  ${
                    !item.isRead && !primaryActions
                      ? `<button type="button" class="text-[11px] font-semibold text-violet-600 hover:underline" data-notification-close data-notification-id="${item.id}">Mark as read</button>`
                      : ''
                  }
                </div>
                ${primaryActions}
              </div>
              ${
                item.isRead
                  ? ''
                  : '<span class="flex-shrink-0 mt-2 w-2 h-2 rounded-full bg-violet-600" aria-label="Unread"></span>'
              }
            </li>
          `;
        })
        .join('');

      list.innerHTML = rows;
    }

    // `unreadAll` is the unread count across EVERY module (the bell is scoped to
    // all modules now, but the fallback endpoint or an older cached response can
    // still answer with the file-tracking subset only). "Mark all as read" clears
    // everything the user owns, so it stays enabled while anything is unread —
    // otherwise a user whose only unread items sat outside the bell's scope could
    // never clear them, and the post-login pop-up replayed forever.
    function updateCounters(unread, total, unreadAll) {
      if (badge) {
        if (unread > 0) {
          badge.style.display = 'flex';
          badge.textContent = unread > 99 ? '99+' : unread;
        } else {
          badge.style.display = 'none';
        }
      }

      if (totalCountEl) {
        totalCountEl.textContent = unread > 9 ? '9+' : unread || 0;
        totalCountEl.style.display = unread > 0 ? 'inline-flex' : 'none';
      }

      if (markAllBtn) {
        const clearable = typeof unreadAll === 'number' ? unreadAll : unread;
        markAllBtn.disabled = !clearable;
      }
    }

    function showLoading(isLoading) {
      if (isLoading) {
        loadingState?.classList.remove('hidden');
        listContainer?.classList.add('hidden');
      } else {
        loadingState?.classList.add('hidden');
        listContainer?.classList.remove('hidden');
      }
    }

    function hideErrors() {
      if (errorState) {
        errorState.classList.add('hidden');
        errorState.textContent = '';
      }
    }

    notificationCenter
      .on('update', ({ items, unreadCount, totalCount, newItems, isInitialLoad, raw }) => {
        hideErrors();
        showLoading(false);
        lastItems = items;
        renderList(items);
        updateCounters(unreadCount, totalCount, raw?.data?.unreadCountAll);
        // Only toast items that arrived while this page was open. On the first
        // poll everything looks new, and toasting it would re-pop the same card
        // on every page load or refresh — the post-login flash queue
        // (notification-flash.js) is what surfaces the existing backlog.
        if (!isInitialLoad && newItems && newItems.length > 0) {
          showDynamicToasts(newItems, unreadCount);
        }
      })
      .on('error', (error) => {
        showLoading(false);
        if (errorState) {
          errorState.textContent = error?.isAuthError
            ? 'Authentication required to load notifications. Please refresh.'
            : (error?.message || 'Unable to load notifications right now.');
          errorState.classList.remove('hidden');
        }
      });

    showLoading(true);
    notificationCenter.start();
    window.FileTrackerNotificationCenter = notificationCenter;
    if (refreshBtn) {
      refreshBtn.addEventListener('click', (event) => {
        event.preventDefault();
        showLoading(true);
        notificationCenter.refresh();
      });
    }

    const TAB_ACTIVE = ['bg-white', 'text-slate-900', 'shadow-sm'];
    const TAB_IDLE = ['text-slate-500'];

    function applyTabStyles() {
      tabButtons.forEach((button) => {
        const isActive = button.dataset.notificationTab === activeTab;
        button.classList.remove(...TAB_ACTIVE, ...TAB_IDLE);
        button.classList.add(...(isActive ? TAB_ACTIVE : TAB_IDLE));
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }

    tabButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const next = button.dataset.notificationTab;
        if (!next || next === activeTab) {
          return;
        }
        activeTab = next;
        applyTabStyles();
        renderList(lastItems);
      });
    });

    applyTabStyles();

    if (markAllBtn) {
      markAllBtn.addEventListener('click', async (event) => {
        event.preventDefault();
        const endpoint = root.dataset.markAllEndpoint;
        if (!endpoint) {
          return;
        }

        const originalLabel = markAllBtn.innerHTML;
        markAllBtn.disabled = true;
        markAllBtn.innerHTML =
          '<span class="h-4 w-4 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>';

        try {
          const response = await fetch(endpoint, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
          });

          if (!response.ok) {
            throw new Error(`Unable to mark all as read (status ${response.status}).`);
          }

          // Everything is read server-side now, so kill the post-login flash queue
          // too: without this the pop-up kept replaying (and came back at the next
          // login) even though the user had just cleared the bell.
          window.NotificationFlash?.suppress?.();
          await notificationCenter.refresh();
        } catch (error) {
          alert(error?.message || 'Unable to mark notifications as read.');
        } finally {
          markAllBtn.innerHTML = originalLabel;
          if (window.lucide?.createIcons) {
            window.lucide.createIcons();
          }
        }
      });
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        showLoading(true);
        notificationCenter.refresh();
      }
    });

    // ── Dynamic Toast Notifications ──────────────────────────────────────────
    function getOrCreateToastContainer() {
      let container = document.querySelector('.notification-flash-container');
      if (container) {
        return container;
      }
      container = document.createElement('div');
      container.className = 'notification-flash-container';
      document.body.appendChild(container);
      return container;
    }

    function dismissToast(toast) {
      toast.classList.add('notification-flash-toast--closing');
      setTimeout(() => {
        toast.remove();
      }, 150);
    }

    function showDynamicToasts(newItems, unreadCount) {
      if (!TOASTS_ENABLED && !window.KLAES_ENABLE_NOTIFICATION_TOASTS) {
        return;
      }
      if (!Array.isArray(newItems) || !newItems.length) {
        return;
      }

      const container = getOrCreateToastContainer();

      // Show only the latest/first new item card on screen
      const item = newItems[0];

      // Remove any existing toast to replace it with the latest
      const existingToasts = container.querySelectorAll('.notification-flash-toast');
      existingToasts.forEach((t) => {
        if (t.dataset.dismissTimeoutId) {
          clearTimeout(Number(t.dataset.dismissTimeoutId));
        }
        t.remove();
      });

      const toast = document.createElement('div');
      toast.className = 'notification-flash-toast';
      toast.dataset.toastNotificationId = item.id;

      const fileNo = item.data?.fileNumber || item.data?.file_number;
      const office = item.data?.officeName || item.data?.office_name;

      const raw = item.data?.raw || {};
      const trackerId = raw.file_tracker_id || raw.file_tracking_id;
      const status = (raw.assignment_status || '').toLowerCase();
      const pendingStatuses = ['pending', 'pending_acceptance', 'awaiting_acceptance'];
      const isPendingAssignment = (!status || pendingStatuses.includes(status)) &&
        (item.type?.startsWith('file_tracking') || raw.module === 'file_tracking') &&
        trackerId;

      const isDigitalRequest = (item.type === 'digital_request' || raw.module === 'digital_request') && raw.request_id;
      const requestId = raw.request_id;

      const isInteractive = isPendingAssignment || isDigitalRequest;
      const duration = isInteractive ? 18000 : 8000;
      toast.style.setProperty('--toast-duration', `${duration}ms`);

      let actionButtonsHtml = '';
      if (isPendingAssignment) {
        actionButtonsHtml = `
          <div class="toast-action-bar">
            <button
              type="button"
              class="toast-bar-action text-emerald-600 hover:bg-slate-50 transition"
              data-action="accept"
              data-tracker-id="${trackerId}"
              data-notification-id="${item.id}"
            >
              Accept
            </button>
            <button
              type="button"
              class="toast-bar-action text-red-500 hover:bg-slate-50 transition"
              data-action="reject"
              data-tracker-id="${trackerId}"
              data-notification-id="${item.id}"
            >
              Reject
            </button>
          </div>
        `;
      } else if (isDigitalRequest) {
        actionButtonsHtml = `
          <div class="toast-action-bar">
            <button
              type="button"
              class="toast-bar-action text-violet-600 hover:bg-slate-50 transition"
              data-action="approve-dr"
              data-dr-request-id="${requestId}"
              data-notification-id="${item.id}"
            >
              Approve
            </button>
            <button
              type="button"
              class="toast-bar-action text-red-500 hover:bg-slate-50 transition"
              data-action="reject-dr"
              data-dr-request-id="${requestId}"
              data-notification-id="${item.id}"
            >
              Reject
            </button>
          </div>
        `;
      } else {
        actionButtonsHtml = `
          <div class="toast-action-bar">
            <button
              type="button"
              class="toast-bar-action text-blue-600 hover:bg-slate-50 transition"
              data-action="open-notifications"
            >
              Open
            </button>
            <button
              type="button"
              class="toast-bar-action text-slate-500 hover:bg-slate-50 transition"
              data-action="close-toast"
            >
              Close
            </button>
          </div>
        `;
      }

      toast.innerHTML = `
        <!-- Floating Bell with Badge -->
        <div class="toast-floating-bell-wrapper">
          <div class="toast-floating-bell">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2a5.006 5.006 0 0 0-5 5v4.586l-.707.707A1 1 0 0 0 7 14h10a1 1 0 0 0 .707-1.707L17 11.586V7a5.006 5.006 0 0 0-5-5zm-2 13a2 2 0 0 0 4 0h-4z"/>
            </svg>
          </div>
          ${unreadCount > 1 ? `
            <div class="toast-bell-badge">
              +${unreadCount - 1}
            </div>
          ` : ''}
        </div>

        <div class="notification-flash-toast__content text-center">
          <div class="toast-header-text">${escapeHtml(item.title || 'Notification')}</div>
          <p class="toast-body-text mt-2 px-1">${escapeHtml(item.body || '')}</p>
          <div class="mt-2.5 flex justify-center gap-1.5 text-[9px] uppercase tracking-wide text-slate-400 font-bold">
            ${fileNo ? `<span class="px-2 py-0.5 bg-slate-100 rounded-full">File ${escapeHtml(fileNo)}</span>` : ''}
            ${office ? `<span class="px-2 py-0.5 bg-slate-100 rounded-full">${escapeHtml(office)}</span>` : ''}
          </div>
        </div>

        ${actionButtonsHtml}
        <div class="notification-flash-toast__progress">
          <span class="notification-flash-toast__progress-bar"></span>
        </div>
      `;

      container.appendChild(toast);

      // Clicking anywhere on the popup card (except action or dismiss buttons) opens
      // /notifications. Acknowledging it here also clears the unread flag, so the
      // post-login flash never re-pops a card the user has already opened.
      toast.addEventListener('click', (e) => {
        if (!e.target.closest('.toast-bar-action') && !e.target.closest('.toast-dismiss')) {
          markNotificationRead(item.id).catch(() => {});
          window.location.href = '/notifications';
        }
      });

      // Establish auto-dismiss for standalone toast
      const dismissTimeout = setTimeout(() => {
        dismissToast(toast);
      }, duration);
      toast.dataset.dismissTimeoutId = dismissTimeout;

      const actionButtons = toast.querySelectorAll('.toast-bar-action');
      actionButtons.forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();
          e.stopPropagation();

          const action = btn.dataset.action;

          // Handle local informational actions
          if (action === 'open-notifications') {
            markNotificationRead(item.id).catch(() => {});
            window.location.href = '/notifications';
            return;
          }
          if (action === 'close-toast') {
            if (toast.dataset.dismissTimeoutId) {
              clearTimeout(Number(toast.dataset.dismissTimeoutId));
            }
            markNotificationRead(item.id).catch(() => {});
            dismissToast(toast);
            return;
          }

          const trackerId = btn.dataset.trackerId;
          const notificationId = Number(btn.dataset.notificationId || '');
          const drRequestId = btn.dataset.drRequestId;

          if (toast.dataset.dismissTimeoutId) {
            clearTimeout(Number(toast.dataset.dismissTimeoutId));
          }

          actionButtons.forEach((b) => (b.disabled = true));

          const originalLabel = btn.innerHTML;
          btn.innerHTML = '<span class="inline-flex items-center gap-1"><span class="h-3.5 w-3.5 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span></span>';

          try {
            if (action === 'accept' || action === 'reject') {
              let note = '';
              if (action === 'reject') {
                note = window.prompt('Enter a reason for rejecting (optional):', '');
                if (note === null) {
                  actionButtons.forEach((b) => (b.disabled = false));
                  btn.innerHTML = originalLabel;
                  return;
                }
              }

              await performAssignmentAction(trackerId, action, {
                note: note,
                notificationId,
              });

              if (notificationId) {
                try { await markNotificationRead(notificationId); } catch (_) {}
              }

              btn.innerHTML = '✓ Done';
            } else if (action === 'approve-dr' || action === 'reject-dr') {
              const act = action === 'approve-dr' ? 'approve' : 'reject';
              let rejectionReason = '';
              if (act === 'reject') {
                rejectionReason = window.prompt('Enter rejection reason:', '');
                if (rejectionReason === null) {
                  actionButtons.forEach((b) => (b.disabled = false));
                  btn.innerHTML = originalLabel;
                  return;
                }
                if (!rejectionReason.trim()) {
                  alert('Rejection reason is required.');
                  actionButtons.forEach((b) => (b.disabled = false));
                  btn.innerHTML = originalLabel;
                  return;
                }
              }

              const res = await fetch(`/digital-request/${act}/${drRequestId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(act === 'reject' ? { rejection_reason: rejectionReason } : {}),
              });
              const data = await res.json().catch(() => ({}));
              if (!res.ok || data?.success === false) throw new Error(data?.message || 'Request failed.');

              if (notificationId) {
                try { await markNotificationRead(notificationId); } catch (_) {}
              }

              btn.innerHTML = '✓ Done';
            }

            if (window.FileTrackerNotificationCenter) {
              window.FileTrackerNotificationCenter.refresh();
            }

            setTimeout(() => {
              dismissToast(toast);
            }, 1000);

          } catch (err) {
            alert(err?.message || 'Unable to complete action.');
            actionButtons.forEach((b) => (b.disabled = false));
            btn.innerHTML = originalLabel;
          }
        });
      });
    }

    async function performAssignmentAction(trackerId, action, options = {}) {
      const endpoint =
        action === 'accept'
          ? `/api/file-trackers/${trackerId}/accept`
          : `/api/file-trackers/${trackerId}/reject`;

      const payload = {
        notification_id: options.notificationId ?? null,
      };

      if (action === 'reject') {
        payload.note = options.note || null;
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data?.success === false) {
        throw new Error(data?.message || 'Unable to update assignment.');
      }

      return data;
    }

    root.addEventListener('click', async (event) => {
      const closeButton = event.target.closest('[data-notification-close]');
      if (closeButton) {
        event.preventDefault();
        const notificationId = Number(closeButton.dataset.notificationId || '');
        if (!notificationId) {
          return;
        }

        const previousLabel = closeButton.innerHTML;
        closeButton.dataset.originalLabel = previousLabel;
        closeButton.innerHTML = '<span class="inline-flex items-center gap-1 text-[11px] uppercase tracking-wide text-gray-500"><span class="h-3 w-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>Updating...</span>';
        closeButton.disabled = true;

        try {
          await markNotificationRead(notificationId);
          notificationCenter.refresh();
        } catch (error) {
          alert('Unable to update notification status. Please try again.');
        } finally {
          closeButton.disabled = false;
          closeButton.innerHTML = closeButton.dataset.originalLabel || previousLabel;
          delete closeButton.dataset.originalLabel;
        }

        return;
      }

      // ── Digital Request: Approve / Reject ──────────────────────────────────
      const drButton = event.target.closest('[data-dr-action]');
      if (drButton) {
        event.preventDefault();
        const requestId     = drButton.dataset.drRequestId;
        const action        = drButton.dataset.drAction;
        const notifId       = Number(drButton.dataset.notificationId || '');

        if (!requestId || !action) return;

        let rejectionReason = '';
        if (action === 'reject') {
          rejectionReason = window.prompt('Enter rejection reason:', '');
          if (rejectionReason === null) return; // user cancelled
          if (!rejectionReason.trim()) { alert('Rejection reason is required.'); return; }
        }

        const originalLabel = drButton.innerHTML;
        drButton.disabled   = true;
        drButton.innerHTML  = '<span class="inline-flex items-center gap-1"><span class="h-4 w-4 border-2 border-white/40 border-t-transparent rounded-full animate-spin"></span>Processing...</span>';

        try {
          const res = await fetch(`/digital-request/${action}/${requestId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(action === 'reject' ? { rejection_reason: rejectionReason } : {}),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || data?.success === false) throw new Error(data?.message || 'Request failed.');

          if (notifId) {
            try { await markNotificationRead(notifId); } catch (_) { /* non-fatal */ }
          }
          notificationCenter.refresh();
          if (window.showNotification) {
            window.showNotification(
              action === 'approve' ? 'File request approved successfully.' : 'File request rejected.',
              action === 'approve' ? 'success' : 'warning'
            );
          }
        } catch (err) {
          alert(err?.message || 'Unable to process request.');
          drButton.disabled  = false;
          drButton.innerHTML = originalLabel;
        }
        return;
      }

      const actionButton = event.target.closest('[data-notification-action]');
      if (!actionButton) {
        return;
      }

      event.preventDefault();

      const trackerId = Number(actionButton.dataset.trackerId || '');
      const action = actionButton.dataset.notificationAction;
      const notificationId = Number(actionButton.dataset.notificationId || '');

      if (!trackerId || !action) {
        return;
      }

      if (action === 'reject') {
        const note = window.prompt('Enter a reason for rejecting (optional):', '');
        if (note === null) {
          return;
        }
        actionButton.dataset.note = note;
      }

      const originalLabel = actionButton.innerHTML;
      actionButton.disabled = true;
      actionButton.innerHTML = '<span class="inline-flex items-center gap-1"><span class="h-4 w-4 border-2 border-white/40 border-t-transparent rounded-full animate-spin"></span>Processing...</span>';

      try {
        await performAssignmentAction(trackerId, action, {
          note: actionButton.dataset.note,
          notificationId,
        });
        if (notificationId) {
          try {
            await markNotificationRead(notificationId);
          } catch (markError) {
            console.warn('Assignment updated but notification could not be marked read', markError);
          }
        }
        notificationCenter.refresh();
        if (window.showNotification) {
          window.showNotification(
            `Assignment ${action === 'accept' ? 'accepted' : 'rejected'} successfully.`,
            action === 'accept' ? 'success' : 'warning'
          );
        }
      } catch (error) {
        alert(error?.message || 'Unable to update assignment.');
      } finally {
        delete actionButton.dataset.note;
        actionButton.disabled = false;
        actionButton.innerHTML = originalLabel;
      }
    });

    if (toggleBtn && panel) {
      toggleBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = panel.classList.contains('hidden');
        togglePanel(panel, toggleBtn, willOpen);
        if (willOpen) {
          notificationCenter.requestBrowserPermission();
        }
      });

      document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
          togglePanel(panel, toggleBtn, false);
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          togglePanel(panel, toggleBtn, false);
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initNotifications);
})(window, document);
