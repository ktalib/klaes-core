/* ============================================================================
   ALAES VFC — Valuation for Compensation, field entry (static clone)
   ----------------------------------------------------------------------------
   A faithful clone of resources/views/valuation_compensations/mobile.blade.php
   with every server call replaced by the DEMO_LOOKUP fixtures below. Where the
   live app hits an endpoint the function keeps its name and is marked  // API →

   Saving does not post anywhere: submitForm() validates exactly as the live app
   does, then reports the record it would have written.
   ========================================================================== */

/* ══════════════════════════════════════════════════════════════════════════
   1. DEMO DATA  — stands in for /valuation-compensations/mobile/lookup
   ══════════════════════════════════════════════════════════════════════════ */

const SESSION_KEY = 'alaes-demo-session';

// Bank marks are generated rather than fetched, so the page works offline.
function bankLogo(initials, bg) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="12" fill="${bg}"/>
    <text x="32" y="41" font-family="Arial,Helvetica,sans-serif" font-size="24" font-weight="bold"
          fill="#ffffff" text-anchor="middle">${initials}</text>
  </svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

const DEMO_LOOKUP = {
  success: true,

  // ── Projects, their sub-projects and reference numbers ──────────────────
  projects: [
    {
      id: 1,
      name: 'Umuahia Ring Road Expansion',
      code: 'PRJ-UM-001',
      fileno: 'LUAC/AB/30117/UM',
      total_items: 240,
      valuations_count: 86,
      workers_count: 4,
      our_reference: 'ALAES/VFC/2026/0117',
      your_reference: 'ABSG/WKS/RR/112',
      district: 'Umuahia Urban',
      lga: 'UMUAHIA NORTH',
      sub_projects: [
        { id: 11, name: 'Phase 1 — Bende Road Corridor', code: 'SP-UM-01' },
        { id: 12, name: 'Phase 2 — Mission Hill Junction', code: 'SP-UM-02' },
        { id: 13, name: 'Phase 3 — Olokoro Approach',     code: 'SP-UM-03' },
      ],
    },
    {
      id: 2,
      name: 'Aba Industrial Layout Acquisition',
      code: 'PRJ-ABA-004',
      fileno: 'LABA/38402',
      total_items: 512,
      valuations_count: 203,
      workers_count: 6,
      our_reference: 'ALAES/VFC/2026/0402',
      your_reference: 'ABSG/IND/AL/338',
      district: 'Ariaria',
      lga: 'ABA NORTH',
      sub_projects: [
        { id: 21, name: 'Block A — Ariaria Fringe',   code: 'SP-ABA-A' },
        { id: 22, name: 'Block B — Ogbor Hill Spur',  code: 'SP-ABA-B' },
      ],
    },
    {
      id: 3,
      name: 'Ohafia Water Scheme Right of Way',
      code: 'PRJ-OH-002',
      fileno: 'LUM/OH/41955',
      total_items: 96,
      valuations_count: 31,
      workers_count: 3,
      our_reference: 'ALAES/VFC/2026/0219',
      your_reference: 'ABSG/WTR/OH/077',
      district: 'Ohafia Central',
      lga: 'OHAFIA',
      sub_projects: [
        { id: 31, name: 'Pipeline Corridor A', code: 'SP-OH-A' },
        { id: 32, name: 'Reservoir Site',      code: 'SP-OH-B' },
      ],
    },
    {
      id: 4,
      name: 'Isiala Ngwa Rural Access Roads',
      code: 'PRJ-ING-007',
      fileno: 'LUM/34410',
      total_items: 148,
      valuations_count: 12,
      workers_count: 2,
      our_reference: 'ALAES/VFC/2026/0511',
      your_reference: 'ABSG/RAP/ING/019',
      district: 'Okpuala Ngwa',
      lga: 'ISIALA NGWA NORTH',
      sub_projects: [
        { id: 41, name: 'Lot 1 — Okpuala Spur', code: 'SP-ING-1' },
        { id: 42, name: 'Lot 2 — Mbawsi Link',  code: 'SP-ING-2' },
      ],
    },
  ],

  // ── Workers, keyed by project id ────────────────────────────────────────
  workers: {
    1: [
      { id: 101, worker_code: 'VFC-UM-014', name: 'Obinna Kalu' },
      { id: 102, worker_code: 'VFC-UM-021', name: 'Chioma Nwankwo' },
      { id: 103, worker_code: 'VFC-UM-033', name: 'Uche Agwu' },
      { id: 104, worker_code: 'VFC-UM-040', name: 'Adaeze Onyeka' },
    ],
    2: [
      { id: 201, worker_code: 'VFC-ABA-002', name: 'Emeka Udo' },
      { id: 202, worker_code: 'VFC-ABA-009', name: 'Ngozi Eze' },
      { id: 203, worker_code: 'VFC-ABA-015', name: 'Ikechukwu Nwoke' },
      { id: 204, worker_code: 'VFC-ABA-018', name: 'Chinelo Abara' },
      { id: 205, worker_code: 'VFC-ABA-023', name: 'Samuel Ekwueme' },
      { id: 206, worker_code: 'VFC-ABA-031', name: 'Blessing Orji' },
    ],
    3: [
      { id: 301, worker_code: 'VFC-OH-003', name: 'Kalu Agwu' },
      { id: 302, worker_code: 'VFC-OH-007', name: 'Nneka Okereke' },
      { id: 303, worker_code: 'VFC-OH-012', name: 'Chidi Uduma' },
    ],
    4: [
      { id: 401, worker_code: 'VFC-ING-001', name: 'Uzoma Nwachukwu' },
      { id: 402, worker_code: 'VFC-ING-005', name: 'Amarachi Ibe' },
    ],
  },

  buildingTypes: [
    { name: 'Bungalow' },
    { name: 'Storey Building' },
    { name: 'Duplex' },
    { name: 'Block of Flats' },
    { name: 'Mud House' },
    { name: 'Shop / Lock-up Store' },
    { name: 'Warehouse' },
    { name: 'Church / Mosque' },
    { name: 'Uncompleted Structure' },
    { name: 'Other' },
  ],

  // type 'CompletionStage' feeds the stage dropdown; type 'Other' feeds the
  // compensated-items grid. Same shape the live lookup returns.
  valuationItems: [
    { name: 'Foundation Level',   type: 'CompletionStage' },
    { name: 'Lintel Level',       type: 'CompletionStage' },
    { name: 'Roofing Level',      type: 'CompletionStage' },
    { name: 'Plastered',          type: 'CompletionStage' },
    { name: 'Fully Completed',    type: 'CompletionStage' },
    { name: 'Other',              type: 'CompletionStage' },

    { name: 'Cornstalk Fence',          type: 'Other' },
    { name: 'Sandcare Wall',            type: 'Other' },
    { name: 'Wire mesh',                type: 'Other' },
    { name: 'Mud Wall',                 type: 'Other' },
    { name: 'Pavement',                 type: 'Other' },
    { name: 'Mass concrete pavement',   type: 'Other' },
    { name: 'Interlock Court yard',     type: 'Other' },
    { name: 'DPC',                      type: 'Other' },
    { name: 'Fish pond',                type: 'Other' },
    { name: 'Borehole',                 type: 'Other' },
    { name: 'Septic Tank',              type: 'Other' },
    { name: 'Water Tank / Stand',       type: 'Other' },
    { name: 'Economic Trees',           type: 'Other' },
    { name: 'Farm Crops',               type: 'Other' },
    { name: 'Gate / Gate House',        type: 'Other' },
    { name: 'Others',                   type: 'Other' },
  ],

  streets: [
    { name: 'Bende Road' },
    { name: 'Aba Road' },
    { name: 'Library Avenue' },
    { name: 'Finbarrs Road' },
    { name: 'Azikiwe Road' },
    { name: 'Faulks Road' },
    { name: 'Ikot Ekpene Road' },
    { name: 'Okigwe Road' },
    { name: 'Ngwa Road' },
    { name: 'Umule Road' },
    { name: 'Mission Hill Road' },
    { name: 'World Bank Road' },
  ],

  districts: [
    { name: 'Umuahia Urban' },
    { name: 'Olokoro' },
    { name: 'Ubakala' },
    { name: 'Ariaria' },
    { name: 'Ogbor Hill' },
    { name: 'Osisioma' },
    { name: 'Ohafia Central' },
    { name: 'Abiriba' },
    { name: 'Okpuala Ngwa' },
    { name: 'Umuobiakwa' },
  ],

  // The 17 LGAs of Abia State.
  lgas: [
    { LGAName: 'ABA NORTH' }, { LGAName: 'ABA SOUTH' }, { LGAName: 'AROCHUKWU' },
    { LGAName: 'BENDE' }, { LGAName: 'IKWUANO' }, { LGAName: 'ISIALA NGWA NORTH' },
    { LGAName: 'ISIALA NGWA SOUTH' }, { LGAName: 'ISUIKWUATO' }, { LGAName: 'OBI NGWA' },
    { LGAName: 'OHAFIA' }, { LGAName: 'OSISIOMA NGWA' }, { LGAName: 'UGWUNAGBO' },
    { LGAName: 'UKWA EAST' }, { LGAName: 'UKWA WEST' }, { LGAName: 'UMUAHIA NORTH' },
    { LGAName: 'UMUAHIA SOUTH' }, { LGAName: 'UMU NNEOCHI' },
  ],

  banks: [
    { name: 'Access Bank',          code: '044', logo: bankLogo('A',  '#e85b25') },
    { name: 'Ecobank Nigeria',      code: '050', logo: bankLogo('E',  '#00537f') },
    { name: 'Fidelity Bank',        code: '070', logo: bankLogo('F',  '#2b3990') },
    { name: 'First Bank of Nigeria',code: '011', logo: bankLogo('FB', '#00549f') },
    { name: 'First City Monument Bank', code: '214', logo: bankLogo('FC', '#6b2d8b') },
    { name: 'Guaranty Trust Bank',  code: '058', logo: bankLogo('GT', '#dd4c27') },
    { name: 'Keystone Bank',        code: '082', logo: bankLogo('K',  '#014c8c') },
    { name: 'Polaris Bank',         code: '076', logo: bankLogo('P',  '#7e3f98') },
    { name: 'Providus Bank',        code: '101', logo: bankLogo('PR', '#b8860b') },
    { name: 'Stanbic IBTC Bank',    code: '221', logo: bankLogo('S',  '#00539b') },
    { name: 'Sterling Bank',        code: '232', logo: bankLogo('ST', '#d31145') },
    { name: 'Union Bank of Nigeria',code: '032', logo: bankLogo('U',  '#00a9e0') },
    { name: 'United Bank for Africa', code: '033', logo: bankLogo('UB', '#d3222a') },
    { name: 'Unity Bank',           code: '215', logo: bankLogo('UN', '#88a94b') },
    { name: 'Wema Bank',            code: '035', logo: bankLogo('W',  '#76237a') },
    { name: 'Zenith Bank',          code: '057', logo: bankLogo('Z',  '#e60000') },
    { name: 'Kuda Microfinance Bank', code: '090267', logo: bankLogo('KU', '#40196d') },
    { name: 'Opay Digital Services', code: '999992', logo: bankLogo('OP', '#1a8f5f') },
    { name: 'Moniepoint MFB',       code: '090405', logo: bankLogo('MP', '#0357ee') },
    { name: 'PalmPay',              code: '999991', logo: bankLogo('PA', '#7642f0') },
  ],
};

