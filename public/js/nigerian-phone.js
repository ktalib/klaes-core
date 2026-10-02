/**
 * Shared Nigerian phone-number validation.
 *
 * Mark any input with data-phone-ng and it will:
 *   - keep only digits as the user types, folding +234/234 to a leading 0
 *   - cap the value at 11 digits
 *   - show an inline error on blur and block its form's submit
 *
 * Add data-phone-ng-required to also reject an empty value.
 * The error node is created next to the input unless data-phone-error="<id>"
 * points at an existing element.
 *
 * Mirrors App\Rules\NigerianPhone on the server. Keep the two in step.
 */
(function () {
    'use strict';

    // [data-sms-phone] fields are prefilled by sms-applicant-phone.js and feed
    // the SMS gateways, so they are Nigerian numbers by definition — adopt them
    // without needing data-phone-ng repeated on each one.
    var SELECTOR = '[data-phone-ng], [data-sms-phone]';
    var PATTERN = /^0\d{10}$/;
    var MESSAGE = 'Enter an 11-digit number starting with 0, such as 08012345678.';
    var EMPTY_MESSAGE = 'Phone number is required.';

    function normalize(raw) {
        var digits = String(raw == null ? '' : raw).replace(/\D+/g, '');

        if (digits.indexOf('234') === 0 && digits.length > 10) {
            digits = '0' + digits.slice(3);
        } else if (digits.length && digits[0] !== '0' && digits.length >= 10) {
            digits = '0' + digits;
        }

        return digits.slice(0, 11);
    }

    function errorNodeFor(input) {
        var targetId = input.getAttribute('data-phone-error');
        if (targetId) {
            var existing = document.getElementById(targetId);
            if (existing) { return existing; }
        }

        if (input._ngPhoneError) { return input._ngPhoneError; }

        var node = document.createElement('p');
        node.className = 'mt-1 text-xs text-red-600 hidden';
        node.setAttribute('data-phone-ng-error', '');
        if (input.parentNode) {
            input.parentNode.insertBefore(node, input.nextSibling);
        }
        input._ngPhoneError = node;
        return node;
    }

    function showError(input, message) {
        var node = errorNodeFor(input);
        if (node) {
            node.textContent = message;
            node.classList.remove('hidden');
        }
        input.classList.add('border-red-500');
        input.setAttribute('aria-invalid', 'true');
    }

    function clearError(input) {
        var node = errorNodeFor(input);
        if (node) { node.classList.add('hidden'); }
        input.classList.remove('border-red-500');
        input.removeAttribute('aria-invalid');
    }

    function isRequired(input) {
        return input.hasAttribute('data-phone-ng-required') || input.hasAttribute('required');
    }

    function validate(input, silent) {
        var value = (input.value || '').trim();

        if (!value) {
            if (isRequired(input)) {
                if (!silent) { showError(input, EMPTY_MESSAGE); }
                return false;
            }
            clearError(input);
            return true;
        }

        if (!PATTERN.test(value)) {
            if (!silent) { showError(input, MESSAGE); }
            return false;
        }

        clearError(input);
        return true;
    }

    function attach(input) {
        if (input._ngPhoneBound) { return; }
        input._ngPhoneBound = true;

        input.setAttribute('inputmode', 'numeric');
        // Deliberately NO maxlength: it would truncate a pasted "+234 801 234 5678"
        // to 11 raw characters before normalize() could fold the country code,
        // silently producing a different number. normalize() caps the result.
        if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '08012345678');
        }

        input.addEventListener('input', function () {
            if (input._ngPhoneSyncing) { return; }

            var cleaned = normalize(input.value);
            if (cleaned !== input.value) {
                input.value = cleaned;
                // Re-announce so Alpine/Vue x-model picks up the rewritten value.
                input._ngPhoneSyncing = true;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input._ngPhoneSyncing = false;
            }

            validate(input, true);
        });

        input.addEventListener('blur', function () { validate(input, false); });

        var form = input.form;
        if (form && !form._ngPhoneBound) {
            form._ngPhoneBound = true;
            form.addEventListener('submit', function (event) {
                var fields = form.querySelectorAll(SELECTOR);
                var firstBad = null;

                Array.prototype.forEach.call(fields, function (field) {
                    if (field.disabled || field.offsetParent === null) { return; }
                    if (!validate(field, false) && !firstBad) { firstBad = field; }
                });

                if (firstBad) {
                    event.preventDefault();
                    event.stopPropagation();
                    firstBad.focus();
                    firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, true);
        }
    }

    function scan(root) {
        var scope = root || document;
        Array.prototype.forEach.call(scope.querySelectorAll(SELECTOR), attach);
    }

    document.addEventListener('DOMContentLoaded', function () { scan(document); });

    // Modals inject their markup after load, so pick up new fields as they appear.
    if (window.MutationObserver) {
        document.addEventListener('DOMContentLoaded', function () {
            new MutationObserver(function () { scan(document); })
                .observe(document.body, { childList: true, subtree: true });
        });
    }

    window.NigerianPhone = {
        normalize: normalize,
        isValid: function (value) { return PATTERN.test(String(value || '').trim()); },
        validateField: validate,
        scan: scan,
        /** True when every marked field inside `scope` passes; shows errors. */
        validateWithin: function (scope) {
            var fields = (scope || document).querySelectorAll(SELECTOR);
            var ok = true;
            Array.prototype.forEach.call(fields, function (field) {
                if (field.disabled) { return; }
                if (!validate(field, false)) { ok = false; }
            });
            return ok;
        }
    };
})();
