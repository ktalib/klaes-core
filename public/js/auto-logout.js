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

  const AutoLogout = {
    init(options = {}) {
      this.config = Object.assign({}, DEFAULTS, options);
      this.logoutForm = document.querySelector(this.config.logoutFormSelector);
      this.statusIndicator = document.querySelector(this.config.statusSelector);
      this.warningTimer = null;
      this.logoutTimer = null;
      this.warningModal = null;
      this.active = Boolean(this.config.enabled);

      if (!this.active) {
        this.hideStatusIndicator();
        return;
      }

      this.showStatusIndicator();
      this.attachActivityListeners();
      this.resetTimers();
    },

    attachActivityListeners() {
      const events = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click',
      ];
      events.forEach((eventName) => {
        document.addEventListener(
          eventName,
          () => this.resetTimers(),
          { passive: true }
        );
      });
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

      const warningDelay = Math.max(
        0,
        this.config.inactivityLimit - this.config.warningOffset
      );

      this.warningTimer = window.setTimeout(
        () => this.showWarning(),
        warningDelay
      );

      this.logoutTimer = window.setTimeout(
        () => this.performLogout(),
        this.config.inactivityLimit
      );
    },

    showWarning() {
      if (this.warningModal) {
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
        this.resetTimers();
      });

      logoutNowButton?.addEventListener('click', () => {
        this.performLogout();
      });
    },

    hideWarning() {
      if (this.warningModal) {
        this.warningModal.remove();
        this.warningModal = null;
      }
      window.clearInterval(this.countdownInterval);
    },

    performLogout() {
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
