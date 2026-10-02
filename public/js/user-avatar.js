/**
 * Small avatar renderer shared by the file-tracking screens (Quick Search, Log a File,
 * Quick Search Mobile). Returns markup rather than a node so it drops straight into the
 * template strings those screens already build their rows with.
 *
 * A photo URL that 404s (the row points at a file no longer on disk) falls back to the
 * person's initials rather than a broken-image glyph.
 */
(function (window, document) {
  'use strict';

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function initials(name) {
    return String(name || '')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(function (part) {
        return part.charAt(0).toUpperCase();
      })
      .join('') || '?';
  }

  var UserAvatar = {
    /**
     * @param {string|null} url   photo URL, or null when the user has none
     * @param {string} name       used for initials and the alt text
     * @param {number} size       pixels (default 32)
     */
    html: function (url, name, size) {
      var px = size || 32;
      var safeName = escapeHtml(name || '');
      var style = 'width:' + px + 'px;height:' + px + 'px;border-radius:9999px;overflow:hidden;'
        + 'display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;'
        + 'background:#e2e8f0;color:#475569;font-weight:700;font-size:' + Math.round(px * 0.38) + 'px;'
        + 'border:1px solid rgba(148,163,184,.5);vertical-align:middle;';

      if (!url) {
        return '<span class="ua-avatar" style="' + style + '" title="' + safeName + '">'
          + escapeHtml(initials(name)) + '</span>';
      }

      // onerror swaps in the initials, so a missing file degrades quietly.
      return '<span class="ua-avatar" style="' + style + '" title="' + safeName + '">'
        + '<img src="' + escapeHtml(url) + '" alt="' + safeName + '"'
        + ' style="width:100%;height:100%;object-fit:cover;"'
        + ' onerror="this.parentNode.textContent=\'' + escapeHtml(initials(name)).replace(/'/g, '') + '\';">'
        + '</span>';
    },

    /**
     * Squared passport frame — for form fields where the photo reads as a document
     * portrait rather than a round row avatar.
     *
     * @param {string|null} url
     * @param {string} name
     * @param {number} size  pixels (default 46)
     */
    passport: function (url, name, size) {
      var px = size || 46;
      var safeName = escapeHtml(name || '');
      var style = 'width:' + px + 'px;height:' + px + 'px;border-radius:10px;overflow:hidden;'
        + 'display:flex;align-items:center;justify-content:center;flex:0 0 auto;'
        + 'background:#e2e8f0;color:#475569;font-weight:700;font-size:' + Math.round(px * 0.34) + 'px;'
        + 'border:1px solid #cbd5e1;';

      if (!url) {
        return '<div style="' + style + '" title="' + safeName + '">'
          + escapeHtml(initials(name)) + '</div>';
      }

      return '<div style="' + style + '" title="' + safeName + '">'
        + '<img src="' + escapeHtml(url) + '" alt="' + safeName + '"'
        + ' style="width:100%;height:100%;object-fit:cover;"'
        + ' onerror="this.parentNode.textContent=\'' + escapeHtml(initials(name)).replace(/'/g, '') + '\';">'
        + '</div>';
    },

    /**
     * Avatar followed by the name, for a "who holds this" line.
     */
    withName: function (url, name, size) {
      return '<span style="display:inline-flex;align-items:center;gap:8px;">'
        + this.html(url, name, size)
        + '<span>' + escapeHtml(name || '—') + '</span>'
        + '</span>';
    },

    initials: initials
  };

  window.UserAvatar = UserAvatar;
})(window, document);
