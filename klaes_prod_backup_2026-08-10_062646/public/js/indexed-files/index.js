import { genderAttr, genderRowClass } from './gender.js';

const config = window.indexedFilesConfig || {};
const totalCols = (() => {
  const table = document.getElementById('indexed-files-table');
  const headerColumns = table ? table.querySelectorAll('thead th').length : 1;
  return Math.max(headerColumns, 1);
})();

const state = {
  page: 1,
  perPage: 20,
  // Newest-indexed first. Sorting by id looks like a date sort but isn't: backfill
  // commands write a historical created_at onto brand-new ids.
  sort: 'created_at',
  direction: 'desc',
  search: '',
  isLoading: false,
};

const dom = {
  tableBody: document.getElementById('indexed-files-tbody'),
  searchInput: document.getElementById('indexed-files-search'),
  perPageSelect: document.getElementById('indexed-files-per-page'),
  prevButton: document.getElementById('indexed-files-prev'),
  nextButton: document.getElementById('indexed-files-next'),
  pageLabel: document.getElementById('indexed-files-page'),
  summaryText: document.getElementById('indexed-files-summary'),
  statsTotal: document.getElementById('stats-total-indexed'),
  statsToday: document.getElementById('stats-indexed-today'),
  statsRegistries: document.getElementById('stats-registries'),
  table: document.getElementById('indexed-files-table'),
};

const headers = Array.from(dom.table.querySelectorAll('thead th[data-sort]'));
let actionHandlersBound = false;
const rowCache = new Map();

function createParams() {
  const params = new URLSearchParams({
    page: String(state.page),
    per_page: String(state.perPage),
    sort: state.sort,
    direction: state.direction,
  });

  if (state.search.trim() !== '') {
    params.set('search', state.search.trim());
  }

  if (config.registry) {
    params.set('registry', config.registry);
  }

  if (config.isCorrespondingFile) {
    params.set('is_corresponding_file', '1');
  }

  return params;
}

async function fetchJson(url, signal) {
  const response = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    signal,
  });

  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || `Request failed with status ${response.status}`);
  }

  return response.json();
}

function setLoading(isLoading) {
  state.isLoading = isLoading;
  if (isLoading) {
    dom.tableBody.innerHTML = '<tr><td colspan="' + totalCols + '" class="p-6 text-center text-sm text-gray-500">Loading indexed files...</td></tr>';
  }
}

