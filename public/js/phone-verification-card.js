(function (window, document) {
  'use strict';

  var PhoneVerificationCard = {
    init: function () {
      this.root = document.getElementById('phoneVerificationCard');
      if (!this.root) {
        return;
      }

      this.sendUrl = this.root.getAttribute('data-send-url');
      this.verifyUrl = this.root.getAttribute('data-verify-url');
      this.codeLength = parseInt(this.root.getAttribute('data-code-length'), 10) || 6;


      this.stepTarget = document.getElementById('pvcStepTarget');
      this.stepCode = document.getElementById('pvcStepCode');
      this.emailInput = document.getElementById('pvcEmailInput');
      this.phoneInput = document.getElementById('pvcPhoneInput');
      this.codeInput = document.getElementById('pvcCodeInput');
      this.maskedTarget = document.getElementById('pvcMaskedTarget');
      this.sendBtn = document.getElementById('pvcSendBtn');
      this.verifyBtn = document.getElementById('pvcVerifyBtn');
      this.resendBtn = document.getElementById('pvcResendBtn');
      this.backBtn = document.getElementById('pvcBackBtn');
      this.targetMessage = document.getElementById('pvcTargetMessage');
      this.codeMessage = document.getElementById('pvcCodeMessage');

      this.csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      this.cooldownTimer = null;

      this.bind();
      this.lockSidebar();
      this.open();

    },

    bind: function () {
      var self = this;

      this.sendBtn.addEventListener('click', function () {
        self.send(self.sendBtn, 'Sending…', 'Send Verification Code');
      });

      this.resendBtn.addEventListener('click', function () {
        // Resend goes to the address the code was already sent to, on the same
        // route — not to whatever is sitting in the other (hidden) pane.
        self.send(self.resendBtn, 'Sending…', 'Resend code');
      });

      this.verifyBtn.addEventListener('click', function () {
        self.verify();
      });

      this.backBtn.addEventListener('click', function () {
        self.showTargetStep();
      });

      // Enter submits the step the user is on.
      this.eachTargetInput(function (input) {
        input.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            self.sendBtn.click();
          }
        });
        input.addEventListener('input', function () {
          self.clearMessage(self.targetMessage);
        });
      });

      // A phone box that only ever holds digits. Pasting a number copied out of
      // a contact card ("+234 703 958 6723", "0703-958-6723") is the normal way
      // this field gets filled, so the punctuation is dropped as it arrives
      // rather than refused afterwards.
      if (this.phoneInput) {
        this.phoneInput.addEventListener('input', function () {
          var cleaned = self.phoneInput.value.replace(/\D+/g, '').slice(0, 11);
          if (cleaned !== self.phoneInput.value) {
            self.phoneInput.value = cleaned;
          }
        });
      }

      this.codeInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          self.verifyBtn.click();
        }
      });

      // Digits only, and submit itself once the last one is typed — the code is
      // read off a handset or copied out of a mail, and asking for a button press
      // after that is friction for nothing.
      this.codeInput.addEventListener('input', function () {
        var cleaned = self.codeInput.value.replace(/\D+/g, '').slice(0, self.codeLength);
        if (cleaned !== self.codeInput.value) {
          self.codeInput.value = cleaned;
        }
        self.clearMessage(self.codeMessage);
        if (cleaned.length === self.codeLength) {
          self.verify();
        }
      });
    },

    /* --- requests --------------------------------------------------------- */

    send: function (button, busyLabel, idleLabel) {
      var self = this;
      if (this.sending) { return; }
      if (!this.phoneInput.reportValidity() || !this.emailInput.reportValidity()) { return; }
      if (!this.checkTargets()) { return; }
      this.sending = true;

      this.clearMessage(this.targetMessage);
      this.clearMessage(this.codeMessage);
      this.setBusy(button, true, busyLabel);

      this.post(this.sendUrl, { channel: 'both', email: this.emailInput.value, phone: this.phoneInput.value })
        .then(function (result) {
          self.sending = false;
          self.setBusy(button, false, idleLabel);

          var data = (result.body && result.body.data) || {};

          if (!result.body || !result.body.success) {
            // A refusal here is nearly always something the user can act on — a
            // placeholder address, a number that is not a Nigerian mobile, a
            // cooldown, or the network holding messages until morning. The server
            // writes those words; this only shows them, on whichever step is on
            // screen.
            var target = self.stepCode.classList.contains('pvc-hidden')
              ? self.targetMessage
              : self.codeMessage;
            self.showMessage(target, self.messageOf(result, 'The code could not be sent. Please try again.'), false);

            if (data.retryAfter) {
              self.startCooldown(data.retryAfter);
            }
            return;
          }

          self.showCodeStep(data.masked);
          self.showMessage(self.codeMessage, result.body.message, true);
          self.startCooldown(data.retryAfter || 0);
        })
        .catch(function () {
          self.sending = false;
          self.setBusy(button, false, idleLabel);
          self.showMessage(self.stepCode.classList.contains('pvc-hidden') ? self.targetMessage : self.codeMessage, 'The code could not be sent. Check your connection and try again.', false);
        });
    },

    verify: function () {
      var self = this;
      var code = this.codeInput.value.replace(/\D+/g, '');

      if (code.length < this.codeLength) {
        this.showMessage(this.codeMessage, 'Enter the ' + this.codeLength + '-digit code that was sent to you.', false);
        return;
      }

      this.clearMessage(this.codeMessage);
      this.setBusy(this.verifyBtn, true, 'Checking…');

      this.post(this.verifyUrl, { code: code })
        .then(function (result) {
          if (result.body && result.body.success) {
            self.showMessage(self.codeMessage, result.body.message, true);
            // Reload rather than unlocking in place: the sidebar, the menus and
            // every gate-aware fragment on the page were rendered for a held
            // account, and a reload is the one way to be sure none of them is
            // left behind.
            window.setTimeout(function () {
              window.location.reload();
            }, 900);
            return;
          }

          self.setBusy(self.verifyBtn, false, 'Verify My Account');
          self.showMessage(self.codeMessage, self.messageOf(result, 'That code is not correct.'), false);
          self.codeInput.value = '';
          self.codeInput.focus();
        })
        .catch(function () {
          self.setBusy(self.verifyBtn, false, 'Verify My Account');
          self.showMessage(self.codeMessage, 'The code could not be checked. Check your connection and try again.', false);
        });
    },

    post: function (url, payload) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      }).then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (body) {
            return { ok: response.ok, body: body };
          });
      });
    },

    /**
     * The server's own words where there are any — including Laravel's validation
     * bag, which carries a different shape from our own responses.
     */
    messageOf: function (result, fallback) {
      var body = result.body || {};
      if (body.message) {
        return body.message;
      }
      if (body.errors) {
        for (var key in body.errors) {
          if (Object.prototype.hasOwnProperty.call(body.errors, key) && body.errors[key].length) {
            return body.errors[key][0];
          }
        }
      }
      return fallback;
    },

    /**
     * The same two rules the server applies, applied here first.
     *
     * Not a substitute for the server's — the endpoint refuses the same input on
     * its own — but a typo caught here costs nothing, while one caught there
     * costs a round trip and, on the SMS side, a cooldown the user then has to
     * sit out before they can correct it.
     */
    checkTargets: function () {
      var phone = (this.phoneInput.value || '').replace(/\D+/g, '');
      var email = (this.emailInput.value || '').trim();

      if (!/^0\d{10}$/.test(phone)) {
        this.showMessage(this.targetMessage, 'Enter an 11-digit mobile number starting with 0, such as 08012345678.', false);
        this.phoneInput.focus();
        return false;
      }

      // Something before the @, a domain, and a real suffix after the last dot.
      // Wider than any list of providers on purpose: staff use gmail, yahoo,
      // hotmail and their own domains, and a list would refuse the next one.
      if (!/^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(email)) {
        this.showMessage(this.targetMessage, 'Enter a complete email address, such as musa@gmail.com.', false);
        this.emailInput.focus();
        return false;
      }

      this.phoneInput.value = phone;
      this.emailInput.value = email;
      return true;
    },

    targetInput: function () {
      return this.phoneInput;
    },

    eachTargetInput: function (callback) {
      [this.emailInput, this.phoneInput].forEach(function (input) {
        if (input) {
          callback(input);
        }
      });
    },

    focusTarget: function () {
      var input = this.targetInput();
      if (!input) {
        return;
      }
      var self = this;
      window.setTimeout(function () {
        input.focus();
        // An address already in the box is the thing most likely to need
        // replacing, so it starts selected. Nothing is selected when it is empty.
        if (input.value && input === self.targetInput()) {
          input.select();
        }
      }, 60);
    },

    /* --- steps and state -------------------------------------------------- */

    showCodeStep: function (masked) {
      this.stepTarget.classList.add('pvc-hidden');
      this.stepCode.classList.remove('pvc-hidden');

      if (masked) {
        this.maskedTarget.textContent = masked;
      } else if (!this.maskedTarget.textContent) {
        this.maskedTarget.textContent = 'your email address and phone number';
      }

      this.codeInput.value = '';
      this.codeInput.focus();
    },

    showTargetStep: function () {
      this.stepCode.classList.add('pvc-hidden');
      this.stepTarget.classList.remove('pvc-hidden');
      this.clearMessage(this.targetMessage);
      this.focusTarget();
    },

    /**
     * Count the resend button down. The server enforces the same wait — this only
     * saves the user pressing a button that is going to refuse.
     */
    startCooldown: function (seconds) {
      var self = this;
      var left = parseInt(seconds, 10) || 0;

      window.clearInterval(this.cooldownTimer);

      if (left <= 0) {
        this.resendBtn.disabled = false;
        this.resendBtn.textContent = 'Resend code';
        return;
      }

      var tick = function () {
        if (left <= 0) {
          window.clearInterval(self.cooldownTimer);
          self.resendBtn.disabled = false;
          self.resendBtn.textContent = 'Resend code';
          return;
        }
        self.resendBtn.disabled = true;
        self.resendBtn.textContent = 'Resend in ' + left + 's';
        left -= 1;
      };

      tick();
      this.cooldownTimer = window.setInterval(tick, 1000);
    },

    setBusy: function (button, busy, label) {
      button.disabled = busy;
      button.textContent = label;
    },

    showMessage: function (target, message, ok) {
      target.textContent = message;
      target.classList.remove('pvc-hidden');
      target.classList.toggle('pvc-success', !!ok);
      target.classList.toggle('pvc-error', !ok);
    },

    clearMessage: function (target) {
      target.textContent = '';
      target.classList.add('pvc-hidden');
    },

    /* Same visual half of the rule the photo gate uses: the sidebar goes inert. */
    lockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('sidebar-locked');
        sidebar.setAttribute('aria-disabled', 'true');
      }
    },

    open: function () {
      this.root.classList.add('pvc-open');
      this.root.setAttribute('aria-hidden', 'false');
      this.focusTarget();
    }
  };

  window.PhoneVerificationCard = PhoneVerificationCard;

  document.addEventListener('DOMContentLoaded', function () {
    PhoneVerificationCard.init();
  });
})(window, document);
