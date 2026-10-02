(function (window, document) {
  'use strict';

  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

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

  function formatRelativeTime(isoString) {
    if (!isoString) return '';
    const timestamp = Date.parse(isoString);
    if (Number.isNaN(timestamp)) return '';
    const diffSeconds = Math.floor((Date.now() - timestamp) / 1000);
    if (diffSeconds < 45) return 'Just now';
    if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)}m ago`;
    if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)}h ago`;
    if (diffSeconds < 604800) return `${Math.floor(diffSeconds / 86400)}d ago`;
    return new Date(timestamp).toLocaleDateString();
  }

  function encodeQuery(params) {
    return Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null && value !== '')
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');
  }

  function notificationTemplate(item) {
    const meta = item.meta || {};
    const chips = [];
    if (meta.fileNumber) chips.push(`File ${escapeHtml(meta.fileNumber)}`);
    if (meta.fileName) chips.push(escapeHtml(meta.fileName));
    if (item.type) chips.push(escapeHtml(item.type.replace(/[_-]/g, ' ')));
    const badges = [];
    if (item.createdAt) badges.push(formatRelativeTime(item.createdAt));

    return `
      <article class="notification-item ${item.isRead ? '' : 'notification-item--unread'}" data-notification-id="${item.id}">
        <div class="notification-item__pin ${item.isRead ? '' : 'notification-item__pin--active'}"></div>
        <div class="notification-item__icon">
          <i data-lucide="${item.icon || 'bell'}" class="h-5 w-5"></i>
        </div>
        <div class="notification-item__body">
          <div class="notification-item__header">
            <p class="notification-item__title">${escapeHtml(item.title)}</p>
            <span class="notification-item__time">${formatRelativeTime(item.createdAt)}</span>
          </div>
          <p class="notification-item__message">${escapeHtml(item.body)}</p>
          <div class="notification-item__chips">
            ${chips.map((label) => `<span>${label}</span>`).join('')}
          </div>
          <div class="notification-item__meta">
            ${badges.map((label) => `<span>${label}</span>`).join('')}
          </div>
          <div class="notification-item__actions">
            <button class="btn btn-outline btn-xs" data-action="toggle-read" data-notification-id="${item.id}" data-read="${item.isRead ? '1' : '0'}">
              ${item.isRead ? 'Mark as unread' : 'Mark as read'}
            </button>
            <button class="btn btn-outline btn-xs text-red-600 border-red-200" data-action="delete" data-notification-id="${item.id}">
              Delete
            </button>
          </div>
        </div>
      </article>
    `;
  }

  function initNotificationCenter() {
    const root = document.getElementById('notification-center-app');
    if (!root) return;

    const config = {
      listEndpoint: root.dataset.listEndpoint,
      markReadBase: root.dataset.markReadBase,
      markUnreadBase: root.dataset.markUnreadBase,
      deleteBase: root.dataset.deleteBase,
      markAllEndpoint: root.dataset.markAllEndpoint,
      settingsEndpoint: root.dataset.settingsEndpoint,
      settingsUpdateEndpoint: root.dataset.settingsUpdateEndpoint,
      csrf: (window.NotificationCenterConfig && window.NotificationCenterConfig.csrf) || document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    const state = {
      page: 1,
      status: 'all',
      search: '',
      perPage: 15,
      totalPages: 1,
    };

    const listContainer = $('#notification-list-container', root);
    const paginationEl = $('#notification-pagination', root);
    const searchInput = root.querySelector('[data-action="search-input"]');
    const unreadCountEl = document.getElementById('notification-unread-count');
    const statusLabel = root.querySelector('[data-state="status-label"]');

    async function request(url, options = {}) {
      const response = await fetch(url, {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': config.csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        ...options,
      });
      if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `Request failed (${response.status})`);
      }
      return response.json();
    }

    function setStatusLabel(text) {
      if (statusLabel) {
        statusLabel.textContent = text;
      }
    }

    function renderList(items) {
      if (!items.length) {
        listContainer.innerHTML = `
          <div class="notification-empty">
            <i data-lucide="bell-off" class="h-8 w-8 text-gray-400 mb-3"></i>
            <p>No notifications match your filters.</p>
            <p class="text-xs text-gray-500 mt-1">Try switching filters or clearing your search.</p>
          </div>
        `;
        return;
      }
      listContainer.innerHTML = items.map(notificationTemplate).join('');
    }

    function renderPagination(pagination) {
      if (!pagination) {
        paginationEl.innerHTML = '';
        return;
      }

      state.totalPages = pagination.lastPage;

      const prevDisabled = state.page <= 1 ? 'disabled' : '';
      const nextDisabled = state.page >= pagination.lastPage ? 'disabled' : '';

      paginationEl.innerHTML = `
        <button class="btn btn-outline btn-xs" data-action="prev-page" ${prevDisabled}>Previous</button>
        <span>Page ${pagination.currentPage} of ${pagination.lastPage}</span>
        <button class="btn btn-outline btn-xs" data-action="next-page" ${nextDisabled}>Next</button>
      `;
    }

    function updateCounters(unreadCount) {
      if (unreadCountEl) {
        unreadCountEl.textContent = unreadCount;
      }
    }

    async function fetchNotifications() {
      const params = {
        page: state.page,
        perPage: state.perPage,
        status: state.status,
        search: state.search,
      };
      const url = `${config.listEndpoint}?${encodeQuery(params)}`;
      listContainer.innerHTML = `
        <div class="notification-empty">
          <i data-lucide="loader-2" class="h-6 w-6 text-blue-500 animate-spin mb-2"></i>
          <p>Loading notifications...</p>
        </div>
      `;
      setStatusLabel(`Loading ${state.status === 'all' ? 'all' : state.status} notifications...`);

      try {
        const payload = await request(url);
        const data = payload.data || {};
        renderList(data.items || []);
        renderPagination(data.pagination || null);
        updateCounters(data.unreadCount || 0);
        const statusMap = {
          all: 'Showing latest updates',
          unread: 'Filtering unread alerts',
          read: 'Viewing archive',
        };
        setStatusLabel(statusMap[state.status] || 'Showing latest updates');
        if (window.lucide?.createIcons) {
          window.lucide.createIcons();
        }
      } catch (error) {
        listContainer.innerHTML = `
          <div class="notification-empty text-red-500">
            ${escapeHtml(error.message || 'Unable to load notifications.')}
          </div>
        `;
        setStatusLabel('Unable to load notifications');
      }
    }

    async function toggleRead(notificationId, isRead) {
      const base = isRead ? config.markUnreadBase : config.markReadBase;
      await request(`${base}/${notificationId}/${isRead ? 'unread' : 'read'}`, {
        method: 'PATCH',
      });
    }

    async function deleteNotification(notificationId) {
      await request(`${config.deleteBase}/${notificationId}`, {
        method: 'DELETE',
      });
    }

    async function handleSettingsDrawer(open) {
      const drawer = document.querySelector('[data-settings-drawer]');
      if (!drawer) return;
      if (open) {
        drawer.classList.add('active');
        try {
          const payload = await request(config.settingsEndpoint);
          const form = $('#notification-settings-form');
          if (form && payload?.data) {
            const data = payload.data;
            $$('input[type="checkbox"]', form).forEach((input) => {
              const name = input.name;
              if (!name) return;
              input.checked = Boolean(data[name]);
            });
          }
        } catch (error) {
          console.warn('Unable to load notification settings', error);
        }
        return;
      }
      drawer.classList.remove('active');
    }

    function attachEvents() {
      root.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-action]');
        if (!action) return;

        const actionType = action.dataset.action;

        if (actionType === 'refresh-list') {
          fetchNotifications();
        }

        if (actionType === 'mark-all-read') {
          action.disabled = true;
          try {
            await request(config.markAllEndpoint, { method: 'PATCH' });
            // Nothing is unread any more, so the post-login flash queue must not
            // replay this backlog on the next login either.
            window.NotificationFlash?.suppress?.();
            fetchNotifications();
          } catch (error) {
            alert(error.message || 'Unable to mark notifications.');
          } finally {
            action.disabled = false;
          }
        }

        if (actionType === 'open-settings') {
          handleSettingsDrawer(true);
        }

        if (actionType === 'close-settings') {
          handleSettingsDrawer(false);
        }

        if (actionType === 'prev-page' && state.page > 1) {
          state.page -= 1;
          fetchNotifications();
        }

        if (actionType === 'next-page' && state.page < state.totalPages) {
          state.page += 1;
          fetchNotifications();
        }
      });

      if (listContainer) {
        listContainer.addEventListener('click', async (event) => {
          const button = event.target.closest('[data-action]');
          if (!button) return;
          const action = button.dataset.action;
          const notificationId = Number(button.dataset.notificationId);
          if (!notificationId) return;

          if (action === 'toggle-read') {
            button.disabled = true;
            const currentlyRead = button.dataset.read === '1';
            try {
              await toggleRead(notificationId, currentlyRead);
              fetchNotifications();
            } catch (error) {
              alert(error.message || 'Unable to update notification.');
            } finally {
              button.disabled = false;
            }
          }

          if (action === 'delete') {
            if (!window.confirm('Delete this notification?')) {
              return;
            }
            button.disabled = true;
            try {
              await deleteNotification(notificationId);
              fetchNotifications();
            } catch (error) {
              alert(error.message || 'Unable to delete notification.');
            } finally {
              button.disabled = false;
            }
          }
        });
      }

      const form = $('#notification-settings-form');
      if (form) {
        form.addEventListener('submit', async (event) => {
          event.preventDefault();
          const payload = {};
          $$('input', form).forEach((input) => {
            if (input.type === 'checkbox') {
              payload[input.name] = input.checked;
            } else {
              payload[input.name] = input.value;
            }
          });

          const submitBtn = form.querySelector('button[type="submit"]');
          submitBtn.disabled = true;
          submitBtn.textContent = 'Saving...';

          try {
            await request(config.settingsUpdateEndpoint, {
              method: 'PUT',
              body: JSON.stringify(payload),
            });
            handleSettingsDrawer(false);
          } catch (error) {
            alert(error.message || 'Unable to save settings.');
          } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save changes';
          }
        });
      }

      if (searchInput) {
        searchInput.addEventListener('keyup', (event) => {
          if (event.key === 'Enter') {
            state.search = searchInput.value.trim();
            state.page = 1;
            fetchNotifications();
          }
        });
      }

      $$('.filter-pill', root).forEach((pill) => {
        pill.addEventListener('click', () => {
          $$('.filter-pill', root).forEach((node) => node.classList.remove('active'));
          pill.classList.add('active');
          state.status = pill.dataset.filter || 'all';
          state.page = 1;
          fetchNotifications();
        });
      });
    }

    attachEvents();
    fetchNotifications();
  }

  document.addEventListener('DOMContentLoaded', initNotificationCenter);
})(window, document);