/* ══════════════════════════════════════════════════════════════════════════
   2. STATE
   ══════════════════════════════════════════════════════════════════════════ */
let lookupData = null;
let selectedItems = [];
let allBanks = [];
let isInitializing = true;

/* ══════════════════════════════════════════════════════════════════════════
   3. LOOKUP LOAD                        // API → GET /mobile/lookup
   ══════════════════════════════════════════════════════════════════════════ */
async function loadLookupData() {
  try {
    const data = DEMO_LOOKUP;
    lookupData = data;

    // Populate Projects
    const pSel = document.getElementById('projectSelect');
    pSel.innerHTML = '<option value="">Select Project</option>';
    data.projects.forEach(p => {
      const displayCode = p.fileno || p.code;
      pSel.innerHTML += `<option value="${p.id}">${p.name} (${displayCode})</option>`;
    });

    // Populate Streets
    const stSel = document.getElementById('streetSelect');
    stSel.innerHTML = '<option value="">Select Street</option>';
    data.streets.forEach(s => { stSel.innerHTML += `<option value="${s.name}">${s.name}</option>`; });
    stSel.innerHTML += `<option value="Other">Other</option>`;

    // Populate LGAs
    const lgaSel = document.getElementById('lgaSelect');
    lgaSel.innerHTML = '<option value="">Select LGA</option>';
    data.lgas.forEach(l => { lgaSel.innerHTML += `<option value="${l.LGAName}">${l.LGAName}</option>`; });

    // Populate Districts
    const dSel = document.getElementById('districtSelect');
    dSel.innerHTML = '<option value="">Select District</option>';
    data.districts.forEach(d => { dSel.innerHTML += `<option value="${d.name}">${d.name}</option>`; });
    dSel.innerHTML += `<option value="Other">Other</option>`;

    allBanks = data.banks || [];

    // Populate Valuation Items and the building dropdowns
    const itemGrid = document.getElementById('valuationItemsGrid');
    const buildingSel = document.querySelector('.building-type-mobile-select');
    const buildingStageSel = document.querySelector('.building-stage-mobile-select');
    itemGrid.innerHTML = '';
    if (buildingSel) buildingSel.innerHTML = '<option value="">Select Type</option>';
    if (buildingStageSel) buildingStageSel.innerHTML = '<option value="">Select Stage</option>';

    // Which items are measured how — drives the calculator inside each item.
    const linearItems = ['Cornstalk Fence', 'Sandcare Wall', 'Wire mesh', 'Mud Wall'];
    const volumeItems = ['Pavement', 'Mass concrete pavement', 'Interlock Court yard', 'DPC', 'Fish pond'];

    const sections = {
      linear: { title: 'Linear Measurements (Fencing/Walls)', sub: 'L + B × Rate', icon: 'maximize-2', items: [] },
      volume: { title: 'Volume Measurements (Pavement/DPC)',  sub: 'L × B × Rate', icon: 'box',        items: [] },
      other:  { title: 'Other Structures',                    sub: 'Standard & Area Based', icon: 'archive', items: [] },
    };

    data.buildingTypes.forEach(type => {
      if (buildingSel) buildingSel.innerHTML += `<option value="${type.name}">${type.name}</option>`;
    });
    data.valuationItems.filter(i => i.type === 'CompletionStage').forEach(stage => {
      if (buildingStageSel) buildingStageSel.innerHTML += `<option value="${stage.name}">${stage.name}</option>`;
    });

    data.valuationItems.forEach(item => {
      if (item.type === 'CompletionStage') return;
      if (linearItems.includes(item.name)) sections.linear.items.push(item);
      else if (volumeItems.includes(item.name)) sections.volume.items.push(item);
      else sections.other.items.push(item);
    });

    Object.values(sections).forEach(sec => {
      if (!sec.items.length) return;
      itemGrid.innerHTML += `
        <div class="items-section-header">
          <h3><i data-lucide="${sec.icon}" style="width:10px;"></i> ${sec.title}</h3>
          <p>${sec.sub}</p>
        </div>`;
      sec.items.forEach(item => {
        let type = 'standard';
        if (linearItems.includes(item.name)) type = 'linear';
        else if (volumeItems.includes(item.name)) type = 'volume';

        itemGrid.innerHTML += `
          <div class="check-item ${item.name === 'Others' || item.name === 'Other' ? 'special-other' : ''}" data-val="${item.name}" data-type="${type}">
            <div class="check-box"><i data-lucide="check" style="width:10px;height:10px;"></i></div>
            <span class="check-label">${item.name}</span>
          </div>
          <div class="calc-box" id="calc-${item.name.replace(/\s+/g, '-')}">
            <div class="calc-grid">
              ${type === 'linear' ? `
                <div class="calc-field" style="grid-column: 1/-1;">
                  <label>Dimensions (Sum: L + B + ... N)</label>
                  <input type="text" class="calc-inp d-val" placeholder="e.g. 15.5 + 10">
                </div>
                <div class="calc-field" style="grid-column: 1/-1;">
                  <div class="calc-readout">
                    <span class="calc-readout-label">Total Length (L+B)</span>
                    <span class="l-total-display calc-readout-val">0.00m</span>
                  </div>
                  <input type="hidden" class="l-val" value="0">
                </div>` : ''}
              ${type === 'volume' ? `
                <div class="calc-field">
                  <label>Length (L)</label>
                  <input type="number" class="calc-inp l-val" placeholder="0.00" step="0.01">
                </div>
                <div class="calc-field">
                  <label>Breadth (B)</label>
                  <input type="number" class="calc-inp w-val" placeholder="0.00" step="0.01">
                </div>
                <div class="calc-field" style="grid-column: 1/-1;">
                  <div class="calc-readout">
                    <span class="calc-readout-label">Area Covered (L×B)</span>
                    <span class="v-total-display calc-readout-val">0.00m²</span>
                  </div>
                </div>` : ''}
              ${type === 'standard' ? `
                <div class="calc-field" style="grid-column: 1/-1; display: none;">
                  <label>Quantity / Area</label>
                  <input type="number" class="calc-inp q-val" value="1" step="0.01">
                </div>` : ''}
              <div class="calc-field" style="grid-column: 1/-1;">
                <label>${type === 'standard' ? 'Amount (₦)' : (type === 'linear' ? 'Rate (₦/m)' : 'Rate (₦/m³)')}</label>
                <input type="number" class="calc-inp r-val" placeholder="0.00" step="0.01">
              </div>
              <div class="calc-result">
                <span class="calc-total-label">SUB AMOUNT</span>
                <span class="calc-total-val">₦0.00</span>
              </div>
            </div>
          </div>`;
      });
    });

    initCheckItems();
    initCalcListeners();

    isInitializing = false;   // system is now ready for calculations
    if (window.lucide) window.lucide.createIcons();
  } catch (err) {
    console.error('Initialization Error:', err);
    isInitializing = false;
    showToast('Failed to load system data. Check connection.', 'danger');
  }
}