function renderRows(rows) {
  if (!Array.isArray(rows) || rows.length === 0) {
    dom.tableBody.innerHTML = '<tr><td colspan="' + totalCols + '" class="p-6 text-center text-sm text-gray-500">No indexed files found.</td></tr>';
    return;
  }

  const standardCellClass = 'p-3 whitespace-nowrap text-gray-600';

  closeAllActionMenus();
  rowCache.clear();

  const hidden = Array.isArray(config.hiddenColumns) ? config.hiddenColumns : [];

  function col(key, html) {
    return hidden.includes(key) ? '' : html;
  }

  const tableVariant = typeof config.tableVariant === 'string'
    ? config.tableVariant.toLowerCase().trim()
    : 'main';
  const isKangisVariant = tableVariant === 'kangis' || tableVariant === 'sltr';
  const isCadastralVariant = tableVariant === 'cadastral';

  const fragments = rows.map((row, rowIndex) => {
    const viewUrl = buildViewUrl(row.view_url, row.id);
    const statusBadge = buildStatusBadge(row.status);
    const fileNumberBadge = buildFileNumberBadge(row.file_number, row.is_temp_fallback, row.kangis_fileno_placeholder);
    const landUseBadge = buildLandUseBadge(row.land_use_type);
    const lgaValue = row.lga == null ? '' : String(row.lga).toUpperCase();
    // Row tint by gender — see the legend partial for the key.
    const genderClass = genderRowClass(row.gender);
    const genderData = `data-gender="${escapeHtml(genderAttr(row.gender))}"`;
    rowCache.set(String(row.id), row);

    // Cadastral: render cells driven by config.columns order
    if (isCadastralVariant && Array.isArray(config.columns)) {
      const rowCells = config.columns
        .filter(c => !hidden.includes(c.key))
        .map(c => {
          switch (c.key) {
            case 'sn':
              return `<td class="p-3 whitespace-nowrap text-gray-500 font-medium text-center">${(state.page - 1) * state.perPage + rowIndex + 1}</td>`;
            case 'shelf_location':
              return `<td class="${standardCellClass}">${escapeHtml(row.shelf_location)}</td>`;
            case 'file_title':
              return `<td class="p-3 whitespace-nowrap text-gray-700">${escapeHtml(row.file_title)}</td>`;
            case 'file_number':
              return `<td class="p-3 whitespace-nowrap">
                ${fileNumberBadge}
                ${row.has_edms_files ? `<div class="mt-1">
                  <button type="button" class="edms-view-files-btn inline-flex items-center gap-1 text-[10px] font-bold text-orange-600 hover:text-orange-800 hover:underline transition-colors" data-id="${row.id}" data-file-number="${escapeHtml(row.file_number)}" data-registry-folder="Cadastral_Registry1">
                    <i data-lucide="folder-open" class="w-3 h-3"></i>
                    <span>View Files</span>
                  </button>
                </div>` : ''}
              </td>`;
            case 'corresponding_fileno':
              return `<td class="${standardCellClass}">${row.corresponding_fileno
                ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-200">${escapeHtml(row.corresponding_fileno)}</span>`
                : '<span class="text-gray-400">-</span>'}</td>`;
            case 'related_fileno_action':
              return `<td class="${standardCellClass}">${row.has_related_files
                ? `<button type="button" class="view-related-files-btn inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm" data-id="${row.id}"><i data-lucide="link" class="w-3 h-3"></i><span>View FileNo(s)</span></button>`
                : '<span class="text-gray-400 font-medium text-[10px] uppercase">None</span>'}</td>`;
            case 'land_use_type':
              return `<td class="p-3 whitespace-nowrap">${landUseBadge}</td>`;
            case 'plot_number':
              return `<td class="${standardCellClass}">${escapeHtml(row.plot_number)}</td>`;
            case 'tp_no':
              return `<td class="${standardCellClass}">${escapeHtml(row.tp_no)}</td>`;
            case 'lpkn_no':
              return `<td class="${standardCellClass}">${escapeHtml(row.lpkn_no)}</td>`;
            case 'district':
              return `<td class="${standardCellClass}">${escapeHtml(row.district)}</td>`;
            case 'lga':
              return `<td class="${standardCellClass}">${escapeHtml(lgaValue)}</td>`;
            case 'indexed_by':
              return `<td class="${standardCellClass}">${escapeHtml(row.indexed_by)}</td>`;
            case 'indexed_date':
              return `<td class="${standardCellClass}">${escapeHtml(row.indexed_at ?? '')}</td>`;
            case 'dciv_fileno':
              return `<td class="p-3">${Number(row.dciv_status) === 1 && row.dciv_fileno ? `<div class="flex flex-col gap-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">${escapeHtml(row.dciv_fileno)}</span><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap"><i data-lucide="alert-triangle" class="w-3 h-3"></i>Under Investigation</span></div>` : '<span class="text-gray-400">-</span>'}</td>`;
            case 'dciv_reason':
              return `<td class="p-3 text-gray-600 max-w-xs whitespace-normal break-words">${Number(row.dciv_status) === 1 && row.dciv_reason ? escapeHtml(row.dciv_reason) : '<span class="text-gray-400">-</span>'}</td>`;
            case 'status':
              return `<td class="p-3 whitespace-nowrap">${statusBadge}</td>`;
            default:
              return '';
          }
        })
        .join('');
      return `<tr class="hover:bg-gray-50 ${genderClass}" ${genderData} data-row-id="${row.id}">${rowCells}${config.hideActions ? '' : `<td class="p-3 whitespace-nowrap text-center">${buildActionsMenu(row, viewUrl)}</td>`}</tr>`;
    }

    const rowCells = isKangisVariant
      ? (() => {
        // Detect based on prefix as requested
        const fn = (row.file_number || '').toUpperCase().trim();
        const p = (row.kangis_fileno_placeholder || '').toUpperCase().trim();
        const rel = (row.related_file_no || '').toUpperCase().trim();
        const mls = (row.mls_file_no || '').toUpperCase().trim();
        const nkn = (row.new_kangis_file_no || '').toUpperCase().trim();

        let kangisVal = row.kangis_file_no || '';
        let placeholderVal = row.kangis_fileno_placeholder || '';
        let newKangisVal = row.new_kangis_file_no || '';
        let mlsVal = row.mls_file_no || (row.related_file_no !== '-' ? row.related_file_no : '');

        // Smart detection based on prefixes
        const allValues = [fn, p, rel, mls, nkn, row.temp_file_no || ''].map(v => v.toUpperCase().trim()).filter(v => v !== '' && v !== '-');

        allValues.forEach(val => {
          if (val.startsWith('KNML') || val.startsWith('MNKL') || val.startsWith('MLKN') || val.startsWith('KNGP')) {
            if (!kangisVal) kangisVal = val;
          } else if (val.startsWith('KN') && !val.startsWith('KNML')) {
            // If it has a space and 4+ digits, it's likely a placeholder
            if (val.includes(' ') && /\d{4,}/.test(val)) {
              if (!placeholderVal) placeholderVal = val;
            } else {
              if (!newKangisVal) newKangisVal = val;
            }
          } else if (/^(RES|COM|IND|AG|CON|MISC|SIT|SLTR|DCIV|LPCC|GKN|LPKN)/.test(val)) {
            if (!mlsVal) mlsVal = val;
          }
        });

        // Fallback to row fields if still empty
        if (!kangisVal && (fn.startsWith('KNML') || fn.startsWith('MNKL') || fn.startsWith('MLKN') || fn.startsWith('KNGP'))) kangisVal = row.file_number;
        if (!newKangisVal && fn.startsWith('KN') && !fn.startsWith('KNML') && !fn.includes(' ')) newKangisVal = row.file_number;
        if (!mlsVal && /^(RES|COM|IND|AG|CON|MISC|SIT|SLTR|DCIV|LPCC|GKN|LPKN)/.test(fn)) mlsVal = row.file_number;

        // Ensure mlsVal strictly matches MLS prefixes, otherwise empty it out
        if (mlsVal && !/^(RES|COM|IND|AG|CON|MISC|SIT|SLTR|DCIV|LPCC|GKN|LPKN)/.test(mlsVal.toUpperCase().trim())) {
          mlsVal = '';
        }

        // A Kangis FileNo that just repeats the Newkangis number (e.g. KN153 -> "KN153")
        // is a bad copy, not a mapping — don't print the same number in both columns.
        const normFileNo = v => (v || '').toUpperCase().replace(/[\s\-\/.]+/g, '');
        if (kangisVal && newKangisVal && normFileNo(kangisVal) === normFileNo(newKangisVal)) {
          kangisVal = '';
        }

        return `
            ${col('shelf_location', `<td class="${standardCellClass}">${escapeHtml(row.shelf_location)}</td>`)}
            ${col('file_number', `<td class="p-3 whitespace-nowrap">
              ${tableVariant === 'sltr'
                ? (row.file_number ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 border border-violet-200">${escapeHtml(row.file_number)}</span>` : '<span class="text-gray-400">-</span>')
                : (newKangisVal ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">${escapeHtml(newKangisVal)}</span>` : '<span class="text-gray-400">-</span>')}
              ${tableVariant === 'sltr' && row.has_edms_files ? `<div class="mt-1"><button type="button" class="edms-view-files-btn inline-flex items-center gap-1 text-[10px] font-bold text-violet-600 hover:text-violet-800 hover:underline transition-colors" data-id="${row.id}" data-file-number="${escapeHtml(row.file_number)}" data-registry-folder="SLTR_Registry"><i data-lucide="folder-open" class="w-3 h-3"></i><span>View Files</span></button></div>` : ''}
              ${tableVariant === 'kangis' && row.has_edms_files ? `<div class="mt-1"><button type="button" class="edms-view-files-btn inline-flex items-center gap-1 text-[10px] font-bold text-purple-600 hover:text-purple-800 hover:underline transition-colors" data-id="${row.id}" data-file-number="${escapeHtml(row.file_number)}" data-registry-folder="KANGIS_Registry"><i data-lucide="folder-open" class="w-3 h-3"></i><span>View Files</span></button></div>` : ''}
            </td>`)}
            ${col('kangis_fileno_placeholder', `<td class="${standardCellClass}">
              <div class="flex flex-col gap-1">
                ${kangisVal ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 cursor-help" title="Placeholder File No: ${escapeHtml(placeholderVal || 'N/A')}">${escapeHtml(kangisVal)}</span>` : ''}
                ${placeholderVal && placeholderVal !== kangisVal ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200 mt-1 cursor-help" title="Placeholder File No: ${escapeHtml(placeholderVal)}">${escapeHtml(placeholderVal)}</span>` : ''}
                ${!kangisVal && !placeholderVal ? '<span class="text-gray-400">-</span>' : ''}
              </div>
            </td>`)}
            ${col('new_kangis_file_no', `<td class="${standardCellClass}">${newKangisVal ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">${escapeHtml(newKangisVal)}</span>` : '<span class="text-gray-400">-</span>'}</td>`)}
            ${col('related_file_no', `<td class="${standardCellClass}">
              ${mlsVal ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">${escapeHtml(mlsVal)}</span>` : '<span class="text-gray-400">-</span>'}
            </td>`)}
            ${col('related_fileno_action', `<td class="${standardCellClass}">
              ${row.has_related_files ? `
                <button type="button" class="view-related-files-btn inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm" data-id="${row.id}">
                  <i data-lucide="link" class="w-3 h-3"></i>
                  <span>View Related FileNo(s)</span>
                </button>
              ` : '<span class="text-gray-400 font-medium text-[10px] uppercase">None</span>'}
            </td>`)}
            ${col('dciv_fileno', `<td class="p-3">${Number(row.dciv_status) === 1 && row.dciv_fileno ? `<div class="flex flex-col gap-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">${escapeHtml(row.dciv_fileno)}</span><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap"><i data-lucide="alert-triangle" class="w-3 h-3"></i>Under Investigation</span></div>` : '<span class="text-gray-400">-</span>'}</td>`)}
            ${col('file_title', `<td class="p-3 whitespace-nowrap text-gray-700">${escapeHtml(row.file_title)}</td>`)}
            ${col('land_use_type', `<td class="p-3 whitespace-nowrap">${landUseBadge}</td>`)}
            ${col('plot_number', `<td class="${standardCellClass}">${escapeHtml(row.plot_number)}</td>`)}
            ${col('tp_no', `<td class="${standardCellClass}">${escapeHtml(row.tp_no)}</td>`)}
            ${col('lpkn_no', `<td class="${standardCellClass}">${escapeHtml(row.lpkn_no)}</td>`)}
            ${col('district', `<td class="${standardCellClass}">${escapeHtml(row.district)}</td>`)}
            ${col('lga', `<td class="${standardCellClass}">${escapeHtml(lgaValue)}</td>`)}
            ${col('indexed_by', `<td class="${standardCellClass}">${escapeHtml(row.indexed_by)}</td>`)}
            ${col('indexed_date', `<td class="${standardCellClass}">${escapeHtml(row.indexed_at ?? '')}</td>`)}
            ${col('dciv_reason', `<td class="p-3 text-gray-600 max-w-xs whitespace-normal break-words">${Number(row.dciv_status) === 1 && row.dciv_reason ? escapeHtml(row.dciv_reason) : '<span class="text-gray-400">-</span>'}</td>`)}
            ${col('lon_value', buildLonCell(row))}
            ${col('lat_value', buildLatCell(row))}
            ${col('latlon', buildLatLonCell(row))}
            ${col('status', `<td class="p-3 whitespace-nowrap">${statusBadge}</td>`)}
          `;
      })()
      : `
        ${col('shelf_location', `<td class="${standardCellClass}">${escapeHtml(row.shelf_location)}</td>`)}
        ${col('general_registry', `<td class="${standardCellClass}">${escapeHtml(row.general_registry)}</td>`)}
        ${col('registry', `<td class="${standardCellClass}">${escapeHtml(getRegistryDisplayValue(row))}</td>`)}
        ${col('file_number', `<td class="p-3 whitespace-nowrap">
          ${fileNumberBadge}
          ${tableVariant === 'main' ? `<div class="mt-1"><button type="button" class="edms-view-files-btn inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-800 hover:underline transition-colors" data-id="${row.id}" data-file-number="${escapeHtml(row.file_number)}" data-registry-folder="Lands_Registry"><i data-lucide="folder-open" class="w-3 h-3"></i><span>View Files</span></button></div>` : ''}
        </td>`)}
        ${col('corresponding_fileno', `<td class="${standardCellClass}">${row.corresponding_fileno ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-200">${escapeHtml(row.corresponding_fileno)}</span>` : '<span class="text-gray-400">-</span>'}</td>`)}
        ${col('pp_lands_fileno', `<td class="${standardCellClass}">${row.pp_lands_fileno ? `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">${escapeHtml(row.pp_lands_fileno)}</span>` : '<span class="text-gray-400">-</span>'}</td>`)}
        ${col('related_file_no', `<td class="${standardCellClass}">
          ${row.has_related_files ? `
            <div class="flex flex-col gap-1">
              <button type="button" class="view-related-files-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm" data-id="${row.id}">
                <i data-lucide="link" class="w-3 h-3"></i>
                <span>${escapeHtml(row.related_file_display && row.related_file_display !== '-' ? row.related_file_display : 'View Related Files')}</span>
              </button>
            </div>
          ` : '<span class="text-gray-400 font-medium text-[10px] uppercase">None</span>'}
        </td>`)}
        ${col('temp_file_no', `<td class="${standardCellClass}">
          ${row.temp_file_no ? `
            <button type="button" class="open-temp-file-btn inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg hover:bg-amber-600 hover:text-white hover:border-amber-600 transition-all shadow-sm"
              data-file-id="${row.id}"
              data-file-number="${escapeHtml(row.file_number)}"
              data-file-title="${escapeHtml(row.file_title)}"
              data-plot-number="${escapeHtml(row.plot_number)}"
              data-district="${escapeHtml(row.district)}"
              data-lga="${escapeHtml(row.lga)}"
              data-location="${escapeHtml(row.location ?? '')}"
              data-temp-file-no="${escapeHtml(row.temp_file_no)}">
              <i data-lucide="file-clock" class="w-3 h-3"></i>
              <span>${escapeHtml(row.temp_file_no)}</span>
            </button>
          ` : '<span class="text-gray-400 font-medium text-[10px] uppercase">None</span>'}
        </td>`)}
        ${col('dciv_fileno', `<td class="p-3">${Number(row.dciv_status) === 1 && row.dciv_fileno ? `<div class="flex flex-col gap-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">${escapeHtml(row.dciv_fileno)}</span><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap"><i data-lucide="alert-triangle" class="w-3 h-3"></i>Under Investigation</span></div>` : '<span class="text-gray-400">-</span>'}</td>`)}
        ${col('file_title', `<td class="p-3 whitespace-nowrap text-gray-700">${escapeHtml(row.file_title)}</td>`)}
        ${col('plot_number', `<td class="${standardCellClass}">${escapeHtml(row.plot_number)}</td>`)}
        ${col('indexed_date', `<td class="${standardCellClass}">${escapeHtml(row.indexed_at ?? '')}</td>`)}
        ${col('indexed_by', `<td class="${standardCellClass}">${escapeHtml(row.indexed_by)}</td>`)}
        ${col('tp_no', `<td class="${standardCellClass}">${escapeHtml(row.tp_no)}</td>`)}
        ${col('lpkn_no', `<td class="${standardCellClass}">${escapeHtml(row.lpkn_no)}</td>`)}
        ${col('land_use_type', `<td class="p-3 whitespace-nowrap">${landUseBadge}</td>`)}
        ${col('district', `<td class="${standardCellClass}">${escapeHtml(row.district)}</td>`)}
        ${col('lga', `<td class="${standardCellClass}">${escapeHtml(lgaValue)}</td>`)}
        ${col('registry_batch_no', `<td class="${standardCellClass}">${escapeHtml(row.registry_batch_no)}</td>`)}
        ${col('dciv_reason', `<td class="p-3 text-gray-600 max-w-xs whitespace-normal break-words">${Number(row.dciv_status) === 1 && row.dciv_reason ? escapeHtml(row.dciv_reason) : '<span class="text-gray-400">-</span>'}</td>`)}
        ${col('lon_value', buildLonCell(row))}
        ${col('lat_value', buildLatCell(row))}
        ${col('latlon', buildLatLonCell(row))}
        ${col('status', `<td class="p-3 whitespace-nowrap">${statusBadge}</td>`)}
      `;

    return `
      <tr class="hover:bg-gray-50 ${genderClass}" ${genderData} data-row-id="${row.id}">
        ${rowCells}
        ${config.hideActions ? '' : `<td class="p-3 whitespace-nowrap text-center">${buildActionsMenu(row, viewUrl)}</td>`}
      </tr>
    `;
  }).join('');

  dom.tableBody.innerHTML = fragments;
  bindActionHandlers();
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

function buildViewUrl(viewUrl, id) {
  if (typeof viewUrl === 'string' && viewUrl.includes('__ID__')) {
    return viewUrl.replace('__ID__', String(id));
  }

  if (typeof viewUrl === 'string') {
    return viewUrl;
  }

  if (typeof config.showUrlTemplate === 'string') {
    return config.showUrlTemplate.replace('__ID__', String(id));
  }

  return '#';
}

function buildStatusBadge(status) {
  const normalized = (status || '').toLowerCase();
  const styles = {
    typed: 'bg-indigo-100 text-indigo-700',
    scanned: 'bg-amber-100 text-amber-700',
    indexed: 'bg-blue-100 text-blue-700',
  };

  const label = status || 'Unknown';
  const className = styles[normalized] || 'bg-slate-100 text-slate-700';

  return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${className}">${escapeHtml(label)}</span>`;
}

function buildDcivStatusBadge(status) {
  if (Number(status) === 1) {
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 border border-rose-200"><i data-lucide="alert-triangle" class="w-3 h-3"></i>DCIV</span>';
  }
  return '<span class="text-gray-400">-</span>';
}

function buildFileNumberBadge(fileNumber, isTempFallback = false, kangisPlaceholder = '') {
  if (!fileNumber) {
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">No File</span>';
  }

  const hasPlaceholder = kangisPlaceholder && kangisPlaceholder !== fileNumber;
  const titleAttr = hasPlaceholder ? ` title="Placeholder File No: ${escapeHtml(kangisPlaceholder)}"` : '';
  const cursorClass = hasPlaceholder ? ' cursor-help' : '';

  if (isTempFallback) {
    return `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200${cursorClass}"${titleAttr}>${escapeHtml(fileNumber)}</span>`;
  }

  return `<span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200${cursorClass}"${titleAttr}>${escapeHtml(fileNumber)}</span>`;
}

function buildLatCell(row) {
  const hasLat = row.latitude !== null && row.latitude !== undefined && row.latitude !== '';
  const displayText = hasLat ? Number(row.latitude).toFixed(6) : '<span class="text-gray-400">-</span>';
  return `<td class="p-3 whitespace-nowrap text-sm font-medium text-slate-700 lat-value-cell">${displayText}</td>`;
}

function buildLonCell(row) {
  const hasLon = row.longitude !== null && row.longitude !== undefined && row.longitude !== '';
  const displayText = hasLon ? Number(row.longitude).toFixed(6) : '<span class="text-gray-400">-</span>';
  return `<td class="p-3 whitespace-nowrap text-sm font-medium text-slate-700 lon-value-cell">${displayText}</td>`;
}

function buildLatLonCell(row) {
  return `<td class="p-3 whitespace-nowrap text-gray-600 latlon-cell">
    <button type="button" class="open-location-map-btn inline-flex items-center gap-2 justify-center rounded-2xl border border-slate-200 bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 transition"
      data-id="${row.id}"
      data-lat="${escapeHtml(row.latitude ?? '')}"
      data-lon="${escapeHtml(row.longitude ?? '')}"
      data-file-number="${escapeHtml(row.file_number)}"
      data-file-title="${escapeHtml(row.file_title)}">
      <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
      <span>View Map</span>
    </button>
  </td>`;
}

function buildLandUseBadge(landUse) {
  if (!landUse || landUse === '-') {
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-gray-100 text-gray-500 border border-gray-200">N/A</span>';
  }

  const raw = landUse.toString().trim().toUpperCase();

  // Normalize abbreviations to full names for consistency
  const normMap = {
    'COM': 'COMMERCIAL',
    'RES': 'RESIDENTIAL',
    'IND': 'INDUSTRIAL',
    'MIX': 'MIXED USE',
    'AGR': 'AGRICULTURAL'
  };

  const displayLabel = normMap[raw] || raw;

  const palette = {
    'COMMERCIAL': 'bg-amber-50 text-amber-700 border-amber-200',
    'RESIDENTIAL': 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'INDUSTRIAL': 'bg-slate-50 text-slate-700 border-slate-200',
    'MIXED USE': 'bg-purple-50 text-purple-700 border-purple-200',
    'PUBLIC': 'bg-blue-50 text-blue-700 border-blue-200',
    'AGRICULTURAL': 'bg-lime-50 text-lime-700 border-lime-200',
    'EDUCATIONAL': 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'RELIGIOUS': 'bg-rose-50 text-rose-700 border-rose-200'
  };

  const className = palette[displayLabel] || 'bg-gray-50 text-gray-600 border-gray-200';

  return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase border ${className}">${escapeHtml(displayLabel)}</span>`;
}

function buildActionsMenu(row, viewUrl) {
  const id = row.id;
  const editUrl = buildEditUrl(id);
  const deleteUrl = buildDeleteUrl(id);
  const trackingUrl = buildTrackingUrl(id);
  const safeFileNumber = escapeHtml(row.file_number ?? `File #${id}`);

  const allowTracking = isTrackingAllowed(row);
  const trackingClass = allowTracking ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed opacity-60';
  const trackingDisabledAttr = allowTracking ? '' : 'disabled="disabled"';

  // Primary Edit Button for immediate access
  const primaryEdit = editUrl
    ? `<button type="button" class="edit-file-btn inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded-xl hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all duration-200 shadow-sm" data-file-id="${id}" data-edit-url="${escapeHtml(editUrl)}">
          <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
          <span>Edit</span>
        </button>`
    : '';

  const viewButton = viewUrl
    ? `<button type="button" class="view-file-btn block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors" data-file-id="${id}" data-view-url="${escapeHtml(viewUrl)}">
          <i data-lucide="eye" class="h-4 w-4 mr-2.5 inline text-blue-500"></i>
          View Details
        </button>`
    : '';

  const editButton = editUrl
    ? `<button type="button" class="edit-file-btn block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors font-semibold" data-file-id="${id}" data-edit-url="${escapeHtml(editUrl)}">
          <i data-lucide="edit" class="h-4 w-4 mr-2.5 inline text-indigo-500"></i>
          Edit Record
        </button>`
    : '';

  const isKangisVariant = (config.tableVariant || '') === 'kangis';
  const trackingLabel = isKangisVariant ? 'Print Tracking Sheet' : 'View Tracking Sheet';

  const isMatchedRow = !!(row.corresponding_fileno && String(row.corresponding_fileno).trim() !== '' && String(row.corresponding_fileno).trim() !== '-');
  const commissionSheetButton = (config.enableCommissioningSheet && isMatchedRow)
    ? `<button type="button" class="print-commissioning-sheet-btn block w-full text-left px-4 py-2.5 text-sm text-orange-700 hover:bg-orange-50 transition-colors" data-file-id="${id}">
          <i data-lucide="file-text" class="h-4 w-4 mr-2.5 inline text-orange-600"></i>
          Matching Slip
        </button>`
    : '';

  if (config.enableCommissioningSheet) {
    if (!isMatchedRow) {
      return '';
    }
    return `
      <div class="relative inline-block text-left" data-action-menu>
        <button type="button" class="actions-dropdown-btn inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 hover:text-blue-600 hover:border-blue-200 focus:outline-none focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm" data-file-id="${id}">
          <i data-lucide="more-horizontal" class="h-5 w-5"></i>
        </button>
        <div class="actions-dropdown-menu hidden absolute right-0 z-30 mt-2 w-48 origin-top-right rounded-2xl border border-slate-100 bg-white shadow-xl ring-1 ring-black/5 focus:outline-none overflow-hidden" data-menu-for="${id}">
          <div class="py-1.5">
            ${commissionSheetButton}
          </div>
        </div>
      </div>
    `;
  }

  const trackingButton = trackingUrl
    ? `<button type="button" class="print-tracking-btn block w-full text-left px-4 py-2.5 text-sm ${trackingClass} transition-colors" data-file-id="${id}" data-tracking-url="${escapeHtml(trackingUrl)}" ${trackingDisabledAttr}>
          <i data-lucide="printer" class="h-4 w-4 mr-2.5 inline ${allowTracking ? 'text-emerald-500' : 'text-gray-300'}"></i>
          ${trackingLabel}
        </button>`
    : '';

  // IsDuplicate is hidden — "Move to Indexing Duplicates" took its place in the
  // menu. Kept (with its handler and endpoint) so it can be put back by adding
  // ${duplicateButton} to the menu again.
  // eslint-disable-next-line no-unused-vars
  const duplicateButton = `<button type="button" class="indexed-duplicate-btn block w-full text-left px-4 py-2.5 text-sm text-amber-700 hover:bg-amber-50 transition-colors" data-file-id="${id}" data-file-number="${safeFileNumber}">
          <i data-lucide="copy-check" class="h-4 w-4 mr-2.5 inline text-amber-600"></i>
          IsDuplicate
        </button>`;

  // Destructive: archives the record to indexing_duplicates and deletes it from
  // file_indexings, fileNumber, customers_staging and entities_staging. Kept out
  // of the menu entirely unless assign_role is Supper Admin.
  // flex + shrink-0 icon so the two-word-longer label wraps inside the menu
  // instead of being clipped by its overflow-hidden rounded corners.
  const moveToDuplicatesButton = !config.canMoveToIndexingDuplicates ? '' : `<button type="button" class="move-indexing-duplicate-btn flex items-start gap-2.5 w-full text-left px-4 py-2.5 text-sm text-rose-700 hover:bg-rose-50 transition-colors" data-file-id="${id}" data-file-number="${safeFileNumber}">
          <i data-lucide="folder-minus" class="h-4 w-4 mt-0.5 shrink-0 text-rose-600"></i>
          <span class="leading-snug">Move to Indexing Duplicates</span>
        </button>`;

  const duplicateCallupButton = `<button type="button" class="duplicate-callup-btn block w-full text-left px-4 py-2.5 text-sm ${row.has_duplicate ? 'text-rose-700 hover:bg-rose-50' : 'text-gray-400 cursor-not-allowed opacity-40'} transition-colors" data-file-id="${id}" data-file-number="${safeFileNumber}" ${row.has_duplicate ? '' : 'disabled="disabled"'}>
          <i data-lucide="copy" class="h-4 w-4 mr-2.5 inline ${row.has_duplicate ? 'text-rose-600' : 'text-gray-300'}"></i>
          Duplicate Call-up Sheet
        </button>`;

  const tempFileButton = `<button type="button" class="has-temp-file-btn block w-full text-left px-4 py-2.5 text-sm text-teal-700 hover:bg-teal-50 transition-colors" data-file-id="${id}" data-file-number="${safeFileNumber}"
          data-file-title="${escapeHtml(row.file_title ?? '')}"
          data-plot-number="${escapeHtml(row.plot_number ?? '')}"
          data-district="${escapeHtml(row.district ?? '')}"
          data-lga="${escapeHtml(row.lga ?? '')}"
          data-location="${escapeHtml(row.location ?? '')}"
          data-temp-file-no="${escapeHtml(row.temp_file_no ?? '')}">
          <i data-lucide="file-key" class="h-4 w-4 mr-2.5 inline text-teal-600"></i>
          Has Temporary File
        </button>`;

  const mccFileNoButton = `<button type="button" class="mcc-fileno-btn flex items-start gap-2.5 w-full text-left px-4 py-2.5 text-sm text-orange-700 hover:bg-orange-50 transition-colors" data-file-id="${id}">
          <i data-lucide="git-compare" class="h-4 w-4 mt-0.5 shrink-0 text-orange-600"></i>
          <span class="leading-snug">Match Correspondence<br>FileNo${isMatchedRow ? ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-50 text-green-600 border border-green-200 align-middle">MATCHED</span>' : ''}</span>
        </button>`;

  const isPpMatchedRow = !!(row.pp_lands_matching && Number(row.pp_lands_matching) === 1) || !!(row.pp_lands_fileno && String(row.pp_lands_fileno).trim() !== '' && String(row.pp_lands_fileno).trim() !== '-');
  const mppFileNoButton = `<button type="button" class="mpp-fileno-btn flex items-start gap-2.5 w-full text-left px-4 py-2.5 text-sm text-sky-700 hover:bg-sky-50 transition-colors" data-file-id="${id}">
          <i data-lucide="map" class="h-4 w-4 mt-0.5 shrink-0 text-sky-600"></i>
          <span class="leading-snug">Match Shadow File ${isPpMatchedRow ? ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-50 text-green-600 border border-green-200 align-middle">MATCHED</span>' : ''}</span>
        </button>`;

  // Disabled when the record has no related file numbers at all. When it does, the
  // handler opens the Related Files list rather than guessing: the row only knows
  // *that* related files exist, not how many (link-sourced rows show "Linked Records"
  // with no count), so the authoritative list decides whether to confirm one directly
  // or make the operator pick.
  const unlinkRelatedButton = `<button type="button" class="unlink-related-menu-btn flex items-start gap-2.5 w-full text-left px-4 py-2.5 text-sm ${row.has_related_files ? 'text-red-700 hover:bg-red-50' : 'text-gray-400 cursor-not-allowed opacity-40'} transition-colors" data-file-id="${id}" data-file-number="${safeFileNumber}" ${row.has_related_files ? '' : 'disabled="disabled"'}>
          <i data-lucide="unlink" class="h-4 w-4 mt-0.5 shrink-0 ${row.has_related_files ? 'text-red-600' : 'text-gray-300'}"></i>
          <span class="leading-snug">Unlink Related FileNo</span>
        </button>`;

  const updatePlaceholderButton = (isKangisVariant || row.kangis_fileno_placeholder) ? `<button type="button" class="update-placeholder-btn block w-full text-left px-4 py-2.5 text-sm text-purple-700 hover:bg-purple-50 transition-colors" data-file-id="${id}" data-placeholder="${escapeHtml(row.kangis_fileno_placeholder ?? '')}">
          <i data-lucide="edit-3" class="h-4 w-4 mr-2.5 inline text-purple-600"></i>
          KANGIS FileNo Placeholder
        </button>` : '';

  const deleteButton = deleteUrl
    ? `<button type="button" class="indexed-delete-btn block w-full text-left px-4 py-2.5 text-sm text-gray-400 cursor-not-allowed opacity-40 transition-colors" data-delete-url="${deleteUrl}" data-file-number="${safeFileNumber}" disabled="disabled">
          <i data-lucide="trash-2" class="h-4 w-4 mr-2.5 inline text-gray-300"></i>
          Delete Record
        </button>`
    : '';

  // KANGIS variant: only show Print Tracking Sheet
  if (isKangisVariant) {
    return `
      <div class="relative inline-block text-left" data-action-menu>
        <button type="button" class="actions-dropdown-btn inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 hover:text-blue-600 hover:border-blue-200 focus:outline-none focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm" data-file-id="${id}">
          <i data-lucide="more-horizontal" class="h-5 w-5"></i>
        </button>
        <div class="actions-dropdown-menu hidden absolute right-0 z-30 mt-2 w-64 origin-top-right rounded-2xl border border-slate-100 bg-white shadow-xl ring-1 ring-black/5 focus:outline-none overflow-hidden" data-menu-for="${id}">
          <div class="py-1.5">
            <div class="px-4 py-2 border-b border-slate-50 mb-1">
               <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">File Actions</p>
            </div>
            ${trackingButton}
            ${updatePlaceholderButton}
            ${moveToDuplicatesButton ? '<div class="border-t border-slate-50 my-1.5"></div>' + moveToDuplicatesButton : ''}
          </div>
        </div>
      </div>
    `;
  }

  return `
    <div class="relative inline-block text-left" data-action-menu>
      <button type="button" class="actions-dropdown-btn inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 hover:text-blue-600 hover:border-blue-200 focus:outline-none focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm" data-file-id="${id}">
        <i data-lucide="more-horizontal" class="h-5 w-5"></i>
      </button>
      <div class="actions-dropdown-menu hidden absolute right-0 z-30 mt-2 w-64 origin-top-right rounded-2xl border border-slate-100 bg-white shadow-xl ring-1 ring-black/5 focus:outline-none overflow-hidden" data-menu-for="${id}">
        <div class="py-1.5">
          <div class="px-4 py-2 border-b border-slate-50 mb-1">
             <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">File Actions</p>
          </div>
          ${editButton}
          ${viewButton}
          ${trackingButton}
          ${commissionSheetButton}
          ${moveToDuplicatesButton}
          ${duplicateCallupButton}
          ${tempFileButton}
          ${mccFileNoButton}
          ${mppFileNoButton}
          ${unlinkRelatedButton}
          ${updatePlaceholderButton}
          ${deleteButton ? '<div class="border-t border-slate-50 my-1.5"></div>' + deleteButton : ''}
        </div>
      </div>
    </div>
  `;
}

function buildEditUrl(id) {
  if (!id) {
    return null;
  }

  if (typeof config.editUrlTemplate === 'string') {
    return config.editUrlTemplate.replace('__ID__', String(id));
  }

  if (typeof config.editUrl === 'string') {
    return config.editUrl;
  }

  return null;
}

function buildDeleteUrl(id) {
  if (!id) {
    return null;
  }

  if (typeof config.deleteUrlTemplate === 'string') {
    return config.deleteUrlTemplate.replace('__ID__', String(id));
  }

  if (typeof config.deleteUrl === 'string') {
    return config.deleteUrl;
  }

  return null;
}

function buildTrackingUrl(id) {
  if (!id) {
    return null;
  }

  if (typeof config.trackingUrlTemplate === 'string') {
    return config.trackingUrlTemplate.replace('__ID__', String(id));
  }

  if (typeof config.trackingUrl === 'string') {
    return config.trackingUrl;
  }

  return `/fileindexing/batch-tracking-sheet?files=${encodeURIComponent(id)}`;
}

function isLandsRegistry(generalRegistry) {
  return String(generalRegistry || '').trim().toLowerCase() === 'lands registry';
}

function getRegistryDisplayValue(row) {
  if (!row) {
    return '-';
  }

  const generalRegistry = String(row.general_registry || '').trim();
  if (generalRegistry !== '' && generalRegistry !== '-' && !isLandsRegistry(generalRegistry)) {
    return '1';
  }

  return row.registry || '-';
}

function isTrackingAllowed(row) {
  if (!row || !row.id) {
    return false;
  }

  return true;
}

function updatePagination(meta) {
  if (!meta) {
    dom.summaryText.textContent = 'Showing -- of -- results';
    dom.pageLabel.textContent = 'Page --';
    dom.prevButton.disabled = true;
    dom.nextButton.disabled = true;
    return;
  }

  const { current_page: currentPage, per_page: perPage, total, last_page: lastPage } = meta;
  const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const end = total === 0 ? 0 : Math.min(start + perPage - 1, total);

  dom.summaryText.textContent = `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${total.toLocaleString()} results`;
  dom.pageLabel.textContent = `Page ${currentPage.toLocaleString()} of ${lastPage.toLocaleString()}`;
  dom.prevButton.disabled = currentPage <= 1;
  dom.nextButton.disabled = currentPage >= lastPage;
}

function bindActionHandlers() {
  if (actionHandlersBound || !dom.tableBody) {
    return;
  }

  dom.tableBody.addEventListener('click', handleTableBodyClick);
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('click', handleLocationModalClick);
  actionHandlersBound = true;
}

function handleTableBodyClick(event) {
  const dropdownButton = event.target.closest('.actions-dropdown-btn');
  if (dropdownButton) {
    event.preventDefault();
    event.stopPropagation();
    toggleActionMenu(dropdownButton);
    return;
  }

  const viewButton = event.target.closest('.view-file-btn');
  if (viewButton) {
    event.preventDefault();
    event.stopPropagation();
    handleView(viewButton);
    return;
  }

  const editButton = event.target.closest('.edit-file-btn');
  if (editButton) {
    event.preventDefault();
    event.stopPropagation();
    handleEdit(editButton);
    return;
  }

  const trackingButton = event.target.closest('.print-tracking-btn');
  if (trackingButton) {
    event.preventDefault();
    event.stopPropagation();
    handleTracking(trackingButton);
    return;
  }

  const commissionSheetBtn = event.target.closest('.print-commissioning-sheet-btn');
  if (commissionSheetBtn) {
    event.preventDefault();
    event.stopPropagation();
    handlePrintCommissioningSheet(commissionSheetBtn);
    return;
  }

  const locationButton = event.target.closest('.open-location-map-btn');
  if (locationButton) {
    event.preventDefault();
    event.stopPropagation();
    openLocationMapModal(locationButton);
    return;
  }

  const duplicateButton = event.target.closest('.indexed-duplicate-btn');
  if (duplicateButton) {
    event.preventDefault();
    event.stopPropagation();
    handleMarkDuplicate(duplicateButton);
    return;
  }

  const moveDuplicateButton = event.target.closest('.move-indexing-duplicate-btn');
  if (moveDuplicateButton) {
    event.preventDefault();
    event.stopPropagation();
    handleMoveToIndexingDuplicates(moveDuplicateButton);
    return;
  }

  const callupButton = event.target.closest('.duplicate-callup-btn');
  if (callupButton && !callupButton.disabled) {
    event.preventDefault();
    event.stopPropagation();
    const fn = callupButton.getAttribute('data-file-number') || '';
    const fid = callupButton.getAttribute('data-file-id') || '';
    window.open(`/duplicate-callup?file_number=${encodeURIComponent(fn)}&indexed_id=${encodeURIComponent(fid)}`, '_blank');
    closeAllActionMenus();
    return;
  }

  const tempFileButton = event.target.closest('.has-temp-file-btn, .open-temp-file-btn');
  if (tempFileButton) {
    event.preventDefault();
    event.stopPropagation();
    const viewOnly = tempFileButton.classList.contains('open-temp-file-btn');
    handleHasTempFile(tempFileButton, viewOnly);
    return;
  }

  const mccBtn = event.target.closest('.mcc-fileno-btn');
  if (mccBtn) {
    event.preventDefault();
    event.stopPropagation();
    openMccModal(mccBtn);
    return;
  }

  const mppBtn = event.target.closest('.mpp-fileno-btn');
  if (mppBtn) {
    event.preventDefault();
    event.stopPropagation();
    openMppModal(mppBtn);
    return;
  }

  const deleteButton = event.target.closest('.indexed-delete-btn');
  if (deleteButton) {
    event.preventDefault();
    event.stopPropagation();
    handleDelete(deleteButton);
    return;
  }

  const updatePlaceholderBtn = event.target.closest('.update-placeholder-btn');
  if (updatePlaceholderBtn) {
    event.preventDefault();
    event.stopPropagation();
    handleUpdatePlaceholder(updatePlaceholderBtn);
    return;
  }

  const relatedBtn = event.target.closest('.view-related-files-btn');
  if (relatedBtn) {
    event.preventDefault();
    event.stopPropagation();
    const id = relatedBtn.getAttribute('data-id');
    openRelatedFilesModal(id);
    return;
  }

  const unlinkMenuBtn = event.target.closest('.unlink-related-menu-btn');
  if (unlinkMenuBtn) {
    event.preventDefault();
    event.stopPropagation();
    if (unlinkMenuBtn.disabled) return;
    closeAllActionMenus();
    // autoUnlink: with exactly one related file the confirmation opens straight away;
    // with more than one the list stays up so the operator says which.
    openRelatedFilesModal(unlinkMenuBtn.getAttribute('data-file-id'), { autoUnlink: true });
    return;
  }

  const edmsViewBtn = event.target.closest('.edms-view-files-btn');
  if (edmsViewBtn) {
    event.preventDefault();
    event.stopPropagation();
    const id = edmsViewBtn.getAttribute('data-id');
    const fileNumber = edmsViewBtn.getAttribute('data-file-number');
    const registryFolder = edmsViewBtn.getAttribute('data-registry-folder') || 'Cadastral_Registry';
    openEdmsFilesModal(id, fileNumber, registryFolder);
  }
}

// ── Map Modal State ───────────────────────────────────────────────
let locationMap = null;
let locationMarker = null;
let mapTileLayer = null;

function openLocationMapModal(button) {
  const id = button.getAttribute('data-id');
  const lat = button.getAttribute('data-lat') || '';
  const lon = button.getAttribute('data-lon') || '';
  const fileNumber = button.getAttribute('data-file-number') || 'Unknown';
  const fileTitle = button.getAttribute('data-file-title') || '-';
  const modal = document.getElementById('location-map-modal');
  const title = document.getElementById('location-map-modal-title');
  const numberEl = document.getElementById('location-map-file-number');
  const titleEl = document.getElementById('location-map-file-title');
  const latInput = document.getElementById('location-map-latitude');
  const lonInput = document.getElementById('location-map-longitude');
  const saveButton = document.getElementById('location-map-save-btn');
  const preview = document.getElementById('location-map-preview');

  if (!modal || !latInput || !lonInput || !saveButton || !preview) {
    return;
  }

  if (title) {
    title.textContent = `${fileNumber} — Map Coordinates`;
  }
  if (numberEl) {
    numberEl.textContent = fileNumber;
  }
  if (titleEl) {
    titleEl.textContent = fileTitle || '-';
  }

  modal.dataset.rowId = id;
  modal.classList.remove('hidden');

  // Default fallback: Kano, Nigeria
  const DEFAULT_LAT = 12.0022;
  const DEFAULT_LON = 8.5922;
  const hasCoords = lat !== '' && lon !== '';

  if (hasCoords) {
    latInput.value = lat;
    lonInput.value = lon;
    initLeafletMap(preview, parseFloat(lat), parseFloat(lon), latInput, lonInput, saveButton);
  } else {
    // No coordinates — attempt geocode from file title / location
    latInput.value = '';
    lonInput.value = '';
    saveButton.disabled = true;
    saveButton.textContent = 'Geocoding…';

    const geocodeQuery = fileTitle && fileTitle !== '-' ? fileTitle : fileNumber;
    geocodeLocation(geocodeQuery, preview, latInput, lonInput, saveButton, DEFAULT_LAT, DEFAULT_LON);
  }

  // Wire up manual coordinate input → marker update
  latInput.oninput = () => onManualCoordChange(latInput, lonInput, preview);
  lonInput.oninput = () => onManualCoordChange(latInput, lonInput, preview);

  // Wire up save button
  saveButton.onclick = async () => {
    const updatedLat = latInput.value.trim();
    const updatedLon = lonInput.value.trim();
    await submitLocationUpdate(id, updatedLat, updatedLon);
  };
}

function closeLocationMapModal() {
  const modal = document.getElementById('location-map-modal');
  if (modal) {
    modal.classList.add('hidden');
  }
  // Destroy Leaflet map to prevent memory leaks
  if (locationMap) {
    locationMap.remove();
    locationMap = null;
    locationMarker = null;
    mapTileLayer = null;
  }
}

function initLeafletMap(container, lat, lon, latInput, lonInput, saveButton) {
  // Destroy previous map if any
  if (locationMap) {
    locationMap.remove();
    locationMap = null;
    locationMarker = null;
  }

  // Ensure Leaflet is loaded
  if (typeof L === 'undefined') {
    container.innerHTML = '<div class="flex h-full min-h-[28rem] items-center justify-center text-sm text-red-500">Leaflet library failed to load. Please refresh the page.</div>';
    return;
  }

  container.innerHTML = '';
  container.style.minHeight = '28rem';

  locationMap = L.map(container, {
    center: [lat, lon],
    zoom: 16,
    zoomControl: true,
    attributionControl: false,
  });

  // Satellite tile layer (ArcGIS)
  mapTileLayer = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 20, attribution: 'Esri' }
  ).addTo(locationMap);

  // Custom draggable marker (red themed)
  const customIcon = L.divIcon({
    className: 'custom-map-marker',
    html: `<div style="
      width: 32px; height: 32px; background: #ef4444; border: 3px solid white;
      border-radius: 50% 50% 50% 0; transform: rotate(-45deg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.35);
      display: flex; align-items: center; justify-content: center;
    "><div style="
      width: 10px; height: 10px; background: white; border-radius: 50%; transform: rotate(45deg);
    "></div></div>`,
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32],
  });

  locationMarker = L.marker([lat, lon], { icon: customIcon, draggable: true }).addTo(locationMap);

  // Update inputs when marker is dragged
  locationMarker.on('dragend', function () {
    const pos = locationMarker.getLatLng();
    latInput.value = pos.lat.toFixed(6);
    lonInput.value = pos.lng.toFixed(6);
    saveButton.disabled = false;
  });

  // Ensure map fills the container
  setTimeout(() => {
    if (locationMap) {
      locationMap.invalidateSize();
    }
  }, 100);

  saveButton.disabled = false;
  saveButton.textContent = 'Save Coordinates';
}

function onManualCoordChange(latInput, lonInput, preview) {
  const lat = parseFloat(latInput.value);
  const lon = parseFloat(lonInput.value);
  const saveButton = document.getElementById('location-map-save-btn');

  if (!isNaN(lat) && !isNaN(lon) && locationMap && locationMarker) {
    locationMarker.setLatLng([lat, lon]);
    locationMap.setView([lat, lon], locationMap.getZoom());
    saveButton.disabled = false;
  } else if (locationMap) {
    saveButton.disabled = true;
  }
}

async function geocodeLocation(query, preview, latInput, lonInput, saveButton, fallbackLat, fallbackLon) {
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`,
      { headers: { 'User-Agent': 'KLAES-EDMS/1.0' } }
    );
    const results = await response.json();
    if (results && results.length > 0) {
      const foundLat = parseFloat(results[0].lat);
      const foundLon = parseFloat(results[0].lon);
      latInput.value = foundLat.toFixed(6);
      lonInput.value = foundLon.toFixed(6);
      initLeafletMap(preview, foundLat, foundLon, latInput, lonInput, saveButton);
    } else {
      // Fallback to Kano default
      latInput.value = fallbackLat.toFixed(6);
      lonInput.value = fallbackLon.toFixed(6);
      initLeafletMap(preview, fallbackLat, fallbackLon, latInput, lonInput, saveButton);
      saveButton.disabled = false;
    }
  } catch (err) {
    console.warn('Geocoding failed, using default location', err);
    latInput.value = fallbackLat.toFixed(6);
    lonInput.value = fallbackLon.toFixed(6);
    initLeafletMap(preview, fallbackLat, fallbackLon, latInput, lonInput, saveButton);
    saveButton.disabled = false;
  }
}

async function submitLocationUpdate(id, latitude, longitude) {
  const saveButton = document.getElementById('location-map-save-btn');
  try {
    const parsedLat = latitude === '' ? null : Number(latitude);
    const parsedLon = longitude === '' ? null : Number(longitude);

    if (parsedLat === null || parsedLon === null || Number.isNaN(parsedLat) || Number.isNaN(parsedLon)) {
      throw new Error('Valid latitude and longitude are required.');
    }

    const payload = { latitude: parsedLat, longitude: parsedLon };
    const url = (typeof config.updateCoordinatesUrlTemplate === 'string'
      ? config.updateCoordinatesUrlTemplate.replace('__ID__', String(id))
      : `${window.location.origin}/api/indexed-files/${id}/coordinates`);

    saveButton.disabled = true;
    saveButton.textContent = 'Saving…';

    const response = await fetch(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Unable to save coordinates');
    }

    const row = getRowFromCache(id);
    if (row) {
      row.latitude = data.data.latitude;
      row.longitude = data.data.longitude;
      updateLatLonCell(id, row);
    }

    closeLocationMapModal();
    window.alert('Coordinates saved successfully.');
  } catch (error) {
    console.error(error);
    window.alert(error.message || 'Failed to save coordinates.');
  } finally {
    saveButton.disabled = false;
    saveButton.textContent = 'Save Coordinates';
  }
}

function updateLatLonCell(id, row) {
  const rowEl = document.querySelector(`tr[data-row-id="${id}"]`);
  if (!rowEl) {
    return;
  }
  const latCell = rowEl.querySelector('.lat-value-cell');
  if (latCell) {
    const hasLat = row.latitude !== null && row.latitude !== undefined && row.latitude !== '';
    latCell.innerHTML = hasLat ? Number(row.latitude).toFixed(6) : '<span class="text-gray-400">-</span>';
  }

  const lonCell = rowEl.querySelector('.lon-value-cell');
  if (lonCell) {
    const hasLon = row.longitude !== null && row.longitude !== undefined && row.longitude !== '';
    lonCell.innerHTML = hasLon ? Number(row.longitude).toFixed(6) : '<span class="text-gray-400">-</span>';
  }

  const cell = rowEl.querySelector('.latlon-cell');
  if (!cell) {
    return;
  }

  cell.innerHTML = `<button type="button" class="open-location-map-btn inline-flex items-center gap-2 justify-center rounded-2xl border border-slate-200 bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 transition"
      data-id="${row.id}"
      data-lat="${escapeHtml(row.latitude ?? '')}"
      data-lon="${escapeHtml(row.longitude ?? '')}"
      data-file-number="${escapeHtml(row.file_number)}"
      data-file-title="${escapeHtml(row.file_title)}">
      <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
      <span>View Map</span>
    </button>`;
  if (window.lucide) {
    window.lucide.createIcons();
  }
}

function openRelatedFilesModal(id, options) {
  const opts = options || {};
  const modal = document.getElementById('related-files-modal');
  const tbody = document.getElementById('related-files-table-body');
  const closeBtns = [
    document.getElementById('close-related-modal-btn'),
    document.getElementById('close-related-modal-footer-btn')
  ];

  if (!modal || !tbody) return;

  // Show modal and loading state
  modal.classList.remove('hidden');
  const parentContainer = document.getElementById('parent-file-number-container');
  if (parentContainer) parentContainer.classList.add('hidden');

  tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-sm"><i data-lucide="loader" class="h-5 w-5 animate-spin inline-block mr-2"></i>Loading related files...</td></tr>';
  if (window.lucide) window.lucide.createIcons();

  // Close handler is now managed by the global delegated listener at the bottom of the file
  // but we can keep a local reference if we need to do specific cleanup.
  const close = () => {
    modal.classList.add('hidden');
  };

  // Fetch data
  const url = `${window.location.origin}/api/indexed-files/related-files/${id}`;
  fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      const parentContainer = document.getElementById('parent-file-number-container');
      const parentBadge = document.getElementById('parent-file-number-badge');

      if (data.success && data.data.length > 0) {
        // parent_prop_id drives the "this also clears Property ID X" warning in the
        // unlink confirmation; each row carries its own prop_id (see backfillRelatedFromIndex).
        const meta = data.meta || {};

        // More than one related file => make the operator pick, don't assume.
        const multiNotice = ensureMultiNotice();
        const countEl = document.getElementById('related-files-count');
        if (multiNotice) {
          if (data.data.length > 1) {
            if (countEl) countEl.textContent = data.data.length;
            multiNotice.classList.remove('hidden');
          } else {
            multiNotice.classList.add('hidden');
          }
        }

        // Show Parent File Number
        const firstRow = data.data[0];
        if (firstRow && firstRow.main_file_number && parentContainer && parentBadge) {
          parentBadge.textContent = firstRow.main_file_number;
          if (firstRow.main_is_temp_fallback) {
            parentBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-100';
          } else {
            parentBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100';
          }
          parentContainer.classList.remove('hidden');
        }

        tbody.innerHTML = data.data.map((file, idx) => `
          <tr class="hover:bg-gray-50 transition-colors related-file-row" data-file-number="${escapeHtml(file.file_number)}">
            <td class="px-4 py-3 text-sm text-gray-600 font-medium">${idx + 1}</td>
            <td class="px-4 py-3 text-sm">
                <button type="button" class="related-fileno-link inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:border-blue-300 cursor-pointer transition-colors"
                    data-file-number="${escapeHtml(file.file_number)}" title="Click to backfill details from the indexed file">
                    ${escapeHtml(file.file_number)}
                    <i data-lucide="download" class="w-3 h-3 opacity-60"></i>
                </button>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700 font-semibold js-cell-title">${escapeHtml(file.file_title || '-')}</td>
            <td class="px-4 py-3 text-sm text-gray-600 js-cell-location">${escapeHtml(file.location || '-')}</td>
            <td class="px-4 py-3 text-xs text-gray-500">
                <div class="flex flex-col gap-1">
                    <span class="font-medium js-cell-plot">Plot: ${escapeHtml(file.plot_number || '-')}</span>
                    <span class="js-cell-tplpkn">TP: ${escapeHtml(file.tp_no || '-')} | LPKN: ${escapeHtml(file.lpkn_no || '-')}</span>
                </div>
            </td>
            <td class="px-4 py-3 text-center">
                <button type="button" class="edit-related-file-btn p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    data-id="${file.id}"
                    data-parent-id="${id}"
                    data-file-number="${escapeHtml(file.file_number)}"
                    data-file-title="${escapeHtml(file.file_title || '')}"
                    data-location="${escapeHtml(file.location || '')}"
                    data-plot-number="${escapeHtml(file.plot_number || '')}"
                    data-tp-no="${escapeHtml(file.tp_no || '')}"
                    data-lpkn-no="${escapeHtml(file.lpkn_no || '')}">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                </button>
                <button type="button" class="unlink-related-file-btn p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Unlink this related file number"
                    data-id="${file.id}"
                    data-parent-id="${id}"
                    data-parent-file-number="${escapeHtml(file.main_file_number || '')}"
                    data-file-number="${escapeHtml(file.file_number)}"
                    data-prop-id="${escapeHtml(file.prop_id || '')}">
                    <i data-lucide="unlink" class="w-4 h-4"></i>
                </button>
            </td>
          </tr>
        `).join('');

        if (window.lucide) window.lucide.createIcons();

        // Bind Edit logic
        bindRelatedEditHandlers(id);
        // Clicking a related file number backfills its row from the indexed record.
        bindRelatedFilenoBackfill();
        bindRelatedUnlinkHandlers(id, meta);

        // Entered from the row menu with a single related file: go straight to the
        // confirmation. More than one and the list stays up for the operator to choose.
        if (opts.autoUnlink && data.data.length === 1) {
          document.querySelector('.unlink-related-file-btn')?.click();
        }
      } else {
        if (parentContainer) parentContainer.classList.add('hidden');
        document.getElementById('related-files-multi-notice')?.classList.add('hidden');
        const msg = data.message || 'No related files found for this record.';
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-sm ${data.success ? 'text-gray-500' : 'text-red-500'} font-medium">${msg}</td></tr>`;
      }
    })
    .catch(err => {
      console.error(err);
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-red-500 font-medium">Error loading related files. Please try again.</td></tr>';
    });
}

function openEdmsFilesModal(id, fileNumber, registryFolder) {
  registryFolder = registryFolder || 'Cadastral_Registry';
  const modal = document.getElementById('edms-files-modal');
  const title = document.getElementById('edms-files-modal-title');
  const body = document.getElementById('edms-files-modal-body');
  const closeBtn = document.getElementById('close-edms-files-modal');
  const backdrop = document.getElementById('edms-files-backdrop');

  if (!modal || !body) return;

  if (title) title.textContent = fileNumber || 'File Documents';

  body.innerHTML = `
    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
      <i data-lucide="loader" class="h-8 w-8 animate-spin mb-3"></i>
      <p class="text-sm font-medium">Loading files...</p>
    </div>`;

  modal.classList.remove('hidden');
  if (window.lucide) window.lucide.createIcons();

  // Close handler is now managed by the global delegated listener at the bottom of the file
  const close = () => modal.classList.add('hidden');
  backdrop?.addEventListener('click', close, { once: true });

  fetch(`${window.location.origin}/api/indexed-files/edms-files/${id}?folder=${encodeURIComponent(registryFolder)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        body.innerHTML = `<p class="text-center text-red-500 py-8 text-sm font-medium">${data.message || 'Failed to load files.'}</p>`;
        return;
      }
      if (!data.has_files || !data.files.length) {
        body.innerHTML = `
        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
          <i data-lucide="folder-x" class="h-10 w-10 mb-3"></i>
          <p class="text-sm font-semibold">No scanned files found</p>
          <p class="text-xs mt-1">No documents are stored for this file number yet.</p>
        </div>`;
        if (window.lucide) window.lucide.createIcons();
        return;
      }

      const images = data.files.filter(f => f.type === 'image');
      const pdfs = data.files.filter(f => f.type === 'pdf');

      // Preview list combines images first, then PDFs, so prev/next navigation works for both
      const previewList = [...images, ...pdfs];
      // Stash on the modal so click handlers below can read it
      body.dataset.previewIndexBase = '0';
      window.__edmsPreviewList = previewList;

      let html = '';

      if (images.length) {
        html += `<div class="mb-4">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Images (${images.length})</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">`;
        images.forEach((f, i) => {
          html += `
          <button type="button" data-edms-preview-index="${i}" class="edms-preview-trigger group block w-full text-left rounded-xl border border-slate-200 overflow-hidden hover:border-orange-400 hover:shadow-md transition-all bg-slate-50">
            <div class="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden">
              <img src="${f.url}" alt="${escapeHtml(f.name)}" draggable="false" oncontextmenu="return false" style="-webkit-user-drag:none;user-select:none;" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" onerror="this.parentElement.innerHTML='<span class=text-slate-400 text-xs>Preview unavailable</span>'">
            </div>
            <div class="px-2 py-1.5">
              <p class="text-[10px] font-semibold text-slate-600 truncate" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</p>
            </div>
          </button>`;
        });
        html += `</div></div>`;
      }

      if (pdfs.length) {
        const pdfStartIndex = images.length;
        html += `<div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">PDF Documents (${pdfs.length})</p>
        <div class="space-y-2">`;
        pdfs.forEach((f, i) => {
          html += `
          <button type="button" data-edms-preview-index="${pdfStartIndex + i}" class="edms-preview-trigger flex items-center gap-3 p-3 w-full text-left rounded-xl border border-slate-200 hover:border-red-300 hover:bg-red-50 transition-all group">
            <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
              <i data-lucide="file-text" class="w-5 h-5 text-red-600"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-700 truncate group-hover:text-red-700" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</p>
              <p class="text-[10px] text-slate-400 uppercase">PDF Document</p>
            </div>
            <i data-lucide="eye" class="w-4 h-4 text-slate-400 group-hover:text-red-500 shrink-0"></i>
          </button>`;
        });
        html += `</div></div>`;
      }

      body.innerHTML = html;
      if (window.lucide) window.lucide.createIcons();

      // Wire up preview triggers
      body.querySelectorAll('.edms-preview-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
          const idx = parseInt(btn.getAttribute('data-edms-preview-index'), 10) || 0;
          openEdmsPreview(window.__edmsPreviewList || [], idx);
        });
      });
    })
    .catch(() => {
      body.innerHTML = `<p class="text-center text-red-500 py-8 text-sm font-medium">Network error. Please try again.</p>`;
    });
}

/**
 * Lightbox-style in-page preview for scanned EDMS files (images + PDFs).
 * Builds an overlay on first use and reuses it afterwards.
 */
function openEdmsPreview(list, startIndex) {
  if (!Array.isArray(list) || !list.length) return;
  let index = Math.max(0, Math.min(startIndex || 0, list.length - 1));

  // Zoom/rotate state for the currently displayed item
  let rotation = 0;
  let zoom = 1;

  let overlay = document.getElementById('edms-preview-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'edms-preview-overlay';
    overlay.className = 'fixed inset-0 z-[200] hidden items-center justify-center bg-slate-950/85 backdrop-blur-sm select-none';
    overlay.innerHTML = `
      <button type="button" id="edms-preview-close" class="absolute top-4 right-4 z-[220] w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition" aria-label="Close preview">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
      <button type="button" id="edms-preview-prev" class="absolute left-4 top-1/2 -translate-y-1/2 z-[220] w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition" aria-label="Previous">
        <i data-lucide="chevron-left" class="w-6 h-6"></i>
      </button>
      <button type="button" id="edms-preview-next" class="absolute right-4 top-1/2 -translate-y-1/2 z-[220] w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition" aria-label="Next">
        <i data-lucide="chevron-right" class="w-6 h-6"></i>
      </button>
      <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[220] px-4 py-1.5 rounded-full bg-white/10 text-white text-xs font-semibold tracking-wide" id="edms-preview-caption">—</div>

      <!-- Toolbar: rotate / zoom -->
      <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-[220] flex items-center gap-1 px-2 py-1.5 rounded-full bg-white/10 backdrop-blur text-white shadow-lg" id="edms-preview-toolbar">
        <button type="button" id="edms-rotate-left" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/20 transition" title="Rotate left"><i data-lucide="rotate-ccw" class="w-4 h-4"></i></button>
        <button type="button" id="edms-rotate-right" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/20 transition" title="Rotate right"><i data-lucide="rotate-cw" class="w-4 h-4"></i></button>
        <span class="w-px h-5 bg-white/20 mx-1"></span>
        <button type="button" id="edms-zoom-out" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/20 transition" title="Zoom out"><i data-lucide="zoom-out" class="w-4 h-4"></i></button>
        <button type="button" id="edms-zoom-in" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/20 transition" title="Zoom in"><i data-lucide="zoom-in" class="w-4 h-4"></i></button>
        <button type="button" id="edms-reset" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/20 transition" title="Reset"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
      </div>

      <div class="w-full h-full flex items-center justify-center p-6 sm:p-12" id="edms-preview-stage"></div>

      <!-- Watermark: repeated red "FOR OFFICIAL USE ONLY" overlay (non-interactive) -->
      <div class="pointer-events-none absolute inset-0 z-[215]" id="edms-preview-watermark"></div>
    `;
    document.body.appendChild(overlay);

    // Tiled diagonal red watermark
    const wmSvg = `<svg xmlns='http://www.w3.org/2000/svg' width='360' height='230'><text x='24' y='140' fill='rgba(220,38,38,0.30)' font-family='Arial, sans-serif' font-size='24' font-weight='bold' transform='rotate(-30 180 115)'>FOR OFFICIAL USE ONLY</text></svg>`;
    const wm = overlay.querySelector('#edms-preview-watermark');
    wm.style.backgroundImage = `url("data:image/svg+xml,${encodeURIComponent(wmSvg)}")`;
    wm.style.backgroundRepeat = 'repeat';

    // Block right-click / drag-save across the whole viewer
    overlay.addEventListener('contextmenu', (e) => e.preventDefault());
    overlay.addEventListener('dragstart', (e) => e.preventDefault());
    if (window.lucide) window.lucide.createIcons();
  }

  const stage = overlay.querySelector('#edms-preview-stage');
  const caption = overlay.querySelector('#edms-preview-caption');
  const closeBtn = overlay.querySelector('#edms-preview-close');
  const prevBtn = overlay.querySelector('#edms-preview-prev');
  const nextBtn = overlay.querySelector('#edms-preview-next');
  const toolbar = overlay.querySelector('#edms-preview-toolbar');
  const rotateLeftBtn = overlay.querySelector('#edms-rotate-left');
  const rotateRightBtn = overlay.querySelector('#edms-rotate-right');
  const zoomInBtn = overlay.querySelector('#edms-zoom-in');
  const zoomOutBtn = overlay.querySelector('#edms-zoom-out');
  const resetBtn = overlay.querySelector('#edms-reset');

  // Apply the current rotation + zoom to the on-screen image (images only)
  const applyTransform = () => {
    const img = stage.querySelector('img');
    if (!img) return;
    img.style.transform = `rotate(${rotation}deg) scale(${zoom})`;
    // Swap max bounds on quarter-turns so a rotated page still fits the viewport
    if (Math.abs(rotation % 180) === 90) {
      img.style.maxWidth = '82vh';
      img.style.maxHeight = '88vw';
    } else {
      img.style.maxWidth = '88vw';
      img.style.maxHeight = '82vh';
    }
  };

  const render = () => {
    const item = list[index];
    if (!item) return;
    rotation = 0;
    zoom = 1;
    caption.textContent = `${item.name}  ·  ${index + 1} / ${list.length}`;
    const isImage = item.type === 'image';
    // Rotate/zoom only apply to images
    toolbar.style.display = isImage ? 'flex' : 'none';
    if (isImage) {
      stage.innerHTML = `
        <img src="${item.url}" alt="${escapeHtml(item.name)}" draggable="false"
             class="object-contain rounded-lg shadow-2xl bg-white transition-transform duration-200"
             style="max-width:88vw;max-height:82vh;-webkit-user-drag:none;user-select:none;">
      `;
      applyTransform();
    } else {
      // PDF: toolbar hidden (no download), no "open in new tab" link
      stage.innerHTML = `
        <div class="w-full h-full max-w-5xl flex flex-col bg-white rounded-lg overflow-hidden shadow-2xl">
          <iframe src="${item.url}#toolbar=0&navpanes=0" class="w-full flex-1 border-0" title="${escapeHtml(item.name)}"></iframe>
          <div class="px-4 py-2 bg-slate-100 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-center">
            <span class="truncate font-semibold uppercase tracking-widest text-red-600">For Official Use Only</span>
          </div>
        </div>
      `;
    }
    prevBtn.style.visibility = list.length > 1 ? 'visible' : 'hidden';
    nextBtn.style.visibility = list.length > 1 ? 'visible' : 'hidden';
  };

  const close = () => {
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    stage.innerHTML = '';
    document.removeEventListener('keydown', onKey);
  };
  const next = () => { index = (index + 1) % list.length; render(); };
  const prev = () => { index = (index - 1 + list.length) % list.length; render(); };
  const rotateLeft = () => { rotation -= 90; applyTransform(); };
  const rotateRight = () => { rotation += 90; applyTransform(); };
  const zoomIn = () => { zoom = Math.min(zoom + 0.25, 4); applyTransform(); };
  const zoomOut = () => { zoom = Math.max(zoom - 0.25, 0.25); applyTransform(); };
  const reset = () => { rotation = 0; zoom = 1; applyTransform(); };
  const onKey = (e) => {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'r' || e.key === 'R') rotateRight();
  };

  // (Re)bind handlers — clone-replace to avoid stacking listeners
  const rebind = (el, handler) => {
    const clone = el.cloneNode(true);
    el.replaceWith(clone);
    clone.addEventListener('click', handler);
    return clone;
  };
  rebind(closeBtn, close);
  rebind(prevBtn, prev);
  rebind(nextBtn, next);
  rebind(rotateLeftBtn, rotateLeft);
  rebind(rotateRightBtn, rotateRight);
  rebind(zoomInBtn, zoomIn);
  rebind(zoomOutBtn, zoomOut);
  rebind(resetBtn, reset);
  overlay.onclick = (e) => { if (e.target === overlay) close(); };
  document.addEventListener('keydown', onKey);

  overlay.classList.remove('hidden');
  overlay.classList.add('flex');
  render();
  if (window.lucide) window.lucide.createIcons();
}

// Clicking a related file number looks up its indexed record and fills that row's
// File Title / Location / Plot / TP / LPKN cells (and the Edit button's data-*),
// so the details backfill on demand instead of showing dashes.
function bindRelatedFilenoBackfill() {
  document.querySelectorAll('.related-fileno-link').forEach(btn => {
    btn.addEventListener('click', () => {
      const fileNumber = (btn.dataset.fileNumber || '').trim();
      if (!fileNumber) return;
      const row = btn.closest('.related-file-row');
      if (!row) return;

      const icon = btn.querySelector('i');
      if (icon) icon.classList.add('animate-spin');

      fetch(`${window.location.origin}/api/file-indexings/related-indexed-details?file_number=${encodeURIComponent(fileNumber)}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(res => (res.ok ? res.json() : null))
        .then(payload => {
          if (!payload || !payload.indexed || !payload.data) {
            if (typeof showToast === 'function') showToast('No indexed details found for ' + fileNumber, 'info');
            return;
          }
          const d = payload.data;
          const setCell = (sel, val) => {
            const el = row.querySelector(sel);
            if (el && val != null && String(val).trim() !== '') el.textContent = val;
          };
          setCell('.js-cell-title', d.file_title);
          setCell('.js-cell-location', d.location);
          if (d.plot_number != null && String(d.plot_number).trim() !== '') {
            const plot = row.querySelector('.js-cell-plot');
            if (plot) plot.textContent = 'Plot: ' + d.plot_number;
          }
          const tp = (d.tp_no != null && String(d.tp_no).trim() !== '') ? d.tp_no : '-';
          const lpkn = (d.lpkn_no != null && String(d.lpkn_no).trim() !== '') ? d.lpkn_no : '-';
          const tpCell = row.querySelector('.js-cell-tplpkn');
          if (tpCell) tpCell.textContent = `TP: ${tp} | LPKN: ${lpkn}`;

          // Keep the row's Edit button in sync so a subsequent edit preserves the backfill.
          const editBtn = row.querySelector('.edit-related-file-btn');
          if (editBtn) {
            if (d.file_title) editBtn.dataset.fileTitle = d.file_title;
            if (d.location) editBtn.dataset.location = d.location;
            if (d.plot_number) editBtn.dataset.plotNumber = d.plot_number;
            if (d.tp_no) editBtn.dataset.tpNo = d.tp_no;
            if (d.lpkn_no) editBtn.dataset.lpknNo = d.lpkn_no;
          }
        })
        .catch(() => {
          if (typeof showToast === 'function') showToast('Failed to load details for ' + fileNumber, 'error');
        })
        .finally(() => {
          if (icon) icon.classList.remove('animate-spin');
        });
    });
  });
}

function bindRelatedEditHandlers(parentId) {
  const editBtns = document.querySelectorAll('.edit-related-file-btn');
  const editModal = document.getElementById('edit-related-file-modal');
  const editForm = document.getElementById('edit-related-file-form');
  const closeBtn = document.getElementById('close-edit-related-modal');
  const cancelBtn = document.getElementById('cancel-edit-related');
  const backdrop = document.getElementById('edit-related-backdrop');

  if (!editModal || !editForm) return;

  const closeEditModal = () => editModal.classList.add('hidden');

  editBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // Populate fields
      document.getElementById('edit-related-id').value = btn.dataset.id;
      document.getElementById('edit-related-file-number').value = btn.dataset.fileNumber;
      document.getElementById('edit-related-file-title').value = btn.dataset.fileTitle;
      document.getElementById('edit-related-location').value = btn.dataset.location;
      document.getElementById('edit-related-plot-number').value = btn.dataset.plotNumber;
      document.getElementById('edit-related-tp-no').value = btn.dataset.tpNo;
      document.getElementById('edit-related-lpkn-no').value = btn.dataset.lpknNo;

      editModal.classList.remove('hidden');
      if (window.lucide) window.lucide.createIcons();
    });
  });

  closeBtn?.addEventListener('click', closeEditModal);
  cancelBtn?.addEventListener('click', closeEditModal);
  backdrop?.addEventListener('click', closeEditModal);

  // Handle Form Submission
  editForm.onsubmit = async (e) => {
    e.preventDefault();
    const id = document.getElementById('edit-related-id').value;
    const formData = new FormData(editForm);
    const data = Object.fromEntries(formData.entries());

    try {
      const response = await fetch(`${window.location.origin}/api/indexed-files/related-files/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });

      const result = await response.json();
      if (result.success) {
        alert('Related file updated successfully!');
        closeEditModal();
        openRelatedFilesModal(parentId); // Refresh table
      } else {
        alert('Error: ' + (result.message || 'Failed to update'));
      }
    } catch (err) {
      console.error(err);
      alert('A network error occurred.');
    }
  };
}

// The Related Files modal markup is duplicated across several blades (the shared
// components.indexed-files-table component, indexed_files/index.blade.php, the per-registry
// pages). Rather than add these two elements to every copy and let them drift, the JS owns
// them and injects them on first use.
function ensureUnlinkModal() {
  let modal = document.getElementById('unlink-related-file-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'unlink-related-file-modal';
    modal.className = 'fixed inset-0 z-[120] hidden overflow-y-auto';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = `
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="unlink-related-backdrop"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div id="unlink-related-header" class="bg-red-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <i data-lucide="unlink" class="w-5 h-5 text-white" id="unlink-related-header-icon"></i>
              <h3 class="text-lg font-bold text-white" id="unlink-related-header-title">Unlink Related File Number</h3>
            </div>
            <button type="button" id="close-unlink-related-modal" class="text-white/70 hover:text-white transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <div id="unlink-related-confirm-body" class="px-6 py-6 space-y-4">
            <p class="text-sm text-gray-600">
              Remove <span id="unlink-related-file-number" class="font-bold text-gray-900">-</span>
              as a related file of <span id="unlink-related-parent" class="font-bold text-gray-900">-</span>?
            </p>
            <div id="unlink-related-propid" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
              <p class="text-xs text-amber-800 font-medium" id="unlink-related-propid-text"></p>
            </div>
            <p class="text-xs text-gray-500">This cannot be undone from the interface. The action is recorded in the audit log.</p>
          </div>
          <div id="unlink-related-result-body" class="hidden px-6 py-6"></div>
          <div id="unlink-related-confirm-footer" class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
            <button type="button" id="cancel-unlink-related" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
            <button type="button" id="confirm-unlink-related" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-red-600 hover:bg-red-700 transition-all shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">Unlink</button>
          </div>
          <div id="unlink-related-result-footer" class="hidden bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
            <button type="button" id="done-unlink-related" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 transition-all shadow-sm">Done</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modal);
  }
  return modal;
}

// Banner shown above the related-files list when there is more than one entry, so the
// operator picks which to unlink instead of assuming there is only one.
function ensureMultiNotice() {
  let notice = document.getElementById('related-files-multi-notice');
  if (!notice) {
    const tbody = document.getElementById('related-files-table-body');
    // The list sits inside a scroll wrapper; the banner belongs above that wrapper.
    const anchor = tbody?.closest('table')?.parentElement;
    if (!anchor || !anchor.parentElement) return null;

    notice = document.createElement('div');
    notice.id = 'related-files-multi-notice';
    notice.className = 'hidden mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3';
    notice.innerHTML = `
      <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
      <p class="text-xs text-amber-800 font-medium">
        This file has <span id="related-files-count" class="font-bold">0</span> related file numbers.
        Choose the specific one you want to unlink — only the row you pick is removed.
      </p>`;
    anchor.parentElement.insertBefore(notice, anchor);
  }
  return notice;
}

// Every close path (Done, the X, the backdrop) goes through here, so the refresh queued
// by a successful unlink runs no matter how the operator dismisses the card.
function closeUnlinkModal() {
  const modal = document.getElementById('unlink-related-file-modal');
  if (!modal) return;
  modal.classList.add('hidden');

  const pending = modal._pendingRefresh;
  modal._pendingRefresh = null;
  if (typeof pending === 'function') pending();
}

// Puts the modal back into its "confirm" state — needed because the same modal is
// reused for the result card and would otherwise still show the previous outcome.
function resetUnlinkModalState() {
  document.getElementById('unlink-related-confirm-body')?.classList.remove('hidden');
  document.getElementById('unlink-related-confirm-footer')?.classList.remove('hidden');
  document.getElementById('unlink-related-result-body')?.classList.add('hidden');
  document.getElementById('unlink-related-result-footer')?.classList.add('hidden');

  const header = document.getElementById('unlink-related-header');
  if (header) header.className = 'bg-red-600 px-6 py-4 flex items-center justify-between';
  const title = document.getElementById('unlink-related-header-title');
  if (title) title.textContent = 'Unlink Related File Number';
  const icon = document.getElementById('unlink-related-header-icon');
  if (icon) icon.setAttribute('data-lucide', 'unlink');
}

// Swaps the confirm body for an outcome card. The backend reports which store the entry
// came out of and what happened to the Property ID; all of that is worth showing, which
// a native alert() cannot do.
function showUnlinkResult(payload, context) {
  const data = payload.data || {};
  const body = document.getElementById('unlink-related-result-body');
  if (!body) return;

  const sources = [];
  if (data.removed_link_rows > 0) {
    sources.push(`${data.removed_link_rows} related-file link row${data.removed_link_rows === 1 ? '' : 's'}`);
  }
  if (data.removed_from_related_fileno) {
    sources.push('the related file number list');
  }

  // Both "cleared it" and "there was never one" end in the same state — no parent
  // Property ID on this record — so both report as UNLINKED. The KEPT branches below
  // stay as they are: the linkage really does survive there, and saying otherwise
  // would tell the operator the parcel link is gone when it is not.
  let propRow;
  if (data.prop_id_cleared) {
    propRow = `
      <div class="flex items-start gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0 mt-0.5">UNLINKED</span>
        <span class="text-gray-700">Parent Property ID <span class="font-bold">${escapeHtml(data.cleared_prop_id)}</span> unlinked from this record.</span>
      </div>`;
  } else if (data.prop_id_note === 'no_parent_prop_id') {
    propRow = `
      <div class="flex items-start gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0 mt-0.5">UNLINKED</span>
        <span class="text-gray-700">Parent Property ID unlinked.</span>
      </div>`;
  } else if (data.prop_id_note === 'still_justified_by_remaining_related_file') {
    propRow = `
      <div class="flex items-start gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0 mt-0.5">KEPT</span>
        <span class="text-gray-700">Another related file still points at the same parcel.</span>
      </div>`;
  } else if (data.prop_id_note === 'parent_prop_id_from_another_source') {
    propRow = `
      <div class="flex items-start gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0 mt-0.5">KEPT</span>
        <span class="text-gray-700">The linked Property ID was not set by this related file.</span>
      </div>`;
  } else {
    // Defensive: any note the UI does not recognise still ends up here.
    propRow = `
      <div class="flex items-start gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0 mt-0.5">UNLINKED</span>
        <span class="text-gray-700">Parent Property ID unlinked.</span>
      </div>`;
  }

  body.innerHTML = `
    <div class="flex items-start gap-3 mb-5">
      <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2 shrink-0">
        <i data-lucide="check" class="w-5 h-5 text-emerald-600"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-gray-900">Related file unlinked</p>
        <p class="text-xs text-gray-500 mt-0.5">
          <span class="font-semibold text-gray-700">${escapeHtml(data.file_number || '')}</span>
          is no longer a related file of
          <span class="font-semibold text-gray-700">${escapeHtml(context.parentFileNumber || '')}</span>.
        </p>
      </div>
    </div>
    <dl class="rounded-xl border border-gray-100 bg-gray-50/60 divide-y divide-gray-100 text-xs">
      <div class="px-4 py-3">
        <dt class="font-bold text-gray-400 uppercase tracking-wider text-[10px] mb-1">Removed from</dt>
        <dd class="text-gray-700">${sources.length ? escapeHtml(sources.join(' and ')) : '&mdash;'}</dd>
      </div>
      <div class="px-4 py-3">
        <dt class="font-bold text-gray-400 uppercase tracking-wider text-[10px] mb-1">Property ID</dt>
        <dd>${propRow}</dd>
      </div>
      <div class="px-4 py-3">
        <dt class="font-bold text-gray-400 uppercase tracking-wider text-[10px] mb-1">Related files remaining</dt>
        <dd class="text-gray-700 font-bold">${Number(data.remaining) || 0}</dd>
      </div>
    </dl>
    <p class="text-[11px] text-gray-400 mt-4 flex items-center gap-1.5">
      <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
      Recorded in the audit log.
    </p>`;

  document.getElementById('unlink-related-confirm-body')?.classList.add('hidden');
  document.getElementById('unlink-related-confirm-footer')?.classList.add('hidden');
  body.classList.remove('hidden');
  document.getElementById('unlink-related-result-footer')?.classList.remove('hidden');

  const header = document.getElementById('unlink-related-header');
  if (header) header.className = 'bg-emerald-600 px-6 py-4 flex items-center justify-between';
  const title = document.getElementById('unlink-related-header-title');
  if (title) title.textContent = 'Unlink Complete';
  const icon = document.getElementById('unlink-related-header-icon');
  if (icon) icon.setAttribute('data-lucide', 'check-circle');

  // Queued rather than run now, so the card is not yanked out from under the operator.
  const modal = document.getElementById('unlink-related-file-modal');
  if (modal) {
    modal._pendingRefresh = () => {
      if (Number(data.remaining) > 0) {
        openRelatedFilesModal(context.parentId); // refresh the surviving rows
      } else {
        document.getElementById('related-files-modal')?.classList.add('hidden');
      }
      // The row's related-files button and display text are derived server-side.
      if (typeof loadTable === 'function') loadTable();
    };
  }

  const doneBtn = document.getElementById('done-unlink-related');
  if (doneBtn) doneBtn.onclick = closeUnlinkModal;

  if (window.lucide) window.lucide.createIcons();
}

/** Failure card — same modal, same shape, so errors are not a different experience. */
function showUnlinkError(message) {
  const body = document.getElementById('unlink-related-result-body');
  if (!body) return;

  body.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="bg-red-50 border border-red-200 rounded-xl p-2 shrink-0">
        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-gray-900">Nothing was unlinked</p>
        <p class="text-xs text-gray-600 mt-1 leading-relaxed">${escapeHtml(message)}</p>
      </div>
    </div>`;

  document.getElementById('unlink-related-confirm-body')?.classList.add('hidden');
  document.getElementById('unlink-related-confirm-footer')?.classList.add('hidden');
  body.classList.remove('hidden');
  document.getElementById('unlink-related-result-footer')?.classList.remove('hidden');

  const title = document.getElementById('unlink-related-header-title');
  if (title) title.textContent = 'Unlink Failed';

  const doneBtn = document.getElementById('done-unlink-related');
  if (doneBtn) doneBtn.onclick = closeUnlinkModal;

  if (window.lucide) window.lucide.createIcons();
}

// Unlink removes ONE related file number from the record. The backend decides what
// happens to the linked Property ID; `meta.parent_prop_id` + the row's own prop_id let
// the confirmation say so in advance (see IndexedFileTableController::unlinkRelatedFile).
function bindRelatedUnlinkHandlers(parentId, meta) {
  const modal = ensureUnlinkModal();
  const confirmBtn = document.getElementById('confirm-unlink-related');
  if (!modal || !confirmBtn) return;

  // Closing is handled by the global delegated listener at the bottom of this file —
  // binding it here would stack a fresh listener every time the modal is reopened.
  const parentPropId = String(meta?.parent_prop_id ?? '').trim();

  document.querySelectorAll('.unlink-related-file-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const fileNumber = btn.dataset.fileNumber || '';
      const rowPropId = String(btn.dataset.propId || '').trim();

      // The modal doubles as the outcome card, so it may still be showing the previous
      // result when reopened for another row.
      resetUnlinkModalState();

      document.getElementById('unlink-related-file-number').textContent = fileNumber || '-';
      document.getElementById('unlink-related-parent').textContent = btn.dataset.parentFileNumber || '-';

      // Mirrors the backend rule: the Property ID is only cleared when it came from
      // the file being unlinked. Whether another related file still justifies it is
      // resolved server-side, so the wording stays conditional here.
      const propBox = document.getElementById('unlink-related-propid');
      const propText = document.getElementById('unlink-related-propid-text');
      if (parentPropId && rowPropId && parentPropId === rowPropId) {
        propText.textContent =
          `The linked Property ID ${parentPropId} came from this file, so it will be cleared too ` +
          `— unless another related file still points at the same parcel.`;
        propBox.classList.remove('hidden');
      } else if (parentPropId) {
        propText.textContent =
          `The linked Property ID ${parentPropId} will be kept — it was not set by this related file.`;
        propBox.classList.remove('hidden');
      } else {
        propBox.classList.add('hidden');
      }

      confirmBtn.dataset.fileNumber = fileNumber;
      confirmBtn.dataset.linkId = btn.dataset.id || '';
      confirmBtn.dataset.parentId = btn.dataset.parentId || parentId;
      confirmBtn.dataset.parentFileNumber = btn.dataset.parentFileNumber || '';

      modal.classList.remove('hidden');
      if (window.lucide) window.lucide.createIcons();
    });
  });

  confirmBtn.onclick = async () => {
    const fileNumber = confirmBtn.dataset.fileNumber || '';
    const linkId = confirmBtn.dataset.linkId || '';
    const targetParentId = confirmBtn.dataset.parentId || parentId;
    if (!fileNumber) return;

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Unlinking...';

    try {
      const response = await fetch(`${window.location.origin}/api/indexed-files/${targetParentId}/related-files`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        // A "json_*" link_id means the entry lives in related_fileno rather than
        // file_indexing_links; the backend reads the id and matches the number anyway.
        body: JSON.stringify({ file_number: fileNumber, link_id: linkId })
      });

      const result = await response.json();
      if (result.success) {
        // The outcome card reports which store the entry came out of, what happened to
        // the Property ID and how many related files are left; refreshing the table is
        // deferred to its Done button.
        showUnlinkResult(result, {
          parentId: targetParentId,
          parentFileNumber: confirmBtn.dataset.parentFileNumber || '',
        });
      } else {
        showUnlinkError(result.message || 'Failed to unlink related file.');
      }
    } catch (err) {
      console.error(err);
      showUnlinkError('A network error occurred. Nothing was changed.');
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Unlink';
    }
  };
}

