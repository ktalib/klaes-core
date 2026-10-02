(function (window, document) {
  'use strict';

  const DEFAULT_POLL_INTERVAL = 20000;

  const DEFAULTS = {
    endpoints: [],
    pollInterval: DEFAULT_POLL_INTERVAL,
    fetchOptions: { credentials: 'same-origin' },
    soundUrl: null,
    iconUrl: null,
    maxItems: 20,
  };

  const EVENT_TYPES = ['update', 'error'];

  function PushNotificationCenter(options = {}) {
    this.config = Object.assign({}, DEFAULTS, options);
    this.config.endpoints = Array.isArray(this.config.endpoints)
      ? this.config.endpoints.filter(Boolean)
      : [];

    this.state = {
      timerId: null,
      previousIds: new Set(),
      initialised: false,
      lastEndpointIndex: -1,
      audio: null,
      audioPrimed: false,
    };

    this.listeners = {
      update: [],
      error: [],
    };

    this.ensureAudio();
    this.ensureBrowserPermissionPrime();
  }

  PushNotificationCenter.prototype.ensureAudio = function () {
    if (!this.config.soundUrl) {
      return;
    }

    try {
      this.state.audio = new Audio(this.config.soundUrl);
      this.state.audio.preload = 'auto';
      const primeAudio = () => {
        if (!this.state.audio || this.state.audioPrimed) {
          return;
        }
        const audio = this.state.audio;
        audio.volume = 0.7;
        audio
          .play()
          .then(() => {
            audio.pause();
            audio.currentTime = 0;
            this.state.audioPrimed = true;
          })
          .catch(() => {
            // Autoplay restrictions – will retry on next gesture.
          });
      };
      document.addEventListener('click', primeAudio, { once: true });
      document.addEventListener('keydown', primeAudio, { once: true });
    } catch (error) {
      console.warn('Unable to initialise notification audio', error);
      this.state.audio = null;
    }
  };

  PushNotificationCenter.prototype.ensureBrowserPermissionPrime = function () {
    if (!('Notification' in window)) {
      return;
    }

    const requestPermission = () => {
      if (Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {
          /* ignored */
        });
      }
    };

    document.addEventListener('click', requestPermission, { once: true });
    document.addEventListener('keydown', requestPermission, { once: true });
  };

  PushNotificationCenter.prototype.on = function (event, handler) {
    if (!EVENT_TYPES.includes(event) || typeof handler !== 'function') {
      return this;
    }
    this.listeners[event].push(handler);
    return this;
  };

  PushNotificationCenter.prototype.emit = function (event, payload) {
    const handlers = this.listeners[event] || [];
    handlers.forEach((handler) => {
      try {
        handler(payload);
      } catch (error) {
        console.error('PushNotificationCenter listener error', error);
      }
    });
  };

  PushNotificationCenter.prototype.nextEndpoint = function () {
    if (!this.config.endpoints.length) {
      return null;
    }
    const index =
      (this.state.lastEndpointIndex + 1) % this.config.endpoints.length;
    this.state.lastEndpointIndex = index;
    return this.config.endpoints[index];
  };

  PushNotificationCenter.prototype.fetchOnce = async function (endpoint) {
    const response = await fetch(endpoint, {
      headers: { Accept: 'application/json' },
      ...this.config.fetchOptions,
    });

    if (response.status === 401 || response.status === 419) {
      const error = new Error('Authentication required');
      error.isAuthError = true;
      error.status = response.status;
      throw error;
    }

    if (!response.ok) {
      const error = new Error('Unable to load notifications');
      error.status = response.status;
      throw error;
    }

    const payload = await response.json();
    if (!payload || payload.success === false) {
      const error = new Error(
        payload?.message || 'Unable to load notifications'
      );
      throw error;
    }

    return payload;
  };

  PushNotificationCenter.prototype.playSound = function () {
    if (!this.state.audio) {
      return;
    }
    try {
      this.state.audio.currentTime = 0;
      const playPromise = this.state.audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(() => {
          /* autoplay prevented */
        });
      }
    } catch (error) {
      console.warn('Unable to play notification audio', error);
    }
  };

  PushNotificationCenter.prototype.showBrowserNotification = function (item) {
    if (
      !item ||
      !('Notification' in window) ||
      Notification.permission !== 'granted'
    ) {
      return;
    }

    try {
      const notification = new Notification(
        item.title || 'File tracker update',
        {
          body: item.body || '',
          tag: item.id ? `file-tracker-${item.id}` : undefined,
          icon: this.config.iconUrl || item.icon,
          badge: this.config.iconUrl || item.badge,
          renotify: false,
          data: item,
        }
      );

      notification.onclick = () => {
        try {
          window.focus();
        } catch (error) {
          /* ignored */
        }
        notification.close();
      };
    } catch (error) {
      console.warn('Unable to show browser notification', error);
    }
  };

  PushNotificationCenter.prototype.broadcastBrowserNotifications =
    function (items) {
      if (!Array.isArray(items) || !items.length) {
        return;
      }
      items.forEach((item) => this.showBrowserNotification(item));
    };

  PushNotificationCenter.prototype.handlePayload = function (payload) {
    const items = Array.isArray(payload?.data?.items)
      ? payload.data.items
      : [];
    const unreadCount = payload?.data?.unreadCount ?? 0;
    const totalCount =
      payload?.data?.count ?? payload?.data?.total ?? items.length;

    const currentIds = new Set(items.map((item) => item.id));
    const newItems = items.filter(
      (item) => item && !this.state.previousIds.has(item.id)
    );

    // The first payload after a page load has an empty previousIds set, so
    // every item looks "new". Flag it so listeners can skip announcing a
    // backlog the user has already seen.
    const isInitialLoad = !this.state.initialised;

    const shouldPlaySound =
      !isInitialLoad && newItems.length > 0 && this.state.audioPrimed;

    if (isInitialLoad) {
      this.state.initialised = true;
    }

    if (shouldPlaySound) {
      this.playSound();
    }

    if (newItems.length && !isInitialLoad) {
      this.broadcastBrowserNotifications(newItems.slice(0, 3));
    }

    this.state.previousIds = currentIds;

    this.emit('update', {
      items,
      unreadCount,
      totalCount,
      newItems,
      isInitialLoad,
      raw: payload,
    });
  };

  PushNotificationCenter.prototype.fetch = async function () {
    if (!this.config.endpoints.length) {
      return;
    }

    const attempts = this.config.endpoints.length;
    let lastError = null;

    for (let attempt = 0; attempt < attempts; attempt += 1) {
      const endpoint = this.nextEndpoint();
      try {
        const payload = await this.fetchOnce(endpoint);
        this.handlePayload(payload);
        return;
      } catch (error) {
        lastError = error;
        if (!error.isAuthError) {
          break;
        }
      }
    }

    if (lastError) {
      this.emit('error', lastError);
    }
  };

  PushNotificationCenter.prototype.start = function () {
    if (this.state.timerId) {
      return this;
    }
    this.fetch();
    this.state.timerId = window.setInterval(
      () => this.fetch(),
      this.config.pollInterval
    );
    return this;
  };

  PushNotificationCenter.prototype.stop = function () {
    if (this.state.timerId) {
      window.clearInterval(this.state.timerId);
      this.state.timerId = null;
    }
    return this;
  };

  PushNotificationCenter.prototype.refresh = function () {
    return this.fetch();
  };

  PushNotificationCenter.prototype.requestBrowserPermission = function () {
    if (!('Notification' in window)) {
      return;
    }

    if (Notification.permission === 'default') {
      Notification.requestPermission().catch(() => {
        /* ignored */
      });
    }
  };

  window.PushNotificationCenter = {
    create(options) {
      return new PushNotificationCenter(options);
    },
  };
})(window, document);