/* ══════════════════════════════════════════════════════════════════════════
   4. BANK SEARCH
   ══════════════════════════════════════════════════════════════════════════ */
const bankSearch = document.getElementById('bankSearch');
const bankResults = document.getElementById('bankResults');
const selectedBankLogo = document.getElementById('selectedBankLogo');
const bankNameVal = document.getElementById('bankNameVal');

bankSearch.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  if (!q) { bankResults.classList.add('hidden'); return; }

  const matches = allBanks.filter(b => b.name.toLowerCase().includes(q));
  if (!matches.length) {
    bankResults.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--text-dim);">No banks found</div>';
  } else {
    bankResults.innerHTML = matches.map((b, i) => `
      <div class="bank-item" data-bank="${i}">
        <div class="selected-logo-wrap" style="width:32px;height:32px;">
          <img src="${b.logo}" alt="" class="bank-logo-img">
        </div>
        <span>${b.name}</span>
      </div>`).join('');
    // Bind by index rather than inlining the name — a bank with an apostrophe
    // would break an inline onclick string.
    bankResults.querySelectorAll('.bank-item').forEach(el => {
      el.onclick = () => {
        const b = matches[Number(el.dataset.bank)];
        selectBank(b.name, b.logo);
      };
    });
  }
  bankResults.classList.remove('hidden');
});