function handleDocumentClick(event) {
  if (!event.target.closest('[data-action-menu]')) {
    closeAllActionMenus();
  }
}

function handleLocationModalClick(event) {
  const closeButton = event.target.closest('.location-map-close-btn');
  if (closeButton) {
    event.preventDefault();
    closeLocationMapModal();
  }
}

function toggleActionMenu(button) {
  if (!button) {
    return;
  }

  const container = button.parentElement;
  if (!container) {
    return;
  }

  const menu = container.querySelector('.actions-dropdown-menu');
  if (!menu) {
    return;
  }

  const isHidden = menu.classList.contains('hidden');
  closeAllActionMenus();
  if (isHidden) {
    // Use fixed positioning to escape all overflow containers
    const buttonRect = button.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const spaceBelow = viewportHeight - buttonRect.bottom;
    const menuHeight = 220; // approximate menu height

    menu.style.position = 'fixed';
    menu.style.zIndex = '9999';
    menu.style.right = (window.innerWidth - buttonRect.right) + 'px';
    menu.style.left = 'auto';
    menu.style.bottom = 'auto';
    menu.style.top = 'auto';
    menu.style.marginTop = '0';
    menu.style.marginBottom = '0';

    if (spaceBelow < menuHeight) {
      // Open upward
      menu.style.bottom = (viewportHeight - buttonRect.top + 4) + 'px';
    } else {
      // Open downward
      menu.style.top = (buttonRect.bottom + 4) + 'px';
    }

    menu.classList.remove('hidden');
  }
}

