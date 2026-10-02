(function (window, document) {
  'use strict';

  /**
   * Ensure lucide icons remain stable even if CDN response changes.
   */
  function attachLucideSafeGuards() {
    function ensureLucideReady() {
      const lib = window.lucide;
      if (!lib) {
        return false;
      }

      const fallbackIcons =
        lib.icons || lib.allIcons || lib.iconNodes || lib.availableIcons;
      if (!lib.icons && fallbackIcons) {
        lib.icons = fallbackIcons;
      }

      if (typeof lib.createIcons === 'function' && !lib.__patchedCreateIcons) {
        const originalCreateIcons = lib.createIcons.bind(lib);
        lib.createIcons = function safeCreateIcons(options = {}) {
          const icons =
            options.icons ||
            lib.icons ||
            lib.allIcons ||
            lib.iconNodes ||
            lib.availableIcons;

          if (!icons) {
            console.warn('[Lucide] Icon map is unavailable; skipping render.', options);
            return;
          }

          try {
            return originalCreateIcons({ ...options, icons });
          } catch (error) {
            console.error('[Lucide] Failed to render icons:', error);
          }
        };
        lib.__patchedCreateIcons = true;
      }

      window.ensureLucideIcons = function ensureIcons(options = {}) {
        if (!lib) return;
        const icons =
          options.icons ||
          lib.icons ||
          lib.allIcons ||
          lib.iconNodes ||
          lib.availableIcons;
        if (!icons || typeof lib.createIcons !== 'function') return;
        lib.createIcons({ ...options, icons });
      };

      return true;
    }

    const ensure = () => ensureLucideReady();

    if (!ensure()) {
      document.addEventListener('DOMContentLoaded', ensure, { once: true });
      window.addEventListener('load', ensure, { once: true });
      setTimeout(ensure, 0);
    }
  }

  attachLucideSafeGuards();

  /**
   * Shared helpers exposed under window.AppLayout
   */
  const AppLayout = (window.AppLayout = window.AppLayout || {});

  AppLayout.ensureLucide = function ensureLucide() {
    if (typeof window.ensureLucideIcons === 'function') {
      window.ensureLucideIcons();
    } else if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  AppLayout.showAlerts = function showAlerts(payload = {}) {
    if (typeof Swal === 'undefined') {
      return;
    }

    if (payload.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: payload.success,
        confirmButtonColor: '#10b981',
      });
    }

    if (payload.error) {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: payload.error,
        confirmButtonColor: '#ef4444',
      });
    }

    if (Array.isArray(payload.validation) && payload.validation.length) {
      Swal.fire({
        icon: 'error',
        title: 'Validation Errors',
        html:
          '<ul style="text-align: left;">' +
          payload.validation.map((item) => `<li>${item}</li>`).join('') +
          '</ul>',
        confirmButtonColor: '#ef4444',
      });
    }
  };

  AppLayout.registerCsvImporter = function registerCsvImporter() {
    if (window.handleCsvImport) return;

    function parseRow(line) {
      const out = [];
      let cur = '';
      let inQuotes = false;
      for (let i = 0; i < line.length; i += 1) {
        const ch = line[i];
        if (ch === '"') {
          if (inQuotes && line[i + 1] === '"') {
            cur += '"';
            i += 1;
          } else {
            inQuotes = !inQuotes;
          }
        } else if (ch === ',' && !inQuotes) {
          out.push(cur);
          cur = '';
        } else {
          cur += ch;
        }
      }
      out.push(cur);
      return out.map((value) => value.replace(/^\s+|\s+$/g, ''));
    }

    function parseCsv(text) {
      const lines = text.split(/\r?\n/).filter((line) => line.trim() !== '');
      if (!lines.length) return [];
      const headers = parseRow(lines.shift()).map((header) => header.trim().toLowerCase());
      return lines.map((line) => {
        const cols = parseRow(line);
        const obj = {};
        headers.forEach((header, idx) => {
          obj[header] = (cols[idx] || '').trim();
        });
        return obj;
      });
    }

    window.handleCsvImport = function handleCsvImport() {
      try {
        const fileInput = document.getElementById('csvFileInput');
        const resultDiv = document.getElementById('csv-result');
        const loadingDiv = document.getElementById('csv-loading');

        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
          if (resultDiv) {
            resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please select a CSV file first.</div>';
          }
          return;
        }

        const file = fileInput.files[0];
        const isCsv = file.name.toLowerCase().endsWith('.csv') || file.type === 'text/csv';
        if (!isCsv) {
          if (resultDiv) {
            resultDiv.innerHTML = '<div class="text-red-600 text-sm">Please select a valid CSV file.</div>';
          }
          return;
        }

        if (loadingDiv) loadingDiv.style.display = 'block';
        const reader = new FileReader();
        reader.onload = function (event) {
          try {
            const records = parseCsv(event.target.result || '');
            const buyers = records.map((record) => ({
              buyerTitle: record['title'] || '',
              firstName: record['first name'] || record.first_name || '',
              middleName: record['middle name'] || record.middle_name || '',
              surname: record.surname || record['last name'] || record.last_name || '',
              unit_no: (record['unit number'] || record.unit_no || '').toUpperCase(),
              unitMeasurement: record['unit measurement'] || record.unit_measurement || '',
            }));
            window.dispatchEvent(new CustomEvent('update-buyers', { detail: { buyers } }));
            if (resultDiv) {
              resultDiv.innerHTML = `<div class="text-green-700 bg-green-50 border border-green-200 p-2 rounded text-sm">Imported ${buyers.length} buyer(s) from CSV.</div>`;
            }
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
              setTimeout(() => window.lucide.createIcons(), 50);
            }
          } catch (error) {
            console.error('CSV parse error:', error);
            if (resultDiv) {
              resultDiv.innerHTML = '<div class="text-red-700 bg-red-50 border border-red-200 p-2 rounded text-sm">Error parsing CSV. Please check the file format.</div>';
            }
          } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
          }
        };
        reader.onerror = function () {
          if (loadingDiv) loadingDiv.style.display = 'none';
          if (resultDiv) {
            resultDiv.innerHTML = '<div class="text-red-700 bg-red-50 border border-red-200 p-2 rounded text-sm">Failed to read the file.</div>';
          }
        };
        reader.readAsText(file);
      } catch (error) {
        console.error('handleCsvImport global error:', error);
      }
    };

    document.addEventListener('click', function (event) {
      const btn = event.target.closest('#importCsvButton');
      if (!btn) return;
      event.preventDefault();
      if (typeof window.handleCsvImport === 'function') {
        window.handleCsvImport();
      }
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.AppLayout.registerCsvImporter();
    if (window.AppLayout && typeof window.AppLayout.initPersonalAlert === 'function') {
      window.AppLayout.initPersonalAlert();
    }
  });

  if (window.AppLayoutPendingAlerts) {
    AppLayout.showAlerts(window.AppLayoutPendingAlerts);
    delete window.AppLayoutPendingAlerts;
  }

  const numberFormatter = new Intl.NumberFormat();

  function clampNumber(value, min, max) {
    if (typeof value !== 'number' || Number.isNaN(value)) {
      return min;
    }
    return Math.min(Math.max(value, min), max);
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function buildModuleChips(modules) {
    if (!Array.isArray(modules) || !modules.length) {
      return '';
    }

    return (
      '<div class="personal-alert-modules">' +
      modules
        .map(function (module) {
          const label = escapeHtml(module.label || module.key || 'Work Station');
          const count = numberFormatter.format(module.count || 0);
          const target = numberFormatter.format(module.target || 0);
          return '<span class="personal-alert-chip">' + label + ': ' + count + ' / ' + target + '</span>';
        })
        .join('') +
      '</div>'
    );
  }

  function renderAlertMarkup(alert) {
    const tones = ['success', 'warning', 'danger', 'info'];
    const tone = tones.includes(alert.tone) ? alert.tone : 'info';
    const percent = clampNumber(alert.percent || 0, 0, 150);
    const achieved = numberFormatter.format(alert.achieved || 0);
    const target = numberFormatter.format(alert.target || 0);
    const expected = numberFormatter.format(alert.expected || 0);
    const windowLabel = alert.window && alert.window.label ? escapeHtml(alert.window.label) : 'Daily target window';
    const modulesMarkup = buildModuleChips(alert.modules);

    return (
      '<div class="personal-alert-card" data-alert-card data-visible="false" data-tone="' + tone + '">' +
      '<button type="button" class="personal-alert-dismiss" aria-label="Dismiss alert" data-dismiss-alert>&times;</button>' +
      '<p class="personal-alert-label">Personal Alert</p>' +
      '<h3>' + escapeHtml(alert.headline || 'Daily progress update') + '</h3>' +
      '<p>' + escapeHtml(alert.details || 'Track your shift output in real time.') + '</p>' +
      '<div class="personal-alert-progress"><span style="width:' + percent + '%"></span></div>' +
      '<div class="personal-alert-meta">' +
      '<span>' + achieved + ' / ' + target + ' today</span>' +
      '<span>' + expected + ' expected by now</span>' +
      '</div>' +
      modulesMarkup +
      '<div class="personal-alert-window">' + windowLabel + '</div>' +
      '</div>'
    );
  }

  function createPersonalAlertManager() {
    const container = document.getElementById('global-personal-alert');
    if (!container) {
      return null;
    }

    if (container.dataset.disabled === 'true') {
      return null;
    }

    const metaEndpoint = document.querySelector('meta[name="activity-personal-alert"]');
    const endpoint = container.dataset.endpoint || (metaEndpoint ? metaEndpoint.getAttribute('content') : null);
    if (!endpoint) {
      return null;
    }

    const refreshValue = parseInt(container.dataset.refreshMinutes || '10', 10);
    const refreshMinutes = Number.isFinite(refreshValue) && refreshValue > 0 ? refreshValue : 10;
    const searchParams = new URLSearchParams(window.location.search);
    const forceAlert = container.dataset.forceAlert === '1' || searchParams.get('force_alert') === '1';

    return {
      container,
      endpoint,
      refreshMinutes,
      forceAlert,
      timer: null,
      lastSignature: null,
      snoozedUntil: 0,
      autoHideTimer: null,
      init() {
        this.fetchAlert();
        if (this.refreshMinutes > 0) {
          this.timer = setInterval(this.fetchAlert.bind(this), this.refreshMinutes * 60 * 1000);
        }
      },
      shouldSnooze() {
        return Date.now() < this.snoozedUntil;
      },
      signature(alert) {
        return JSON.stringify([
          alert.status,
          alert.tone,
          alert.target,
          alert.achieved,
          alert.expected,
          alert.percent,
        ]);
      },
      attachDismiss() {
        const dismissBtn = this.container.querySelector('[data-dismiss-alert]');
        if (!dismissBtn) {
          return;
        }
        dismissBtn.addEventListener('click', () => this.hide(true));
      },
      hide(manual = false) {
        const card = this.container.querySelector('[data-alert-card]');
        if (!card) {
          return;
        }
        if (this.autoHideTimer) {
          clearTimeout(this.autoHideTimer);
          this.autoHideTimer = null;
        }
        card.setAttribute('data-visible', 'false');
        setTimeout(() => {
          this.container.innerHTML = '';
        }, 220);
        if (manual) {
          this.snoozedUntil = Date.now() + 5 * 60 * 1000;
          this.lastSignature = null;
        }
      },
      render(alert) {
        if (!alert || this.shouldSnooze()) {
          if (!alert) {
            this.hide(false);
          }
          return;
        }

        const signature = this.signature(alert);
        if (signature === this.lastSignature && this.container.querySelector('[data-alert-card]')) {
          return;
        }

        this.lastSignature = signature;
        this.container.innerHTML = renderAlertMarkup(alert);
        requestAnimationFrame(() => {
          const card = this.container.querySelector('[data-alert-card]');
          if (card) {
            card.setAttribute('data-visible', 'true');
          }
        });
        this.attachDismiss();
        if (this.autoHideTimer) {
          clearTimeout(this.autoHideTimer);
        }
        this.autoHideTimer = setTimeout(() => {
          this.hide(false);
        }, 5000);
      },
      async fetchAlert() {
        if (this.shouldSnooze()) {
          return;
        }

        try {
          const url = new URL(this.endpoint, window.location.origin);
          url.searchParams.set('_', Date.now().toString());
          if (this.forceAlert) {
            url.searchParams.set('force_alert', '1');
          }

          const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          });

          if (!response.ok) {
            throw new Error('Unable to load alert');
          }

          const payload = await response.json();
          const alert = payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;

          if (!alert) {
            this.hide(false);
            return;
          }

          this.render(alert);
        } catch (error) {
          console.warn('Personal alert fetch failed', error);
        }
      },
    };
  }

  AppLayout.initPersonalAlert = function initPersonalAlert() {
    if (this._personalAlertManager) {
      return;
    }
    const manager = createPersonalAlertManager();
    if (!manager) {
      return;
    }
    this._personalAlertManager = manager;
    manager.init();
  };
})(window, document);
