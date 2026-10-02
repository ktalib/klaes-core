{{--
  Edit Record modal behaviour — SHARED, included by more than one page.

  Included by:
    - legal_search/js.blade.php             (on-premise Legal Search screen)
    - system-admin/phs/edit_request_preview (PHS "Correct Search Result")

  Raw JS on purpose: no <script> wrapper and no IIFE. js.blade.php is one big
  <script> and calls openEditModal / closeEditModal / firstFilled from outside
  this block, so these must be declared in the host's scope.

  The host page must already have in scope:
    csrfToken, cleanupAjax(url, body), searchResults,
    dbLandUseOptions, dbDistrictOptions, dbInstrumentTypeOptions

  This exists so the field definitions below are written ONCE. They name real
  table columns; a second hand-maintained copy is the kind of thing that rots
  without anyone noticing.

  NOTE: this block must stay self-balancing. It ends at the edit-modal
  listeners — the cleanup-toolbar wiring that follows in js.blade.php binds to
  .row-checkbox / #cleanup-edit-btn and belongs to that page alone.
--}}
  const editFieldDefs = {
    'file_history_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      // Instrument/transaction identity leads the date block: the type drives which
      // party roles matter, so it is picked before the registration date/time.
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction/Commencement Date', type: 'date' },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time', type: 'time' },
      // Standard parties
      { key: 'party_1', label: 'Party 1' },
      { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      // Party role fields (mapped from backend EDITABLE_COLUMNS)
      { key: 'Assignor', label: 'Assignor' },
      { key: 'Assignee', label: 'Assignee' },
      { key: 'Mortgagor', label: 'Mortgagor' },
      { key: 'Mortgagee', label: 'Mortgagee' },
      { key: 'Grantor', label: 'Grantor' },
      { key: 'Grantee', label: 'Grantee' },
      { key: 'Surrenderor', label: 'Surrenderor' },
      { key: 'Surrenderee', label: 'Surrenderee' },
      { key: 'Lessor', label: 'Lessor' },
      { key: 'Lessee', label: 'Lessee' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'CofO_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'np_fileno', label: 'NP File No', type: 'fileno' },
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      // CofO_staging has no literal reg_date column; its registration date/time is
      // stored in deeds_date/deeds_time (same as pra), which the report/timeline
      // prefer for the displayed "Reg Date". transaction_time is kept as the
      // last-resort Reg Time fallback.
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction/Commencement Date', type: 'date' },
      { key: 'deeds_date', label: 'Reg Date', type: 'date' },
      { key: 'deeds_time', label: 'Reg Time', type: 'time' },
      // Standard parties
      { key: 'Grantor', label: 'Party 1' },
      { key: 'Grantee', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      // Party role fields (mapped from backend EDITABLE_COLUMNS)
      { key: 'Assignor', label: 'Assignor' },
      { key: 'Assignee', label: 'Assignee' },
      { key: 'Mortgagor', label: 'Mortgagor' },
      { key: 'Mortgagee', label: 'Mortgagee' },
      { key: 'Surrenderor', label: 'Surrenderor' },
      { key: 'Surrenderee', label: 'Surrenderee' },
      { key: 'Lessor', label: 'Lessor' },
      { key: 'Lessee', label: 'Lessee' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'period', label: 'Period' }, { key: 'period_unit', label: 'Period Unit' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'pra': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      // pra has no reg_date/reg_time columns; its registration date/time is
      // tracked as deeds_date/deeds_time instead.
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction/Commencement Date', type: 'date' },
      { key: 'deeds_date', label: 'Deeds Date', type: 'date' },
      { key: 'deeds_time', label: 'Deeds Time', type: 'time' },
      // Standard parties
      { key: 'party_1', label: 'Party 1' }, { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' }, { key: 'party_4', label: 'Party 4' },
      // Party role fields (mapped from backend EDITABLE_COLUMNS)
      { key: 'Assignor', label: 'Assignor' },
      { key: 'Assignee', label: 'Assignee' },
      { key: 'Mortgagor', label: 'Mortgagor' },
      { key: 'Mortgagee', label: 'Mortgagee' },
      { key: 'Grantor', label: 'Grantor' },
      { key: 'Grantee', label: 'Grantee' },
      { key: 'Surrenderor', label: 'Surrenderor' },
      { key: 'Surrenderee', label: 'Surrenderee' },
      { key: 'Lessor', label: 'Lessor' },
      { key: 'Lessee', label: 'Lessee' },
      { key: 'Donor', label: 'Donor' },
      { key: 'Donee', label: 'Donee' },
      { key: 'Vendor', label: 'Vendor' },
      { key: 'Purchaser', label: 'Purchaser' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'deed_registrations': [
      { key: 'fileno', label: 'File No', readonly: true },
      { key: 'parent_fileno', label: 'MLS/NP File No', type: 'fileno' },
      { key: 'serial_no', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'page_no', label: 'Page No', type: 'particular' },
      { key: 'volume_no', label: 'Volume No', type: 'particular' },
      { key: 'registration_number', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'instrument_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'instrument_date', label: 'Instrument Date', type: 'date' },
      { key: 'deeds_date', label: 'Deeds Date', type: 'date' }, { key: 'deeds_time', label: 'Deeds Time', type: 'time' },
      { key: 'grantor', label: 'Party 1' }, { key: 'grantee', label: 'Party 2' },
      { key: 'lga', label: 'LGA' }, { key: 'district', label: 'District' },
      { key: 'plot_number', label: 'Plot Number' }, { key: 'size', label: 'Size' },
      { key: 'property_description', label: 'Property Description', type: 'textarea' },
    ],
  };

  const firstFilled = (...values) => values.find(v => v !== null && v !== undefined && String(v).trim() !== '');

  const updateRegNoFromParticulars = () => {
    const serialInput = document.querySelector('#edit-modal-body [name="serialNo"], #edit-modal-body [name="serial_no"]');
    const pageInput = document.querySelector('#edit-modal-body [name="pageNo"], #edit-modal-body [name="page_no"]');
    const volumeInput = document.querySelector('#edit-modal-body [name="volumeNo"], #edit-modal-body [name="volume_no"]');
    const regNoInput = document.querySelector('#edit-modal-body [name="regNo"], #edit-modal-body [name="registration_number"]');

    if (!serialInput || !pageInput || !volumeInput || !regNoInput) return;

    const serial = String(serialInput.value || '').trim() || '0';
    const page = String(pageInput.value || '').trim() || '0';
    const volume = String(volumeInput.value || '').trim() || '0';
    regNoInput.value = `${serial}/${page}/${volume}`;
  };

  const bindParticularsToRegNo = () => {
    const particulars = document.querySelectorAll('#edit-modal-body [name="serialNo"], #edit-modal-body [name="pageNo"], #edit-modal-body [name="volumeNo"], #edit-modal-body [name="serial_no"], #edit-modal-body [name="page_no"], #edit-modal-body [name="volume_no"]');
    particulars.forEach(el => {
      el.addEventListener('input', updateRegNoFromParticulars);
    });
    updateRegNoFromParticulars();
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const getModalSelectOptions = (source, record, fieldKey) => {
    const unique = new Set();

    const add = (v) => {
      const val = String(v ?? '').trim();
      if (val !== '') unique.add(val);
    };

    if (source === 'land_use') {
      dbLandUseOptions.forEach(add);
      add(record?.land_use);
    }

    if (source === 'lga') {
      const lgaSelect = document.getElementById('lga');
      if (lgaSelect) {
        Array.from(lgaSelect.options).forEach(opt => add(opt.value));
      }
      add(record?.lgsaOrCity);
      add(record?.lga);
      (searchResults || []).forEach(r => add(r?.lgsaOrCity || r?.lga));
    }

    if (source === 'district') {
      dbDistrictOptions.forEach(add);
      add(record?.districtName);
      add(record?.district);
    }

    if (source === 'transaction_type') {
      dbInstrumentTypeOptions.forEach(add);

      add(record?.transaction_type);
      add(record?.instrument_type);
      add(record?.title_type);
      (searchResults || []).forEach(r => {
        add(r?.transaction_type);
        add(r?.instrument_type);
        add(r?.title_type);
      });
    }

    // If no source options are available, still keep current value selectable
    if (unique.size === 0) {
      add(record?.[fieldKey]);
    }

    return Array.from(unique).sort((a, b) => a.localeCompare(b));
  };

  let editingRecord = { table: null, id: null };

  const getEditFieldSpanClass = (field) => {
    const fullWidthKeys = ['location', 'property_description', 'comments', 'remarks'];
    if (field.type === 'textarea' || fullWidthKeys.includes(field.key)) {
      return 'lg:col-span-12';
    }
    if (field.type === 'particular') {
      return 'lg:col-span-4';
    }
    return 'lg:col-span-6';
  };

  const openEditModal = async (table, id) => {
    editingRecord = { table, id };
    const modal = document.getElementById('edit-record-modal');
    const body = document.getElementById('edit-modal-body');
    body.innerHTML = '<div class="text-center py-8 text-gray-500">Loading...</div>';
    modal.classList.remove('hidden');

    const res = await cleanupAjax('/legal_search/get-record', { table, id });
    if (!res.success) {
      body.innerHTML = `<div class="text-center py-8 text-red-500">${res.message || 'Failed to load record.'}</div>`;
      return;
    }

    const record = res.data;
    const fields = editFieldDefs[table] || [];
    
    let html = '<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">';
    let inSection = false;
    fields.forEach(f => {
      let val = record[f.key] ?? '';
      // For the readonly fileno field, fall back to any available file number
      if (f.key === 'fileno' && !val) {
        val = record.mlsFNo || record.file_number || record.kangisFileNo || record.NewKANGISFileno || '';
      }
      // Section grouping (Registration Number)
      if (f.sectionStart) {
        html += `<div class="lg:col-span-12 border border-gray-200 rounded-lg p-4 mt-1">
          <label class="block text-xs font-semibold uppercase tracking-wide text-blue-700 mb-3">${f.sectionStart}</label>
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">`;
        inSection = true;
      }
      if (f.type === 'textarea') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <textarea name="${f.key}" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">${val}</textarea>
          </div>`;
      } else if (f.type === 'date') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="date" name="${f.key}" value="${val ? val.substring(0, 10) : ''}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
          </div>`;
      } else if (f.type === 'time') {
        // Stored times can arrive as "17:00", "17:00:00" or a full datetime —
        // pull out HH:MM so the native <input type="time"> accepts them.
        const timeMatch = String(val ?? '').match(/(\d{1,2}):(\d{2})/);
        const timeVal = timeMatch ? `${timeMatch[1].padStart(2, '0')}:${timeMatch[2]}` : '';
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="time" name="${f.key}" value="${timeVal}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
          </div>`;
      } else if (f.type === 'fileno') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <div class="flex gap-2">
              <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <button type="button" class="edit-fileno-picker inline-flex items-center px-2.5 py-2 text-xs font-medium border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100" data-target="${f.key}">Pick</button>
            </div>
          </div>`;
      } else if (f.type === 'select') {
        const options = getModalSelectOptions(f.optionSource, record, f.key);
        const optionsHtml = options.map(opt => {
          const selected = String(val) === String(opt) ? 'selected' : '';
          return `<option value="${escapeHtml(opt)}" ${selected}>${escapeHtml(opt)}</option>`;
        }).join('');

        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <select name="${f.key}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <option value="">Select ${escapeHtml(f.label)}</option>
              ${optionsHtml}
            </select>
          </div>`;
      } else {
        const readOnlyAttr = f.readonly ? 'readonly' : '';
        const readOnlyClass = f.readonly ? 'bg-gray-50' : '';
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" ${readOnlyAttr} class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black ${readOnlyClass}">
          </div>`;
      }
      // Close section grouping
      if (f.sectionEnd && inSection) {
        html += '</div></div>';
        inSection = false;
      }
    });
    html += '</div>';
    body.innerHTML = html;
    bindParticularsToRegNo();
  };

  const closeEditModal = () => {
    document.getElementById('edit-record-modal').classList.add('hidden');
    editingRecord = { table: null, id: null };
  };

  const saveEditModal = async () => {
    const body = document.getElementById('edit-modal-body');
    const fields = {};
    body.querySelectorAll('input, textarea, select').forEach(el => {
      if (el.name) fields[el.name] = el.value;
    });

    const saveBtn = document.getElementById('edit-modal-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const res = await cleanupAjax('/legal_search/update', {
      table: editingRecord.table,
      id: editingRecord.id,
      fields,
    });

    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';

    if (res.success) {
      closeEditModal();
      refreshAfterCleanup();
    } else {
      alert(res.message || 'Failed to save.');
    }
  };

  // Edit modal events
  document.getElementById('edit-modal-close')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-cancel')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-backdrop')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-save')?.addEventListener('click', saveEditModal);