function closeAllActionMenus() {
  if (!dom.tableBody) {
    return;
  }
  dom.tableBody.querySelectorAll('.actions-dropdown-menu').forEach((menu) => {
    menu.classList.add('hidden');
  });
}

function handleView(button) {
  const viewUrl = button.getAttribute('data-view-url');
  const fileId = button.getAttribute('data-file-id');

  if (viewUrl && viewUrl !== '#' && viewUrl !== '') {
    window.open(viewUrl, '_blank');
    closeAllActionMenus();
    return;
  }

  const row = getRowFromCache(fileId);
  if (row) {
    showRowDetails(row);
  }
}

function handleEdit(button) {
  const editUrl = button.getAttribute('data-edit-url');
  if (editUrl && editUrl !== '#' && editUrl !== '') {
    window.open(editUrl, '_blank');
    closeAllActionMenus();
    return;
  }

  alert('Edit route is not configured for this record.');
}

function handleTracking(button) {
  const trackingUrl = button.getAttribute('data-tracking-url');
  if (trackingUrl && trackingUrl !== '#' && trackingUrl !== '') {
    window.open(trackingUrl, '_blank');
  } else {
    alert('Tracking sheet route is not configured for this record.');
  }
  closeAllActionMenus();
}

async function fetchImageAsBase64(url) {
  if (!url) return null;
  try {
    const response = await fetch(url, { mode: 'cors', headers: { 'Accept': 'image/*' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const blob = await response.blob();
    return await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  } catch (err) {
    console.warn(`Image fetch failed: ${url}`, err);
    return null;
  }
}

function formatTimeAMPM(value) {
  if (!value) return '';
  try {
    const d = new Date(value);
    if (!isNaN(d.getTime())) {
      return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
  } catch (e) {}
  return String(value);
}

function formatDateOnly(value) {
  if (!value) return '';
  try {
    const d = new Date(value);
    if (!isNaN(d.getTime())) {
      return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
  } catch (e) {}
  return String(value);
}

function handlePrintCommissioningSheet(button) {
  const fileId = button.getAttribute('data-file-id');
  const row = rowCache.get(String(fileId));
  closeAllActionMenus();
  if (!row) {
    alert('Unable to load file details for the commissioning sheet.');
    return;
  }

  const mainNo = row.file_number || '—';
  const matchedNo = row.corresponding_fileno || '—';
  const run = () => generateMatchedFileCommissioningSheetPDF(row).catch((err) => {
    console.error('Commissioning sheet error:', err);
    if (window.Swal) {
      window.Swal.fire({ icon: 'error', title: 'Failed to generate slip', text: (err && err.message) ? err.message : String(err) });
    } else {
      alert('Failed to generate commissioning sheet: ' + (err && err.message ? err.message : err));
    }
  });

  if (window.Swal) {
    window.Swal.fire({
      icon: 'question',
      title: 'Generate Matching Slip?',
      html: `<div class="text-sm text-left">
               <div><span class="font-semibold text-slate-700">Land:</span> ${escapeHtml(mainNo)}</div>
               <div><span class="font-semibold text-slate-700">Cadastral:</span> ${escapeHtml(matchedNo)}</div>
             </div>`,
      showCancelButton: true,
      confirmButtonText: 'Generate',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#ea580c',
      reverseButtons: true,
      focusCancel: true,
    }).then((result) => {
      if (result.isConfirmed) run();
    });
  } else {
    if (window.confirm(`Generate Cadastral Correspondence File Matching Slip for ${mainNo} ↔ ${matchedNo}?`)) {
      run();
    }
  }
}

async function fetchMatchedFileDetails(fileNumber) {
  if (!fileNumber) return null;
  try {
    const url = `/mls-file-no-matching/get-file-details?file_number=${encodeURIComponent(fileNumber)}`;
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
    if (!response.ok) return null;
    const json = await response.json();
    return (json && json.success && json.data) ? json.data : null;
  } catch (err) {
    console.warn('Matched file lookup failed', err);
    return null;
  }
}

async function generateMatchedFileCommissioningSheetPDF(row, watermarkText = 'ORIGINAL') {
  if (!window.jspdf || !window.jspdf.jsPDF) {
    alert('jsPDF library is not loaded.');
    return;
  }
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const mainFileNo = row.file_number || '';
  const matchedFileNo = row.corresponding_fileno || '';
  const isTemporaryFile = String(mainFileNo).endsWith('(T)') || String(matchedFileNo).endsWith('(T)');

  const [logo1Base64, logo2Base64, leftFooterLogoBase64, footerLogoBase64, matchedDetails] = await Promise.all([
    fetchImageAsBase64('/assets/logo/logo1.png')
      .then(r => r || fetchImageAsBase64('/assets/logo/logo1.jpg'))
      .then(r => r || fetchImageAsBase64('/assets/logo/logoKlase.png')),
    fetchImageAsBase64('/assets/logo/logo3.jpeg')
      .then(r => r || fetchImageAsBase64('/assets/logo/las.jpeg'))
      .then(r => r || fetchImageAsBase64('/assets/logo/logo3.jpg')),
    fetchImageAsBase64('/assets/logo/Klase.png'),
    fetchImageAsBase64('/assets/logo/las.png').then(r => r || fetchImageAsBase64('/assets/logo/las.jpg')),
    fetchMatchedFileDetails(matchedFileNo)
  ]);

  if (logo1Base64) doc.addImage(logo1Base64, 'JPEG', 12, 10, 18, 18);
  if (logo2Base64) doc.addImage(logo2Base64, 'JPEG', 180, 10, 18, 18);

  doc.setFontSize(13);
  doc.setFont('helvetica', 'bold');
  doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 105, 16, { align: 'center' });
  doc.setFontSize(11);
  doc.text('DEPARTMENT OF LAND', 105, 23, { align: 'center' });
  doc.setFontSize(10.5);
  doc.text(isTemporaryFile ? 'TEMPORARY CADASTRAL CORRESPONDENCE FILE MATCHING SLIP' : 'CADASTRAL CORRESPONDENCE FILE MATCHING SLIP', 105, 30, { align: 'center' });

  doc.setTextColor(255, 0, 0);
  doc.setGState(doc.GState({ opacity: 0.18 }));
  doc.setFontSize(45);
  doc.text(watermarkText, 105, 150, { align: 'center', angle: 45, baseline: 'middle' });
  doc.setGState(doc.GState({ opacity: 1.0 }));
  doc.setTextColor(0, 0, 0);

  const qrPayload = row.tracking_id || mainFileNo || matchedFileNo;
  if (qrPayload) {
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrPayload)}`;
    const qrBase64 = await fetchImageAsBase64(qrUrl);
    if (qrBase64) {
      try { doc.addImage(qrBase64, 'PNG', 92.5, 34, 22, 22); } catch (e) { console.warn('QR add failed', e); }
    }
  }

  const colTopY = 62;
  const colBottomY = 200;
  const leftColX = 12;
  const rightColX = 110;
  const colWidth = 88;

  doc.setFontSize(11);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(30, 64, 175);
  doc.text('LAND INFORMATION', leftColX + colWidth / 2, colTopY + 6, { align: 'center' });
  doc.setTextColor(180, 83, 9);
  doc.text('CADASTRAL INFORMATION', rightColX + colWidth / 2, colTopY + 6, { align: 'center' });
  doc.setTextColor(0, 0, 0);

  doc.setDrawColor(200);
  doc.line(leftColX, colTopY + 9, leftColX + colWidth, colTopY + 9);
  doc.line(rightColX, colTopY + 9, rightColX + colWidth, colTopY + 9);
  doc.setDrawColor(120);

  const mainLocationParts = [row.plot_number, row.district, row.lga].filter(v => v && String(v).trim() !== '' && String(v).trim() !== '-');
  const mainLocation = (row.location && row.location !== '-') ? row.location : mainLocationParts.join(', ');

  const matchedLocationParts = [];
  if (matchedDetails) {
    if (matchedDetails.plot_no) matchedLocationParts.push(matchedDetails.plot_no);
    if (matchedDetails.district_name) matchedLocationParts.push(matchedDetails.district_name);
    if (matchedDetails.lga_name || matchedDetails.lga) matchedLocationParts.push(matchedDetails.lga_name || matchedDetails.lga);
  }
  const matchedLocation = (matchedDetails && matchedDetails.location) ? matchedDetails.location : matchedLocationParts.join(', ');

  const mainFields = [
    ['File No:', mainFileNo],
    ['File Title:', row.file_title || ''],
    ['Plot No:', row.plot_number || ''],
    ['TP No:', row.tp_no || ''],
    ['LPKN No:', row.lpkn_no || ''],
    ['District:', row.district || ''],
    ['LGA:', row.lga || ''],
    ['Location:', mainLocation],
  ];

  const matchedFields = [
    ['File No:', matchedFileNo],
    ['File Title:', matchedDetails ? (matchedDetails.title || '') : ''],
    ['Plot No:', matchedDetails ? (matchedDetails.plot_no || '') : ''],
    ['TP No:', matchedDetails ? (matchedDetails.tp_no || '') : ''],
    ['LPKN No:', matchedDetails ? (matchedDetails.lpkn_no || '') : ''],
    ['District:', matchedDetails ? (matchedDetails.district_name || '') : ''],
    ['LGA:', matchedDetails ? (matchedDetails.lga_name || matchedDetails.lga || '') : ''],
    ['Location:', matchedLocation],
  ];

  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');

  const fieldRowGap = 11;
  const fieldsStartY = colTopY + 18;
  const labelOffsetMain = 0;
  const valueOffsetMain = 24;
  const lineEndMain = leftColX + colWidth - 1;

  const wrappedLineHeight = 4;
  const drawColumn = (xOrigin, fields, options = {}) => {
    const showLabels = options.showLabels !== false;
    const valueOffset = showLabels ? valueOffsetMain : 0;
    let y = fieldsStartY;
    let lastUnderlineY = fieldsStartY;
    fields.forEach(([label, value]) => {
      if (showLabels) {
        doc.setFont('helvetica', 'bold');
        doc.text(label, xOrigin + labelOffsetMain, y);
      }
      doc.setFont('helvetica', 'normal');
      const valueStr = String(value || '');
      const maxWidth = colWidth - valueOffset - 1;
      const lines = doc.splitTextToSize(valueStr, maxWidth);
      doc.text(lines, xOrigin + valueOffset, y);
      const lastLineY = y + (lines.length - 1) * wrappedLineHeight;
      const lineY = lastLineY + 2;
      lastUnderlineY = lineY;
      doc.setDrawColor(160);
      const underlineStart = xOrigin + Math.max(0, valueOffset - 1);
      doc.line(underlineStart, lineY, xOrigin + colWidth - 1, lineY);
      y += fieldRowGap + (lines.length - 1) * wrappedLineHeight;
    });
    return lastUnderlineY;
  };

  const leftEndY  = drawColumn(leftColX,  mainFields);
  const rightEndY = drawColumn(rightColX, matchedFields, { showLabels: false });

  // Center divider ends exactly at the last field underline
  doc.setDrawColor(120);
  doc.setLineWidth(0.3);
  doc.line(105, colTopY, 105, Math.max(leftEndY, rightEndY) + 1);

  const matchedAt = row.created_at || row.indexed_at;
  const matchedBy = String(row.created_by || row.indexed_by || '').trim();
  const timeStr = formatTimeAMPM(matchedAt);
  const dateStr = formatDateOnly(matchedAt);
  const sentence = `Matched on ${dateStr || '____________'} at ${timeStr || '________'} by ${matchedBy || '____________'}.`;

  // Signatures
  let commonY = colBottomY + 14;
  doc.setFont('helvetica', 'normal');
  doc.text('Created by Signature', 50, commonY, { align: 'center' });
  doc.text('Approved by Signature', 150, commonY, { align: 'center' });
  doc.setDrawColor(120);
  doc.line(20, commonY + 10, 80, commonY + 10);
  doc.line(120, commonY + 10, 180, commonY + 10);

  // Footer logos
  if (leftFooterLogoBase64) doc.addImage(leftFooterLogoBase64, 'JPEG', 15, 272, 18, 18);
  if (footerLogoBase64) doc.addImage(footerLogoBase64, 'JPEG', 165, 272, 28, 12);

  // "Matched on…" sentence sits between the two footer logos
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.text(sentence, 105, 280, { align: 'center' });

  const safeName = String(mainFileNo || matchedFileNo || 'matching').replace(/[^a-zA-Z0-9_-]+/g, '_');
  const blobUrl = doc.output('bloburl');
  const printWin = window.open(blobUrl, '_blank');
  if (printWin) {
    printWin.addEventListener('load', () => {
      try { printWin.focus(); printWin.print(); } catch (e) {}
    });
  } else {
    doc.save(`cadastral-matching-slip-${safeName}.pdf`);
  }
}

function handleDelete(button) {
  const deleteUrl = button.getAttribute('data-delete-url');
  const fileNumber = button.getAttribute('data-file-number') || 'this file';
  if (!deleteUrl) {
    return;
  }

  const confirmation = window.confirm(`Delete ${fileNumber}? This action cannot be undone.`);
  if (!confirmation) {
    closeAllActionMenus();
    return;
  }

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = deleteUrl;
  form.style.display = 'none';

  const tokenInput = document.createElement('input');
  tokenInput.type = 'hidden';
  tokenInput.name = '_token';
  tokenInput.value = csrfToken;

  const methodInput = document.createElement('input');
  methodInput.type = 'hidden';
  methodInput.name = '_method';
  methodInput.value = 'DELETE';

  form.appendChild(tokenInput);
  form.appendChild(methodInput);
  document.body.appendChild(form);

  form.submit();
}

async function handleMarkDuplicate(button) {
  const fileId = button.getAttribute('data-file-id');
  const fileNumber = button.getAttribute('data-file-number') || `File #${fileId}`;

  if (!fileId) {
    return;
  }

  let isConfirmed = false;
  if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
    const confirmation = await window.Swal.fire({
      title: 'Mark As Duplicate?',
      text: `Mark ${fileNumber} as duplicate?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, mark duplicate',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#d97706'
    });
    isConfirmed = !!confirmation.isConfirmed;
  } else {
    isConfirmed = window.confirm(`Mark ${fileNumber} as duplicate?`);
  }

  if (!isConfirmed) {
    closeAllActionMenus();
    return;
  }

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        icon: 'error',
        title: 'Missing CSRF Token',
        text: 'Unable to locate CSRF token. Please refresh the page and try again.'
      });
    } else {
      alert('Unable to locate CSRF token. Please refresh the page and try again.');
    }
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/mark-duplicate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ id: fileId })
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Failed to mark file as duplicate.');
    }

    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        icon: 'success',
        title: 'Updated',
        text: result.message || 'File marked as duplicate successfully.'
      });
    } else {
      alert(result.message || 'File marked as duplicate successfully.');
    }
    closeAllActionMenus();
    await loadTable();
  } catch (error) {
    console.error('Failed to mark duplicate:', error);
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        icon: 'error',
        title: 'Failed',
        text: error.message || 'Failed to mark file as duplicate.'
      });
    } else {
      alert(error.message || 'Failed to mark file as duplicate.');
    }
  }
}

/**
 * Render one preview section as a table of source table => row count.
 */
function renderMovePreviewTable(title, rows, tone) {
  if (!Array.isArray(rows) || rows.length === 0) {
    return '';
  }

  const palette = {
    delete: { head: 'text-rose-700', count: 'text-rose-700', border: 'border-rose-100' },
    keep: { head: 'text-slate-600', count: 'text-slate-700', border: 'border-slate-100' },
    block: { head: 'text-amber-700', count: 'text-amber-700', border: 'border-amber-100' }
  }[tone] || { head: 'text-slate-600', count: 'text-slate-700', border: 'border-slate-100' };

  const body = rows.map(row => `
    <tr class="border-t ${palette.border}">
      <td class="py-1 pr-3 text-left font-mono text-[11px] text-slate-500">${escapeHtml(row.table)}</td>
      <td class="py-1 pr-3 text-left">${escapeHtml(row.label)}</td>
      <td class="py-1 text-right font-semibold ${palette.count}">${Number(row.count).toLocaleString()}</td>
    </tr>
  `).join('');

  return `
    <div class="mt-3">
      <p class="text-[11px] font-bold uppercase tracking-wider ${palette.head} text-left">${escapeHtml(title)}</p>
      <table class="w-full text-xs mt-1">
        <tbody>${body}</tbody>
      </table>
    </div>
  `;
}

/**
 * Plain-text equivalent of the preview, for the no-SweetAlert fallback.
 */
function movePreviewToText(rows) {
  return rows.map(row => `  - ${row.table}: ${row.count} (${row.label})`).join('\n');
}

/**
 * Open the shared file number selector and resolve with the chosen number, or
 * null if the operator closed it without applying one.
 *
 * The selector is a full-screen modal of its own, so it is never stacked inside
 * the SweetAlert card — the caller closes the card first and re-opens it after.
 * Closing is watched on the modal's own class list, because the plugin gives no
 * cancel callback.
 */
function pickDuplicateOfFileNumber() {
  return new Promise(resolve => {
    if (typeof window.GlobalFileNoModal === 'undefined') {
      resolve((window.prompt('Duplicate of — file number this record duplicates:') || '').trim() || null);
      return;
    }

    const modalEl = document.getElementById('global-fileno-modal');
    let settled = false;
    let observer = null;

    const finish = value => {
      if (settled) return;
      settled = true;
      if (observer) observer.disconnect();
      resolve(value);
    };

    window.GlobalFileNoModal.open({
      initialTab: 'mls',
      autoPopulateGenericFields: false,
      callback: selection => finish((selection && selection.fileNumber ? String(selection.fileNumber) : '').trim() || null)
    });

    // Dismissed without applying — the modal re-hides itself.
    if (modalEl && typeof MutationObserver !== 'undefined') {
      observer = new MutationObserver(() => {
        if (modalEl.classList.contains('hidden')) {
          finish(null);
        }
      });
      observer.observe(modalEl, { attributes: true, attributeFilter: ['class'] });
    }
  });
}

/**
 * Move an indexed file to indexing_duplicates. This deletes the record from the
 * indexing, file number, customer and entity tables, so the confirmation first
 * asks the server exactly which tables lose rows — and which record/transaction
 * tables (PRA, CofO, file history, file tracker) still reference the file and are
 * left untouched — before anything is removed. The operator must also pick the
 * file this one duplicates, via the shared file number selector.
 */
async function handleMoveToIndexingDuplicates(button) {
  const fileId = button.getAttribute('data-file-id');
  const fileNumber = button.getAttribute('data-file-number') || `File #${fileId}`;

  if (!fileId) {
    return;
  }

  const hasSwal = typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';

  // First gate: a plain yes/no. The detailed impact card (what gets deleted, and
  // which file this duplicates) is the second confirmation, after the preview
  // call — no point costing that request for a misclicked menu item.
  const proceed = hasSwal
    ? (await window.Swal.fire({
        title: 'Move to Indexing Duplicates?',
        text: `Are you sure you want to move ${fileNumber} to indexing duplicates?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        confirmButtonColor: '#e11d48',
        reverseButtons: true
      })).isConfirmed
    : window.confirm(`Move ${fileNumber} to indexing duplicates?`);

  if (!proceed) {
    closeAllActionMenus();
    return;
  }

  let preview = null;
  try {
    if (hasSwal) {
      window.Swal.fire({
        title: 'Checking impact…',
        html: `<p class="text-sm text-slate-600">Counting the records attached to <strong>${escapeHtml(fileNumber)}</strong>.</p>`,
        allowOutsideClick: false,
        didOpen: () => window.Swal.showLoading()
      });
    }

    const previewResponse = await fetch(
      `${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/indexing-duplicate-preview`,
      { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
    );
    const previewResult = await previewResponse.json().catch(() => ({}));

    if (!previewResponse.ok || !previewResult.success) {
      throw new Error(previewResult.message || 'Could not check what this move would delete.');
    }

    preview = previewResult.preview || {};
    if (hasSwal) {
      window.Swal.close();
    }
  } catch (error) {
    console.error('Failed to preview indexing duplicate move:', error);
    if (hasSwal) {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message });
    } else {
      alert(error.message);
    }
    return;
  }

  const deletions = preview.deletions || [];
  const retained = preview.retained || [];
  const blocking = preview.blocking || {};
  const blockingRows = Object.keys(blocking).map(table => ({
    table,
    label: blocking[table],
    count: parseInt(String(blocking[table]), 10) || 0
  }));

  // Blocked files never reach the confirm step — the move would be refused server
  // side anyway, so show why instead of offering a button that cannot work.
  if (blockingRows.length > 0) {
    const message = `${fileNumber} cannot be moved — it still has attached work.`;
    if (hasSwal) {
      await window.Swal.fire({
        icon: 'warning',
        title: 'Cannot Move This File',
        html: `
          <p class="text-sm text-slate-600 text-left">${escapeHtml(message)}</p>
          ${renderMovePreviewTable('Blocking references', blockingRows, 'block')}
          <p class="text-xs text-slate-500 mt-3 text-left">Detach or delete those first.</p>
        `
      });
    } else {
      alert(`${message}\n\n${movePreviewToText(blockingRows)}`);
    }
    closeAllActionMenus();
    return;
  }

  const transactionTotal = Number(preview.transaction_total || 0);
  const transactionWarning = transactionTotal > 0
    ? `<p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mt-3 text-left">
         This file number carries <strong>${transactionTotal.toLocaleString()}</strong> registered transaction record(s).
         They are <strong>not</strong> deleted — but a file with real dealings is usually the original, not the duplicate.
       </p>`
    : '';

  // PRA / CofO rows move with the record, so this one is a stop-and-think.
  const deletedTransactionTotal = Number(preview.deleted_transaction_total || 0);
  const deletedTransactionWarning = deletedTransactionTotal > 0
    ? `<p class="text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded p-2 mt-3 text-left">
         <strong>${deletedTransactionTotal.toLocaleString()}</strong> property/CofO record(s) will be
         <strong>deleted</strong> along with this file (archived in the snapshot).
         Make sure this is the duplicate and not the original.
       </p>`
    : '';

  // The file this one duplicates, stored on the archive record so the indexing
  // duplicates page can say what it was a duplicate of.
  let duplicateOf = '';

  if (hasSwal) {
    const PICK = 'pick-file-number';
    let confirmed = false;

    // Re-opened after each trip to the file number selector, which is a
    // full-screen modal and cannot be nested inside this card.
    for (;;) {
      const picked = duplicateOf
        ? `<span class="font-semibold text-slate-800 break-all">${escapeHtml(duplicateOf)}</span>`
        : '<span class="text-slate-400">No file number selected</span>';

      const confirmation = await window.Swal.fire({
        title: 'Move to Indexing Duplicates?',
        html: `
          <p class="text-sm text-slate-600 text-left">
            <strong>${escapeHtml(fileNumber)}</strong> will be moved to indexing duplicates.
            The rows below are deleted from the live tables (a full snapshot is archived).
          </p>
          ${renderMovePreviewTable('Will be deleted from', deletions, 'delete')}
          ${renderMovePreviewTable('Kept — still references this file', retained, 'keep')}
          ${deletedTransactionWarning}
          ${transactionWarning}
          <div class="mt-4 text-left">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Duplicate of</p>
            <div class="mt-1 flex items-center gap-2 rounded border border-slate-200 bg-slate-50 px-3 py-2">
              <span class="flex-1 min-w-0 text-sm">${picked}</span>
              <button type="button" id="move-pick-fileno"
                      class="shrink-0 rounded bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                ${duplicateOf ? 'Change' : 'Select file number'}
              </button>
            </div>
          </div>
        `,
        icon: 'warning',
        width: 600,
        showCancelButton: true,
        confirmButtonText: 'Yes, move it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        focusConfirm: false,
        allowOutsideClick: false,
        didOpen: () => {
          const pickButton = document.getElementById('move-pick-fileno');
          if (pickButton) {
            pickButton.addEventListener('click', () => window.Swal.close({ isDismissed: true, dismiss: PICK }));
          }
        },
        preConfirm: () => {
          // The whole point of the archive is knowing which file this duplicated,
          // so require it rather than storing an unattributed deletion.
          if (duplicateOf === '') {
            window.Swal.showValidationMessage('Select the file number this record duplicates.');
            return false;
          }
          return true;
        }
      });

      if (confirmation.dismiss === PICK) {
        // Keep the previous choice when the selector is closed without applying.
        duplicateOf = (await pickDuplicateOfFileNumber()) || duplicateOf;
        continue;
      }

      confirmed = confirmation.isConfirmed;
      break;
    }

    if (!confirmed) {
      closeAllActionMenus();
      return;
    }
  } else {
    if (!window.confirm(
      `Move ${fileNumber} to indexing duplicates?\n\n` +
      `Will be deleted from:\n${movePreviewToText(deletions) || '  (nothing)'}\n\n` +
      `Kept (still references this file):\n${movePreviewToText(retained) || '  (none)'}`
    )) {
      closeAllActionMenus();
      return;
    }

    duplicateOf = (window.prompt('Duplicate of — file number this record duplicates:') || '').trim();
    if (duplicateOf === '') {
      alert('A "duplicate of" file number is required.');
      closeAllActionMenus();
      return;
    }
  }

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    const message = 'Unable to locate CSRF token. Please refresh the page and try again.';
    if (hasSwal) {
      await window.Swal.fire({ icon: 'error', title: 'Missing CSRF Token', text: message });
    } else {
      alert(message);
    }
    return;
  }

  try {
    const response = await fetch(
      `${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/move-to-indexing-duplicates`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ duplicate_of: duplicateOf })
      }
    );

    const result = await response.json().catch(() => ({}));

    if (!response.ok || !result.success) {
      const error = new Error(result.message || 'Failed to move the file to indexing duplicates.');
      error.isBlocked = !!result.blocked;
      throw error;
    }

    const d = result.details || {};
    // A zero fileNumber count means the registry row was not found and is still
    // there — surface it rather than reporting a clean sweep.
    const registryNote = d.fileNumber_deleted === 0
      ? '<p class="text-xs text-amber-600 mt-2">No matching fileNumber row was found, so the file number table was left unchanged.</p>'
      : '';

    if (hasSwal) {
      await window.Swal.fire({
        icon: 'success',
        title: 'Moved',
        html: `
          <p class="text-sm text-slate-600">${escapeHtml(result.message || 'Moved.')}</p>
          ${registryNote}
        `
      });
    } else {
      alert(result.message || 'Moved to indexing duplicates.');
    }

    closeAllActionMenus();
    rowCache.delete(String(fileId));
    await loadTable();
    loadStats();
  } catch (error) {
    console.error('Failed to move to indexing duplicates:', error);
    if (hasSwal) {
      await window.Swal.fire({
        icon: error.isBlocked ? 'warning' : 'error',
        title: error.isBlocked ? 'Cannot Move This File' : 'Failed',
        text: error.message
      });
    } else {
      alert(error.message);
    }
  }
}

/* ---------------------------------------------------------------------------
 * MCC FileNo — Match Cadastral Correspondence FileNo modal
 * ------------------------------------------------------------------------- */
let mccState = { fileId: null, fileNumber: null, selectedCadastral: null, isMatched: false };

function mccShow(id, show) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('hidden', !show);
}

function mccDetailRow(label, value) {
  const clean = value !== undefined && value !== null ? String(value).trim() : '';
  const display = (clean !== '' && clean !== '-') ? escapeHtml(clean) : '<span class="text-gray-300">—</span>';
  return `<div class="flex flex-col">
      <dt class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">${label}</dt>
      <dd class="text-gray-800 font-medium break-words">${display}</dd>
    </div>`;
}

function renderMccLandDetails(row) {
  const el = document.getElementById('mcc-land-details');
  if (!el) return;
  const locationParts = [row.plot_number, row.district, row.lga].filter(v => v && String(v).trim() !== '' && String(v).trim() !== '-');
  const location = (row.location && row.location !== '-') ? row.location : locationParts.join(', ');
  el.innerHTML = [
    mccDetailRow('File No', row.file_number),
    mccDetailRow('File Title', row.file_title),
    mccDetailRow('Plot No', row.plot_number),
    mccDetailRow('TP No', row.tp_no),
    mccDetailRow('LPKN No', row.lpkn_no),
    mccDetailRow('District', row.district),
    mccDetailRow('LGA', row.lga),
    mccDetailRow('Location', location),
  ].join('');
}

function renderMccCadastralDetails(fileNumber, details) {
  const el = document.getElementById('mcc-cadastral-details');
  if (!el) return;
  const locationParts = [];
  if (details) {
    if (details.plot_no) locationParts.push(details.plot_no);
    if (details.district_name) locationParts.push(details.district_name);
    if (details.lga_name || details.lga) locationParts.push(details.lga_name || details.lga);
  }
  const location = (details && details.location) ? details.location : locationParts.join(', ');
  el.innerHTML = [
    mccDetailRow('File No', fileNumber),
    mccDetailRow('File Title', details ? details.title : ''),
    mccDetailRow('Plot No', details ? details.plot_no : ''),
    mccDetailRow('TP No', details ? details.tp_no : ''),
    mccDetailRow('LPKN No', details ? details.lpkn_no : ''),
    mccDetailRow('District', details ? details.district_name : ''),
    mccDetailRow('LGA', details ? (details.lga_name || details.lga) : ''),
    mccDetailRow('Location', location),
  ].join('');
}

function setMccMatchEnabled(enabled) {
  const btn = document.getElementById('mcc-match-btn');
  if (!btn) return;
  btn.disabled = !enabled;
  btn.classList.toggle('opacity-50', !enabled);
  btn.classList.toggle('cursor-not-allowed', !enabled);
}

async function loadMccCadastralDetails(fileNumber) {
  mccShow('mcc-cadastral-awaiting', false);
  mccShow('mcc-cadastral-details', false);
  mccShow('mcc-cadastral-loading', true);
  const details = await fetchMatchedFileDetails(fileNumber);
  renderMccCadastralDetails(fileNumber, details);
  mccShow('mcc-cadastral-loading', false);
  mccShow('mcc-cadastral-details', true);
  if (window.lucide) window.lucide.createIcons();
}

async function openMccModal(button) {
  const fileId = button.getAttribute('data-file-id');
  const row = rowCache.get(String(fileId));
  closeAllActionMenus();
  if (!row) {
    alert('Unable to load file details for matching.');
    return;
  }

  const modal = document.getElementById('mcc-file-modal');
  if (!modal) return;

  const isMatched = !!(row.corresponding_fileno && String(row.corresponding_fileno).trim() !== '' && String(row.corresponding_fileno).trim() !== '-');
  mccState = {
    fileId,
    fileNumber: row.file_number || '',
    selectedCadastral: isMatched ? row.corresponding_fileno : null,
    isMatched,
  };

  const idInput = document.getElementById('mcc-file-id');
  if (idInput) idInput.value = fileId;
  renderMccLandDetails(row);

  const badge = document.getElementById('mcc-status-badge');
  const cadInput = document.getElementById('mcc-cadastral-input');
  if (cadInput) cadInput.value = isMatched ? (row.corresponding_fileno || '') : '';

  if (isMatched) {
    mccShow('mcc-cadastral-selector', false);
    mccShow('mcc-match-btn', false);
    mccShow('mcc-unmatch-btn', true);
    if (badge) badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Matched</span>`;
    modal.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
    await loadMccCadastralDetails(row.corresponding_fileno);
  } else {
    mccShow('mcc-cadastral-selector', true);
    mccShow('mcc-cadastral-awaiting', true);
    mccShow('mcc-cadastral-details', false);
    mccShow('mcc-cadastral-loading', false);
    mccShow('mcc-match-btn', true);
    mccShow('mcc-unmatch-btn', false);
    setMccMatchEnabled(false);
    if (badge) badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><i data-lucide="clock" class="w-3.5 h-3.5"></i> Not Matched</span>`;
    modal.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
  }
}

function closeMccModal() {
  const modal = document.getElementById('mcc-file-modal');
  if (modal) modal.classList.add('hidden');
}

function openMccCadastralSelector() {
  const onPicked = (fileNumber) => {
    if (!fileNumber) return;
    mccState.selectedCadastral = fileNumber;
    const cadInput = document.getElementById('mcc-cadastral-input');
    if (cadInput) cadInput.value = fileNumber;
    setMccMatchEnabled(true);
    loadMccCadastralDetails(fileNumber);
  };

  if (typeof window.GlobalFileNoModal !== 'undefined' && typeof window.GlobalFileNoModal.open === 'function') {
    window.GlobalFileNoModal.open({
      callback: function (result) {
        if (result && result.fileNumber) onPicked(String(result.fileNumber).trim());
      }
    });
  } else {
    const val = prompt('Enter the Cadastral Correspondence File Number:');
    if (val && val.trim()) onPicked(val.trim());
  }
}

async function submitMccMatch() {
  const fileId = mccState.fileId;
  const fileNumber = mccState.fileNumber || `File #${fileId}`;
  const correspondingFileNo = mccState.selectedCadastral;

  if (!fileId || !correspondingFileNo) {
    if (window.Swal) {
      await window.Swal.fire({ icon: 'warning', title: 'Select a file', text: 'Please select a cadastral correspondence file number first.' });
    } else {
      alert('Please select a cadastral correspondence file number first.');
    }
    return;
  }

  let isConfirmed = false;
  if (window.Swal) {
    const confirmation = await window.Swal.fire({
      title: 'Confirm Match',
      html: `<div class="text-sm text-left space-y-1">
               <div><span class="font-semibold" style="color:#7a1212">Land:</span> ${escapeHtml(fileNumber)}</div>
               <div><span class="font-semibold" style="color:#8a5a2b">Cadastral:</span> ${escapeHtml(correspondingFileNo)}</div>
             </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, match',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#8a5a2b',
      reverseButtons: true,
    });
    isConfirmed = !!confirmation.isConfirmed;
  } else {
    isConfirmed = window.confirm(`Match ${fileNumber} ↔ ${correspondingFileNo}?`);
  }
  if (!isConfirmed) return;

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/match-correspondence`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ corresponding_fileno: correspondingFileNo })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to match correspondence file.');

    closeMccModal();
    if (window.Swal) {
      await window.Swal.fire({ icon: 'success', title: 'Matched', text: result.message || 'Correspondence file matched successfully.' });
    } else {
      alert(result.message || 'Correspondence file matched successfully.');
    }
    await loadTable();
  } catch (error) {
    console.error('Failed to match correspondence file:', error);
    if (window.Swal) {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to match correspondence file.' });
    } else {
      alert(error.message || 'Failed to match correspondence file.');
    }
  }
}

async function submitMccUnmatch() {
  const fileId = mccState.fileId;
  const fileNumber = mccState.fileNumber || `File #${fileId}`;
  const correspondingFileNo = mccState.selectedCadastral || '';
  if (!fileId) return;

  let isConfirmed = false;
  if (window.Swal) {
    const confirmation = await window.Swal.fire({
      title: 'Remove Match?',
      html: `Unmatch <strong>${escapeHtml(fileNumber)}</strong>${correspondingFileNo ? ` from <strong>${escapeHtml(correspondingFileNo)}</strong>` : ''}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, unmatch',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#7a1212',
      reverseButtons: true,
    });
    isConfirmed = !!confirmation.isConfirmed;
  } else {
    isConfirmed = window.confirm(`Remove the correspondence match for ${fileNumber}?`);
  }
  if (!isConfirmed) return;

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/unmatch-correspondence`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id: fileId })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to remove correspondence match.');

    closeMccModal();
    if (window.Swal) {
      await window.Swal.fire({ icon: 'success', title: 'Unmatched', text: result.message || 'Correspondence match removed successfully.' });
    } else {
      alert(result.message || 'Correspondence match removed successfully.');
    }
    await loadTable();
  } catch (error) {
    console.error('Failed to remove correspondence match:', error);
    if (window.Swal) {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to remove correspondence match.' });
    } else {
      alert(error.message || 'Failed to remove correspondence match.');
    }
  }
}

/* ---------------------------------------------------------------------------
 * MPP FileNo — Match Physical Planning-Land (Shadow Files) modal
 * ------------------------------------------------------------------------- */
let mppState = { fileId: null, fileNumber: null, selectedPp: null, isMatched: false };

function mppShow(id, show) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('hidden', !show);
}

function renderMppLandDetails(row) {
  const el = document.getElementById('mpp-land-details');
  if (!el) return;
  const locationParts = [row.plot_number, row.district, row.lga].filter(v => v && String(v).trim() !== '' && String(v).trim() !== '-');
  const location = (row.location && row.location !== '-') ? row.location : locationParts.join(', ');
  el.innerHTML = [
    mccDetailRow('File No', row.file_number),
    mccDetailRow('File Title', row.file_title),
    mccDetailRow('Plot No', row.plot_number),
    mccDetailRow('TP No', row.tp_no),
    mccDetailRow('LPKN No', row.lpkn_no),
    mccDetailRow('District', row.district),
    mccDetailRow('LGA', row.lga),
    mccDetailRow('Location', location),
  ].join('');
}

function renderMppPpDetails(fileNumber, details) {
  const el = document.getElementById('mpp-pp-details');
  if (!el) return;
  const locationParts = [];
  if (details) {
    if (details.plot_no) locationParts.push(details.plot_no);
    if (details.district_name) locationParts.push(details.district_name);
    if (details.lga_name || details.lga) locationParts.push(details.lga_name || details.lga);
  }
  const location = (details && details.location) ? details.location : locationParts.join(', ');
  el.innerHTML = [
    mccDetailRow('File No', fileNumber),
    mccDetailRow('File Title', details ? details.title : ''),
    mccDetailRow('Plot No', details ? details.plot_no : ''),
    mccDetailRow('TP No', details ? details.tp_no : ''),
    mccDetailRow('LPKN No', details ? details.lpkn_no : ''),
    mccDetailRow('District', details ? details.district_name : ''),
    mccDetailRow('LGA', details ? (details.lga_name || details.lga) : ''),
    mccDetailRow('Location', location),
  ].join('');
}

function setMppMatchEnabled(enabled) {
  const btn = document.getElementById('mpp-match-btn');
  if (!btn) return;
  btn.disabled = !enabled;
  btn.classList.toggle('opacity-50', !enabled);
  btn.classList.toggle('cursor-not-allowed', !enabled);
}

async function loadMppPpDetails(fileNumber) {
  mppShow('mpp-pp-awaiting', false);
  mppShow('mpp-pp-details', false);
  mppShow('mpp-pp-loading', true);
  const details = await fetchMatchedFileDetails(fileNumber);
  renderMppPpDetails(fileNumber, details);
  mppShow('mpp-pp-loading', false);
  mppShow('mpp-pp-details', true);
  if (window.lucide) window.lucide.createIcons();
}

async function openMppModal(button) {
  const fileId = button.getAttribute('data-file-id');
  const row = rowCache.get(String(fileId));
  closeAllActionMenus();
  if (!row) {
    alert('Unable to load file details for matching.');
    return;
  }

  const modal = document.getElementById('mpp-file-modal');
  if (!modal) return;

  const isMatched = !!(row.pp_lands_fileno && String(row.pp_lands_fileno).trim() !== '' && String(row.pp_lands_fileno).trim() !== '-');
  mppState = {
    fileId,
    fileNumber: row.file_number || '',
    selectedPp: isMatched ? row.pp_lands_fileno : null,
    isMatched,
  };

  const idInput = document.getElementById('mpp-file-id');
  if (idInput) idInput.value = fileId;
  renderMppLandDetails(row);

  const badge = document.getElementById('mpp-status-badge');
  const ppInput = document.getElementById('mpp-pp-input');
  if (ppInput) ppInput.value = isMatched ? (row.pp_lands_fileno || '') : '';

  if (isMatched) {
    mppShow('mpp-pp-selector', false);
    mppShow('mpp-match-btn', false);
    mppShow('mpp-unmatch-btn', true);
    if (badge) badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Matched</span>`;
    modal.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
    await loadMppPpDetails(row.pp_lands_fileno);
  } else {
    mppShow('mpp-pp-selector', true);
    mppShow('mpp-pp-awaiting', true);
    mppShow('mpp-pp-details', false);
    mppShow('mpp-pp-loading', false);
    mppShow('mpp-match-btn', true);
    mppShow('mpp-unmatch-btn', false);
    setMppMatchEnabled(false);
    if (badge) badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><i data-lucide="clock" class="w-3.5 h-3.5"></i> Not Matched</span>`;
    modal.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
  }
}

function closeMppModal() {
  const modal = document.getElementById('mpp-file-modal');
  if (modal) modal.classList.add('hidden');
}

function openMppPpSelector() {
  const onPicked = (fileNumber) => {
    if (!fileNumber) return;
    mppState.selectedPp = fileNumber;
    const ppInput = document.getElementById('mpp-pp-input');
    if (ppInput) ppInput.value = fileNumber;
    setMppMatchEnabled(true);
    loadMppPpDetails(fileNumber);
  };

  if (typeof window.GlobalFileNoModal !== 'undefined' && typeof window.GlobalFileNoModal.open === 'function') {
    window.GlobalFileNoModal.open({
      callback: function (result) {
        if (result && result.fileNumber) onPicked(String(result.fileNumber).trim());
      }
    });
  } else {
    const val = prompt('Enter the Physical Planning File Number:');
    if (val && val.trim()) onPicked(val.trim());
  }
}

async function submitMppMatch() {
  const fileId = mppState.fileId;
  const fileNumber = mppState.fileNumber || `File #${fileId}`;
  const ppFileNo = mppState.selectedPp;

  if (!fileId || !ppFileNo) {
    if (window.Swal) {
      await window.Swal.fire({ icon: 'warning', title: 'Select a file', text: 'Please select a physical planning file number first.' });
    } else {
      alert('Please select a physical planning file number first.');
    }
    return;
  }

  let isConfirmed = false;
  if (window.Swal) {
    const confirmation = await window.Swal.fire({
      title: 'Confirm Match',
      html: `<div class="text-sm text-left space-y-1">
               <div><span class="font-semibold" style="color:#12407a">Land:</span> ${escapeHtml(fileNumber)}</div>
               <div><span class="font-semibold" style="color:#2b8a5a">Physical Planning:</span> ${escapeHtml(ppFileNo)}</div>
             </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, match',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#2b8a5a',
      reverseButtons: true,
    });
    isConfirmed = !!confirmation.isConfirmed;
  } else {
    isConfirmed = window.confirm(`Match ${fileNumber} ↔ ${ppFileNo}?`);
  }
  if (!isConfirmed) return;

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/match-physical-planning`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ pp_lands_fileno: ppFileNo })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to match physical planning file.');

    closeMppModal();
    if (window.Swal) {
      await window.Swal.fire({ icon: 'success', title: 'Matched', text: result.message || 'Physical planning file matched successfully.' });
    } else {
      alert(result.message || 'Physical planning file matched successfully.');
    }
    await loadTable();
  } catch (error) {
    console.error('Failed to match physical planning file:', error);
    if (window.Swal) {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to match physical planning file.' });
    } else {
      alert(error.message || 'Failed to match physical planning file.');
    }
  }
}

async function submitMppUnmatch() {
  const fileId = mppState.fileId;
  const fileNumber = mppState.fileNumber || `File #${fileId}`;
  const ppFileNo = mppState.selectedPp || '';
  if (!fileId) return;

  let isConfirmed = false;
  if (window.Swal) {
    const confirmation = await window.Swal.fire({
      title: 'Remove Match?',
      html: `Unmatch <strong>${escapeHtml(fileNumber)}</strong>${ppFileNo ? ` from <strong>${escapeHtml(ppFileNo)}</strong>` : ''}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, unmatch',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#12407a',
      reverseButtons: true,
    });
    isConfirmed = !!confirmation.isConfirmed;
  } else {
    isConfirmed = window.confirm(`Remove the physical planning match for ${fileNumber}?`);
  }
  if (!isConfirmed) return;

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/unmatch-physical-planning`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id: fileId })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to remove physical planning match.');

    closeMppModal();
    if (window.Swal) {
      await window.Swal.fire({ icon: 'success', title: 'Unmatched', text: result.message || 'Physical planning match removed successfully.' });
    } else {
      alert(result.message || 'Physical planning match removed successfully.');
    }
    await loadTable();
  } catch (error) {
    console.error('Failed to remove physical planning match:', error);
    if (window.Swal) {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to remove physical planning match.' });
    } else {
      alert(error.message || 'Failed to remove physical planning match.');
    }
  }
}

function handleHasTempFile(button, viewOnly = false) {
  const fileId = button.getAttribute('data-file-id');
  const fileNumber = button.getAttribute('data-file-number') || '';
  const fileTitle = button.getAttribute('data-file-title') || '';
  const plotNumber = button.getAttribute('data-plot-number') || '';
  const district = button.getAttribute('data-district') || '';
  const lga = button.getAttribute('data-lga') || '';
  const location = button.getAttribute('data-location') || '';

  if (!fileId) return;

  closeAllActionMenus();

  const modal = document.getElementById('temp-file-modal');
  if (!modal) return;

  // Populate read-only fields
  document.getElementById('temp-file-id').value = fileId;
  document.getElementById('temp-file-file-number').value = fileNumber;
  document.getElementById('temp-file-file-title').value = fileTitle;
  document.getElementById('temp-file-plot-number').value = plotNumber;
  document.getElementById('temp-file-district').value = district;
  document.getElementById('temp-file-lga').value = lga;
  document.getElementById('temp-file-location').value = location;

  // Clear the editable field (pre-fill if existing)
  document.getElementById('temp-file-no-input').value = button.getAttribute('data-temp-file-no') || '';

  // Toggle edit controls based on mode
  const editControls = modal.querySelectorAll('#open-fileno-selector-btn, #submit-temp-file, #cancel-temp-file');
  editControls.forEach(el => el.classList.toggle('hidden', viewOnly));
  const footer = modal.querySelector('.bg-gray-50.px-8.py-5');
  if (footer) footer.classList.toggle('hidden', viewOnly);

  modal.classList.remove('hidden');
  if (window.lucide) window.lucide.createIcons();
}

function openTempFileNoSelector() {
  if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
    // Fallback: prompt for manual entry if GlobalFileNoModal is not available
    const val = prompt('Enter temporary file number:');
    if (val && val.trim()) {
      document.getElementById('temp-file-no-input').value = val.trim();
    }
    return;
  }

  window.GlobalFileNoModal.open({
    callback: function (result) {
      if (result && result.fileNumber) {
        const input = document.getElementById('temp-file-no-input');
        if (input) {
          input.value = result.fileNumber;
        }
      }
    }
  });
}

async function submitTempFile() {
  const fileId = document.getElementById('temp-file-id').value;
  const tempFileNo = document.getElementById('temp-file-no-input').value.trim();

  if (!tempFileNo) {
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the Temporary File Number.' });
    } else {
      alert('Please enter the Temporary File Number.');
    }
    return;
  }

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    // 1. Update the temporary file number first (before popping has_transaction)
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/set-temp-file`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        temp_file_no: tempFileNo
      })
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Failed to set temporary file number.');
    }

    // Hide the temp file modal first as requested
    document.getElementById('temp-file-modal').classList.add('hidden');

    // 2. Ask "Has Transaction?"
    let hasTransaction = false;
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      const promptResult = await window.Swal.fire({
        title: 'Has Transaction?',
        text: "Does this file have a transaction?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        confirmButtonColor: '#0d9488', // teal-600
        cancelButtonColor: '#64748b', // slate-500
      });
      hasTransaction = !!promptResult.isConfirmed;
    } else {
      hasTransaction = window.confirm('Does this file have a transaction?');
    }

    // 3. Update has_transaction status in database
    const updateResponse = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/set-temp-file`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        temp_file_no: tempFileNo,
        has_transaction: hasTransaction
      })
    });

    const updateResult = await updateResponse.json().catch(() => ({}));
    if (!updateResponse.ok || !updateResult.success) {
      throw new Error(updateResult.message || 'Failed to update transaction status.');
    }

    const row = updateResult.data || result.data;

    if (hasTransaction) {
      // 4. If click yes, prepare the property transaction modal
      if (row && typeof window.openPropertyTransactionModal === 'function') {
        const tempSuffixPattern = /\(\s*T\s*\)\s*$/i;
        let fileNo = row.file_number || '';
        const originalFileNumber = document.getElementById('temp-file-file-number').value || '';

        // Ensure we use the main file number (not the temp file number)
        if (!fileNo || fileNo === '-' || fileNo === '') {
          fileNo = originalFileNumber;
        }

        // Clean main file number from any (T) suffix
        if (fileNo) {
          fileNo = fileNo.replace(tempSuffixPattern, '').trim();
        }

        // If for some reason the main file number is still empty or is identical to the temp one,
        // fallback to the original main file number.
        if (fileNo === tempFileNo && originalFileNumber && originalFileNumber !== tempFileNo) {
          fileNo = originalFileNumber.replace(tempSuffixPattern, '').trim();
        }

        const hasTemp = row.has_temp_file || tempSuffixPattern.test(fileNo) || !!tempFileNo;

        const fileIndexingData = {
          id: row.id,
          file_number: fileNo, // clean main file number
          has_temp_file: hasTemp ? 1 : 0,
          file_title: row.file_title || document.getElementById('temp-file-file-title').value || '',
          lga: row.lga || document.getElementById('temp-file-lga').value || '',
          district: row.district || document.getElementById('temp-file-district').value || '',
          land_use_type: row.land_use_type || '',
          plot_no: row.plot_no || row.plot_number || document.getElementById('temp-file-plot-number').value || '',
          tp_no: row.tp_no || '',
          lpkn_no: row.lpkn_no || '',
          temp_file_no: tempFileNo,
          location: row.location || document.getElementById('temp-file-location').value || '',
          property_description: row.location || document.getElementById('temp-file-location').value || [row.district, row.lga].filter(Boolean).join(', '),
          existing_records: []
        };

        // Show loading while fetching existing records for the modal
        if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            title: 'Loading...',
            text: 'Fetching transaction details',
            allowOutsideClick: false,
            didOpen: () => {
              window.Swal.showLoading();
            }
          });
        }

        try {
          const checkResponse = await fetch(`${window.location.origin}/api/property-records/check/${encodeURIComponent(fileNo)}`);
          const checkData = await checkResponse.json();

          if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
            window.Swal.close();
          }

          if (checkData.success && checkData.records) {
            fileIndexingData.existing_records = checkData.records;
          }

          window.openPropertyTransactionModal(fileIndexingData);
        } catch (error) {
          console.error('Error fetching transactions:', error);
          if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
            window.Swal.close();
          }
          window.openPropertyTransactionModal(fileIndexingData);
        }
      }
    } else {
      // If clicked No, show success alert
      if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
        await window.Swal.fire({ icon: 'success', title: 'Updated', text: 'Temporary file number saved successfully.' });
      } else {
        alert('Temporary file number saved successfully.');
      }
    }

    await loadTable();
  } catch (error) {
    console.error('Failed to set temp file:', error);
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to set temporary file number.' });
    } else {
      alert(error.message || 'Failed to set temporary file number.');
    }
  }
}

