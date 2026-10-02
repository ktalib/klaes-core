(function (window, document) {
  'use strict';

  function initDropdown() {
    const dropdown = document.querySelector('[data-user-profile-dropdown]');
    if (!dropdown) {
      return;
    }

    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');

    if (!toggle || !menu) {
      return;
    }

    let isOpen = false;

    function setOpen(state) {
      isOpen = state;
      menu.classList.toggle('hidden', !isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    }

    toggle.addEventListener('click', (event) => {
      event.preventDefault();
      setOpen(!isOpen);
    });

    document.addEventListener('click', (event) => {
      if (!dropdown.contains(event.target)) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initDropdown);
})(window, document);
