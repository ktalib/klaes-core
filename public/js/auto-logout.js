(function (window, document) {
  'use strict';

  const DEFAULTS = {
    enabled: true,
    inactivityLimit: 3 * 60 * 1000,
    warningOffset: 30 * 1000,
    logoutFormSelector: '#autoLogoutForm',
    statusSelector: '#autoLogoutStatus',
    homeUrl: '/',
  };

  /*
   | Idle time belongs to the person, not to one tab. Each tab used to run its own
   | timer off its own mouse and keyboard, so a KLAES tab left open behind the one
   | being worked in reached the limit and signed the whole session out — about an
   | hour after sign-in, mid-task, and several background tabs at the same second.
   | Every tab now stamps the same value here and asks it before acting.
   */
  const ACTIVITY_KEY = 'klaes.autoLogout.lastActivity';
  const ACTIVITY_WRITE_INTERVAL = 5 * 1000;

  const AutoLogout = {
    init(options = {}) {
      this.config = Object.assign({}, DEFAULTS, options);
      this.logoutForm = document.querySelector(this.config.logoutFormSelector);
      this.statusIndicator = document.querySelector(this.config.statusSelector);
      this.warningTimer = null;
      this.logoutTimer = null;
      this.warningModal = null;
      this.active = Boolean(this.config.enabled);
      this.lastActivity = Date.now();
      this.lastActivityWrite = 0;

      if (!this.active) {
        this.hideStatusIndicator();
        return;
      }

      this.showStatusIndicator();
      this.attachActivityListeners();
      // A page that has just loaded is activity: it also rescues a tab left over
      // from before this sign-in, which would otherwise act on the old value.
      this.recordActivity();
    },

    attachActivityListeners() {
      const events = [
        'mousedown',
        'mousemove',
        'keydown',
        'keypress',
        'scroll',
        'touchstart',
        'click',
      ];
      events.forEach((eventName) => {
        document.addEventListener(
          eventName,
          () => this.recordActivity(),
          // Capture: scrolling inside a table or panel never bubbles to document.
          { passive: true, capture: true }
        );
      });

      window.addEventListener('storage', (event) => {
        if (event.key === ACTIVITY_KEY) {
          this.resetTimers();
        }
      });
    },

    recordActivity() {
      if (!this.active) {
        return;
      }

      const now = Date.now();
      this.lastActivity = now;

      if (now - this.lastActivityWrite >= ACTIVITY_WRITE_INTERVAL) {
        this.lastActivityWrite = now;
        try {
          window.localStorage.setItem(ACTIVITY_KEY, String(now));
        } catch (error) {
          // Storage blocked: this tab falls back to its own activity alone.
        }
      }

      this.resetTimers();
    },

    // Latest activity in any tab of this browser.
    latestActivity() {
      let shared = 0;
      try {
        shared = Number(window.localStorage.getItem(ACTIVITY_KEY)) || 0;
      } catch (error) {
        shared = 0;
      }

      return Math.max(this.lastActivity, shared);
    },

    remainingTime() {
      return this.config.inactivityLimit - (Date.now() - this.latestActivity());
    },

    showStatusIndicator() {
      if (this.statusIndicator) {
        this.statusIndicator.classList.remove('hidden');
        this.statusIndicator.title = 'Auto logout enabled';
      }
    },

    hideStatusIndicator() {
      if (this.statusIndicator) {
        this.statusIndicator.classList.add('hidden');
        this.statusIndicator.title = 'Auto logout disabled';
      }
    },

    resetTimers() {
      if (!this.active) {
        return;
      }

      window.clearTimeout(this.warningTimer);
      window.clearTimeout(this.logoutTimer);

      this.hideWarning();

      const remaining = Math.max(0, this.remainingTime());

      this.warningTimer = window.setTimeout(
        () => this.showWarning(),
        Math.max(0, remaining - this.config.warningOffset)
      );

      this.logoutTimer = window.setTimeout(
        () => this.performLogout(),
        remaining
      );
    },

    showWarning() {
      if (this.warningModal) {
        return;
      }

      // Activity in another tab since this timer was set: no warning is due.
      if (this.remainingTime() > this.config.warningOffset) {
        this.resetTimers();
        return;
      }

      const modal = document.createElement('div');
      modal.id = 'autoLogoutWarning';
      modal.className =
        'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
          <div class="flex items-center mb-4">
            <div class="bg-yellow-100 rounded-full p-2 mr-3">
              <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Session Timeout Warning</h3>
          </div>
          <p class="text-gray-600 mb-6">Your session will expire in <span id="autoLogoutCountdown" class="font-bold text-red-600">${Math.floor(
            this.config.warningOffset / 1000
          )}</span> seconds due to inactivity.</p>
          <div class="flex space-x-3">
            <button id="autoLogoutStayLoggedIn" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              Stay Logged In
            </button>
            <button id="autoLogoutLogoutNow" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-400">
              Logout Now
            </button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      this.warningModal = modal;

      const stayButton = modal.querySelector('#autoLogoutStayLoggedIn');
      const logoutNowButton = modal.querySelector('#autoLogoutLogoutNow');
      const countdownElement = modal.querySelector('#autoLogoutCountdown');
      let countdown = Math.floor(this.config.warningOffset / 1000);

      this.countdownInterval = window.setInterval(() => {
        countdown -= 1;
        if (countdownElement) {
          countdownElement.textContent = Math.max(countdown, 0);
        }
        if (countdown <= 0) {
          window.clearInterval(this.countdownInterval);
        }
      }, 1000);

      stayButton?.addEventListener('click', () => {
        this.hideWarning();
        this.lastActivityWrite = 0;
        this.recordActivity();
      });

      logoutNowButton?.addEventListener('click', () => {
        this.performLogout(true);
      });
    },

    hideWarning() {
      if (this.warningModal) {
        this.warningModal.remove();
        this.warningModal = null;
      }
      window.clearInterval(this.countdownInterval);
    },

    performLogout(immediate = false) {
      // The last check before a sign-out that cannot be taken back. A background
      // tab's timer fires late and on its own schedule, so the storage event
      // alone is not enough to rely on here.
      if (!immediate && this.remainingTime() > 0) {
        this.resetTimers();
        return;
      }

      if (this.logoutForm) {
        this.logoutForm.submit();
      } else {
        window.location.href = this.config.homeUrl || '/';
      }
    },
  };

  window.AutoLogout = AutoLogout;

  document.addEventListener('DOMContentLoaded', () => {
    const headerRoot = document.querySelector('[data-header-root]');
    const timeoutSeconds = headerRoot?.dataset.autoLogoutTimeout;
    const warningSeconds = headerRoot?.dataset.autoLogoutWarning;

    AutoLogout.init({
      enabled: headerRoot?.dataset.autoLogoutEnabled === 'true',
      homeUrl: headerRoot?.dataset.homeUrl || '/',
      inactivityLimit: timeoutSeconds ? Number(timeoutSeconds) * 1000 : undefined,
      warningOffset: warningSeconds ? Number(warningSeconds) * 1000 : undefined,
    });
  });
})(window, document);