function handleUpdatePlaceholder(button) {
  const fileId = button.getAttribute('data-file-id');
  const fullPlaceholder = (button.getAttribute('data-placeholder') || '').trim();

  if (!fileId) return;

  closeAllActionMenus();

  const modal = document.getElementById('update-placeholder-modal');
  if (!modal) return;

  document.getElementById('update-placeholder-id').value = fileId;

  // Split prefix and serial
  const prefixSelect = document.getElementById('update-placeholder-prefix');
  const serialInput = document.getElementById('update-placeholder-serial');

  if (fullPlaceholder) {
    const parts = fullPlaceholder.split(/\s+/);
    if (parts.length >= 2) {
      const prefix = parts[0].toUpperCase();
      const serial = parts.slice(1).join(' ');

      // Try to match prefix in select
      let found = false;
      for (let i = 0; i < prefixSelect.options.length; i++) {
        if (prefixSelect.options[i].value === prefix) {
          prefixSelect.selectedIndex = i;
          found = true;
          break;
        }
      }

      if (!found) {
        prefixSelect.value = 'OTHER';
      }

      serialInput.value = serial;
    } else {
      prefixSelect.value = ''; // Default to "Select Prefix"
      serialInput.value = fullPlaceholder;
    }
  } else {
    prefixSelect.value = '';
    serialInput.value = '';
  }

  modal.classList.remove('hidden');
  if (window.lucide) window.lucide.createIcons();
}