function selectBank(title, logo) {
  bankSearch.value = title;
  bankNameVal.value = title;
  bankResults.classList.add('hidden');
  selectedBankLogo.querySelector('img').src = logo;
  selectedBankLogo.classList.remove('hidden');
  bankSearch.style.paddingLeft = '48px';
  bankSearch.style.fontWeight = '700';
  bankSearch.style.color = '#fff';
}

// Close bank dropdown on click outside
document.addEventListener('click', e => {
  if (!e.target.closest('.inp-wrap')) bankResults.classList.add('hidden');
});

/* ══════════════════════════════════════════════════════════════════════════
   5. COMPENSATED ITEMS + CALCULATOR
   ══════════════════════════════════════════════════════════════════════════ */
function initCheckItems() {
  document.querySelectorAll('.check-item').forEach(item => {
    item.onclick = function () {
      this.classList.toggle('active');
      const val = this.dataset.val;
      const calcBox = document.getElementById(`calc-${val.replace(/\s+/g, '-')}`);

      if (this.classList.contains('active')) {
        if (!selectedItems.includes(val)) selectedItems.push(val);
        if (val === 'Others' || val === 'Other') {
          document.getElementById('compItemsOtherText').classList.remove('hidden');
        }
        if (calcBox) calcBox.style.display = 'block';
      } else {
        selectedItems = selectedItems.filter(i => i !== val);
        if (val === 'Others' || val === 'Other') {
          document.getElementById('compItemsOtherText').classList.add('hidden');
        }
        if (calcBox) {
          calcBox.style.display = 'none';
          // Reset values in calc box
          calcBox.querySelectorAll('input').forEach(inp => { inp.value = ''; });
          calcBox.querySelector('.calc-total-val').textContent = '₦0.00';
          calcBox.dataset.total = 0;
        }
      }

      updateItemsValue();
      calculateAllCompensation();
    };
  });
}

function initCalcListeners() {
  document.querySelectorAll('.calc-inp').forEach(inp => {
    inp.oninput = function () {
      const box = this.closest('.calc-box');
      if (!box) return;

      const type = box.previousElementSibling.dataset.type;
      const r = parseFloat(box.querySelector('.r-val')?.value) || 0;

      let subTotal = 0;
      if (type === 'linear') {
        // "15.5 + 10 + 8" → the run of fencing, summed.
        const dStr = box.querySelector('.d-val')?.value || '';
        const totalL = dStr.split('+')
          .map(s => parseFloat(s.trim()))
          .filter(n => !isNaN(n))
          .reduce((sum, n) => sum + n, 0);

        box.querySelector('.l-val').value = totalL;
        box.querySelector('.l-total-display').textContent = totalL.toFixed(2) + 'm';
        subTotal = totalL * r;
      } else if (type === 'volume') {
        const l = parseFloat(box.querySelector('.l-val')?.value) || 0;
        const w = parseFloat(box.querySelector('.w-val')?.value) || 0;
        const area = l * w;
        box.querySelector('.v-total-display').textContent = area.toFixed(2) + 'm²';
        subTotal = area * r;
      } else {
        const q = parseFloat(box.querySelector('.q-val')?.value) || 1;
        subTotal = q * r;
      }

      box.querySelector('.calc-total-val').textContent = '₦' + subTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
      box.dataset.total = subTotal;

      updateItemsValue();
      calculateAllCompensation();
    };
  });
}

function updateItemsValue() {
  const finalItems = [];
  document.querySelectorAll('.check-item.active').forEach(item => {
    const name = item.dataset.val;
    const box = document.getElementById(`calc-${name.replace(/\s+/g, '-')}`);
    if (box && box.dataset.total && parseFloat(box.dataset.total) > 0) {
      finalItems.push(`${name} (₦${parseFloat(box.dataset.total).toLocaleString()})`);
    } else {
      finalItems.push(name);
    }
  });
  document.getElementById('compItemsVal').value = finalItems.join(', ');
}

