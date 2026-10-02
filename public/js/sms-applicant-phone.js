/*
 | Prefills an applicant phone field from the file the officer has chosen, and
 | warns when the number it found looks like it belongs to somebody else.
 |
 | WHY THE WARNING EXISTS
 | file_indexings.phone is the only phone column in this database with real
 | volume, and it is polluted: one number in it sits on 1,211 different files,
 | the next on 492. Those are indexing clerks and agents who keyed a batch, not
 | the applicants who own the files. Prefilling blindly and sending would text
 | one clerk about a thousand strangers' land.
 |
 | So a number found on more than a handful of files is offered with an amber
 | warning and a confirm box. Until that box is ticked the server records the
 | message as SKIPPED rather than sending it -- the officer is the only one who
 | can say whether the number really is this applicant's.
 |
 | USAGE
 |   <input data-sms-phone data-sms-phone-for="#file-number-input">
 | The confirm checkbox and the warning are created automatically underneath it,
 | and a hidden phone_confirmed input is kept in step so the form posts it.
 */
(function () {
  'use strict';

  var ENDPOINT = (window.KlaesSms && window.KlaesSms.phoneEndpoint) || '/sms/applicant-phone';

  function debounce(fn, wait) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, wait);
    };
  }

  function build(input) {
    if (input.dataset.smsPhoneReady === '1') { return null; }
    input.dataset.smsPhoneReady = '1';

    var wrap = document.createElement('div');
    wrap.className = 'sms-phone-hint mt-1';
    wrap.style.display = 'none';

    var warning = document.createElement('div');
    warning.className = 'text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1';

    var confirmRow = document.createElement('label');
    confirmRow.className = 'flex items-center gap-2 mt-1 text-xs text-slate-600';

    var box = document.createElement('input');
    box.type = 'checkbox';
    box.className = 'rounded border-slate-300';

    var boxText = document.createElement('span');
    boxText.textContent = 'I have confirmed this number belongs to the applicant';

    confirmRow.appendChild(box);
    confirmRow.appendChild(boxText);
    wrap.appendChild(warning);
    wrap.appendChild(confirmRow);
    input.parentNode.insertBefore(wrap, input.nextSibling);

    /*
     | The form posts phone_confirmed as a normal field. A hidden input rather
     | than the checkbox itself, because an unchecked checkbox posts nothing at
     | all -- and "absent" would then be indistinguishable from "the officer
     | never saw a warning", which is the case where sending IS allowed.
     */
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = input.dataset.smsPhoneConfirmName || 'phone_confirmed';
    hidden.value = '0';
    input.parentNode.insertBefore(hidden, wrap);

    box.addEventListener('change', function () {
      hidden.value = box.checked ? '1' : '0';
    });

    return { wrap: wrap, warning: warning, box: box, hidden: hidden };
  }

  function apply(input, ui, data) {
    if (!ui) { return; }

    // Never overwrite something the officer has typed. The prefill is a
    // convenience, not an opinion about who the applicant is.
    if (!input.value && data.phone) {
      input.value = data.phone;
    }

    if (data.shared && data.warning) {
      ui.warning.textContent = data.warning;
      ui.wrap.style.display = '';
      ui.box.checked = false;
      ui.hidden.value = '0';
      return;
    }

    ui.wrap.style.display = 'none';
    // A number that needed no warning needs no confirmation either.
    ui.hidden.value = '1';
  }

  function lookup(input, ui, fileNumber) {
    if (!fileNumber || fileNumber.trim().length < 3) { return; }

    fetch(ENDPOINT + '?file_number=' + encodeURIComponent(fileNumber.trim()), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (body) {
        if (body && body.success) { apply(input, ui, body.data); }
      })
      .catch(function () {
        // A prefill that cannot be fetched is not an error the officer needs to
        // see -- the field simply stays as they left it.
      });
  }

  function init() {
    document.querySelectorAll('[data-sms-phone]').forEach(function (input) {
      var ui = build(input);
      if (!ui) { return; }

      var sourceSelector = input.dataset.smsPhoneFor;
      var source = sourceSelector ? document.querySelector(sourceSelector) : null;

      // A number typed by hand gets the same shared-number verdict as one that
      // was looked up: an agent's number is no more the applicant's for having
      // been typed in.
      input.addEventListener('blur', function () {
        if (input.value) { lookup(input, ui, input.value); }
      });

      if (!source) { return; }

      var run = debounce(function () { lookup(input, ui, source.value); }, 400);
      source.addEventListener('change', run);
      source.addEventListener('blur', run);

      if (source.value) { run(); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.KlaesSmsPhone = { init: init };
})();