async function submitUpdatePlaceholder() {
  const fileId = document.getElementById('update-placeholder-id').value;
  const prefix = document.getElementById('update-placeholder-prefix').value;
  const serial = document.getElementById('update-placeholder-serial').value.trim();

  if (!prefix && prefix !== 'OTHER') {
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select a prefix.' });
    } else {
      alert('Please select a prefix.');
    }
    return;
  }

  if (!serial) {
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the serial number part.' });
    } else {
      alert('Please enter the serial number part.');
    }
    return;
  }

  const fullPlaceholder = prefix === 'OTHER' ? serial : `${prefix} ${serial}`;

  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    alert('Unable to locate CSRF token. Please refresh the page and try again.');
    return;
  }

  try {
    const response = await fetch(`${window.location.origin}/api/indexed-files/${encodeURIComponent(fileId)}/update-placeholder`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ placeholder: fullPlaceholder })
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Failed to update placeholder.');
    }

    document.getElementById('update-placeholder-modal').classList.add('hidden');

    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'success', title: 'Updated', text: result.message || 'Placeholder updated successfully.' });
    } else {
      alert(result.message || 'Placeholder updated successfully.');
    }

    await loadTable();
  } catch (error) {
    console.error('Failed to update placeholder:', error);
    if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({ icon: 'error', title: 'Failed', text: error.message || 'Failed to update placeholder.' });
    } else {
      alert(error.message || 'Failed to update placeholder.');
    }
  }
}