function calculateBuildingRowMobile(row) {
  const length = parseFloat(row.querySelector('.building-mobile-length').value) || 0;
  const breadth = parseFloat(row.querySelector('.building-mobile-breadth').value) || 0;
  const floors = parseFloat(row.querySelector('.building-mobile-floors')?.value) || 1;

  // If L and B are provided, Area Covered = footprint (L×B) × floors (total built-up area)
  if (length > 0 && breadth > 0) {
    row.querySelector('.building-mobile-area').value = (length * breadth * floors).toFixed(2);
  }

  const area = parseFloat(row.querySelector('.building-mobile-area').value) || 0;
  const rate = parseFloat(row.querySelector('.building-mobile-rate').value) || 0;
  // Floors are already baked into Area Covered, so Amount = Area × Rate.
  row.querySelector('.building-mobile-comp').value = (area * rate).toFixed(2);
}

function calculateAllCompensation() {
  if (isInitializing) return;   // prevent loops during startup

  let totalLength = 0, totalBreadth = 0, totalArea = 0, totalBuildingComp = 0;
  let rateSum = 0, rateCount = 0, weightedRateSum = 0;

  document.querySelectorAll('.building-type-mobile-row').forEach(row => {
    const length  = parseFloat(row.querySelector('.building-mobile-length')?.value) || 0;
    const breadth = parseFloat(row.querySelector('.building-mobile-breadth')?.value) || 0;
    const area    = parseFloat(row.querySelector('.building-mobile-area')?.value) || 0;
    const rate    = parseFloat(row.querySelector('.building-mobile-rate')?.value) || 0;
    const comp    = parseFloat(row.querySelector('.building-mobile-comp')?.value) || 0;

    totalLength += length;
    totalBreadth += breadth;
    totalArea += area;
    totalBuildingComp += comp;

    if (rate > 0) { rateSum += rate; rateCount++; weightedRateSum += area * rate; }
  });

  // Compute average rate
  let averageRate = 0;
  if (totalArea > 0 && weightedRateSum > 0) averageRate = weightedRateSum / totalArea;
  else if (rateCount > 0) averageRate = rateSum / rateCount;

  // Write to global readonly fields
  const gLen = document.getElementById('length');
  const gBrd = document.getElementById('breadth');
  const gArea = document.getElementById('areaCovered');
  const gRate = document.getElementById('rateOfCost');
  if (gLen)  gLen.value  = totalLength > 0 ? totalLength.toFixed(2) : '';
  if (gBrd)  gBrd.value  = totalBreadth > 0 ? totalBreadth.toFixed(2) : '';
  if (gArea) gArea.value = totalArea > 0 ? totalArea.toFixed(2) : '';
  if (gRate) gRate.value = averageRate > 0 ? averageRate.toFixed(2) : '';

  // Grand total includes sub-items
  let grandTotal = totalBuildingComp;
  document.querySelectorAll('.calc-box').forEach(box => {
    if (box.style.display === 'block') grandTotal += parseFloat(box.dataset.total) || 0;
  });

  const mainComp = document.getElementById('compensation_amount');
  if (mainComp) mainComp.value = grandTotal > 0 ? grandTotal.toFixed(2) : '';

  const display = document.getElementById('grandTotalDisplay');
  if (display) display.textContent = '₦' + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ══════════════════════════════════════════════════════════════════════════
   6. LOCATION "OTHER" HANDLING + ADDRESS BUILDER
   ══════════════════════════════════════════════════════════════════════════ */
function isOtherValue(value) {
  return ['other', 'others'].includes((value || '').toString().trim().toLowerCase());
}

function getLocationSelectValue(selectId) {
  const select = document.getElementById(selectId);
  const otherInput = document.getElementById(selectId + 'Other');
  if (!select) return '';
  if (isOtherValue(select.value)) return (otherInput?.value || '').trim();
  return (select.value || '').trim();
}

function toggleLocationOther(selectId) {
  const select = document.getElementById(selectId);
  const otherInput = document.getElementById(selectId + 'Other');
  if (!select || !otherInput) return;

  const shouldShow = isOtherValue(select.value);
  otherInput.classList.toggle('hidden', !shouldShow);
  if (shouldShow) otherInput.focus();
  else otherInput.value = '';
  buildAddress();
}

function setSelectOrOther(selectId, value) {
  const select = document.getElementById(selectId);
  const otherInput = document.getElementById(selectId + 'Other');
  const normalizedValue = (value || '').toString().trim();
  if (!select || !normalizedValue) return;

  const hasOption = Array.from(select.options).some(option => option.value === normalizedValue);
  if (hasOption) {
    select.value = normalizedValue;
    if (otherInput) otherInput.value = '';
  } else {
    select.value = 'Other';
    if (otherInput) otherInput.value = normalizedValue;
  }
  toggleLocationOther(selectId);
}

['streetSelect', 'districtSelect'].forEach(id => {
  const select = document.getElementById(id);
  const otherInput = document.getElementById(id + 'Other');
  if (select) select.addEventListener('change', () => toggleLocationOther(id));
  if (otherInput) otherInput.addEventListener('input', buildAddress);
});

function buildAddress() {
  const plotNo = document.getElementById('plot_no').value;
  const street = getLocationSelectValue('streetSelect');
  const district = getLocationSelectValue('districtSelect');
  const lga = document.getElementById('lgaSelect').value;

  const parts = [];
  if (plotNo) parts.push('Plot ' + plotNo);
  if (street) parts.push(street);
  if (district) parts.push(district + ' District');
  if (lga) parts.push(lga + ' LGA');
  parts.push('Abia State');

  document.getElementById('fullLocation').value = parts.join(', ');
}

/* ══════════════════════════════════════════════════════════════════════════
   7. PROJECT → SUB-PROJECT → WORKER CASCADE      // API → GET /mobile/workers/{id}
   ══════════════════════════════════════════════════════════════════════════ */
document.getElementById('projectSelect').addEventListener('change', async function () {
  const pId = this.value;
  const wSel = document.getElementById('workerSelect');
  const spSel = document.getElementById('subProjectSelect');
  const wBadge = document.getElementById('workerBadge');
  const ourRef = document.getElementById('mobile_our_ref');
  const yourRef = document.getElementById('mobile_your_ref');

  if (!pId) {
    wSel.disabled = true;
    spSel.disabled = true;
    wSel.innerHTML = '<option value="">Select Project First</option>';
    spSel.innerHTML = '<option value="">Select Project First</option>';
    wBadge.classList.add('hidden');
    document.getElementById('mobile-project-info').classList.add('hidden');
    ourRef.value = '';
    yourRef.value = '';
    document.getElementById('mobile_project_code').value = '';
    document.getElementById('mobile_project_fileno').value = '';
    return;
  }

  // Backfill references and update summary
  const proj = lookupData.projects.find(p => p.id == pId);
  if (proj) {
    ourRef.value = proj.our_reference || '';
    yourRef.value = proj.your_reference || '';
    document.getElementById('mobile_project_code').value = proj.code;
    document.getElementById('mobile_project_fileno').value = proj.fileno;

    const safeSetText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    safeSetText('m_proj_id', proj.id);
    safeSetText('m_proj_fileno', proj.fileno);
    safeSetText('m_proj_code', proj.code);
    safeSetText('m_proj_workers', proj.workers_count || 0);
    safeSetText('m_proj_filled', proj.valuations_count || 0);

    document.getElementById('mobile-project-info').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();

    // Populate Sub-projects
    spSel.disabled = false;
    spSel.innerHTML = '<option value="">Select Sub-Project</option>';
    if (proj.sub_projects && proj.sub_projects.length) {
      proj.sub_projects.forEach(sp => { spSel.innerHTML += `<option value="${sp.id}">${sp.name}</option>`; });
    } else {
      spSel.innerHTML = '<option value="">No Sub-Projects Found</option>';
      spSel.disabled = true;
    }

    // Backfill location scope from the project
    if (proj.district) setSelectOrOther('districtSelect', proj.district);
    if (proj.lga) document.getElementById('lgaSelect').value = proj.lga;
    buildAddress();
  }

  wSel.disabled = false;
  wSel.innerHTML = '<option value="">Loading Workers...</option>';
  await new Promise(r => setTimeout(r, 250));   // stands in for the fetch
  const workers = (lookupData.workers && lookupData.workers[pId]) || [];
  wSel.innerHTML = '<option value="">Select Worker</option>';
  workers.forEach(w => {
    wSel.innerHTML += `<option value="${w.id}" data-code="${w.worker_code}">${w.name}</option>`;
  });
});

document.getElementById('workerSelect').addEventListener('change', function () {
  const code = this.options[this.selectedIndex].dataset.code;
  const badge = document.getElementById('workerBadge');
  if (code) {
    document.getElementById('workerCodeDisplay').textContent = code;
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
});

document.querySelectorAll('.loc-trigger').forEach(el => {
  el.addEventListener('change', buildAddress);
  el.addEventListener('input', buildAddress);
});

/* ══════════════════════════════════════════════════════════════════════════
   8. SECTION COLLAPSE + NAV STRIP
   ══════════════════════════════════════════════════════════════════════════ */
document.querySelectorAll('.section-header').forEach(header => {
  header.addEventListener('click', () => {
    header.closest('.section-card').classList.toggle('collapsed');
  });
});

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    const targetSection = document.getElementById(item.dataset.target);
    targetSection.classList.remove('collapsed');   // expand if collapsed
    const offset = 120;                            // topbar + navstrip height
    const bodyRect = document.body.getBoundingClientRect().top;
    const elementRect = targetSection.getBoundingClientRect().top;
    window.scrollTo({ top: elementRect - bodyRect - offset, behavior: 'smooth' });
  });
});

