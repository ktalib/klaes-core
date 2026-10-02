(function (window, document) {
  'use strict';

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  const WelcomePopup = {
    init(options = {}) {
      this.root = document.querySelector(options.selector || '#welcomePopup');
      if (!this.root) {
        return;
      }

      this.options = Object.assign(
        {
          continueSelector: '#continueBtn',
          closeSelector: '#closePopup',
          learnMoreSelector: '#learnMoreBtn',
          usernameSelector: '#username',
          shouldShow: false,
          forceShow: false,
          testEnabled: false,
          markAsShownUrl: null,
        },
        options
      );

      this.bindEvents();

      if (this.options.forceShow || this.options.shouldShow || this.options.testEnabled) {
        this.show();
      }
    },

    bindEvents() {
      const continueBtn = document.querySelector(this.options.continueSelector);
      const closeBtn = document.querySelector(this.options.closeSelector);
      const learnMoreBtn = document.querySelector(this.options.learnMoreSelector);
      const usernameSpan = document.querySelector(this.options.usernameSelector);

      if (usernameSpan && this.options.username) {
        usernameSpan.textContent = this.options.username;
      }

      if (continueBtn) {
        continueBtn.addEventListener('click', () => this.hide());
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', () => this.hide());
      }

      if (learnMoreBtn) {
        learnMoreBtn.addEventListener('click', () => {
          window.alert('This would take you to more information about KLAES.');
        });
      }

      this.root.addEventListener('click', (event) => {
        if (event.target === this.root) {
          this.hide();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && this.root.classList.contains('active')) {
          this.hide();
        }
      });
    },

    show() {
      if (!this.root) {
        return;
      }

      this.root.classList.add('active');
      this.markAsShown();
    },

    hide() {
      if (!this.root) {
        return;
      }

      this.root.classList.remove('active');
    },

    markAsShown() {
      if (!this.options.markAsShownUrl) {
        return;
      }

      const csrf = getCsrfToken();

      fetch(this.options.markAsShownUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({ shown: true }),
      }).catch(() => {
        /* ignored */
      });
    },
  };

  window.WelcomePopup = WelcomePopup;

  document.addEventListener('DOMContentLoaded', () => {
    const popup = document.querySelector('#welcomePopup');
    if (!popup) {
      return;
    }

    WelcomePopup.init({
      selector: '#welcomePopup',
      username: popup.dataset.username,
      markAsShownUrl: popup.dataset.markUrl,
      shouldShow: popup.dataset.shouldShow === 'true',
      forceShow: popup.dataset.forceShow === 'true',
      testEnabled: popup.dataset.testEnabled === 'true',
      continueSelector: '#continueBtn',
      closeSelector: '#closePopup',
      learnMoreSelector: '#learnMoreBtn',
    });
  });
})(window, document);