// Bind temp file modal events
document.addEventListener('DOMContentLoaded', function () {
  const closeTempModalBtn = document.getElementById('close-temp-file-modal');
  const cancelTempBtn = document.getElementById('cancel-temp-file');
  const tempBackdrop = document.getElementById('temp-file-backdrop');
  const submitTempBtn = document.getElementById('submit-temp-file');
  const openFileNoSelectorBtn = document.getElementById('open-fileno-selector-btn');
  const tempFileNoInput = document.getElementById('temp-file-no-input');

  const closeTempModal = () => {
    const modal = document.getElementById('temp-file-modal');
    if (modal) modal.classList.add('hidden');
  };

  if (closeTempModalBtn) closeTempModalBtn.addEventListener('click', closeTempModal);
  if (cancelTempBtn) cancelTempBtn.addEventListener('click', closeTempModal);
  if (tempBackdrop) tempBackdrop.addEventListener('click', closeTempModal);
  if (submitTempBtn) submitTempBtn.addEventListener('click', submitTempFile);
  if (openFileNoSelectorBtn) openFileNoSelectorBtn.addEventListener('click', openTempFileNoSelector);
  if (tempFileNoInput) tempFileNoInput.addEventListener('click', openTempFileNoSelector);

  // Placeholder modal events
  const closePlaceholderModalBtn = document.getElementById('close-update-placeholder-modal');
  const cancelPlaceholderBtn = document.getElementById('cancel-update-placeholder');
  const placeholderBackdrop = document.getElementById('update-placeholder-backdrop');
  const submitPlaceholderBtn = document.getElementById('submit-update-placeholder');

  const closePlaceholderModal = () => {
    const modal = document.getElementById('update-placeholder-modal');
    if (modal) modal.classList.add('hidden');
  };

  if (closePlaceholderModalBtn) closePlaceholderModalBtn.addEventListener('click', closePlaceholderModal);
  if (cancelPlaceholderBtn) cancelPlaceholderBtn.addEventListener('click', closePlaceholderModal);
  if (placeholderBackdrop) placeholderBackdrop.addEventListener('click', closePlaceholderModal);
  if (submitPlaceholderBtn) submitPlaceholderBtn.addEventListener('click', submitUpdatePlaceholder);

  // MCC FileNo (Match Cadastral Correspondence) modal events
  const mccCloseBtn = document.getElementById('close-mcc-file-modal');
  const mccCancelBtn = document.getElementById('cancel-mcc-file');
  const mccBackdrop = document.getElementById('mcc-file-backdrop');
  const mccMatchBtn = document.getElementById('mcc-match-btn');
  const mccUnmatchBtn = document.getElementById('mcc-unmatch-btn');
  const mccSelectorBtn = document.getElementById('mcc-open-fileno-selector-btn');
  const mccCadastralInput = document.getElementById('mcc-cadastral-input');

  if (mccCloseBtn) mccCloseBtn.addEventListener('click', closeMccModal);
  if (mccCancelBtn) mccCancelBtn.addEventListener('click', closeMccModal);
  if (mccBackdrop) mccBackdrop.addEventListener('click', closeMccModal);
  if (mccMatchBtn) mccMatchBtn.addEventListener('click', submitMccMatch);
  if (mccUnmatchBtn) mccUnmatchBtn.addEventListener('click', submitMccUnmatch);
  if (mccSelectorBtn) mccSelectorBtn.addEventListener('click', openMccCadastralSelector);
  if (mccCadastralInput) mccCadastralInput.addEventListener('click', openMccCadastralSelector);

  // MPP FileNo (Match Physical Planning-Land) modal events
  const mppCloseBtn = document.getElementById('close-mpp-file-modal');
  const mppCancelBtn = document.getElementById('cancel-mpp-file');
  const mppBackdrop = document.getElementById('mpp-file-backdrop');
  const mppMatchBtn = document.getElementById('mpp-match-btn');
  const mppUnmatchBtn = document.getElementById('mpp-unmatch-btn');
  const mppSelectorBtn = document.getElementById('mpp-open-fileno-selector-btn');
  const mppPpInput = document.getElementById('mpp-pp-input');

  if (mppCloseBtn) mppCloseBtn.addEventListener('click', closeMppModal);
  if (mppCancelBtn) mppCancelBtn.addEventListener('click', closeMppModal);
  if (mppBackdrop) mppBackdrop.addEventListener('click', closeMppModal);
  if (mppMatchBtn) mppMatchBtn.addEventListener('click', submitMppMatch);
  if (mppUnmatchBtn) mppUnmatchBtn.addEventListener('click', submitMppUnmatch);
  if (mppSelectorBtn) mppSelectorBtn.addEventListener('click', openMppPpSelector);
  if (mppPpInput) mppPpInput.addEventListener('click', openMppPpSelector);
});