// Navigation Highlight on Scroll
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const id = entry.target.id;
      document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.target === id);
      });
    }
  });
}, { threshold: 0.2 });
document.querySelectorAll('section').forEach(section => observer.observe(section));

/* ══════════════════════════════════════════════════════════════════════════
   9. SUBMIT / RESET                              // API → POST /mobile/save
   ══════════════════════════════════════════════════════════════════════════ */
async function submitForm() {
  const form = document.getElementById('vfcForm');
  const formData = new FormData(form);
  formData.set('street_name', getLocationSelectValue('streetSelect'));
  formData.set('district', getLocationSelectValue('districtSelect'));
  buildAddress();
  formData.set('location', document.getElementById('fullLocation')?.value || '');

  // Custom Validation — same field list and order as the live app.
  const requiredFields = [
    { id: 'projectSelect',              name: 'Project',                  section: 'sec-project' },
    { id: 'workerSelect',               name: 'Assigned Worker',          section: 'sec-project' },
    { id: 'owner_name',                 name: 'Owner Full Name',          section: 'sec-owner' },
    { id: 'building_type_final_mobile', name: 'Building Type',            section: 'sec-building' },
    { id: 'buildingCount',              name: 'Building Count',           section: 'sec-building' },
    { id: 'compensation_amount',        name: 'Amount of Compensation',   section: 'sec-building' },
    { id: 'account_name',               name: 'Account Name',             section: 'sec-payment' },
    { id: 'account_number',             name: 'Account Number',           section: 'sec-payment' },
    { id: 'phone_number',               name: 'Phone Number',             section: 'sec-payment' },
    { id: 'plot_no',                    name: 'Plot No',                  section: 'sec-location' },
    { id: 'lgaSelect',                  name: 'LGA',                      section: 'sec-location' },
    { id: 'fullLocation',               name: 'Full Address',             section: 'sec-location' },
  ];

  for (const field of requiredFields) {
    const el = document.getElementById(field.id);
    if (!el || !el.value || el.value.trim() === '') {
      showToast(`⚠️ ${field.name} is required`, 'warning');
      const section = document.getElementById(field.section);
      if (section) {
        section.classList.remove('collapsed');
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.focus(), 500);
      }
      return;
    }
  }

  for (const field of [
    { selectId: 'streetSelect',   inputId: 'streetSelectOther',   name: 'Street Name', section: 'sec-location' },
    { selectId: 'districtSelect', inputId: 'districtSelectOther', name: 'District',    section: 'sec-location' },
  ]) {
    const select = document.getElementById(field.selectId);
    const input = document.getElementById(field.inputId);
    if (select && input && isOtherValue(select.value) && input.value.trim() === '') {
      showToast(`Specify ${field.name}`, 'warning');
      const section = document.getElementById(field.section);
      if (section) {
        section.classList.remove('collapsed');
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => input.focus(), 500);
      }
      return;
    }
  }

  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Saving...';

  let finalItems = [...selectedItems];
  const otherText = document.getElementById('compItemsOtherText').value.trim();
  if (otherText) {
    finalItems = finalItems.filter(i => i !== 'Other' && i !== 'Others');
    finalItems.push(otherText);
  }
  formData.set('compensated_items', finalItems.join(', '));

  const data = Object.fromEntries(formData.entries());
  await new Promise(r => setTimeout(r, 700));   // stands in for the POST

  // No backend: report what would have been written, and log the payload so the
  // shape can be checked against ValuationMobileController::store().
  console.log('VFC record (not posted — static clone):', data);
  const fileNumber = document.getElementById('mobile_project_fileno').value || '—';
  showToast('✅ Record Saved: ' + fileNumber, 'success');
  resetForm(true);

  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="save" style="width: 18px;"></i> <span>Save Record</span>';
  if (window.lucide) window.lucide.createIcons();
}

