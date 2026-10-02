(function () {
  const cfg = window.PHS_ORG || {};
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const org = cfg.institution || {};
  let members = Array.isArray(cfg.members) ? cfg.members : [];
  let emailRows = [];
  let logoFile = null;
  let bannerFile = null;

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  // Normalise free-text names to Title Case so neither ALL CAPS nor all lowercase source data leaks into the UI.
  const titleCase = (v) => String(v ?? '').toLowerCase().replace(/\b\w/g, (m) => m.toUpperCase());

  // SweetAlert toast/dialog with a graceful fallback to the native alert().
  const notify = (message, icon = 'success') => {
    if (window.Swal) {
      return window.Swal.fire({ text: message, icon, confirmButtonColor: '#2563eb' });
    }
    window.alert(message);
    return Promise.resolve();
  };

  function postJson(url, method, payload) {
    return fetch(url, {
      method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: payload ? JSON.stringify(payload) : null,
    }).then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw data;
      return data;
    });
  }

  function roleLabel(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function currentSettings() {
    return {
      name: $('org-name')?.value || org.name || 'Your Organization',
      primaryColor: $('primary-color')?.value || org.primary_color || '#3b82f6',
      secondaryColor: $('secondary-color')?.value || org.secondary_color || '#7c3aed',
      logoUrl: cfg.assets?.logo || '',
      bannerUrl: cfg.assets?.banner || '',
    };
  }

  function applyHeader() {
    const settings = currentSettings();
    $('org-header-name').textContent = `Manage ${settings.name}`;
    $('mobile-org-header-name').textContent = `Manage ${settings.name}`;
    $('org-tokens-display').textContent = fmt(org.token_balance || 0);
    const sidebarName = $('sidebar-org-name');
    if (sidebarName) sidebarName.textContent = settings.name;
    const sidebarTokens = $('sidebar-org-tokens-display');
    if (sidebarTokens) sidebarTokens.textContent = fmt(org.token_balance || 0);
    const switcher = $('org-switcher');
    if (switcher) {
      switcher.innerHTML = `<option value="current">${esc(settings.name)}</option>`;
    }
    const logo = $('org-header-logo');
    if (logo) {
      logo.style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
      logo.innerHTML = settings.logoUrl
        ? `<img src="${settings.logoUrl}" class="w-full h-full object-contain" alt="Logo">`
        : '<i data-lucide="users" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>';
    }
  }

  function updateStats() {
    $('total-users').textContent = members.length;
    $('active-users').textContent = members.filter((m) => m.status === 'active').length;
    $('super-admin-count').textContent = members.filter((m) => m.user_type === 'super_admin').length;
    $('regular-user-count').textContent = members.filter((m) => m.user_type === 'regular_user').length;
  }

  function renderUsersTable() {
    const tbody = $('users-table-body');
    if (!tbody) return;
    tbody.innerHTML = members.map((user) => {
      const isAdmin = user.user_type === 'super_admin';
      const avail = Number(user.allocated_tokens || 0);
      const used = Number(user.tokens_used || 0);
      const allocated = avail + used; // initial allocation, minus nothing — used was carved from it
      const usedPct = allocated > 0 ? Math.min(Math.round((used / allocated) * 100), 100) : 0;
      const isSelf = Number(user.id) === Number(cfg.member?.id);

      const orgBalance = Number(cfg.institution?.token_balance || 0);
      const tokensCell = isAdmin
        ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 whitespace-nowrap">
             <i data-lucide="infinity" class="w-3.5 h-3.5"></i> Org pool · ${fmt(orgBalance)}
           </span>`
        : `<div class="min-w-[150px]">
             <div class="flex items-baseline justify-between mb-1">
               <span class="text-sm font-bold text-gray-900 dark:text-gray-100">${fmt(avail)}</span>
               <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">of ${fmt(allocated)} allocated</span>
             </div>
             <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
               <div class="h-full bg-blue-600 rounded-full" style="width:${usedPct}%"></div>
             </div>
             <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 mt-1">${fmt(used)} used</p>
           </div>`;

      return `<tr class="table-row hover:bg-gray-50/70 dark:hover:bg-gray-700/50 transition-colors">
        <td class="py-3 sm:py-4 px-4 sm:px-6">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-semibold text-sm shadow-sm">${esc((user.name || '?').charAt(0).toUpperCase())}</div>
            <div><p class="font-medium text-sm sm:text-base text-gray-900 dark:text-gray-100">${esc(titleCase(user.name))}</p><p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">${esc(user.email)}</p></div>
          </div>
        </td>
        <td class="py-3 sm:py-4 px-4 sm:px-6 text-sm text-gray-600 dark:text-gray-300"><span>${esc(user.email)}</span>${user.phone ? `<span class="block text-xs text-gray-400 dark:text-gray-500">${esc(user.phone)}</span>` : ''}</td>
        <td class="py-3 sm:py-4 px-4 sm:px-6 text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">${esc(user.job_title || '-')}</td>
        <td class="py-3 sm:py-4 px-4 sm:px-6"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap ${isAdmin ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'}"><span class="w-1.5 h-1.5 rounded-full ${isAdmin ? 'bg-purple-500' : 'bg-blue-500'}"></span>${esc(roleLabel(user.user_type))}</span></td>
        <td class="py-3 sm:py-4 px-4 sm:px-6">${tokensCell}</td>
        <td class="py-3 sm:py-4 px-4 sm:px-6"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${user.status === 'active' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'}"><span class="w-1.5 h-1.5 rounded-full ${user.status === 'active' ? 'bg-green-500' : 'bg-gray-400'}"></span>${esc(roleLabel(user.status))}</span></td>
        <td class="py-3 sm:py-4 px-4 sm:px-6 text-right">
          ${isSelf ? '<span class="text-xs text-gray-400 dark:text-gray-500">Current user</span>' : `<button class="inline-flex items-center gap-1 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors" data-delete-user="${user.id}"><i data-lucide="trash-2" class="w-4 h-4"></i>Remove</button>`}
        </td>
      </tr>`;
    }).join('');

    document.querySelectorAll('[data-delete-user]').forEach((button) => {
      button.addEventListener('click', () => deleteUser(button.dataset.deleteUser));
    });
    window.lucide?.createIcons();
  }

  async function deleteUser(id) {
    if (!confirm('Remove this PHS member?')) return;
    try {
      await postJson(`${cfg.routes.membersBase}/${id}`, 'DELETE');
      members = members.filter((m) => Number(m.id) !== Number(id));
      renderUsersTable();
      updateStats();
    } catch (error) {
      notify(error.message || 'Unable to remove member.', 'error');
    }
  }

  function renderActivityLog(rows) {
    const container = $('activity-log-container');
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = '<p class="text-sm text-gray-500">No activity recorded yet.</p>';
      return;
    }
    container.innerHTML = rows.map((item) => `<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 sm:p-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="flex items-start gap-3"><div class="w-9 h-9 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center"><i data-lucide="activity" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i></div><div><p class="font-medium text-gray-900 dark:text-gray-100 text-sm sm:text-base">${esc(item.description || '-')}</p><p class="text-xs text-gray-500 dark:text-gray-400">${esc(titleCase(item.member) || 'System')}${item.reference ? ' - ' + esc(item.reference) : ''}</p></div></div>
        <span class="text-xs text-gray-500 dark:text-gray-400">${esc(item.at || '')}</span>
      </div>
    </div>`).join('');
    window.lucide?.createIcons();
  }

  async function loadActivityLog() {
    const container = $('activity-log-container');
    if (!container || container.dataset.loaded === '1') return;
    try {
      const response = await fetch(cfg.routes.activity, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      container.dataset.loaded = '1';
      renderActivityLog(data.data || []);
    } catch (e) {
      container.innerHTML = '<p class="text-sm text-red-600">Unable to load activity.</p>';
    }
  }

  function showEmailCopy(row) {
    const fallbackText = row.body_text ? `<pre style="white-space:pre-wrap;max-height:55vh;overflow:auto;text-align:left;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">${esc(row.body_text)}</pre>` : '<p style="text-align:left;color:#64748b;">No message body available.</p>';
    const htmlCopy = row.body_html
      ? `<div style="max-height:55vh;overflow:auto;text-align:left;border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#fff;">${row.body_html}</div>`
      : fallbackText;

    if (window.Swal) {
      window.Swal.fire({
        title: esc(row.subject || 'Email copy'),
        html: `<div style="font-size:13px;color:#475569;text-align:left;margin-bottom:10px;">To: ${esc(row.recipient_email || '—')}<br>Sent: ${esc(row.sent_at || '—')}</div>${htmlCopy}`,
        width: '900px',
        confirmButtonColor: '#2563eb',
      });
      return;
    }

    window.alert(`${row.subject || 'Email copy'}\nTo: ${row.recipient_email || '—'}\nSent: ${row.sent_at || '—'}\n\n${row.body_text || 'No message body available.'}`);
  }

  function renderEmailHistory(rows) {
    const tbody = $('email-history-body');
    if (!tbody) return;
    emailRows = Array.isArray(rows) ? rows : [];

    if (!emailRows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">No email history yet.</td></tr>';
      return;
    }

    tbody.innerHTML = emailRows.map((row, index) => `
      <tr class="dark:hover:bg-gray-700/30 transition-colors">
        <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">${esc(row.sent_at || '—')}</td>
        <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">${esc(row.recipient_email || '—')}</td>
        <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-gray-100">${esc(row.subject || 'No subject')}</td>
        <td class="py-3 px-4 text-xs text-gray-500 dark:text-gray-400 font-mono">${esc(row.mailable || '—')}</td>
        <td class="py-3 px-4 text-right">
          <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" data-email-copy="${index}">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            View Copy
          </button>
        </td>
      </tr>
    `).join('');

    document.querySelectorAll('[data-email-copy]').forEach((button) => {
      button.addEventListener('click', () => {
        const index = Number(button.dataset.emailCopy);
        if (Number.isNaN(index) || !emailRows[index]) return;
        showEmailCopy(emailRows[index]);
      });
    });

    window.lucide?.createIcons();
  }

  async function loadEmailHistory(force = false) {
    const tbody = $('email-history-body');
    if (!tbody || !cfg.routes.emailHistory) return;
    if (!force && tbody.dataset.loaded === '1') return;

    tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">Loading email history...</td></tr>';
    try {
      const response = await fetch(cfg.routes.emailHistory, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      tbody.dataset.loaded = '1';
      renderEmailHistory(data.data || []);
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-red-500">Unable to load email history.</td></tr>';
    }
  }

  function loadBrandingValues() {
    $('org-name').value = org.name || '';
    if ($('org-username')) $('org-username').value = org.username || '';
    $('primary-color').value = org.primary_color || '#3b82f6';
    $('primary-color-hex').value = org.primary_color || '#3b82f6';
    $('secondary-color').value = org.secondary_color || '#7c3aed';
    $('secondary-color-hex').value = org.secondary_color || '#7c3aed';
    updateLogoPreview(cfg.assets?.logo || '');
    updateBannerPreview(cfg.assets?.banner || '');
    updatePreview();
  }

  function updateLogoPreview(url) {
    const area = $('logo-preview-area');
    const preview = $('logo-preview');
    const remove = $('remove-logo-btn');
    if (url) {
      area.innerHTML = `<img src="${url}" class="w-full h-full object-contain" alt="Logo">`;
      preview.innerHTML = `<img src="${url}" class="w-full h-full object-contain" alt="Logo">`;
      remove?.classList.remove('hidden');
    } else {
      area.innerHTML = '<i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>';
      preview.innerHTML = '<i data-lucide="building" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>';
      remove?.classList.add('hidden');
    }
    window.lucide?.createIcons();
  }

  function updateBannerPreview(url) {
    const area = $('banner-preview-area');
    const banner = $('org-banner');
    if (url) {
      area.innerHTML = `<img src="${url}" class="w-full h-full object-cover rounded-2xl" alt="Banner">`;
      banner.style.backgroundImage = `linear-gradient(90deg, rgba(15,23,42,.6), rgba(15,23,42,.15)), url(${url})`;
      banner.style.backgroundSize = 'cover';
      banner.style.backgroundPosition = 'center';
      $('banner-text').style.display = 'none';
    } else {
      area.innerHTML = '<i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>';
      $('banner-text').style.display = 'block';
    }
    window.lucide?.createIcons();
  }

  function updatePreview() {
    const settings = currentSettings();
    $('org-name-preview').textContent = settings.name;
    $('button-preview-primary').style.backgroundColor = settings.primaryColor;
    $('button-preview-secondary').style.borderColor = settings.primaryColor;
    $('button-preview-secondary').style.color = settings.primaryColor;
    $('logo-preview').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
    $('org-header-logo').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
    if (!cfg.assets?.banner && !bannerFile) $('org-banner').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
  }

  function setupTabs() {
    const setSidebarActive = (activeName) => {
      document.querySelectorAll('[data-sidebar-tab]').forEach((btn) => {
        const active = btn.dataset.sidebarTab === activeName;
        btn.classList.toggle('bg-blue-50', active);
        btn.classList.toggle('text-blue-700', active);
        btn.classList.toggle('font-semibold', active);
        btn.classList.toggle('text-gray-600', !active);
        btn.classList.toggle('font-medium', !active);
      });
    };

    // Panel names are read from the DOM rather than hardcoded. The old list had to
    // be edited by hand every time a tab was added, and a panel missing from it was
    // simply never hidden.
    const panelNames = () => Array.from(document.querySelectorAll('[id$="-tab"]'))
      .map((el) => el.id.replace(/-tab$/, ''));

    /**
     * Show one panel.
     *
     * Works whether or not the tab has a twin in the top tab strip. Sidebar-only
     * entries used to do nothing at all: the sidebar handler forwarded the click
     * to `[data-tab="<name>"]`, and when no such element existed the optional
     * chain swallowed it silently.
     */
    const activateTab = (name) => {
      if (!name || !$(name + '-tab')) return;

      panelNames().forEach((panel) => $(panel + '-tab')?.classList.add('hidden'));
      $(name + '-tab').classList.remove('hidden');

      document.querySelectorAll('[data-tab]').forEach((btn) => {
        const active = btn.dataset.tab === name;
        btn.classList.toggle('tab-active', active);
        btn.classList.toggle('text-gray-500', !active);
      });

      setSidebarActive(name);

      if (name === 'activity') loadActivityLog();
      if (name === 'emails') loadEmailHistory();
    };

    document.querySelectorAll('[data-tab]').forEach((tab) => {
      tab.addEventListener('click', () => activateTab(tab.dataset.tab));
    });

    document.querySelectorAll('[data-sidebar-tab]').forEach((button) => {
      button.addEventListener('click', () => activateTab(button.dataset.sidebarTab));
    });

    // Deep-link support: open a specific tab via ?tab=branding (or #branding).
    const requestedTab = new URLSearchParams(window.location.search).get('tab')
      || window.location.hash.replace('#', '');
    if (requestedTab) {
      activateTab(requestedTab);
    }
  }

  function setupColorInputs() {
    [['primary-color', 'primary-color-hex'], ['secondary-color', 'secondary-color-hex']].forEach(([colorId, hexId]) => {
      const color = $(colorId);
      const hex = $(hexId);
      color?.addEventListener('input', () => { hex.value = color.value; updatePreview(); });
      hex?.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) color.value = hex.value; updatePreview(); });
    });
    $('org-name')?.addEventListener('input', updatePreview);
  }

  function setupUploads() {
    $('upload-logo-btn')?.addEventListener('click', () => $('logo-upload').click());
    $('logo-dropzone')?.addEventListener('click', (event) => { if (event.target.id !== 'remove-logo-btn') $('logo-upload').click(); });
    $('logo-upload')?.addEventListener('change', (event) => {
      logoFile = event.target.files[0] || null;
      if (!logoFile) return;
      const reader = new FileReader();
      reader.onload = (e) => updateLogoPreview(e.target.result);
      reader.readAsDataURL(logoFile);
    });
    $('remove-logo-btn')?.addEventListener('click', (event) => { event.stopPropagation(); logoFile = null; cfg.assets.logo = ''; updateLogoPreview(''); });

    $('upload-banner-btn')?.addEventListener('click', () => $('banner-upload').click());
    $('banner-dropzone')?.addEventListener('click', () => $('banner-upload').click());
    $('banner-upload')?.addEventListener('change', (event) => {
      bannerFile = event.target.files[0] || null;
      if (!bannerFile) return;
      const reader = new FileReader();
      reader.onload = (e) => updateBannerPreview(e.target.result);
      reader.readAsDataURL(bannerFile);
    });
  }

  async function saveSettings() {
    const form = new FormData();
    form.append('name', $('org-name').value);
    if ($('org-username')?.value) form.append('username', $('org-username').value);
    form.append('primary_color', $('primary-color').value);
    form.append('secondary_color', $('secondary-color').value);
    if (logoFile) form.append('logo', logoFile);
    if (bannerFile) form.append('banner', bannerFile);
    try {
      const response = await fetch(cfg.routes.branding, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: form });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw data;
      notify(data.message || 'Branding updated.', 'success');
      org.name = $('org-name').value;
      if (data.data?.username !== undefined) {
        org.username = data.data.username;
        if ($('org-username')) $('org-username').value = data.data.username;
      }
      org.primary_color = $('primary-color').value;
      org.secondary_color = $('secondary-color').value;
      applyHeader();
      updatePreview();
    } catch (error) {
      notify(error.message || Object.values(error.errors || {})[0]?.[0] || 'Unable to save branding.', 'error');
    }
  }

  async function addNewUser() {
    const password = $('temp-password').value;
    if (password !== $('confirm-password').value) return notify('Passwords do not match.', 'error');
    const accessRoles = Array.from(document.querySelectorAll('input[name="access-role[]"]:checked')).map(el => el.value);
    if (accessRoles.length === 0) return notify('Please select at least one Access Role.', 'error');
    const payload = {
      name: $('user-fullname').value.trim(),
      email: $('user-email').value.trim(),
      phone: $('user-phone') ? $('user-phone').value.trim() : '',
      password,
      job_title: $('job-title').value.trim(),
      department: $('department').value.trim(),
      user_type: document.querySelector('input[name="user-type"]:checked')?.value || 'regular_user',
      access_role: accessRoles,
      token_allocation: parseInt($('token-allocation')?.value, 10) || 0,
    };
    try {
      const data = await postJson(cfg.routes.membersStore, 'POST', payload);
      members.push(data.data);
      renderUsersTable();
      updateStats();
      closeModal();
      notify(data.message || 'Member added.', 'success');
    } catch (error) {
      notify(error.message || Object.values(error.errors || {})[0]?.[0] || 'Unable to add member.', 'error');
    }
  }

  function closeModal() {
    $('add-user-modal').classList.add('hidden');
    ['user-fullname', 'user-email', 'user-phone', 'job-title', 'department', 'temp-password', 'confirm-password'].forEach((id) => { if ($(id)) $(id).value = ''; });
    $('token-allocation').value = '250';
    // Reset access-role checkboxes (first checked, rest unchecked)
    document.querySelectorAll('input[name="access-role[]"]').forEach((cb, i) => { cb.checked = i === 0; });
    // Reset user-type to regular_user
    const regularInput = document.querySelector('input[name="user-type"][value="regular_user"]');
    if (regularInput) regularInput.checked = true;
  }

  function openAddMemberModal() {
    const limits = cfg.limits || {};
    const currentMembers = members.length;

    // Block opening if total member limit reached
    if (limits.maxMembers != null && currentMembers >= limits.maxMembers) {
      notify(`Your subscription allows a maximum of ${limits.maxMembers} team members. Please upgrade your plan to add more.`, 'error');
      return;
    }

    $('add-user-modal').classList.remove('hidden');
  }

  function setupModal() {
    $('add-user-btn')?.addEventListener('click', openAddMemberModal);
    $('close-modal-btn')?.addEventListener('click', closeModal);
    $('cancel-add-user')?.addEventListener('click', closeModal);
    $('confirm-add-user')?.addEventListener('click', addNewUser);
  }

  let selectedPackage = null;
  let selectedPkgData = { tokens: 0, price: 0 };

  function setupTokenPurchase() {
    const modal = $('token-modal');
    if (!modal) return;
    const qty = $('topup-qty');

    const clampQty = () => Math.max(1, Math.min(10, parseInt(qty?.value, 10) || 1));
    function recalc() {
      const n = clampQty();
      const tokens = n * (selectedPkgData.tokens || 0);
      const total = n * (selectedPkgData.price || 0);
      if ($('topup-tokens')) $('topup-tokens').textContent = tokens.toLocaleString();
      if ($('topup-total')) $('topup-total').textContent = '₦' + total.toLocaleString();
    }

    const cards = document.querySelectorAll('.package-card');
    cards.forEach((card) => {
      card.addEventListener('click', () => {
        cards.forEach((el) => el.classList.remove('border-blue-500', 'ring-2', 'ring-blue-500'));
        card.classList.add('border-blue-500', 'ring-2', 'ring-blue-500');
        selectedPackage = card.dataset.key;
        selectedPkgData = { tokens: Number(card.dataset.tokens) || 0, price: Number(card.dataset.price) || 0 };
        recalc();
      });
    });
    cards[0]?.click();

    qty?.addEventListener('input', recalc);
    $('topup-minus')?.addEventListener('click', () => { if (qty) { qty.value = Math.max(1, clampQty() - 1); recalc(); } });
    $('topup-plus')?.addEventListener('click', () => { if (qty) { qty.value = Math.min(10, clampQty() + 1); recalc(); } });

    $('buy-tokens-btn')?.addEventListener('click', () => { modal.classList.remove('hidden'); });
    $('close-token-modal')?.addEventListener('click', () => modal.classList.add('hidden'));
    $('cancel-token-purchase')?.addEventListener('click', () => modal.classList.add('hidden'));

    // "Pay with Paystack" — directly initiate without an extra method-selection step.
    $('topup-continue')?.addEventListener('click', async () => {
      if (!selectedPackage) return notify('Please select a bundle first.', 'warning');
      const btn = $('topup-continue');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span style="opacity:.6">Redirecting to Paystack…</span>';
      try {
        const data = await postJson(cfg.routes.topupPaystackInitiate, 'POST', {
          package: selectedPackage,
          bundle_count: clampQty(),
        });
        if (data.authorization_url) {
          window.location.href = data.authorization_url;
        } else {
          notify(data.message || 'Could not initiate payment.', 'error');
          btn.disabled = false;
          btn.innerHTML = original;
        }
      } catch (error) {
        notify(error.message || 'Payment initiation failed.', 'error');
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  }

  function ledgerTypeLabel(type) {
    return ({
      purchase: 'Subscription',
      topup: 'Top-up',
      search_debit: 'Search',
      adjustment: 'Adjustment',
      bonus: 'Bonus',
    })[type] || (type ? type.replace(/_/g, ' ') : '—');
  }

  async function loadWalletLedger() {
    const tbody = $('wallet-ledger-body');
    if (!tbody || !cfg.routes.transactions) return;
    try {
      const res = await fetch(cfg.routes.transactions, { headers: { Accept: 'application/json' } });
      const data = await res.json();
      const rows = data.data || [];
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-gray-400">No wallet activity yet.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map((t) => {
        const n = Number(t.tokens || 0);
        const credit = n >= 0;
        const date = t.created_at ? new Date(t.created_at).toLocaleString() : '—';
        const statusCls = t.status === 'completed' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : (t.status === 'pending' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300');
        return `<tr class="dark:hover:bg-gray-700/30 transition-colors">
          <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">${esc(date)}</td>
          <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-gray-100">${esc(ledgerTypeLabel(t.type))}</td>
          <td class="py-3 px-4 text-sm text-right font-semibold ${credit ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">${credit ? '+' : ''}${fmt(n)}</td>
          <td class="py-3 px-4 text-sm text-right text-gray-700 dark:text-gray-300">${t.balance_after != null ? fmt(t.balance_after) : '—'}</td>
          <td class="py-3 px-4 text-sm"><span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${statusCls}">${esc((t.status || '—').charAt(0).toUpperCase() + (t.status || '').slice(1))}</span></td>
          <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400 font-mono text-xs">${esc(t.reference_no || '—')}</td>
        </tr>`;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-red-500">Unable to load wallet activity.</td></tr>';
    }
  }

  function setupMisc() {
    $('close-user-management')?.addEventListener('click', () => { window.location.href = cfg.routes.dashboard; });
    $('sidebar-org-logout-btn')?.addEventListener('click', () => {
      fetch(cfg.routes.logout, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'text/html' } })
        .finally(() => { window.location.href = '/phs'; });
    });
    $('save-settings')?.addEventListener('click', saveSettings);
    $('reset-settings')?.addEventListener('click', () => { loadBrandingValues(); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyHeader();
    updateStats();
    renderUsersTable();
    renderActivityLog([]);
    loadBrandingValues();
    setupTabs();
    setupColorInputs();
    setupUploads();
    setupModal();
    setupTokenPurchase();
    setupMisc();
    loadWalletLedger();
    $('refresh-ledger')?.addEventListener('click', loadWalletLedger);
    $('refresh-email-history')?.addEventListener('click', () => loadEmailHistory(true));
    window.lucide?.createIcons();
  });
})();
