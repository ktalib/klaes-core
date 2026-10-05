{{--
    Step wizard for the module's heavier forms. Include once per page, anywhere.

    Markup it drives (the form keeps its normal action, method and fields):

        <form ... class="form-container" data-wizard data-wizard-errors="(json list of error keys)" novalidate>
            <div class="form-stepper" data-wizard-header></div>
            <div class="form-body">
                <section class="form-step" data-step data-title="Select File" data-icon="folder-search"> … </section>
                <section class="form-step" data-step data-title="Review" data-icon="clipboard-check" data-review>
                    <div data-wizard-summary></div>
                </section>
            </div>
            <div class="form-actions" data-wizard-nav>
                <button type="button" data-wizard-back>…</button>
                <button type="button" data-wizard-next>…</button>
                <button type="submit" data-wizard-submit>…</button>
            </div>
        </form>

    - The header (lucide icon, label, done/active state) and a progress bar are
      built from the steps. An earlier step can be reopened from the header; a
      later one only by passing the steps in between.
    - Next validates the current step: the browser's own constraints on every
      enabled field (required, type, min/max…), [data-wizard-required] hidden
      inputs (their attribute is the message), and any listener on the step's
      'wizard:validate' event calling e.detail.fail(message, element) — the
      file picker uses that.
    - The form submits only from the last step. Enter in an earlier step moves
      on instead; the submit button is disabled once the post is on its way.
    - The Review step lists every labelled field per step; values the file
      supplied are marked as such.
    - Server-side validation is unchanged. After a failed save the step holding
      the first field in data-wizard-errors opens.
--}}
@once
<style>
    .survey-proto [data-wizard] .form-stepper { align-items: center; padding: 16px 24px; }
    .survey-proto [data-wizard] .form-stepper .step-indicator { cursor: default; }
    .survey-proto [data-wizard] .form-stepper .step-indicator.can-go { cursor: pointer; }
    .survey-proto [data-wizard] .form-stepper .step-indicator .num svg { width: 16px; height: 16px; }
    .survey-proto [data-wizard] .form-stepper .step-indicator .label { font-size: 12.5px; font-weight: 600; }
    .survey-proto [data-wizard] .form-stepper .step-indicator .label small { display: block; font-weight: 500; font-size: 10.5px; color: var(--gray-500); text-transform: uppercase; letter-spacing: .04em; }
    .survey-proto [data-wizard] .form-stepper .sep { display: inline-flex; align-items: center; color: var(--gray-400); margin: 0 6px; }
    .survey-proto [data-wizard] .form-stepper .sep svg { width: 16px; height: 16px; }
    .survey-proto [data-wizard] .cad-wz-progress { height: 4px; background: var(--gray-200); }
    .survey-proto [data-wizard] .cad-wz-progress span { display: block; height: 100%; width: 0; background: var(--primary); transition: width .25s ease; }
    .survey-proto [data-wizard] .cad-wz-head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .survey-proto [data-wizard] .cad-wz-head .cad-wz-icon {
        width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center;
        background: var(--primary-light); color: var(--primary-dark); flex: 0 0 40px;
    }
    .survey-proto [data-wizard] .cad-wz-head .cad-wz-icon svg { width: 20px; height: 20px; }
    .survey-proto [data-wizard] .cad-wz-head .step-title { margin: 0; font-size: 18px; }
    .survey-proto [data-wizard] .cad-wz-head .step-subtitle { margin: 2px 0 0; font-size: 13px; color: var(--gray-600); }
    .survey-proto [data-wizard] .cad-wz-alert {
        display: none; gap: 8px; align-items: flex-start; margin-bottom: 14px; padding: 10px 12px;
        border-left: 4px solid #dc2626; background: #fef2f2; color: #7f1d1d; border-radius: 6px; font-size: 13px;
    }
    .survey-proto [data-wizard] .cad-wz-alert.show { display: flex; }
    .survey-proto [data-wizard] .cad-wz-alert ul { margin: 0; padding-left: 18px; }
    .survey-proto [data-wizard] .form-group.cad-wz-invalid input,
    .survey-proto [data-wizard] .form-group.cad-wz-invalid select,
    .survey-proto [data-wizard] .form-group.cad-wz-invalid textarea,
    .survey-proto [data-wizard] .form-group.cad-wz-invalid .select2-selection { border-color: #dc2626 !important; }
    .survey-proto [data-wizard] [data-wizard-nav] { display: flex; align-items: center; gap: 8px; }
    .survey-proto [data-wizard] [data-wizard-nav] .cad-wz-count { margin-right: auto; font-size: 12.5px; color: var(--gray-600); }
    .survey-proto [data-wizard] [data-wizard-nav] .btn { display: inline-flex; align-items: center; gap: 6px; }
    .survey-proto [data-wizard] [data-wizard-nav] .btn svg { width: 16px; height: 16px; }
    .survey-proto [data-wizard] .cad-wz-summary { display: grid; gap: 14px; }
    .survey-proto [data-wizard] .cad-wz-summary h4 {
        display: flex; align-items: center; gap: 6px; margin: 0 0 6px; font-size: 13px; font-weight: 700;
        color: var(--primary-dark); text-transform: uppercase; letter-spacing: .04em;
    }
    .survey-proto [data-wizard] .cad-wz-summary h4 svg { width: 15px; height: 15px; }
    .survey-proto [data-wizard] .cad-wz-summary h4 button { margin-left: auto; font-size: 12px; text-transform: none; letter-spacing: 0; }
    .survey-proto [data-wizard] .cad-wz-summary dl {
        display: grid; grid-template-columns: minmax(140px, 220px) 1fr; margin: 0;
        border: 1px solid var(--gray-200); border-radius: 8px; overflow: hidden; font-size: 13px;
    }
    .survey-proto [data-wizard] .cad-wz-summary dt,
    .survey-proto [data-wizard] .cad-wz-summary dd { margin: 0; padding: 7px 12px; border-top: 1px solid var(--gray-200); }
    .survey-proto [data-wizard] .cad-wz-summary dt { background: var(--gray-50); color: var(--gray-600); font-weight: 600; }
    .survey-proto [data-wizard] .cad-wz-summary dd { color: var(--gray-900); white-space: pre-wrap; word-break: break-word; }
    .survey-proto [data-wizard] .cad-wz-summary dl > :nth-child(1),
    .survey-proto [data-wizard] .cad-wz-summary dl > :nth-child(2) { border-top: none; }
    .survey-proto [data-wizard] .cad-wz-summary .cad-wz-src { margin-left: 6px; font-size: 10px; padding: 1px 6px; border-radius: 999px; background: #e2e8f0; color: #475569; font-weight: 600; text-transform: uppercase; }
    .survey-proto [data-wizard] .cad-wz-summary .cad-wz-empty { color: var(--gray-400); }
</style>

<script>
(function () {
    'use strict';

    function icons() { if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function hiddenOther(el) {
        var other = el.closest('.ab-other');
        return !!(other && other.style.display === 'none');
    }
    function labelOf(group) {
        var l = group && group.querySelector(':scope > label');
        return l ? l.textContent.replace(/\*/g, '').replace(/\s+/g, ' ').trim() : '';
    }

    function Wizard(form) {
        var self = this;
        this.form   = form;
        this.steps  = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
        this.header = form.querySelector('[data-wizard-header]');
        this.back   = form.querySelector('[data-wizard-back]');
        this.next   = form.querySelector('[data-wizard-next]');
        this.submit = form.querySelector('[data-wizard-submit]');
        this.current = 0;
        this.reached = 0;
        if (!this.steps.length) return;

        form.setAttribute('novalidate', 'novalidate');

        // Header, progress bar and each step's own heading.
        var html = '';
        this.steps.forEach(function (step, i) {
            if (i) html += '<span class="sep"><i data-lucide="chevron-right"></i></span>';
            html += '<div class="step-indicator" data-goto="' + i + '">' +
                '<span class="num"><i data-lucide="' + esc(step.dataset.icon || 'circle') + '"></i></span>' +
                '<span class="label"><small>Step ' + (i + 1) + '</small>' + esc(step.dataset.title || '') + '</span></div>';

            var head = document.createElement('div');
            head.className = 'cad-wz-head';
            head.innerHTML = '<div class="cad-wz-icon"><i data-lucide="' + esc(step.dataset.icon || 'circle') + '"></i></div>' +
                '<div><div class="step-title">' + esc(step.dataset.title || '') + '</div>' +
                (step.dataset.subtitle ? '<div class="step-subtitle">' + esc(step.dataset.subtitle) + '</div>' : '') + '</div>';
            var alert = document.createElement('div');
            alert.className = 'cad-wz-alert';
            alert.setAttribute('role', 'alert');
            step.insertBefore(alert, step.firstChild);
            step.insertBefore(head, step.firstChild);
        });
        if (this.header) {
            this.header.innerHTML = html;
            var bar = document.createElement('div');
            bar.className = 'cad-wz-progress';
            bar.innerHTML = '<span></span>';
            this.header.parentNode.insertBefore(bar, this.header.nextSibling);
            this.bar = bar.firstChild;
            this.header.addEventListener('click', function (e) {
                var ind = e.target.closest('[data-goto]');
                if (ind) self.go(parseInt(ind.getAttribute('data-goto'), 10));
            });
        }

        var nav = form.querySelector('[data-wizard-nav]');
        if (nav) {
            this.count = document.createElement('span');
            this.count.className = 'cad-wz-count';
            nav.insertBefore(this.count, nav.firstChild);
        }

        if (this.back) this.back.addEventListener('click', function () { self.show(self.current - 1); });
        if (this.next) this.next.addEventListener('click', function () { self.go(self.current + 1); });

        form.addEventListener('submit', function (e) {
            if (self.current < self.steps.length - 1) {
                e.preventDefault();
                self.go(self.current + 1);
                return;
            }
            for (var i = 0; i < self.steps.length; i++) {
                if (!self.validate(i)) { e.preventDefault(); self.show(i); return; }
            }
            if (self.submit) {
                // One post per click; a slow server is not a reason to file twice.
                setTimeout(function () { self.submit.disabled = true; }, 0);
            }
        });

        // A failed save comes back with errors: open the step that holds the first.
        var start = 0;
        try {
            var keys = JSON.parse(form.dataset.wizardErrors || '[]');
            for (var k = 0; k < keys.length && !start; k++) {
                var key = String(keys[k]);
                var name = key.indexOf('.') < 0 ? key : key.replace(/\.(\w+)/g, '[$1]');
                var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"], [name^="' + key.split('.')[0] + '["]');
                var step = el ? el.closest('[data-step]') : null;
                if (step) start = Math.max(0, this.steps.indexOf(step));
            }
            if (keys.length) this.reached = this.steps.length - 1;
        } catch (e) { /* start at the top */ }

        this.show(start);
    }

    Wizard.prototype.go = function (i) {
        if (i <= this.current) return this.show(i);
        for (var s = this.current; s < i; s++) {
            if (!this.validate(s)) return this.show(s);
        }
        this.show(i);
    };

    Wizard.prototype.show = function (i) {
        var self = this;
        if (i < 0 || i >= this.steps.length) return;
        this.current = i;
        this.reached = Math.max(this.reached, i);
        var last = this.steps.length - 1;

        this.steps.forEach(function (step, n) { step.classList.toggle('active', n === i); });
        if (this.header) {
            this.header.querySelectorAll('[data-goto]').forEach(function (ind) {
                var n = parseInt(ind.getAttribute('data-goto'), 10);
                ind.classList.toggle('active', n === i);
                ind.classList.toggle('done', n < i);
                ind.classList.toggle('can-go', n <= self.reached);
            });
        }
        if (this.bar) this.bar.style.width = (last ? (i / last) * 100 : 100) + '%';
        if (this.back) this.back.style.display = i === 0 ? 'none' : '';
        if (this.next) this.next.style.display = i === last ? 'none' : '';
        if (this.submit) this.submit.style.display = i === last ? '' : 'none';
        if (this.count) this.count.textContent = 'Step ' + (i + 1) + ' of ' + this.steps.length;

        if (this.steps[i].hasAttribute('data-review')) this.summarise(this.steps[i]);

        icons();
        // Select2 sizes itself on open; nudge any box that was hidden at init.
        if (window.jQuery) window.jQuery(window).trigger('resize');
    };

    /** Check one step; show its problems at its top. */
    Wizard.prototype.validate = function (i) {
        var step = this.steps[i];
        var problems = [];
        var firstBad = null;

        step.querySelectorAll('.cad-wz-invalid').forEach(function (g) { g.classList.remove('cad-wz-invalid'); });

        step.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.disabled || el.type === 'hidden' || hiddenOther(el) || !el.willValidate) return;
            if (el.checkValidity()) return;
            var group = el.closest('.form-group');
            if (group) group.classList.add('cad-wz-invalid');
            var label = labelOf(group) || el.name;
            problems.push(label + ': ' + (el.validationMessage || 'check this field.'));
            firstBad = firstBad || el;
        });

        step.querySelectorAll('[data-wizard-required]').forEach(function (el) {
            if (!el.disabled && !String(el.value || '').trim()) problems.push(el.getAttribute('data-wizard-required'));
        });

        step.dispatchEvent(new CustomEvent('wizard:validate', {
            detail: { fail: function (message, el) { problems.push(message); firstBad = firstBad || el || null; } }
        }));

        var alert = step.querySelector('.cad-wz-alert');
        if (alert) {
            if (problems.length) {
                alert.innerHTML = '<i data-lucide="circle-alert" style="width:16px;height:16px;flex:0 0 16px;margin-top:1px;"></i>' +
                    '<div><strong>Before you go on:</strong><ul>' + problems.map(function (p) { return '<li>' + esc(p) + '</li>'; }).join('') + '</ul></div>';
                alert.classList.add('show');
                icons();
            } else {
                alert.classList.remove('show');
                alert.innerHTML = '';
            }
        }

        if (problems.length && firstBad && i === this.current && firstBad.focus) {
            try { firstBad.focus({ preventScroll: false }); } catch (e) { /* not focusable */ }
        }
        return problems.length === 0;
    };

    /** The Review step: every labelled field of every step before it. */
    Wizard.prototype.summarise = function (review) {
        var self = this;
        var target = review.querySelector('[data-wizard-summary]');
        if (!target) return;

        var html = '';
        this.steps.forEach(function (step, n) {
            if (step === review) return;
            var rows = '';
            step.querySelectorAll('.form-group').forEach(function (group) {
                if (group.closest('[data-summary-skip]') || group.style.display === 'none' || hiddenOther(group)) return;
                var label = labelOf(group);
                if (!label) return;

                var parts = [];
                var fromFile = false;
                group.querySelectorAll('input, select, textarea').forEach(function (el) {
                    if (el.type === 'hidden' || el.type === 'button' || el.type === 'submit') return;
                    if (el.disabled) fromFile = fromFile || el.classList.contains('cad-locked');
                    if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) parts.push(el.closest('label') ? el.closest('label').textContent.trim() : el.value); return; }
                    if (el.type === 'file') { Array.prototype.forEach.call(el.files || [], function (f) { parts.push(f.name); }); return; }
                    if (el.tagName === 'SELECT') {
                        var opt = el.options[el.selectedIndex];
                        var t = opt && opt.value !== '' ? opt.textContent.trim() : '';
                        if (t) parts.push(t);
                        return;
                    }
                    if (String(el.value || '').trim() !== '') parts.push(String(el.value).trim());
                });

                var value = parts.join(', ');
                rows += '<dt>' + esc(label) + '</dt><dd>' + (value ? esc(value) : '<span class="cad-wz-empty">—</span>') +
                    (value && fromFile && !group.classList.contains('cad-fp') ? '<span class="cad-wz-src">from file</span>' : '') + '</dd>';
            });
            if (!rows) return;
            html += '<div><h4><i data-lucide="' + esc(step.dataset.icon || 'circle') + '"></i>' + esc(step.dataset.title || '') +
                '<button type="button" class="btn btn-outline btn-xs" data-goto-step="' + n + '">Edit</button></h4><dl>' + rows + '</dl></div>';
        });

        target.className = 'cad-wz-summary';
        target.innerHTML = html || '<p class="helper-text">Nothing entered yet.</p>';
        target.querySelectorAll('[data-goto-step]').forEach(function (b) {
            b.addEventListener('click', function () { self.show(parseInt(b.getAttribute('data-goto-step'), 10)); });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        // After the picker (also a tick late) has filled the form.
        setTimeout(function () {
            document.querySelectorAll('form[data-wizard]').forEach(function (form) {
                if (!form._wizard) form._wizard = new Wizard(form);
            });
        }, 0);
    });
})();
</script>
@endonce