function getRowFromCache(id) {
  if (!id) {
    return null;
  }

  return rowCache.get(String(id)) || null;
}

function showRowDetails(row) {
  const details = [
    `File Number: ${row.file_number || '-'}`,
    `File Name: ${row.file_title || '-'}`,
    `Gen. Registry: ${row.general_registry || '-'}`,
    `Registry: ${getRegistryDisplayValue(row)}`,
    `Sys Batch No: ${row.sys_batch_no || '-'}`,
    `MDC Batch No: ${row.batch_no || '-'}`,
    `Tracking ID: ${row.tracking_id || '-'}`,
    `Plot Number: ${row.plot_number || '-'}`,
    `TP Number: ${row.tp_no || '-'}`,
    `Land Use: ${row.land_use_type || '-'}`,
    `District: ${row.district || '-'}`,
    `LGA: ${row.lga || '-'}`,
    `Indexed Date: ${row.indexed_at || '-'}`,
    `Indexed By: ${row.indexed_by || '-'}`,
  ].join('\n');

  alert(`Indexed File Details\n\n${details}`);
  closeAllActionMenus();
}

function getCsrfToken() {
  const meta = document.head.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : null;
}

async function loadStats() {
  if (!config.statsUrl) {
    return;
  }

  try {
    const statsUrl = new URL(config.statsUrl, window.location.origin);
    if (config.registry) {
      statsUrl.searchParams.set('registry', config.registry);
    }

    const payload = await fetchJson(statsUrl.toString());
    if (!payload.success) {
      return;
    }

    const data = payload.data || {};
    if (dom.statsTotal) {
      dom.statsTotal.textContent = formatNumber(data.total_indexed);
    }
    if (dom.statsToday) {
      dom.statsToday.textContent = formatNumber(data.indexed_today);
    }
    if (dom.statsRegistries) {
      dom.statsRegistries.textContent = formatNumber(data.unique_registries);
    }
  } catch (error) {
    console.error('Failed to load indexed file stats:', error);
  }
}

let tableAbortController = null;

async function loadTable() {
  if (!config.listUrl) {
    dom.tableBody.innerHTML = '<tr><td colspan="' + totalCols + '" class="p-6 text-center text-sm text-red-500">List endpoint is not configured.</td></tr>';
    return;
  }

  try {
    setLoading(true);
    const params = createParams();
    if (tableAbortController) {
      tableAbortController.abort();
    }
    tableAbortController = new AbortController();
    const payload = await fetchJson(`${config.listUrl}?${params.toString()}`, tableAbortController.signal);

    renderRows(payload.data || []);
    updatePagination(payload.meta);
    updateSortIndicators();
  } catch (error) {
    if (error.name === 'AbortError') {
      return;
    }
    console.error('Failed to load indexed files:', error);
    dom.tableBody.innerHTML = '<tr><td colspan="' + totalCols + '" class="p-6 text-center text-sm text-red-500">Unable to load indexed files. Please try again.</td></tr>';
    updatePagination(null);
  } finally {
    setLoading(false);
  }
}

// Mark the column currently driving the order, so the list is never silently sorted
// by something other than the column the user thinks they are looking at.
function updateSortIndicators() {
  headers.forEach((header) => {
    const isActive = header.dataset.sort === state.sort;
    let arrow = header.querySelector('.sort-indicator');

    if (!arrow) {
      arrow = document.createElement('span');
      arrow.className = 'sort-indicator ml-1 text-[10px]';
      header.appendChild(arrow);
    }

    arrow.textContent = isActive ? (state.direction === 'asc' ? '▲' : '▼') : '';
    header.classList.toggle('text-blue-700', isActive);
    header.classList.toggle('text-gray-700', !isActive);
    header.style.cursor = 'pointer';
  });
}

function attachEventListeners() {
  if (dom.searchInput) {
    const debouncedSearch = debounce((event) => {
      state.search = event.target.value;
      state.page = 1;
      loadTable();
    }, 350);

    dom.searchInput.addEventListener('input', debouncedSearch);
  }

  if (dom.perPageSelect) {
    dom.perPageSelect.addEventListener('change', (event) => {
      const value = Number(event.target.value) || 50;
      state.perPage = Math.max(1, Math.min(value, 100));
      state.page = 1;
      loadTable();
    });
  }

  if (dom.prevButton) {
    dom.prevButton.addEventListener('click', () => {
      if (state.page > 1 && !state.isLoading) {
        state.page -= 1;
        loadTable();
      }
    });
  }

  if (dom.nextButton) {
    dom.nextButton.addEventListener('click', () => {
      if (!state.isLoading) {
        state.page += 1;
        loadTable();
      }
    });
  }

  headers.forEach((header) => {
    header.addEventListener('click', () => {
      const sortKey = header.dataset.sort;
      if (!sortKey || state.isLoading) {
        return;
      }

      if (state.sort === sortKey) {
        state.direction = state.direction === 'asc' ? 'desc' : 'asc';
      } else {
        state.sort = sortKey;
        state.direction = sortKey === 'created_at' ? 'desc' : 'asc';
      }

      state.page = 1;
      loadTable();
    });
  });
}

function debounce(callback, delay) {
  let timeoutId;
  return (...args) => {
    window.clearTimeout(timeoutId);
    timeoutId = window.setTimeout(() => callback(...args), delay);
  };
}

function escapeHtml(value) {
  const text = value === undefined || value === null ? '' : String(value);
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  };

  return text.replace(/[&<>"']/g, (char) => map[char]);
}

function formatNumber(value) {
  const number = Number(value);
  if (Number.isNaN(number)) {
    return '--';
  }
  return number.toLocaleString();
}

// Global delegated modal close handler to fix "close buttons not working"
// This works even if modals are moved in the DOM or re-rendered.
document.addEventListener('click', (e) => {
  // 1. Check for Close Related Modal
  if (e.target.closest('#close-related-modal-btn') || e.target.closest('#close-related-modal-footer-btn')) {
    document.getElementById('related-files-modal')?.classList.add('hidden');
  }
  // 2. Check for Close Edit Related Modal
  if (e.target.closest('#close-edit-related-modal') || e.target.closest('#cancel-edit-related')) {
    document.getElementById('edit-related-file-modal')?.classList.add('hidden');
  }
  // 2b. Check for Close Unlink Related Modal — via closeUnlinkModal() so a refresh
  // queued by a successful unlink still runs when dismissed with X / backdrop / Cancel.
  if (e.target.closest('#close-unlink-related-modal') || e.target.closest('#cancel-unlink-related')
      || e.target.closest('#unlink-related-backdrop')) {
    closeUnlinkModal();
  }
  // 3. Check for Close Temp File Modal
  if (e.target.closest('#close-temp-file-modal') || e.target.closest('#cancel-temp-file')) {
    document.getElementById('temp-file-modal')?.classList.add('hidden');
  }
  // 4. Check for Close EDMS Files Modal
  if (e.target.closest('#close-edms-files-modal') || e.target.closest('#close-edms-files-modal-footer')) {
    document.getElementById('edms-files-modal')?.classList.add('hidden');
  }
});

// Callback function triggered by the property transaction modal on successful save/update
window.checkExistingPropertyRecords = function () {
  console.log('checkExistingPropertyRecords callback triggered, reloading table...');
  loadTable();
};

function bootstrap() {
  if (dom.perPageSelect) {
    state.perPage = Number(dom.perPageSelect.value) || state.perPage;
  }

  attachEventListeners();
  loadStats();
  loadTable();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
  bootstrap();
}