function resetForm(skipConfirm) {
  if (!skipConfirm && !confirm('Clear all entries?')) return;
  document.getElementById('vfcForm').reset();
  selectedItems = [];
  document.querySelectorAll('.check-item').forEach(i => i.classList.remove('active'));
  document.querySelectorAll('.calc-box').forEach(b => { b.style.display = 'none'; b.dataset.total = 0; });

  // Sync building rows back to 1
  document.getElementById('buildingCount').value = 1;
  syncBuildingTypesMobile();

  ['length', 'breadth', 'areaCovered', 'rateOfCost', 'compensation_amount'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('grandTotalDisplay').textContent = '₦0.00';

  // Clear bank logo
  const bankLogoEl = document.getElementById('selectedBankLogo');
  if (bankLogoEl) {
    bankLogoEl.classList.add('hidden');
    bankSearch.style.paddingLeft = '14px';
    bankSearch.style.fontWeight = '500';
    bankSearch.style.color = 'var(--text)';
  }

  document.getElementById('workerBadge').classList.add('hidden');
  document.getElementById('mobile-project-info').classList.add('hidden');
  document.getElementById('subProjectSelect').disabled = true;
  document.getElementById('workerSelect').disabled = true;

  ['compItemsOtherText', 'streetSelectOther', 'districtSelectOther'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.value = ''; }
  });

  document.getElementById('valuation_date').value = new Date().toISOString().split('T')[0];
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showToast(msg, type) {
  const t = document.getElementById('toast');
  document.getElementById('toastMsg').textContent = msg;
  t.style.borderColor = type === 'success' ? 'var(--success)'
    : type === 'warning' ? 'var(--warning)' : 'var(--danger)';
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ══════════════════════════════════════════════════════════════════════════
   10. MAP
   ══════════════════════════════════════════════════════════════════════════ */
let map, marker;
function initMap() {
  // Default: Umuahia, Abia State
  const startLat = 5.5320;
  const startLng = 7.4860;

  map = L.map('map', { zoomControl: false }).setView([startLat, startLng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' }).addTo(map);
  marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

  marker.on('dragend', () => {
    const pos = marker.getLatLng();
    updateCoords(pos.lat, pos.lng);
  });
  map.on('click', e => {
    marker.setLatLng(e.latlng);
    updateCoords(e.latlng.lat, e.latlng.lng);
  });

  // Adjust map size when section is expanded
  document.querySelector('#sec-location .section-header').addEventListener('click', () => {
    setTimeout(() => map.invalidateSize(), 100);
  });
}

function updateCoords(lat, lng) {
  const fixedLat = lat.toFixed(6);
  const fixedLng = lng.toFixed(6);
  document.getElementById('lat').value = fixedLat;
  document.getElementById('lng').value = fixedLng;
  document.getElementById('coordDisplay').textContent = `${fixedLat}, ${fixedLng}`;
}

function getCurrentLocation() {
  if (!navigator.geolocation) { showToast('❌ GPS not supported', 'danger'); return; }

  const btn = document.querySelector('.btn-geo');
  btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-color:rgba(59,130,246,.3);border-top-color:var(--accent);"></div> LOCATING...';

  const restore = () => {
    btn.innerHTML = '<i data-lucide="crosshair" style="width: 14px;"></i> PIN CURRENT';
    if (window.lucide) window.lucide.createIcons();
  };

  navigator.geolocation.getCurrentPosition(
    pos => {
      const { latitude: lat, longitude: lng } = pos.coords;
      map.setView([lat, lng], 17);
      marker.setLatLng([lat, lng]);
      updateCoords(lat, lng);
      restore();
      showToast('✅ Location pinned', 'success');
    },
    () => { showToast('❌ GPS access denied', 'danger'); restore(); },
    { enableHighAccuracy: true }
  );
}

/* ══════════════════════════════════════════════════════════════════════════
   11. DYNAMIC BUILDING ROWS
   ══════════════════════════════════════════════════════════════════════════ */
document.getElementById('buildingCount').addEventListener('input', function () {
  syncBuildingTypesMobile();
  calculateAllCompensation();
});

function syncBuildingTypesMobile() {
  const count = parseInt(document.getElementById('buildingCount').value) || 1;
  const container = document.getElementById('building_types_mobile_container');
  if (!container) return;
  const rows = container.querySelectorAll('.building-type-mobile-row');
  const currentCount = rows.length;

  if (count > currentCount) {
    const template = rows[0].cloneNode(true);
    template.querySelectorAll('select').forEach(sel => { sel.value = ''; });
    template.querySelectorAll('input').forEach(inp => {
      if (inp.type !== 'hidden') {
        inp.value = '';
        if (inp.classList.contains('building-type-mobile-other') || inp.classList.contains('building-stage-mobile-other')) {
          inp.classList.add('hidden');
        } else {
          inp.classList.remove('hidden');
        }
      }
    });
    for (let i = 0; i < count - currentCount; i++) container.appendChild(template.cloneNode(true));
  } else if (count < currentCount) {
    for (let i = 0; i < currentCount - count; i++) {
      if (container.lastElementChild && container.children.length > 1) container.lastElementChild.remove();
    }
  }

  // Update Building Numbers
  container.querySelectorAll('.building-type-mobile-row').forEach((row, idx) => {
    const label = row.querySelector('.building-row-title');
    if (label) label.innerHTML = `<i data-lucide="building" style="width: 10px;"></i> Building ${idx + 1}`;
  });

  if (window.lucide) window.lucide.createIcons();
  initBuildingAssessmentListenersMobile();
  updateFinalBuildingAssessmentMobile();
}

function initBuildingAssessmentListenersMobile() {
  const bindOther = (selector, otherClass) => {
    document.querySelectorAll(selector).forEach(sel => {
      sel.onchange = function () {
        const row = this.closest('.building-type-mobile-row');
        const otherInp = row.querySelector(otherClass);
        if (this.value === 'Other' || this.value === 'Others') {
          if (otherInp) { otherInp.classList.remove('hidden'); otherInp.focus(); }
        } else if (otherInp) {
          otherInp.classList.add('hidden');
          otherInp.value = '';
        }
        updateFinalBuildingAssessmentMobile();
      };
    });
  };
  bindOther('.building-type-mobile-select', '.building-type-mobile-other');
  bindOther('.building-stage-mobile-select', '.building-stage-mobile-other');

  document.querySelectorAll('.building-type-mobile-other, .building-stage-mobile-other').forEach(inp => {
    inp.oninput = updateFinalBuildingAssessmentMobile;
  });

  // Measurement and cost inputs
  document.querySelectorAll('.building-mobile-length, .building-mobile-breadth, .building-mobile-area, .building-mobile-rate, .building-mobile-floors').forEach(inp => {
    inp.oninput = function () {
      calculateBuildingRowMobile(this.closest('.building-type-mobile-row'));
      calculateAllCompensation();
      updateFinalBuildingAssessmentMobile();
    };
  });
}

function updateFinalBuildingAssessmentMobile() {
  const types = [], stages = [], floors = [];
  document.querySelectorAll('.building-type-mobile-row').forEach(row => {
    const typeSel = row.querySelector('.building-type-mobile-select');
    const typeOther = row.querySelector('.building-type-mobile-other');
    if (typeSel) {
      let val = typeSel.value;
      if (val === 'Other' || val === 'Others') val = typeOther?.value || val;
      if (val) types.push(val);
    }
    const stageSel = row.querySelector('.building-stage-mobile-select');
    const stageOther = row.querySelector('.building-stage-mobile-other');
    if (stageSel) {
      let val = stageSel.value;
      if (val === 'Other' || val === 'Others') val = stageOther?.value || val;
      if (val) stages.push(val);
    }
    const floorsInp = row.querySelector('.building-mobile-floors');
    if (floorsInp && floorsInp.value) floors.push(floorsInp.value);
  });
  const typeFinal = document.getElementById('building_type_final_mobile');
  const stageFinal = document.getElementById('completion_stage_final_mobile');
  const floorsFinal = document.getElementById('number_of_floors_final_mobile');
  if (typeFinal) typeFinal.value = types.join(', ');
  if (stageFinal) stageFinal.value = stages.join(', ');
  if (floorsFinal) floorsFinal.value = floors.join(', ');
}

/* ══════════════════════════════════════════════════════════════════════════
   12. SESSION + BOOT
   ══════════════════════════════════════════════════════════════════════════ */
function vfcLogout() {
  if (!confirm('Are you sure you want to logout?')) return;
  try {
    sessionStorage.removeItem(SESSION_KEY);
    localStorage.removeItem(SESSION_KEY);
  } catch (e) {}
  window.location.href = '/alaes-vfc';
}

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  // Name the signed-in field officer in the top bar, if one came from the login.
  try {
    const session = JSON.parse(sessionStorage.getItem(SESSION_KEY) || localStorage.getItem(SESSION_KEY) || 'null');
    if (session && session.name) document.getElementById('topbarUser').textContent = session.name;
  } catch (e) {}

  document.getElementById('valuation_date').value = new Date().toISOString().split('T')[0];

  initMap();
  loadLookupData();
  initBuildingAssessmentListenersMobile();

  // Keyboard Visibility Detection (focus-based, for maximum compatibility) —
  // the save bar is hidden while typing so it never covers the field.
  document.querySelectorAll('input, textarea, select').forEach(input => {
    input.addEventListener('focus', () => document.body.classList.add('keyboard-visible'));
    input.addEventListener('blur', () => {
      setTimeout(() => {
        if (!document.activeElement || !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
          document.body.classList.remove('keyboard-visible');
        }
      }, 100);
    });
  });

  if (window.visualViewport) {
    const initialHeight = window.visualViewport.height;
    window.visualViewport.addEventListener('resize', () => {
      if (window.visualViewport.height < initialHeight * 0.85) document.body.classList.add('keyboard-visible');
    });
  }

  // Auto-capitalize all text inputs
  document.querySelectorAll('input[type="text"], textarea, input[type="search"]').forEach(input => {
    input.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
  });
});
