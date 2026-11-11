/*
 * Security questions card (profile.partials.security-questions-card).
 *
 * A step-by-step card: [Password,] Question 1..n, Confirm, Done. Forced (the account
 * owes its answers): it opens on every page, locks the sidebar and has no way to close
 * except finishing or signing out. Change mode (from the profile menu): Cancel closes it.
 * Each step is checked here for a quick message; SecurityQuestionsController::update
 * checks everything again and its errors send the user back to the step concerned.
 */
(function (window, document) {
  'use strict';

  function normalise(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  var SecurityQuestionsCard = {
    init: function () {
      this.root = document.getElementById('securityQuestionsCard');
      if (!this.root) {
        return;
      }

      this.saveUrl = this.root.getAttribute('data-save-url');
      this.forced = this.root.getAttribute('data-forced') === '1';
      this.minLength = parseInt(this.root.getAttribute('data-min-length'), 10) || 2;
      try {
        this.blocked = JSON.parse(this.root.getAttribute('data-blocked') || '[]');
      } catch (e) {
        this.blocked = [];
      }

      this.panes = Array.prototype.slice.call(this.root.querySelectorAll('.sqc-pane'));
      this.donePane = this.root.querySelector('[data-pane="done"]');
      this.stepPanes = this.panes.filter(function (pane) { return pane.getAttribute('data-pane') !== 'done'; });
      this.questionPanes = this.panes.filter(function (pane) { return pane.getAttribute('data-pane') === 'question'; });
      this.stepDots = Array.prototype.slice.call(this.root.querySelectorAll('.sqc-step'));
      this.selects = Array.prototype.slice.call(this.root.querySelectorAll('[data-question-select]'));

      this.nav = document.getElementById('sqcNav');
      this.backBtn = document.getElementById('sqcBackBtn');
      this.nextBtn = document.getElementById('sqcNextBtn');
      this.footnote = document.getElementById('sqcFootnote');
      this.review = document.getElementById('sqcReview');
      this.toggleReviewBtn = document.getElementById('sqcToggleReview');
      this.passwordInput = document.getElementById('sqcPassword');
      this.csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

      this.current = 0;
      this.saving = false;
      this.finished = false;
      this.showAnswers = false;

      this.bind();
      if (this.forced) {
        this.lockSidebar();
      }
      this.syncOptions();
      this.open();
    },

    bind: function () {
      var self = this;

      this.nextBtn.addEventListener('click', function () { self.next(); });
      this.backBtn.addEventListener('click', function () { self.show(self.current - 1); });

      var cancel = document.getElementById('sqcCancelBtn');
      if (cancel) {
        cancel.addEventListener('click', function () { self.close(); });
      }

      this.selects.forEach(function (select) {
        select.addEventListener('change', function () {
          self.syncOptions();
          self.clearError(self.paneOf(select));
        });
      });

      Array.prototype.forEach.call(this.root.querySelectorAll('input'), function (input) {
        input.addEventListener('input', function () { self.clearError(self.paneOf(input)); });
        input.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            self.next();
          }
        });
      });

      Array.prototype.forEach.call(this.root.querySelectorAll('[data-reveal]'), function (button) {
        button.addEventListener('click', function () {
          var input = document.getElementById(button.getAttribute('data-reveal'));
          var hidden = input.type === 'password';
          input.type = hidden ? 'text' : 'password';
          button.textContent = hidden ? 'Hide' : 'Show';
        });
      });

      this.toggleReviewBtn.addEventListener('click', function () {
        self.showAnswers = !self.showAnswers;
        self.renderReview();
      });

      // Escape and clicks on the backdrop do nothing: the card closes only by finishing
      // (or Cancel, when it is not owed).
      this.root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    },

    paneOf: function (element) {
      return element.closest('.sqc-pane');
    },

    /* ── steps ─────────────────────────────────────────────────────────── */

    show: function (index) {
      if (index < 0 || index >= this.stepPanes.length) {
        return;
      }
      this.current = index;

      this.panes.forEach(function (pane) { pane.classList.add('sqc-hidden'); });
      this.stepPanes[index].classList.remove('sqc-hidden');

      this.stepDots.forEach(function (dot, i) {
        dot.classList.toggle('sqc-step-done', i < index);
        dot.classList.toggle('sqc-step-current', i === index);
        var circle = dot.querySelector('.sqc-step-dot');
        circle.innerHTML = i < index
          ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'
          : String(i + 1);
      });

      var onConfirm = this.stepPanes[index].getAttribute('data-pane') === 'confirm';
      this.backBtn.classList.toggle('sqc-hidden', index === 0);
      this.nextBtn.textContent = onConfirm ? 'Save Security Questions' : 'Next';

      if (onConfirm) {
        this.showAnswers = false;
        this.renderReview();
      }

      var field = this.stepPanes[index].querySelector('select, input');
      if (field) {
        // A chosen question moves the cursor on to its answer.
        if (field.tagName === 'SELECT' && field.value) {
          field = this.stepPanes[index].querySelector('input') || field;
        }
        setTimeout(function () { field.focus(); }, 30);
      }
    },

    next: function () {
      if (this.saving) {
        return;
      }
      if (this.finished) {
        this.done();
        return;
      }

      var pane = this.stepPanes[this.current];
      var problem = this.check(pane);
      if (problem) {
        this.showError(pane, problem.message, problem.field);
        return;
      }

      if (pane.getAttribute('data-pane') === 'confirm') {
        this.save();
        return;
      }

      this.show(this.current + 1);
    },

    /** The first problem on a step, or null. The server checks all of it again. */
    check: function (pane) {
      var kind = pane.getAttribute('data-pane');

      if (kind === 'password') {
        return this.passwordInput.value === ''
          ? { message: 'Enter your password.', field: this.passwordInput }
          : null;
      }

      if (kind !== 'question') {
        return null;
      }

      var select = pane.querySelector('select');
      var input = pane.querySelector('input');
      var answer = normalise(input.value);
      var index = this.questionPanes.indexOf(pane);

      if (!select.value) {
        return { message: 'Choose a question.', field: select };
      }
      if (answer === '') {
        return { message: 'Type your answer.', field: input };
      }
      if (answer.length < this.minLength) {
        return { message: 'The answer is too short.', field: input };
      }
      if (this.blocked.indexOf(answer) !== -1 || /^(.)\1*$/.test(answer)) {
        return { message: 'That answer is too easy to guess. Choose something only you would know.', field: input };
      }
      for (var i = 0; i < index; i++) {
        if (normalise(this.questionPanes[i].querySelector('input').value) === answer) {
          return { message: 'Give a different answer to each question.', field: input };
        }
      }

      return null;
    },

    /** A question chosen in one step is greyed out in the others. */
    syncOptions: function () {
      var taken = this.selects.map(function (select) { return select.value; });
      this.selects.forEach(function (select, i) {
        Array.prototype.forEach.call(select.options, function (option) {
          option.disabled = option.value !== '' && taken.indexOf(option.value) !== -1 && taken[i] !== option.value;
        });
      });
    },

    renderReview: function () {
      var self = this;
      this.review.innerHTML = '';

      this.questionPanes.forEach(function (pane, i) {
        var select = pane.querySelector('select');
        var answer = pane.querySelector('input').value.trim();

        var row = document.createElement('div');
        row.className = 'sqc-review-row';

        var edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'sqc-link sqc-review-edit';
        edit.textContent = 'Edit';
        edit.addEventListener('click', function () { self.show(self.stepPanes.indexOf(pane)); });

        var question = document.createElement('div');
        question.className = 'sqc-review-q';
        question.textContent = (i + 1) + '. ' + (select.selectedOptions[0] ? select.selectedOptions[0].text : '');

        var value = document.createElement('div');
        value.className = 'sqc-review-a';
        value.textContent = self.showAnswers ? answer : new Array(Math.min(answer.length, 16) + 1).join('•');

        row.appendChild(edit);
        row.appendChild(question);
        row.appendChild(value);
        self.review.appendChild(row);
      });

      this.toggleReviewBtn.textContent = this.showAnswers ? 'Hide answers' : 'Show answers';
    },

    /* ── saving ────────────────────────────────────────────────────────── */

    save: function () {
      var self = this;
      var payload = {
        questions: this.questionPanes.map(function (pane) { return pane.querySelector('select').value; }),
        answers: this.questionPanes.map(function (pane) { return pane.querySelector('input').value; })
      };
      if (this.passwordInput) {
        payload.current_password = this.passwordInput.value;
      }

      this.saving = true;
      this.nextBtn.disabled = true;
      this.backBtn.disabled = true;
      this.nextBtn.textContent = 'Saving…';

      fetch(this.saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      })
        .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (body) {
            return { status: response.status, body: body };
          });
        })
        .then(function (result) {
          self.saving = false;
          self.nextBtn.disabled = false;
          self.backBtn.disabled = false;

          if (result.status === 200 && result.body.success) {
            self.finish(result.body.message || 'Your security questions are set.');
            return;
          }

          if (result.status === 419) {
            self.showError(self.stepPanes[self.current], 'Your session has expired. Refresh the page and try again.');
            self.nextBtn.textContent = 'Save Security Questions';
            return;
          }

          self.showServerErrors(result.body.errors || {}, result.body.message);
        })
        .catch(function () {
          self.saving = false;
          self.nextBtn.disabled = false;
          self.backBtn.disabled = false;
          self.nextBtn.textContent = 'Save Security Questions';
          self.showError(self.stepPanes[self.current], 'Could not reach the server. Check your connection and try again.');
        });
    },

    /** Server errors are keyed questions.<i> / answers.<i> / current_password: go to that step. */
    showServerErrors: function (errors, fallback) {
      var keys = Object.keys(errors);
      if (!keys.length) {
        this.show(this.current);
        this.showError(this.stepPanes[this.current], fallback || 'Something went wrong. Please try again.');
        return;
      }

      var key = keys[0];
      var pane = null;
      var field = null;
      var match = /^(questions|answers)\.(\d+)$/.exec(key);

      if (key === 'current_password') {
        pane = this.root.querySelector('[data-pane="password"]');
        field = this.passwordInput;
      } else if (match) {
        pane = this.questionPanes[parseInt(match[2], 10)];
        field = pane ? pane.querySelector(match[1] === 'questions' ? 'select' : 'input') : null;
      }
      pane = pane || this.stepPanes[this.current];

      this.show(this.stepPanes.indexOf(pane));
      this.showError(pane, errors[key], field);
    },

    finish: function (message) {
      this.finished = true;
      this.panes.forEach(function (pane) { pane.classList.add('sqc-hidden'); });
      this.donePane.classList.remove('sqc-hidden');
      document.getElementById('sqcDoneMessage').textContent = message;

      this.stepDots.forEach(function (dot) {
        dot.classList.add('sqc-step-done');
        dot.classList.remove('sqc-step-current');
        dot.querySelector('.sqc-step-dot').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
      });

      this.backBtn.classList.add('sqc-hidden');
      this.nextBtn.textContent = 'Continue';
      this.footnote.classList.add('sqc-hidden');
      this.nextBtn.focus();
    },

    done: function () {
      if (this.forced) {
        // Reload so the sidebar and every page open up.
        window.location.reload();
        return;
      }
      this.close();
    },

    /* ── messages ──────────────────────────────────────────────────────── */

    showError: function (pane, message, field) {
      var box = pane.querySelector('.sqc-error');
      if (box) {
        box.textContent = message;
        box.classList.remove('sqc-hidden');
      }
      if (field) {
        field.classList.add('sqc-invalid');
        field.focus();
      }
      if (this.stepPanes[this.current].getAttribute('data-pane') === 'confirm') {
        this.nextBtn.textContent = 'Save Security Questions';
      }
    },

    clearError: function (pane) {
      if (!pane) {
        return;
      }
      var box = pane.querySelector('.sqc-error');
      if (box) {
        box.textContent = '';
        box.classList.add('sqc-hidden');
      }
      Array.prototype.forEach.call(pane.querySelectorAll('.sqc-invalid'), function (el) {
        el.classList.remove('sqc-invalid');
      });
    },

    /* ── open / close ──────────────────────────────────────────────────── */

    lockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('sidebar-locked');
        sidebar.setAttribute('aria-disabled', 'true');
      }
    },

    open: function () {
      this.root.classList.add('sqc-open');
      this.root.setAttribute('aria-hidden', 'false');
      this.show(0);
    },

    close: function () {
      if (this.forced && !this.finished) {
        return;
      }
      this.root.classList.remove('sqc-open');
      this.root.setAttribute('aria-hidden', 'true');
      // Typed answers and the password do not linger in the page.
      Array.prototype.forEach.call(this.root.querySelectorAll('input'), function (input) { input.value = ''; });
    }
  };

  window.SecurityQuestionsCard = SecurityQuestionsCard;

  document.addEventListener('DOMContentLoaded', function () {
    SecurityQuestionsCard.init();
  });
})(window, document);
