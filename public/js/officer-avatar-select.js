/**
 * Shows the selected officer's profile picture beside their name on the Log a File
 * screen (and any other officer picker that opts in).
 *
 * Opt in by giving the <select> options a data-photo / data-name attribute and adding a
 * target element whose id is "<select-id>-profile". Works with plain selects and with
 * Select2, which fires its own change event on the underlying element.
 */
(function (window, document) {
  'use strict';

  var PAIRS = [
    { select: 'receiving-officer', target: 'receiving-officer-profile' },
    { select: 'movement-receiving-officer', target: 'movement-receiving-officer-profile' },
    { select: 'update-log-receiving-officer', target: 'update-log-receiving-officer-profile' }
  ];

  function render(target, name, photo) {
    if (!target) {
      return;
    }

    if (!name) {
      target.classList.add('hidden');
      target.innerHTML = '';
      return;
    }

    // Just the officer's passport photo — the name is already on the select above it,
    // so repeating it here only added noise. Squared frame, initials when no photo.
    target.innerHTML = window.UserAvatar
      ? window.UserAvatar.passport(photo || null, name, 96)
      : '';
    target.classList.remove('hidden');
  }

  function sync(select, target) {
    var option = select.options[select.selectedIndex];

    // "+ Add Other Officer..." and the empty placeholder carry no person.
    if (!option || !select.value || select.value.indexOf('__') === 0) {
      render(target, '', '');
      return;
    }

    var name = option.getAttribute('data-name') || option.textContent.trim();
    render(target, name, option.getAttribute('data-photo') || '');
  }

  function attach(pair) {
    var select = document.getElementById(pair.select);
    var target = document.getElementById(pair.target);

    if (!select || !target) {
      return;
    }

    select.addEventListener('change', function () {
      sync(select, target);
    });

    // Select2 replaces the control but still triggers change on the original element.
    if (window.jQuery) {
      window.jQuery(select).on('change select2:select', function () {
        sync(select, target);
      });
    }

    sync(select, target);
  }

  document.addEventListener('DOMContentLoaded', function () {
    PAIRS.forEach(attach);
  });
})(window, document);
