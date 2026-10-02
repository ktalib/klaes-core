/* ============================================================================
   ALAES File Tracker — Mobile UI (static clone)
   ----------------------------------------------------------------------------
   A faithful clone of resources/views/mobile/dashboard.blade.php with every
   server call replaced by the DEMO_* fixtures below. Nothing here talks to a
   backend: search, timelines, requests and the scanner all read local objects.

   Where the live app calls an endpoint, the function is kept with the same name
   and marked  // API →  so the two stay easy to line up.
   ========================================================================== */

/* ══════════════════════════════════════════════════════════════════════════
   1. DEMO DATA
   ══════════════════════════════════════════════════════════════════════════ */

// Where login.html leaves the signed-in demo account. Read once at boot below.
const SESSION_KEY = 'alaes-demo-session';

const CURRENT_USER = {
  id: 42,
  name: 'Chinedu Okoro',
  username: 'chinedu.o',
  photo_url: null,          // null → the avatar helpers fall back to initials
};

// Role flags. In the live app these come from the server (isSuperAdmin(),
// fr_permissions === 'SCB', isOfs()); here the Profile screen switches them.
let IS_SCB_MONITOR = true;
let IS_OFS         = true;
let IS_SUPER_ADMIN = true;

const DEMO_ROLES = {
  officer: { scb: false, ofs: false, admin: false, label: 'OFFICER' },
  ofs:     { scb: false, ofs: true,  admin: false, label: 'OFS OFFICER' },
  scb:     { scb: true,  ofs: false, admin: false, label: 'SCB MONITOR' },
  admin:   { scb: true,  ofs: true,  admin: true,  label: 'SUPER ADMIN' },
};

// Origin registries (+ short codes) for the File Search request dropdown.
// Origin registries (+ short codes). The codes are the file-number prefixes, so
// the badge beside the selector reads as the series the file belongs to:
//   LUAC/AB/…  Land Use Allocation Committee, Abia
//   LUM/…      Land Umuahia          LABA/…   Land Aba
//   LUM/OH/…   Land Umuahia — Ohafia
const REGISTRIES = [
  { name: 'LUAC Registry',      registry_code: 'LUAC' },
  { name: 'Umuahia Registry',   registry_code: 'LUM' },
  { name: 'Aba Registry',       registry_code: 'LABA' },
  { name: 'Ohafia Registry',    registry_code: 'LUM/OH' },
  { name: 'Cadastral Registry', registry_code: 'CAD' },
  { name: 'DCIV Registry',      registry_code: 'DCIV' },
];

// Registry theme colours — mirror Create File Tracker. Drives the Registry
// (Origin) code badge + Send button colour.
const REGISTRY_THEME = {
  'LUAC Registry':      '#ea580c',  // orange
  'Umuahia Registry':   '#65a30d',  // lime
  'Aba Registry':       '#2563eb',  // blue
  'Ohafia Registry':    '#9333ea',  // purple
  'Cadastral Registry': '#8B4513',  // brown
  'DCIV Registry':      '#065f46',  // green
};

const REQ_DEPARTMENTS = ['Land Administration', 'Survey', 'Legal', 'Physical Planning', 'ICT / ALAES'];

const DEMO_OFFICES = [
  { code: 'LAD-REG', name: 'Land Registry',                department: 'Land Administration' },
  { code: 'LAD-DIR', name: 'Director of Lands',            department: 'Land Administration' },
  { code: 'CS',      name: 'Commission Section',           department: 'Land Administration' },
  { code: 'SUR-MAP', name: 'Survey Mapping Unit',          department: 'Survey' },
  { code: 'LEG-SCH', name: 'Legal Search Office',          department: 'Legal' },
  { code: 'PP-DEV',  name: 'Development Control',          department: 'Physical Planning' },
  { code: 'UM-REG',  name: 'Umuahia Registry',           department: 'ICT / ALAES' },
  { code: 'ABA-REG', name: 'Aba Registry',                department: 'ICT / ALAES' },
  { code: 'DG',      name: 'DG ALAES',                    department: 'ICT / ALAES' },
];

const DEMO_OFFICERS = [
  { id: 11, name: 'Ngozi Eze',        department: 'Land Administration' },
  { id: 12, name: 'Emeka Udo',         department: 'Land Administration' },
  { id: 13, name: 'Chioma Nwankwo',        department: 'Survey' },
  { id: 14, name: 'Obinna Kalu',      department: 'Legal' },
  { id: 15, name: 'Uche Agwu',     department: 'Physical Planning' },
  { id: 16, name: 'Adaeze Onyeka',    department: 'ICT / ALAES' },
];

// Request Purpose lookup (id, name, turnaround_days) — mirrors Quick Search.
const REQUEST_PURPOSES = [
  { id: 1, name: 'Legal Search',              turnaround_days: 5 },
  { id: 2, name: 'Certificate of Occupancy',  turnaround_days: 14 },
  { id: 3, name: 'Assignment / Transfer',     turnaround_days: 10 },
  { id: 4, name: 'Recertification',           turnaround_days: 21 },
  { id: 5, name: 'Court / Investigation',     turnaround_days: 3 },
];

const FS_DEPT_OTHER   = '__DEPT_OTHER__';
const FS_OFFICE_OTHER = '__OFFICE_OTHER__';

// Quick Search / File Location outcome styling — verbatim from the live app.
const LOC_STATUS_META = {
  IN_TRANSIT:                 { label:'In Transit',                 color:'#f59e0b', icon:'fa-truck-fast' },
  IN_ARCHIVE:                 { label:'In Archive',                 color:'#10b981', icon:'fa-box-archive' },
  IN_ARCHIVE_FOUND:           { label:'In Archive — Found',         color:'#10b981', icon:'fa-circle-check' },
  IN_ARCHIVE_NOT_FOUND:       { label:'In Archive — Not Found',     color:'#ef4444', icon:'fa-triangle-exclamation' },
  IN_POOL_OFFICE:             { label:'In Pool Office',             color:'#0ea5e9', icon:'fa-folder-open' },
  IN_POOL_OFFICE_FOUND:       { label:'In Pool Office — Found',     color:'#10b981', icon:'fa-circle-check' },
  IN_POOL_OFFICE_NOT_FOUND:   { label:'In Pool Office — Not Found', color:'#ef4444', icon:'fa-triangle-exclamation' },
  PENDING_FILE:               { label:'Pending (Not Indexed)',      color:'#6b7185', icon:'fa-circle-question' },
  BLIND_REQUEST_SENT:         { label:'Blind Request Sent',         color:'#dc2626', icon:'fa-paper-plane' },
  FILE_NOT_FOUND:             { label:'File Not Found',             color:'#ef4444', icon:'fa-triangle-exclamation' },
  MISSING_FILE:               { label:'Missing File',               color:'#ef4444', icon:'fa-triangle-exclamation' },
  REFER_TO_ORIGINAL_REGISTRY: { label:'Refer to Original Registry', color:'#6b7185', icon:'fa-share-from-square' },
};

const FSR_STATUS_META = {
  PENDING:   { label:'Pending',   bg:'#fef3c7', fg:'#92400e' },
  SEARCHING: { label:'Searching', bg:'#dbeafe', fg:'#1e40af' },
  FOUND:     { label:'Found',     bg:'#dcfce7', fg:'#166534' },
  NOT_FOUND: { label:'Not Found', bg:'#fee2e2', fg:'#991b1b' },
  CLOSED:    { label:'Closed',    bg:'#e5e7eb', fg:'#374151' },
};

const FSR_ACTIVITY_META = {
  PENDING:   { color:'#f59e0b', icon:'fa-clock' },
  SEARCHING: { color:'#0ea5e9', icon:'fa-magnifying-glass' },
  FOUND:     { color:'#10b981', icon:'fa-circle-check' },
  NOT_FOUND: { color:'#ef4444', icon:'fa-triangle-exclamation' },
  CLOSED:    { color:'#6b7185', icon:'fa-circle-xmark' },
};

// ─── Demo scanned pages (stand-ins for the File Digital Library) ────────────
function demoPage(n, caption) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="620" height="820" viewBox="0 0 620 820">
    <rect width="620" height="820" fill="#fdfcf8"/>
    <rect x="34" y="34" width="552" height="752" fill="none" stroke="#d8d4c8" stroke-width="2"/>
    <text x="310" y="108" font-family="Georgia,serif" font-size="26" fill="#3b3a35" text-anchor="middle">ABIA STATE</text>
    <text x="310" y="142" font-family="Georgia,serif" font-size="17" fill="#6b6a63" text-anchor="middle">MINISTRY OF LANDS, SURVEY &amp; URBAN PLANNING</text>
    <line x1="120" y1="168" x2="500" y2="168" stroke="#b9b5a8" stroke-width="2"/>
    <text x="310" y="214" font-family="Georgia,serif" font-size="20" fill="#3b3a35" text-anchor="middle">${caption}</text>
    ${[0,1,2,3,4,5,6,7,8,9,10].map(i =>
      `<rect x="86" y="${262 + i * 34}" width="${i % 3 === 2 ? 320 : 448}" height="11" rx="3" fill="#e3dfd3"/>`).join('')}
    <rect x="86" y="646" width="180" height="76" rx="4" fill="none" stroke="#cfcabb" stroke-width="2"/>
    <text x="176" y="690" font-family="Georgia,serif" font-size="13" fill="#9a968a" text-anchor="middle">SEAL</text>
    <text x="530" y="762" font-family="Georgia,serif" font-size="13" fill="#9a968a" text-anchor="end">Page ${n}</text>
  </svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}
const DEMO_DIGITAL = [
  { name: 'Application Form.jpg',       url: demoPage(1, 'APPLICATION FOR GRANT') },
  { name: 'Certificate of Occupancy.jpg', url: demoPage(2, 'CERTIFICATE OF OCCUPANCY') },
  { name: 'Survey Plan.jpg',            url: demoPage(3, 'SURVEY PLAN — AB/2026/00001') },
  { name: 'Deed of Assignment.jpg',     url: demoPage(4, 'DEED OF ASSIGNMENT') },
];

// ─── The five demo files served by the File Number dropdown ────────────────
// Payload shape mirrors FileLocationResolver's five-outcome response.
const DEMO_FILES = {

  /* 1 ── Out at another office: holder, in-transit dates, live movement log */
  'LUAC/AB/14827/AB': {
    file_number: 'LUAC/AB/14827/AB',
    file_title: 'CHIEF OKEZIE NWACHUKWU',
    status: 'IN_TRANSIT',
    registry: 'Aba Registry',
    origin_registry: 'Aba Registry',
    rack_shelf: 'R4 / S12',
    current_location: 'Legal Department',
    receiving_officer_name: 'Obinna Kalu',
    receiving_officer_photo: null,
    receiving_department: 'Legal',
    date_requested: '02/09/2026',
    date_collected: '03/09/2026',
    duration_with_holder: '8 days',
    can_send_fr: false,
    can_redirect: true,
    is_indexed: true,
    dciv_status: 0,
    title_holders: {
      lines: [
        { label: 'Original Holder', value: 'CHIEF OKEZIE NWACHUKWU',                 tone: 'emerald' },
        { label: 'Current Holder',  value: 'NWACHUKWU HOLDINGS LTD',                 tone: 'indigo' },
      ],
    },
    holder_history: [
      { to: 'CHIEF OKEZIE NWACHUKWU', date: '14/03/1985', transaction_type: 'Right of Occupancy' },
      { to: 'CHIEF OKEZIE NWACHUKWU', date: '09/07/2003', transaction_type: 'Certificate of Occupancy' },
      { to: 'NWACHUKWU HOLDINGS LTD', date: '22/11/2019', transaction_type: 'Deed of Assignment' },
    ],
    bill_balance: { balance_due: 1450000 },
    indexing_bills: { bill_balance: 1450000, grant_rent: 87500 },
    digital: DEMO_DIGITAL,
    tracker: {
      request_purpose_name: 'Legal Search',
      timeline_status: 'amber',
      days_until_deadline: 2,
      deadline: '2026-09-13',
      movement_history: [
        { office_name: 'Aba Registry',     receiving_officer_name: 'Adaeze Onyeka', log_in_date: '2026-09-02', log_in_time: '09:15',
          log_out_date: '2026-09-02', log_out_time: '15:40', status: 'completed', status_label: 'Log-out',
          notes: 'Released to Commission Section on request.' },
        { office_name: 'Commission Section',  receiving_officer_name: 'Ngozi Eze',     log_in_date: '2026-09-03', log_in_time: '08:50',
          log_out_date: '2026-09-04', log_out_time: '16:05', status: 'completed', status_label: 'Log-in' },
        { office_name: 'Legal Search Office', receiving_officer_name: 'Obinna Kalu',   log_in_date: '2026-09-05', log_in_time: '10:20',
          status: 'active', delay_reason: 'Awaiting counsel opinion',
          notes: 'Held pending the search report.' },
      ],
    },
  },

  /* 2 ── Home in its registry, full ownership chain, closed movement history */
  'LUAC/AB/21506/UM': {
    file_number: 'LUAC/AB/21506/UM',
    file_title: 'LOLO NGOZI IHEANACHO',
    status: 'IN_ARCHIVE',
    registry: 'Umuahia Registry',
    origin_registry: 'Umuahia Registry',
    rack_shelf: 'R9 / S03',
    current_location: 'Umuahia Registry — Archive',
    receiving_officer_name: 'Chioma Nwankwo',
    receiving_officer_photo: null,
    next_action: 'Send File Search Request to SCB Monitor',
    can_send_fr: true,
    can_redirect: false,
    is_indexed: true,
    dciv_status: 0,
    title_holders: {
      lines: [
        { label: 'Original Holder', value: 'CHIEF IHEANACHO OKORO',        tone: 'emerald' },
        { label: 'Current Holder',  value: 'LOLO NGOZI IHEANACHO',     tone: 'indigo' },
      ],
    },
    holder_history: [
      { to: 'CHIEF IHEANACHO OKORO',    date: '30/06/2009', transaction_type: 'Right of Occupancy' },
      { to: 'LOLO NGOZI IHEANACHO', date: '17/02/2016', transaction_type: 'Deed of Assignment' },
    ],
    bill_balance: { balance_due: 0 },
    indexing_bills: { bill_balance: 0, grant_rent: 42000 },
    digital: DEMO_DIGITAL.slice(0, 2),
    tracker: {
      request_purpose_name: 'Recertification',
      timeline_status: 'green',
      days_until_deadline: 11,
      deadline: '2026-09-22',
      movement_history: [
        { office_name: 'Umuahia Registry',        receiving_officer_name: 'Chioma Nwankwo',   log_in_date: '2026-07-18', log_in_time: '09:00',
          log_out_date: '2026-07-18', log_out_time: '14:30', status: 'completed', status_label: 'Log-out' },
        { office_name: 'Development Control',  receiving_officer_name: 'Uche Agwu', log_in_date: '2026-07-19', log_in_time: '10:10',
          log_out_date: '2026-07-26', log_out_time: '12:00', status: 'completed', status_label: 'Log-in',
          notes: 'Site inspection completed.' },
        { office_name: 'Umuahia Registry',        receiving_officer_name: 'Chioma Nwankwo',   log_in_date: '2026-07-27', log_in_time: '08:35',
          log_out_date: '2026-07-27', log_out_time: '09:10', status: 'completed', status_label: 'Log-in',
          notes: 'Returned to shelf R9 / S03.' },
        { office_name: 'Director of Lands',    receiving_officer_name: 'Emeka Udo',    log_in_date: '2026-07-22', log_in_time: '11:00',
          status: 'completed', status_label: 'Approved', purpose: 'approval',
          notes: 'Recertification approved.' },
      ],
    },
  },

  /* 3 ── Sitting in a pool office; an OFS officer can raise a request on it */
  'LUM/34233': {
    file_number: 'LUM/34233',
    linked_file_number: 'LUAC/AB/26085/UM',
    file_title: 'UGWUMBA HOLDINGS NIG. LTD',
    status: 'IN_POOL_OFFICE',
    registry: 'Umuahia Registry',
    origin_registry: 'Umuahia Registry',
    rack_shelf: 'R2 / S07',
    current_location: 'Commission Section (Pool)',
    receiving_officer_name: 'Ngozi Eze',
    receiving_officer_photo: null,
    next_action: 'Send File Search Request to SCB Monitor',
    can_send_fr: true,
    can_redirect: false,
    is_indexed: true,
    dciv_status: 0,
    title_holders: {
      lines: [
        { label: 'Original Holder', value: 'UGWUMBA HOLDINGS NIG. LTD',   tone: 'emerald' },
        { label: 'Current Holder',  value: 'UGWUMBA HOLDINGS NIG. LTD',   tone: 'indigo' },
      ],
    },
    holder_history: [
      { to: 'UGWUMBA HOLDINGS NIG. LTD', date: '05/05/2011', transaction_type: 'Certificate of Occupancy' },
    ],
    bill_balance: { balance_due: 320000 },
    indexing_bills: { bill_balance: 320000, grant_rent: null },
    digital: DEMO_DIGITAL.slice(0, 3),
    tracker: null,
  },

  /* 4 ── Indexed then returned to the original registry before archiving */
  'LABA/38266': {
    file_number: 'LABA/38266',
    file_title: 'MR. SAMUEL EKWUEME',
    status: 'MISSING_FILE',
    registry: 'Aba Registry',
    origin_registry: 'Aba Registry',
    rack_shelf: 'R1 / S22',
    current_location: 'Aba Registry',
    receiving_officer_name: '',
    next_action: 'Send File Search Request to the Original Registry',
    can_send_fr: true,
    can_redirect: false,
    is_missing_file: true,
    is_indexed: true,
    dciv_status: 0,
    title_holders: null,
    original_holder: 'MR. SAMUEL EKWUEME',
    current_holder: 'MR. SAMUEL EKWUEME',
    holder_history: [],
    bill_balance: {},
    indexing_bills: {},
    digital: [],
    tracker: null,
  },

  /* 5 ── A second archived file, in a third registry (Ohafia) */
  'LUM/OH/41903': {
    file_number: 'LUM/OH/41903',
    file_title: 'ABA MAIN MARKET STORES',
    status: 'IN_ARCHIVE',
    registry: 'Ohafia Registry',
    origin_registry: 'Ohafia Registry',
    rack_shelf: 'R6 / S18',
    current_location: 'Ohafia Registry — Archive',
    receiving_officer_name: 'Emeka Udo',
    receiving_officer_photo: null,
    next_action: 'Send File Search Request to SCB Monitor',
    can_send_fr: true,
    can_redirect: false,
    is_indexed: true,
    dciv_status: 0,
    title_holders: {
      lines: [
        { label: 'Original Holder', value: 'ABA MAIN MARKET STORES',    tone: 'emerald' },
        { label: 'Current Holder',  value: 'ABA MAIN MARKET STORES',    tone: 'indigo' },
      ],
    },
    holder_history: [
      { to: 'ABA MAIN MARKET STORES', date: '12/12/2018', transaction_type: 'Certificate of Occupancy' },
    ],
    bill_balance: { balance_due: 76500 },
    indexing_bills: { bill_balance: 76500, grant_rent: 15000 },
    digital: DEMO_DIGITAL.slice(0, 2),
    tracker: {
      request_purpose_name: 'Court / Investigation',
      timeline_status: 'red',
      days_until_deadline: -4,
      deadline: '2026-09-07',
      movement_history: [
        { office_name: 'Ohafia Registry',         receiving_officer_name: 'Emeka Udo',    log_in_date: '2026-08-28', log_in_time: '09:30',
          log_out_date: '2026-08-28', log_out_time: '13:00', status: 'completed', status_label: 'Log-out' },
        { office_name: 'Legal Search Office', receiving_officer_name: 'Obinna Kalu', log_in_date: '2026-08-29', log_in_time: '09:00',
          log_out_date: '2026-09-01', log_out_time: '15:20', status: 'completed', status_label: 'Log-in',
          delay_reason: 'Duplicate allocation query' },
        { office_name: 'Ohafia Registry',         receiving_officer_name: 'Emeka Udo',    log_in_date: '2026-09-02', log_in_time: '08:45',
          log_out_date: '2026-09-02', log_out_time: '09:30', status: 'completed', status_label: 'Log-in' },
      ],
    },
  },
};

// The five file numbers offered by the dropdown, in order.
const DEMO_FILE_NUMBERS = Object.keys(DEMO_FILES);

// Tracking-id → file number, for the QR scanner and Manual Entry lookups.
const DEMO_TRACKING_IDS = {
  'TRK-8F2A11': 'LUAC/AB/14827/AB',
  'TRK-4C91D0': 'LUAC/AB/21506/UM',
  'TRK-77B3E5': 'LUM/34233',
  'TRK-1AD204': 'LABA/38266',
  'TRK-9E6C38': 'LUM/OH/41903',
};

let DEMO_NOTIFICATIONS = [
  { id: 1, type: 'file_search_request', title: 'New File Search Request', body: 'LUAC/AB/14827/AB · raised by Ngozi Eze (Land Administration)', is_read: false, created_at: minutesAgo(9) },
  { id: 2, type: 'file_tracking',       title: 'File logged in',          body: 'LUM/34233 received at Commission Section',                        is_read: false, created_at: minutesAgo(85) },
  { id: 3, type: 'file_search_request', title: 'Request marked Found',    body: 'LUAC/AB/21506/UM · found on shelf R9 / S03',                  is_read: false, created_at: minutesAgo(320) },
  { id: 4, type: 'file_tracking',       title: 'Overdue file',            body: 'LUM/OH/41903 is 4 days past its expected return date',        is_read: true,  created_at: minutesAgo(1500) },
];

const DEMO_DASHBOARD = {
  priority_breakdown: { high: 7, medium: 16, low: 9 },
  weekly_labels:   ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
  weekly_activity: [4, 9, 6, 12, 15, 3, 2],
};

const DEMO_TODAY = { total: 14, found: 8, not_found: 3, awaiting: 3 };

// SCB Monitor — open queue.
let DEMO_FR_OPEN = [
  { id: 501, request_no: 'FSR-2026-0501', file_number: 'LUAC/AB/14827/AB', file_title: 'CHIEF OKEZIE NWACHUKWU',
    status: 'PENDING', request_type: 'Open Request', is_blind: false, is_dfr: false, is_ofs: true, ofs_rank: 'Director',
    requester: 'Ngozi Eze', receiving_officer: 'Ngozi Eze', requester_office: 'Land Registry',
    requester_department: 'Land Administration', current_location: 'Legal Department', created_at: '11/09/2026 08:42',
    created_by: 'Ngozi Eze', request_purpose_name: 'Legal Search', timeline_status: 'amber', days_until_deadline: 2 },
  { id: 502, request_no: 'FSR-2026-0502', file_number: 'LABA/52714', file_title: 'UNINDEXED — OHAFIA LAYOUT PLOT',
    status: 'PENDING', request_type: 'Blind Request', is_blind: true, is_dfr: false, is_ofs: false,
    requester: 'Uche Agwu', receiving_officer: 'Uche Agwu', requester_office: 'Development Control',
    requester_department: 'Physical Planning', current_location: 'Not indexed', created_at: '11/09/2026 09:15',
    created_by: 'Uche Agwu', request_purpose_name: 'Certificate of Occupancy', timeline_status: 'pending', days_until_deadline: null },
  { id: 503, request_no: 'FSR-2026-0503', file_number: 'LUM/34233', file_title: 'UGWUMBA HOLDINGS NIG. LTD',
    status: 'SEARCHING', request_type: 'Open Request', is_blind: false, is_dfr: false, is_ofs: false,
    requester: 'Obinna Kalu', receiving_officer: 'Obinna Kalu', requester_office: 'Legal Search Office',
    requester_department: 'Legal', current_location: 'Commission Section (Pool)', created_at: '10/09/2026 14:03',
    created_by: 'Obinna Kalu', request_purpose_name: 'Assignment / Transfer', timeline_status: 'green', days_until_deadline: 6,
    delay_reason: 'Pool office stock-take in progress' },
];

// SCB Monitor — FSR History.
let DEMO_FR_LOG = [
  { id: 498, request_no: 'FSR-2026-0498', file_number: 'LUAC/AB/21506/UM', file_title: 'LOLO NGOZI IHEANACHO',
    status: 'FOUND', request_type: 'Open Request', is_blind: false, is_dfr: false, requester: 'Chioma Nwankwo',
    receiving_officer: 'Chioma Nwankwo', requester_office: 'Survey Mapping Unit', current_location: 'Umuahia Registry — Archive',
    created_at: '09/09/2026 10:22', responded_at: '09/09/2026 12:04', responder: 'Chinedu Okoro',
    feedback_note: 'Found on shelf R9 / S03.', can_revert: true,
    request_purpose_name: 'Recertification', timeline_status: 'green', days_until_deadline: 11 },
  { id: 497, request_no: 'FSR-2026-0497', file_number: 'LUM/OH/41903', file_title: 'ABA MAIN MARKET STORES',
    status: 'NOT_FOUND', not_found_type: 'PENDING', request_type: 'Open Request', is_blind: false, is_dfr: false,
    requester: 'Emeka Udo', receiving_officer: 'Emeka Udo', requester_office: 'Director of Lands',
    current_location: 'Ohafia Registry — Archive', created_at: '08/09/2026 09:11', responded_at: '08/09/2026 16:50',
    responder: 'Chinedu Okoro', feedback_note: 'Second physical search still running.', can_revert: true,
    request_purpose_name: 'Court / Investigation', timeline_status: 'red', days_until_deadline: -4 },
  { id: 496, request_no: 'FSR-2026-0496', file_number: 'LABA/38266', file_title: 'MR. SAMUEL EKWUEME',
    status: 'NOT_FOUND', not_found_type: 'MISSING', request_type: 'Blind Request', is_blind: true, is_dfr: false,
    requester: 'Adaeze Onyeka', receiving_officer: 'Adaeze Onyeka', requester_office: 'Aba Registry',
    current_location: 'Aba Registry', created_at: '05/09/2026 11:40', responded_at: '06/09/2026 09:05',
    responder: 'Chinedu Okoro', feedback_note: 'Missing since the digitization exercise.', can_revert: false,
    request_purpose_name: 'Legal Search', timeline_status: 'red', days_until_deadline: -12 },
  { id: 495, request_no: 'FSR-2026-0495', file_number: 'LUM/18470', file_title: 'ABIA STATE GOVERNMENT',
    status: 'CLOSED', request_type: 'Digital File Request', is_blind: false, is_dfr: true, requester: 'Ngozi Eze',
    receiving_officer: 'Ngozi Eze', requester_office: 'Land Registry', current_location: 'Umuahia Registry',
    created_at: '02/09/2026 08:00', responded_at: '02/09/2026 08:35', responder: 'Chinedu Okoro',
    request_purpose_name: 'Legal Search', timeline_status: 'green', days_until_deadline: 3 },
];

// OFS — the requests this user raised.
let DEMO_MY_REQUESTS = [
  { id: 601, request_no: 'FSR-2026-0601', file_number: 'LUAC/AB/14827/AB', file_title: 'CHIEF OKEZIE NWACHUKWU',
    status: 'FOUND', request_type: 'Open Request', is_blind: false, is_dfr: false, is_ofs: true,
    current_location: 'Legal Department', created_at: '03/09/2026 09:20', responded_at: '03/09/2026 11:02',
    feedback_note: 'Found and released to your office.', logged_out: true, logged_out_to_me: true,
    logged_out_office: 'Legal Search Office', handler: 'Obinna Kalu' },
  { id: 602, request_no: 'FSR-2026-0602', file_number: 'LUM/34233', file_title: 'UGWUMBA HOLDINGS NIG. LTD',
    status: 'SEARCHING', request_type: 'Open Request', is_blind: false, is_dfr: false, is_ofs: true,
    current_location: 'Commission Section (Pool)', created_at: '10/09/2026 14:03' },
  { id: 603, request_no: 'FSR-2026-0603', file_number: 'LABA/52714', file_title: 'UNINDEXED — OHAFIA LAYOUT PLOT',
    status: 'PENDING', request_type: 'Blind Request', is_blind: true, is_dfr: false, is_ofs: false,
    current_location: 'Not indexed', created_at: '11/09/2026 09:15' },
];

function minutesAgo(m) { return new Date(Date.now() - m * 60000).toISOString(); }

/* ══════════════════════════════════════════════════════════════════════════
   2. UTILITIES  (verbatim from the live app)
   ══════════════════════════════════════════════════════════════════════════ */

// Quotes are escaped as well as angle brackets: nearly every use of this sits inside a
// double-quoted HTML attribute, where a bare " ends the attribute and lets the rest of
// the value become markup.
function esc(s) {
  return String(s || '').replace(/[&<>"']/g, m => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}
// Darken a #rrggbb colour by pct (0..1) — used to build matching button gradients.
function shade(hex, pct) {
  const n = parseInt(String(hex || '').replace('#', ''), 16);
  if (isNaN(n)) return hex;
  let r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  r = Math.round(r * (1 - pct)); g = Math.round(g * (1 - pct)); b = Math.round(b * (1 - pct));
  return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}
function relTime(d) {
  if (!d) return '';
  const diff = Math.floor((new Date() - new Date(d)) / 60000);
  if (diff < 1) return 'Just now';
  if (diff < 60) return `${diff}m ago`;
  if (diff < 1440) return `${Math.floor(diff / 60)}h ago`;
  return `${Math.floor(diff / 1440)}d ago`;
}
function genTrackingId() { return 'TRK-' + Math.random().toString(36).substring(2, 8).toUpperCase(); }
function genLogId() { const d = new Date(); return `LOG-${d.toISOString().replace(/[-:T]/g, '').split('.')[0]}-${Math.floor(Math.random() * 999)}`; }
function fillDateTime() {
  const now = new Date();
  const t = now.toTimeString().substring(0, 5);
  const dt = now.toISOString().split('T')[0];
  document.getElementById('logInTime').value  = t;
  document.getElementById('logInDate').value  = dt;
  document.getElementById('logOutTime').value = t;
  document.getElementById('logOutDate').value = dt;
}
// Stand-in for the network round trip, so spinners are actually visible.
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }

function toast(msg, type = 'success') {
  const el = document.getElementById(type === 'success' ? 'successToast' : 'errorToast');
  el.textContent = msg;
  el.style.display = 'block';
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.style.display = 'none'; }, 3500);
}

/* ─── Avatars ─────────────────────────────────────────────────────────── */
function mobAvatar(url, name, size) {
  const px = size || 30;
  const nm = esc(name || '');
  const ini = String(name || '').split(/\s+/).filter(Boolean).slice(0, 2)
    .map(p => p.charAt(0).toUpperCase()).join('') || '?';
  const style = `width:${px}px;height:${px}px;border-radius:9999px;overflow:hidden;display:inline-flex;`
    + `align-items:center;justify-content:center;flex:0 0 auto;background:var(--surface-2);`
    + `color:var(--muted);font-weight:700;font-size:${Math.round(px * 0.38)}px;`
    + `border:1px solid var(--border);vertical-align:middle;`;
  if (!url) return `<span style="${style}" title="${nm}">${esc(ini)}</span>`;
  return `<span style="${style}" title="${nm}"><img src="${esc(url)}" alt="${nm}" style="width:100%;height:100%;object-fit:cover;"></span>`;
}
// Squared passport frame — used where the photo is presented as a document-style
// portrait (the request sheet) rather than as a small round avatar in a row.
function mobPassport(url, name, size) {
  const px = size || 46;
  const nm = esc(name || '');
  const ini = String(name || '').split(/\s+/).filter(Boolean).slice(0, 2)
    .map(p => p.charAt(0).toUpperCase()).join('') || '?';
  const style = `width:${px}px;height:${px}px;border-radius:10px;overflow:hidden;display:flex;`
    + `align-items:center;justify-content:center;flex:0 0 auto;background:var(--surface-2);`
    + `color:var(--muted);font-weight:700;font-size:${Math.round(px * 0.34)}px;`
    + `border:1px solid var(--border-strong);`;
  if (!url) return `<div style="${style}" title="${nm}">${esc(ini)}</div>`;
  return `<div style="${style}" title="${nm}"><img src="${esc(url)}" alt="${nm}" style="width:100%;height:100%;object-fit:cover;"></div>`;
}
function mobPassportTap(url, name, size) {
  const frame = mobPassport(url, name, size);
  if (!url) return frame;
  return `<span data-photo-view data-photo-url="${esc(url)}" data-photo-name="${esc(name || '')}"`
    + ` title="Tap to view photo" style="display:inline-flex;cursor:pointer;">${frame}</span>`;
}
function mobAvatarRow(name, url, size) {
  if (!name) return '';
  // With a photo the row becomes a tap target that opens it full size; without one
  // there is nothing to enlarge, so it stays plain text.
  const openable = url
    ? ` data-photo-view data-photo-url="${esc(url)}" data-photo-name="${esc(name)}"`
      + ` style="display:inline-flex;align-items:center;gap:7px;justify-content:flex-end;cursor:pointer;" title="Tap to view photo"`
    : ` style="display:inline-flex;align-items:center;gap:7px;justify-content:flex-end;"`;
  return `<span${openable}>` + mobAvatar(url, name, size || 26)
    + `<span style="${url ? 'text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;' : ''}">${esc(name)}</span></span>`;
}
function mobShowPhoto(url, name) {
  if (!url) return;
  document.getElementById('mobPhotoViewer')?.remove();
  const wrap = document.createElement('div');
  wrap.id = 'mobPhotoViewer';
  wrap.style.cssText = 'position:fixed;inset:0;z-index:4000;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(2,6,23,.88);';
  wrap.innerHTML = `
    <div style="max-width:100%;text-align:center;">
      <img src="${esc(url)}" alt="${esc(name || '')}" style="max-width:100%;max-height:70vh;border-radius:16px;border:1px solid var(--border-strong);object-fit:contain;background:var(--surface-2);">
      <div style="margin-top:12px;font-size:15px;font-weight:700;color:#fff;">${esc(name || '')}</div>
      <button type="button" data-photo-close style="margin-top:14px;padding:9px 20px;border-radius:12px;border:1px solid var(--border-strong);background:var(--surface-2);color:var(--text);font-size:13px;font-weight:600;">Close</button>
    </div>`;
  const close = () => wrap.remove();
  wrap.addEventListener('click', e => { if (e.target === wrap || e.target.closest('[data-photo-close]')) close(); });
  document.addEventListener('keydown', function onKey(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
  });
  document.body.appendChild(wrap);
}
// Delegated so rows rendered later still open the viewer.
document.addEventListener('click', function (e) {
  const trigger = e.target.closest('[data-photo-view]');
  if (!trigger) return;
  e.preventDefault(); e.stopPropagation();
  mobShowPhoto(trigger.getAttribute('data-photo-url'), trigger.getAttribute('data-photo-name'));
});

/* ══════════════════════════════════════════════════════════════════════════
   3. THEME
   ══════════════════════════════════════════════════════════════════════════ */
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  try { localStorage.setItem('alaes-theme', theme); } catch (e) {}
  const ic = document.getElementById('themeToggleIcon');
  if (ic) ic.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  const mc = document.querySelector('meta[name="theme-color"]');
  if (mc) mc.setAttribute('content', theme === 'dark' ? '#0c0e15' : '#f4f5fb');
}
function toggleTheme() {
  const cur = document.documentElement.getAttribute('data-theme') || 'light';
  applyTheme(cur === 'dark' ? 'light' : 'dark');
  if (document.getElementById('dashboard-screen').classList.contains('active')) renderDashboard();
}

/* ══════════════════════════════════════════════════════════════════════════
   4. NOTIFICATIONS                                    // API → /notifications
   ══════════════════════════════════════════════════════════════════════════ */
let notifications = [];

function updateNotificationBadge() {
  const unread = notifications.filter(n => !n.is_read).length;
  const badge = document.getElementById('notificationBadge');
  badge.textContent = unread;
  badge.style.display = unread > 0 ? 'block' : 'none';
}
function renderNotifications() {
  const container = document.getElementById('notificationList');
  if (!notifications.length) {
    container.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--faint);">No notifications</div>';
    return;
  }
  container.innerHTML = notifications.map(n => `
    <div class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
      <div class="notification-title">${esc(n.title)}</div>
      <div class="notification-desc">${esc(n.body || '')}</div>
      <div class="notification-time">${relTime(n.created_at)}</div>
    </div>`).join('');
  container.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', e => {
      e.stopPropagation();
      const n = notifications.find(n => String(n.id) === item.dataset.id);
      if (n) n.is_read = true;
      renderNotifications(); updateNotificationBadge();
    });
  });
}
function loadNotifications() {
  // File Request notifications are for SCB Monitors only on the app.
  notifications = DEMO_NOTIFICATIONS.filter(n => IS_SCB_MONITOR || n.type !== 'file_search_request');
  renderNotifications(); updateNotificationBadge();
}

/* ══════════════════════════════════════════════════════════════════════════
   5. DASHBOARD                                  // API → /dashboard/stats
   ══════════════════════════════════════════════════════════════════════════ */
let weeklyChart = null;

function chartColors() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  return { grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)', tick: dark ? '#9aa0b6' : '#6b7185' };
}

function renderDashboard() {
  const hr = new Date().getHours();
  const greet = hr < 12 ? 'Good Morning' : hr < 18 ? 'Good Afternoon' : 'Good Evening';
  document.getElementById('greetingMsg').textContent = `${greet}, ${CURRENT_USER.name.split(' ')[0]}!`;
  loadFsToday();   // "Requests Today" tile (SCB Monitors only)

  const s = DEMO_DASHBOARD;
  const pb = s.priority_breakdown, h = pb.high, m = pb.medium, l = pb.low, tot = h + m + l || 1;
  document.getElementById('priorityDistribution').innerHTML = `
    <div class="priority-item"><span class="priority-label">High</span><div class="priority-bar"><div class="priority-bar-fill fill-high" style="width:${(h / tot) * 100}%"></div></div><span class="priority-value">${h}</span></div>
    <div class="priority-item"><span class="priority-label">Medium</span><div class="priority-bar"><div class="priority-bar-fill fill-medium" style="width:${(m / tot) * 100}%"></div></div><span class="priority-value">${m}</span></div>
    <div class="priority-item"><span class="priority-label">Low</span><div class="priority-bar"><div class="priority-bar-fill fill-low" style="width:${(l / tot) * 100}%"></div></div><span class="priority-value">${l}</span></div>`;

  renderRecentActivity();

  const cc = chartColors();
  if (weeklyChart) weeklyChart.destroy();
  weeklyChart = new Chart(document.getElementById('weeklyChart').getContext('2d'), {
    type: 'line',
    data: { labels: s.weekly_labels, datasets: [{ label: 'Total', data: s.weekly_activity, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.12)', borderWidth: 2.5, fill: true, tension: 0.4, pointBackgroundColor: '#8b5cf6', pointRadius: 4 }] },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: cc.grid }, ticks: { color: cc.tick, font: { size: 9 } } },
        x: { grid: { display: false }, ticks: { color: cc.tick, font: { size: 9 } } },
      },
    },
  });
}

// Recent Activity panel — only SCB Monitors have File Search Requests to show.
function renderRecentActivity() {
  const el = document.getElementById('recentActivityList');
  if (!el) return;
  if (!IS_SCB_MONITOR) {
    el.innerHTML = '<p style="text-align:center;color:var(--faint);font-size:12px;padding:20px;">No recent activity</p>';
    return;
  }
  // Merge the Open inbox and the FSR History, then take the 5 most recent.
  const seen = new Set();
  const recent = [].concat(DEMO_FR_OPEN, DEMO_FR_LOG)
    .filter(fr => !seen.has(fr.id) && seen.add(fr.id))
    .sort((a, b) => (b.id || 0) - (a.id || 0))
    .slice(0, 5);
  el.innerHTML = recent.map(fr => {
    const m = FSR_ACTIVITY_META[fr.status] || FSR_ACTIVITY_META.PENDING;
    const title = fr.file_number || fr.file_title || fr.request_no || '—';
    // "Requested by" is the selected Requester Officer; "Created by" is the account.
    const requester = fr.receiving_officer || fr.requester || fr.requester_office || fr.requester_department || '';
    const details = [
      ['fa-user',     requester,           'Requested by'],
      ['fa-building', fr.requester_office, 'Office'],
      ['fa-user-pen', fr.created_by,       'Created by'],
    ].filter(([, val]) => val && String(val).trim() && String(val).trim() !== '—')
     .map(([icon, val, label]) =>
       `<div style="font-size:12px;color:var(--text);"><i class="fas ${icon}" style="width:12px;margin-right:5px;color:var(--primary);"></i><span style="color:var(--text);">${label}:</span> ${esc(val)}</div>`).join('');
    return `<div class="activity-item"><div class="activity-icon" style="background:${m.color}22;color:${m.color};"><i class="fas ${m.icon}"></i></div><div class="activity-content"><div class="activity-title">${esc(title)}</div><div class="activity-time">${esc(fr.request_no || '')}${fr.created_at ? ' · ' + esc(fr.created_at) : ''}</div>${details ? `<div style="margin-top:5px;display:flex;flex-direction:column;gap:3px;">${details}</div>` : ''}</div></div>`;
  }).join('');
}

function renderFsToday(t) {
  if (!t) return;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = (v ?? 0); };
  set('fsToday', t.total); set('fsTodayFound', t.found);
  set('fsTodayNotFound', t.not_found); set('fsTodayAwaiting', t.awaiting);
}
function loadFsToday() {
  if (!IS_SCB_MONITOR) return;
  renderFsToday(DEMO_TODAY);
}

/* ══════════════════════════════════════════════════════════════════════════
   6. LOG A FILE                                        // API → POST /file-trackers
   ══════════════════════════════════════════════════════════════════════════ */
function populateCreateForm() {
  const depts = [...new Set(DEMO_OFFICES.map(o => o.department))].sort();
  const dept = document.getElementById('department');
  dept.innerHTML = '<option value="">Select department</option>' +
    depts.map(d => `<option value="${esc(d)}">${esc(d)}</option>`).join('');

  const officeOpts = DEMO_OFFICES.map(o => `<option value="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.name)}</option>`).join('');
  document.getElementById('originOffice').innerHTML    = '<option value="">Select origin office</option>' + officeOpts;
  document.getElementById('receivingOffice').innerHTML = '<option value="">Select receiving office</option>' + officeOpts;

  document.getElementById('receivingOfficer').innerHTML = '<option value="">Select receiving officer</option>' +
    DEMO_OFFICERS.map(o => `<option value="${o.id}" data-name="${esc(o.name)}" data-photo="">${esc(o.name)}</option>`).join('');
}

// Log a File: init defaults when the screen opens.
function initCreateForm() {
  fillDateTime();
  const tid = document.getElementById('trackingIdField');
  const lid = document.getElementById('logIdField');
  if (tid && !tid.value) tid.value = genTrackingId();
  if (lid && !lid.value) lid.value = genLogId();
}

async function createNewFile() {
  const fileName      = document.getElementById('createFileName').value.trim();
  const department    = document.getElementById('department').value;
  const originSel     = document.getElementById('originOffice');
  const recvOfficeSel = document.getElementById('receivingOffice');
  const recvOfficerSel = document.getElementById('receivingOfficer');

  if (!fileName)              { toast('File Title is required', 'error'); return; }
  if (!department)            { toast('Please select a department', 'error'); return; }
  if (!originSel.value)       { toast('Please select origin office', 'error'); return; }
  if (!recvOfficeSel.value)   { toast('Please select receiving office', 'error'); return; }
  if (!recvOfficerSel.value)  { toast('Please select receiving officer', 'error'); return; }

  const btn = document.getElementById('submitCreateBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
  await wait(700);   // demo: no POST is made

  const tracking = document.getElementById('trackingIdField').value || genTrackingId();
  toast(`✅ File created! Tracking: ${tracking}`);
  ['createFileNumber', 'createFileName', 'createNotes'].forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('trackingIdField').value = genTrackingId();
  document.getElementById('logIdField').value      = genLogId();
  fillDateTime();
  ['department', 'originOffice', 'receivingOffice', 'receivingOfficer'].forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('receivingOfficerProfile').style.display = 'none';

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-circle-plus"></i> Log a File';
  setActiveTab('files');
}

/* ══════════════════════════════════════════════════════════════════════════
   7. FILE SEARCH — the five-outcome locator          // API → /files/search
   ══════════════════════════════════════════════════════════════════════════ */
let lastFileSearch = null;
// The duplicate record the user picked, as {record_id, source}. Null until they
// choose, and cleared on every fresh render — a request raised on a number that
// two physical files share must name WHICH one.
let selectedCandidate = null;

function populateFilePicker() {
  const sel = document.getElementById('fileSearchSelect');
  sel.innerHTML = '<option value="">Select file number…</option>' +
    DEMO_FILE_NUMBERS.map(fn => {
      const f = DEMO_FILES[fn];
      return `<option value="${esc(fn)}">${esc(fn)} — ${esc(f.file_title)}</option>`;
    }).join('');
}

// Blind request: a not-yet-indexed file can't be picked from the list (it only
// serves indexed files), so swap the picker for a free-text input.
function toggleBlindSearch() {
  const on     = document.getElementById('fsBlindToggle')?.checked;
  const manual = document.getElementById('fileSearchManual');
  const sel    = document.getElementById('fileSearchSelect');
  const btn    = document.getElementById('fileSearchBtn');
  if (on) {
    sel.style.display = 'none';
    manual.style.display = ''; manual.focus();
    // Blind mode → the search button becomes a red "send" (the request goes to SCB).
    btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
    btn.style.background = '#ef4444';
  } else {
    sel.style.display = '';
    manual.style.display = 'none'; manual.value = '';
    btn.innerHTML = '<i class="fas fa-search"></i>';
    btn.style.background = '';
    clearTimeout(blindAutoLoadTimer);
  }
}
let blindAutoLoadTimer = null;
function onBlindManualInput() {
  if (!document.getElementById('fsBlindToggle')?.checked) return;
  const manual = document.getElementById('fileSearchManual');
  clearTimeout(blindAutoLoadTimer);
  if (!manual || !manual.value.trim()) return;
  blindAutoLoadTimer = setTimeout(() => searchFile(), 700);
}

// Resolve a file the way FileLocationResolver does: a known demo number returns
// its payload; anything else comes back as a blind/pending outcome.
function resolveDemoFile(val) {
  const key = Object.keys(DEMO_FILES).find(k => k.toUpperCase() === val.toUpperCase());
  if (key) return JSON.parse(JSON.stringify(DEMO_FILES[key]));
  return {
    file_number: val,
    file_title: null,
    status: 'PENDING_FILE',
    registry: null,
    origin_registry: null,
    rack_shelf: null,
    current_location: 'Not indexed',
    receiving_officer_name: '',
    next_action: 'Send Blind Request to SCB Monitor',
    can_send_fr: true,
    can_redirect: false,
    is_blind: true,
    is_indexed: false,
    dciv_status: 0,
    title_holders: null,
    holder_history: [],
    bill_balance: {},
    indexing_bills: {},
    digital: [],
    tracker: null,
  };
}

async function searchFile() {
  const sel    = document.getElementById('fileSearchSelect');
  const manual = document.getElementById('fileSearchManual');
  const blind  = document.getElementById('fsBlindToggle')?.checked;
  const result = document.getElementById('fileSearchResult');
  const val    = blind ? (manual?.value || '').trim().toUpperCase() : (sel.value || '').trim();
  if (!val) {
    result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:4px;">Please ${blind ? 'type' : 'select'} a file number.</p>`;
    return;
  }

  const btn = document.getElementById('fileSearchBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  result.innerHTML = '';
  await wait(420);

  const d = resolveDemoFile(val);
  lastFileSearch = d;
  const meta = LOC_STATUS_META[d.status] || { label: d.status, color: '#6b7185', icon: 'fa-file' };

  // Detail rows mirror the web Quick Search: an in-transit file is physically held
  // by a Receiving Officer in their department — show the holding Department as the
  // location and label the holder explicitly. Other statuses keep the expected
  // archive/pool location.
  let detailRows;
  if (d.status === 'IN_TRANSIT') {
    let dept = String(d.receiving_department || '').trim();
    if (dept && !/department$/i.test(dept)) dept = dept + ' Department';
    detailRows = [
      ['Registry', d.registry],
      ['Shelf/Rack', d.rack_shelf || '—'],
      ['Receiving Officer (holder)', mobAvatarRow(d.receiving_officer_name, d.receiving_officer_photo), true],
      ['Department', dept || d.current_location],
      ['Date Requested', d.date_requested],
      ['Date Collected', d.date_collected],
      ['Duration with holder', d.duration_with_holder],
    ];
  } else {
    detailRows = [
      ['Registry', d.registry],
      ['Shelf/Rack', d.rack_shelf || '—'],
      ['Current Location (Expected)', d.current_location],
      ['Receiving Officer', mobAvatarRow(d.receiving_officer_name, d.receiving_officer_photo), true],
    ];
  }
  // r[2] marks a value that is already HTML (an avatar + name), so it is not escaped again.
  const rowsHtml = detailRows.filter(r => r[1]).map(r =>
    `<div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--muted);">${esc(r[0])}</span><span style="font-size:13px;font-weight:600;text-align:right;">${r[2] ? r[1] : esc(r[1])}</span></div>`).join('');

  // Any located outcome warrants a physical check — some files were missing from the
  // start of the digitization exercise. Does NOT apply to a file already in transit.
  const physicalNote = (d.status && !/^IN_TRANSIT/.test(d.status) && d.status !== 'MISSING_FILE')
    ? `<p style="margin-top:8px;font-size:13px;font-style:italic;color:#ef4444;line-height:1.6;">The SCBs would have to do a Physical Check to ascertain the availability of the File as some files were missing even from the beginning of the Digitization Exercise</p>`
    : '';

  // A file under DCIV investigation is re-directed to the DCIV Director rather than
  // sent to the SCB. Derived from the server-computed next_action label.
  const isDciv = /DCIV Director/.test(d.next_action || '');
  // A duplicate_fileno flag only diverts the file to the Director Land when the
  // category carries a duplication to resolve.
  const directsToLand = !!(d.duplicate_flag && d.duplicate_flag.directs_to_land);

  const dupCandidates = Array.isArray(d.duplicate_candidates) ? d.duplicate_candidates : [];
  const hasCandidates = dupCandidates.length > 1;
  const hasIndexedCandidate = dupCandidates.some(c => c.source === 'file_indexings');
  selectedCandidate = null;   // cleared on every render

  // OFS send block, split so the File Digital Library can sit between the Registry
  // (Origin) selector and the Send button.
  let ofsRegistry = '', ofsButton = '';
  const renderScbForm = IS_OFS && d.can_send_fr && !isDciv && (!directsToLand || hasIndexedCandidate);
  const scbFormHidden = directsToLand && hasCandidates;
  if (renderScbForm) {
    const label = d.is_missing_file
      ? (d.is_blind ? 'Send Blind Request to the Original Registry' : 'Send File Search Request to the Original Registry')
      : (d.next_action || (d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor'));
    // Button colour matches the status label (e.g. In Pool Office = sky).
    const grad = `linear-gradient(135deg, ${meta.color}, ${shade(meta.color, 0.18)})`;
    const regOpts = REGISTRIES.map(r =>
      `<option value="${esc(r.name)}" data-code="${esc(r.registry_code || '')}"${d.origin_registry && r.name === d.origin_registry ? ' selected' : ''}>${esc(r.name)}</option>`).join('');
    const fieldSelCss = 'width:100%;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);';
    const fieldInpCss = 'width:100%;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);margin-top:8px;';
    const fieldLblCss = 'display:block;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:5px;';
    const deptOpts = REQ_DEPARTMENTS.map(dn => `<option value="${esc(dn)}">${esc(dn)}</option>`).join('');
    ofsRegistry = `
      <div id="fsScbRequestForm" style="margin-top:12px;display:${scbFormHidden ? 'none' : 'block'};">
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div>
            <label style="${fieldLblCss}"><i class="fas fa-building-columns" style="margin-right:5px;color:var(--primary);"></i>Registry (Origin) <span style="color:#ef4444;">*</span></label>
            <div style="display:flex;gap:8px;align-items:center;">
              <select id="fsRegistry" onchange="onFsRegistryChange()" style="flex:1;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);">
                <option value="">Select Registry (Origin)</option>
                ${regOpts}
              </select>
              <span id="fsRegistryCode" style="flex-shrink:0;min-width:54px;text-align:center;font-size:12px;font-weight:800;color:var(--primary);background:var(--primary-soft);border:1px solid var(--primary);border-radius:10px;padding:9px 8px;">—</span>
            </div>
          </div>
          <div>
            <label style="${fieldLblCss}"><i class="fas fa-building" style="margin-right:5px;color:var(--primary);"></i>Requester Office (Departments) <span style="color:#ef4444;">*</span></label>
            <select id="fsDept" onchange="onFsDeptChange()" style="${fieldSelCss}">
              <option value="">Select Department</option>
              ${deptOpts}
              <option value="${FS_DEPT_OTHER}">Other…</option>
            </select>
            <input id="fsDeptOther" type="text" placeholder="Specify department *" style="${fieldInpCss}display:none;">
          </div>
        </div>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
          <div>
            <label style="${fieldLblCss}"><i class="fas fa-briefcase" style="margin-right:5px;color:var(--primary);"></i>Requester Office <span style="color:#ef4444;">*</span></label>
            <select id="fsOffice" onchange="onFsOfficeChange()" disabled style="${fieldSelCss}">
              <option value="">Select Office</option>
            </select>
            <input id="fsOfficeOther" type="text" placeholder="Specify office *" style="${fieldInpCss}display:none;">
          </div>
          <div>
            <label style="${fieldLblCss}"><i class="fas fa-user-tie" style="margin-right:5px;color:var(--primary);"></i>Requester Officer <span style="color:#ef4444;">*</span></label>
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="flex:0 0 auto;display:flex;">${mobPassportTap(CURRENT_USER.photo_url || null, CURRENT_USER.name, 96)}</div>
              <div style="flex:1 1 auto;min-width:0;">
                <input id="fsOfficer" type="text" readonly value="${esc(CURRENT_USER.name)}" title="The logged-in officer raising this request" style="${fieldSelCss}opacity:.85;cursor:not-allowed;">
              </div>
            </div>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
          <div>
            <label style="${fieldLblCss}"><i class="fas fa-clipboard-list" style="margin-right:5px;color:var(--primary);"></i>Request Purpose <span style="color:#ef4444;">*</span></label>
            <select id="fsPurpose" onchange="onFsPurposeChange()" style="${fieldSelCss}">
              <option value="">Select the reason this file is being requested</option>
              ${REQUEST_PURPOSES.map(p => `<option value="${p.id}" data-turnaround-days="${p.turnaround_days}">${esc(p.name)}</option>`).join('')}
              <option value="in_transit">In-Transit</option>
              <option value="other">Other</option>
            </select>
            <input id="fsPurposeOther" type="text" placeholder="Specify the reason this file is being requested *" style="${fieldInpCss}display:none;">
          </div>
          <div id="fsTimelineDaysWrap">
            <label style="${fieldLblCss}"><i class="fas fa-hourglass-half" style="margin-right:5px;color:var(--primary);"></i>Timeline (Days)</label>
            <input id="fsTimelineDays" type="number" min="0" max="365" placeholder="e.g. 5" style="${fieldSelCss}">
          </div>
        </div>
      </div>`;
    // Send is disabled until an origin Registry is chosen. For a duplicate-number
    // picker the Director Land button is reused, so no second hidden button.
    if (!directsToLand) {
      ofsButton = `<button id="fsSendBtn" class="btn" disabled style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:${grad};opacity:.5;cursor:not-allowed;" onclick="sendFrFromSearch(this)"><i class="fas fa-paper-plane"></i> ${label}</button>`;
    }
  }

  // In-transit files: re-direct the request straight to the office currently holding
  // the file instead of routing it to the SCB Monitor.
  let redirectSend = '';
  if (IS_OFS && d.can_redirect) {
    const office = d.receiving_officer_name || d.current_location || 'Current Office';
    redirectSend = `<button class="btn" style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#f59e0b,#d97706);" onclick="redirectFromSearch(this)"><i class="fas fa-user-tag"></i> Re-direct Request to ${esc(office)}</button>`;
  }
  // A file under DCIV investigation is not blind-searched by the SCB.
  let dcivRedirectSend = '';
  if (IS_OFS && d.can_send_fr && isDciv) {
    dcivRedirectSend = `<button class="btn" style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:linear-gradient(to right, #0b3d2e, #065f46, #0b3d2e);" onclick="redirectDcivFromSearch(this)"><i class="fas fa-shield-halved"></i> Re-direct To DCIV Director (Land Department)</button>`;
  }
  // Duplicate / CofO / W-C-R files go to Director Land by default; selecting the
  // indexed record switches this action to the normal SCB request.
  let landRedirectSend = '';
  if (IS_OFS && d.can_send_fr && directsToLand) {
    const grad = 'linear-gradient(135deg,#9333ea,#7e22ce)';
    landRedirectSend = `<button class="btn" id="landRedirectBtn" ${hasCandidates ? 'disabled' : ''} data-land-background="${grad}" style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:${grad};${hasCandidates ? 'opacity:.5;' : ''}" onclick="redirectLandFromSearch(this)"><i class="fas fa-user-check"></i> Send Request to Director Land</button>`;
  }

  // Movement timeline opens from the section header. Shown for IN_TRANSIT and IN_ARCHIVE.
  const movementDetails = /^IN_TRANSIT|^IN_ARCHIVE/.test(d.status) ? `
    <details id="movementTimelineDetails" style="margin-bottom:12px;border:1px solid var(--border);border-radius:10px;" ontoggle="toggleMovementTimeline(this)">
      <summary style="cursor:pointer;list-style:none;padding:9px 12px;font-size:12px;font-weight:700;color:var(--text);">
        <i class="fas fa-stream" style="margin-right:6px;color:var(--primary);"></i>View Movement timeline
      </summary>
      <div style="padding:8px 12px;"><div id="fileMovementTimeline"></div></div>
    </details>` : '';

  // Mobile File Search is a read-only locator. But if this file has an OPEN File
  // Request waiting on this SCB Monitor, offer a Found/Not-Found shortcut.
  let frShortcut = '';
  if (IS_SCB_MONITOR) {
    const norm = s => String(s || '').trim().toUpperCase();
    const fr = DEMO_FR_OPEN.find(r => norm(r.file_number) === norm(d.file_number));
    if (fr) {
      frShortcut = `
        <div style="margin-top:12px;background:var(--primary-soft);border-radius:12px;padding:10px 12px;" data-fr="${fr.id}">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--primary);">Open File Request · ${esc(fr.request_no)}</div>
          <div style="display:flex;gap:8px;margin-top:10px;align-items:stretch;">
            <button class="btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFrFromSearch(${fr.id}, 'found', this, ${fr.is_blind ? 'true' : 'false'})"><i class="fas fa-check"></i> Found</button>
            <button class="btn ghost-btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;" onclick="respondFrFromSearch(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
          </div>
        </div>`;
    }
  }

  const holderBill = buildHolderBill(d);

  // When the file's origin registry is known, the status pill becomes "In {Registry}"
  // tinted with that registry's colour; otherwise it shows the resolved status.
  // Exception: an IN_TRANSIT file is NOT in its registry — it is out at another office.
  const badge = (d.origin_registry && !/^IN_TRANSIT/.test(d.status || '') && d.status !== 'MISSING_FILE')
    ? { label: 'In ' + d.origin_registry, color: REGISTRY_THEME[d.origin_registry] || meta.color, icon: 'fa-folder-open' }
    : meta;

  // Duplicate-registry notice sits above the file-picker gate.
  const duplicateFlagHtml = d.duplicate_flag ? `
    <div style="margin-bottom:10px;background:${d.duplicate_flag.color}14;border:1px solid ${d.duplicate_flag.color}55;border-radius:12px;padding:10px 12px;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:13px;font-weight:800;color:${d.duplicate_flag.color};"><i class="fas fa-copy" style="margin-right:5px;"></i>${esc(d.duplicate_flag.label)}</span>
      </div>
      ${(d.duplicate_flag.entries && d.duplicate_flag.entries.length > 1) ? `
      <div style="margin-top:6px;padding-left:22px;display:flex;flex-direction:column;gap:2px;">
        ${d.duplicate_flag.entries.map(e => `<div style="font-size:11px;"><span style="font-weight:700;color:${d.duplicate_flag.color};">${esc(e.file_number)}</span> <span style="color:var(--muted);">${esc(e.file_title || '—')}</span></div>`).join('')}
      </div>` : ''}
    </div>` : '';

  const DASH = '—';
  result.innerHTML = `
    ${duplicateFlagHtml}
    ${/* The pick-a-file gate sits ABOVE the result card: until a record is chosen the
          card below describes only ONE of the files sharing this number. */''}
    ${hasCandidates ? `
    <div style="margin-bottom:10px;background:#f59e0b14;border:1px solid #f59e0b55;border-radius:18px;box-shadow:var(--shadow-sm);padding:12px 14px;">
      <div style="font-size:13px;font-weight:800;color:#b45309;"><i class="fas fa-layer-group" style="margin-right:5px;"></i>${dupCandidates.length} files under this number</div>
      <div style="margin-top:3px;font-size:11px;color:#b45309;">Select the exact file you are looking for. The request carries the record you pick, not just the file number.</div>
      <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        ${dupCandidates.map(c => `
        <label style="display:flex;gap:10px;background:var(--card);border:1px solid #f59e0b40;border-radius:10px;padding:10px;">
          <input type="radio" name="dupCandidate" style="margin-top:3px;flex-shrink:0;" onchange="pickDupCandidate(${Number(c.record_id)}, '${esc(c.source)}', this)">
          <div style="min-width:0;flex:1;">
            <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">
              <span style="font-size:13px;font-weight:800;color:var(--text);">${esc(c.file_number)}</span>
              <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;padding:2px 7px;border-radius:999px;background:${c.source === 'duplicate_fileno' ? '#fef3c7;color:#92400e' : '#dbeafe;color:#1e40af'};">${esc(c.source_label)}</span>
            </div>
            <div style="margin-top:2px;font-size:12px;color:var(--text);">${esc(c.holder || DASH)}</div>
            <div style="margin-top:4px;font-size:10px;color:var(--muted);line-height:1.6;">
              ${c.registry ? `<span style="margin-right:10px;"><b>Registry:</b> ${esc(c.registry)}</span>` : ''}
              ${c.rack_shelf ? `<span style="margin-right:10px;"><b>Shelf/Rack:</b> ${esc(c.rack_shelf)}</span>` : ''}
              ${c.current_location ? `<span style="margin-right:10px;"><b>Location:</b> ${esc(c.current_location)}</span>` : ''}
              <span><b>Status:</b> ${esc(c.status_label)}</span>
            </div>
          </div>
        </label>`).join('')}
      </div>
    </div>` : ''}
    <div class="result-card">
      <div style="background:var(--surface-2);padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <div>
          <div style="font-size:15px;font-weight:800;">${esc(d.file_number)}${d.linked_file_number ? ` <span style="font-size:12px;font-weight:700;color:var(--muted);">(${esc(d.linked_file_number)})</span>` : ''}</div>
          <div style="font-size:12px;font-weight:600;color:var(--text);opacity:.82;margin-top:3px;">${esc(d.file_title || '—')}</div>
        </div>
        <span style="white-space:nowrap;font-size:11px;font-weight:700;color:${badge.color};background:${badge.color}1a;border:1px solid ${badge.color}55;padding:5px 10px;border-radius:30px;"><i class="fas ${badge.icon}" style="margin-right:4px;"></i>${esc(badge.label)}</span>
      </div>
      <div style="padding:12px 16px;">
        ${Number(d.dciv_status) === 1 ? `
        <div style="margin-bottom:12px;background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:10px 12px;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:800;color:#be123c;"><i class="fas fa-triangle-exclamation" style="margin-right:5px;"></i>Under Investigation</span>
            ${d.dciv_fileno ? `<span style="font-size:11px;font-weight:700;color:#be123c;background:#ffe4e6;border:1px solid #fecdd3;padding:3px 9px;border-radius:30px;white-space:nowrap;">${esc(d.dciv_fileno)}</span>` : ''}
          </div>
          ${d.dciv_reason ? `<div style="font-size:11px;color:#9f1239;margin-top:6px;line-height:1.5;">${esc(d.dciv_reason)}</div>` : ''}
        </div>` : ''}
        ${renderRelatedFiles(d.dciv_related_files || [])}
        ${(d.status === 'MISSING_FILE' && d.is_indexed) ? `
        <div style="margin-bottom:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:10px 12px;">
          <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-circle-info" style="color:#2563eb;"></i>
            <span style="font-size:13px;font-weight:700;color:#1e40af;">This file was indexed and returned to the Original Registry before archiving.</span>
          </div>
          ${(d.registry || d.rack_shelf) ? `
          <div style="margin-top:6px;padding-left:22px;display:flex;flex-wrap:wrap;gap:12px;font-size:11px;color:#1e40af;">
            ${d.registry ? `<span><strong>Registry:</strong> ${esc(d.registry)}</span>` : ''}
            ${d.rack_shelf ? `<span><strong>Shelf/Rack:</strong> ${esc(d.rack_shelf)}</span>` : ''}
          </div>` : ''}
        </div>` : ''}
        ${holderBill}
        ${movementDetails}
        ${rowsHtml}
        ${ofsRegistry}
        <div id="fsDigital" style="margin-top:12px;"></div>
        ${ofsButton}
        ${redirectSend}
        ${dcivRedirectSend}
        ${landRedirectSend}
        ${physicalNote}
        ${frShortcut}
      </div>
    </div>`;

  // Sync the pre-selected Registry (Origin) when it was auto-detected.
  if (d.origin_registry && document.getElementById('fsRegistry')) onFsRegistryChange();
  else loadFsDigital(d.file_number);

  btn.disabled = false;
  btn.innerHTML = blind ? '<i class="fas fa-paper-plane"></i>' : '<i class="fas fa-search"></i>';
}

function renderRelatedFiles(rf) {
  if (!rf.length) return '';
  const item = f => `
    <div style="font-size:11px;color:#065f46;">
      <span style="font-weight:700;background:#d1fae5;border:1px solid #a7f3d0;padding:2px 8px;border-radius:30px;white-space:nowrap;">${esc(f.related_file_number)}</span>
      ${f.related_file_title ? ` — ${esc(f.related_file_title)}` : ''}
    </div>`;
  const list = `<div style="margin-top:6px;display:flex;flex-direction:column;gap:4px;">${rf.map(item).join('')}</div>`;
  return rf.length > 1
    ? `<details style="margin-bottom:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;">
         <summary style="cursor:pointer;list-style:none;font-size:13px;font-weight:800;color:#047857;"><i class="fas fa-link" style="margin-right:5px;"></i>Linked Related Files (${rf.length})</summary>
         ${list}
       </details>`
    : `<div style="margin-bottom:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;">
         <span style="font-size:13px;font-weight:800;color:#047857;"><i class="fas fa-link" style="margin-right:5px;"></i>Linked Related Files (${rf.length})</span>
         ${list}
       </div>`;
}

// Holders + latest Deeds Bill Balance — collapsed by default, sits right under the
// file-number header. Always shown; the bill section falls back to "—".
function buildHolderBill(d) {
  const DASH = '<span style="color:var(--muted);">—</span>';
  const has = v => !(v === null || v === undefined || v === '');
  const dRow = (l, v) => `<div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--muted);">${esc(l)}</span><span style="font-size:12px;font-weight:600;text-align:right;">${has(v) ? esc(v) : DASH}</span></div>`;
  const money = v => '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const dMoney = (l, v) => dRow(l, has(v) ? money(v) : null);
  const dTotal = (l, v) => `<div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;"><span style="font-size:12px;font-weight:800;">${esc(l)}</span><span style="font-size:13px;font-weight:800;text-align:right;">${has(v) ? money(v) : DASH}</span></div>`;

  const bb = d.bill_balance || {};
  const ib = d.indexing_bills || {};
  const billHtml = `
    <details style="margin-top:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;">
      <summary style="cursor:pointer;list-style:none;padding:8px 10px;font-size:11px;font-weight:800;color:var(--text);"><i class="fas fa-file-invoice-dollar" style="margin-right:6px;color:var(--primary);"></i>Bill Balance Details</summary>
      <div style="padding:0 10px 8px;">
        ${dTotal('Balance', bb.balance_due)}
        ${dMoney('Bill Balance', ib.bill_balance)}${dMoney('Ground Rent', ib.grant_rent)}
      </div>
    </details>`;

  // Original Holder / Current Holder — two DIFFERENT concepts, resolved
  // server-side by TitleHolderResolver. Each line carries its own colour from
  // the resolver's `tone`. (Root of Title is deliberately not shown here.)
  const TONES = {
    amber:   { label: '#b45309', value: '#92400e' },
    emerald: { label: '#047857', value: '#065f46' },
    indigo:  { label: '#4338ca', value: '#3730a3' },
    gray:    { label: 'var(--muted)', value: 'var(--text)' },
  };
  const tRow = (l, v, tone) => {
    const t = TONES[tone] || TONES.gray;
    return `<div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--border);">`
      + `<span style="font-size:11px;font-weight:700;color:${t.label};">${esc(l)}</span>`
      + `<span style="font-size:12px;font-weight:600;text-align:right;color:${has(v) ? t.value : 'var(--muted)'};">${has(v) ? esc(v) : DASH}</span></div>`;
  };
  const th = d.title_holders;
  let titleHoldersHtml;
  if (!th) {
    // Not indexed — no chain to interpret; keep the legacy two rows.
    const origVal = has(d.original_holder) ? d.original_holder : d.current_holder;
    const currVal = has(d.current_holder) ? d.current_holder : d.original_holder;
    titleHoldersHtml = tRow('Original Holder', origVal, 'emerald') + tRow('Current Holder', currVal, 'indigo');
  } else {
    titleHoldersHtml = `
      <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin:2px 0 6px;">Title</div>
      ${(th.lines || []).map(l => tRow(l.label, l.value, l.tone)).join('')}`;
  }

  // Ownership history — a vertical timeline of the dealings on the file. Mortgage
  // and Surrender And Release nodes are hidden from the holders list.
  let hist = (Array.isArray(d.holder_history) ? d.holder_history : []).filter(h => {
    const t = String(h.transaction_type || '').toLowerCase();
    return !t.includes('mortgage') && !t.includes('surrender');
  });
  let holderHistoryHtml = '';
  if (hist.length) {
    const abbrevType = t => {
      if (!t) return '';
      const s = String(t).toLowerCase();
      if (s.includes('right of occupancy')) return 'RofO';
      if (s.includes('certificate of occupancy')) return 'CofO';
      return String(t).replace(/^deed of\s+/i, '').trim();
    };
    // Nodes are NOT labelled Original or Current Holder — position in the chain does
    // not decide either role (spec §12); the Title block above carries the answer.
    const buildNode = (h, isFirst, isLast) => {
      const dot = isFirst ? '#059669' : (isLast ? 'var(--primary)' : 'var(--surface-3)');
      const dotIcon = (isFirst || isLast) ? '#fff' : 'var(--muted)';
      const line = !isLast ? `<span style="position:absolute;left:6px;top:17px;bottom:-2px;width:2px;background:var(--border);"></span>` : '';
      const type = abbrevType(h.transaction_type);
      return `
        <div style="position:relative;padding-left:24px;padding-bottom:${isLast ? '0' : '7px'};">
          ${line}
          <span style="position:absolute;left:0;top:1px;width:15px;height:15px;border-radius:50%;background:${dot};border:2px solid var(--border);display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-user" style="font-size:7px;color:${dotIcon};"></i>
          </span>
          <div style="font-size:13px;font-weight:700;color:var(--text);line-height:1.3;">${esc(h.to || h.holder)}</div>
          <div style="font-size:11px;font-weight:600;color:var(--muted);margin-top:2px;">${esc(h.date || '')}${type ? ` <span style="color:var(--muted);font-weight:500;">(${esc(type)})</span>` : ''}</div>
        </div>`;
    };
    holderHistoryHtml = `
      <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin:10px 0;">Dealings</div>
      <div style="padding:0 2px 2px;">${hist.map((h, i) => buildNode(h, i === 0, i === hist.length - 1)).join('')}</div>`;
  }

  return `
    <details style="margin-bottom:12px;border:1px solid var(--border);border-radius:10px;">
      <summary style="cursor:pointer;list-style:none;padding:9px 12px;font-size:12px;font-weight:700;color:var(--text);"><i class="fas fa-user-tag" style="margin-right:6px;color:var(--primary);"></i>Holders &amp; Bill Balance Details</summary>
      <div style="padding:2px 12px 10px;">
        ${titleHoldersHtml}
        ${holderHistoryHtml}
        ${billHtml}
      </div>
    </details>`;
}

/* ─── OFS request form cascade ─────────────────────────────────────────── */
function onFsRegistryChange() {
  const sel = document.getElementById('fsRegistry');
  if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  const code = opt ? (opt.getAttribute('data-code') || '') : '';
  const codeEl = document.getElementById('fsRegistryCode');
  if (codeEl) codeEl.textContent = code || '—';
  const colour = REGISTRY_THEME[sel.value];
  if (colour && codeEl) { codeEl.style.color = colour; codeEl.style.borderColor = colour; codeEl.style.background = colour + '1a'; }
  const send = document.getElementById('fsSendBtn');
  if (send) {
    const on = !!sel.value;
    send.disabled = !on;
    send.style.opacity = on ? '1' : '.5';
    send.style.cursor = on ? 'pointer' : 'not-allowed';
  }
  loadFsDigital(lastFileSearch ? lastFileSearch.file_number : '', sel.value);
}
function onFsDeptChange() {
  const dept = document.getElementById('fsDept');
  const other = document.getElementById('fsDeptOther');
  const office = document.getElementById('fsOffice');
  if (!dept || !office) return;
  const isOther = dept.value === FS_DEPT_OTHER;
  if (other) other.style.display = isOther ? '' : 'none';
  const offices = DEMO_OFFICES.filter(o => o.department === dept.value);
  office.disabled = !dept.value || isOther;
  office.innerHTML = '<option value="">Select Office</option>' +
    offices.map(o => `<option value="${esc(o.code)}">${esc(o.name)}</option>`).join('') +
    `<option value="${FS_OFFICE_OTHER}">Other…</option>`;
}
function onFsOfficeChange() {
  const office = document.getElementById('fsOffice');
  const other = document.getElementById('fsOfficeOther');
  if (other) other.style.display = office && office.value === FS_OFFICE_OTHER ? '' : 'none';
}
function onFsPurposeChange() {
  const sel = document.getElementById('fsPurpose');
  const other = document.getElementById('fsPurposeOther');
  const days = document.getElementById('fsTimelineDays');
  if (!sel) return;
  if (other) other.style.display = sel.value === 'other' ? '' : 'none';
  const opt = sel.options[sel.selectedIndex];
  const t = opt ? opt.getAttribute('data-turnaround-days') : null;
  if (days && t) days.value = t;     // default turnaround carries into Timeline (Days)
}

// The searched number is registered in BOTH file_indexings and duplicate_fileno, so
// it no longer identifies one physical file: the request carries the record picked.
function pickDupCandidate(recordId, source, radio) {
  selectedCandidate = { record_id: recordId, source };
  const landBtn = document.getElementById('landRedirectBtn');
  const scbForm = document.getElementById('fsScbRequestForm');
  const indexed = source === 'file_indexings';
  if (scbForm) scbForm.style.display = indexed ? 'block' : 'none';
  if (landBtn) {
    landBtn.disabled = false;
    landBtn.style.opacity = '1';
    if (indexed) {
      // An indexed selection may be sent to the SCB for physical confirmation.
      landBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send File Search Request to SCB Monitor';
      landBtn.style.background = 'linear-gradient(135deg,#10b981,#059669)';
      landBtn.onclick = function () { sendFrFromSearch(this); };
    } else {
      landBtn.innerHTML = '<i class="fas fa-user-check"></i> Send Request to Director Land';
      landBtn.style.background = landBtn.getAttribute('data-land-background');
      landBtn.onclick = function () { redirectLandFromSearch(this); };
    }
  }
  if (radio) radio.checked = true;
}

async function sendFrFromSearch(btn) {
  const registry = document.getElementById('fsRegistry')?.value;
  const dept     = document.getElementById('fsDept')?.value;
  const office   = document.getElementById('fsOffice')?.value;
  const purpose  = document.getElementById('fsPurpose')?.value;
  if (!registry) { toast('Select the Registry (Origin)', 'error'); return; }
  if (!dept)     { toast('Select the Requester Office (Departments)', 'error'); return; }
  if (!office)   { toast('Select the Requester Office', 'error'); return; }
  if (!purpose)  { toast('Select the Request Purpose', 'error'); return; }

  const original = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  await wait(650);
  const no = 'FSR-2026-0' + (700 + Math.floor(Math.random() * 99));
  DEMO_MY_REQUESTS.unshift({
    id: Date.now(), request_no: no,
    file_number: lastFileSearch?.file_number || '—',
    file_title: lastFileSearch?.file_title || '—',
    status: 'PENDING',
    request_type: lastFileSearch?.is_blind ? 'Blind Request' : 'Open Request',
    is_blind: !!lastFileSearch?.is_blind, is_dfr: false, is_ofs: true,
    current_location: lastFileSearch?.current_location || '—',
    created_at: new Date().toLocaleString('en-GB').replace(',', ''),
  });
  toast(`File Request ${no} sent to the SCB Monitor`);
  btn.innerHTML = '<i class="fas fa-check"></i> Request sent';
}
async function redirectFromSearch(btn) {
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  await wait(600);
  toast('Request re-directed to the officer holding the file');
  btn.innerHTML = '<i class="fas fa-check"></i> Re-directed';
}
async function redirectDcivFromSearch(btn) {
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  await wait(600);
  toast('Re-directed to the DCIV Director (Land Department)');
  btn.innerHTML = '<i class="fas fa-check"></i> Re-directed';
}
async function redirectLandFromSearch(btn) {
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  await wait(600);
  toast('Request sent to Director Land');
  btn.innerHTML = '<i class="fas fa-check"></i> Sent to Director Land';
}

/* ══════════════════════════════════════════════════════════════════════════
   8. MOVEMENT TIMELINE                        // API → /file-trackers/track/{id}
   ══════════════════════════════════════════════════════════════════════════ */
function toggleMovementTimeline(detailsEl) {
  const container = document.getElementById('fileMovementTimeline');
  if (!container || !detailsEl || !detailsEl.open) return;
  if (container.dataset.loaded === '1') return;

  const d = lastFileSearch || {};
  const originRegistry = d.origin_registry || d.registry || '';
  const rackShelf = d.rack_shelf || '';

  // Default "In Archive" home row — mirrors the permanent registry/archive location
  // shown at the top of the movement log in the desktop Create File Tracker view.
  const inArchiveHomeRow = `
    <div style="position:relative;padding:0 0 14px 26px;margin-left:2px;">
      <span style="position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border);"></span>
      <span style="position:absolute;left:2px;top:6px;width:14px;height:14px;border-radius:50%;background:#10b981;border:3px solid var(--surface);box-shadow:0 0 0 1px rgba(16,185,129,.3);"></span>
      <div style="padding:0 4px 0 0;">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
          <div style="min-width:0;flex:1 1 65%;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Archive / Registry</div>
            <div style="font-size:13px;color:var(--text);font-weight:700;line-height:1.35;">
              <i class="fas fa-box-archive" style="margin-right:5px;color:#10b981;"></i>${esc(originRegistry || 'Registry / Archive')}${rackShelf ? ` — Shelf/Rack ${esc(rackShelf)}` : ''}
            </div>
          </div>
          <div style="min-width:0;flex:1 1 30%;text-align:right;">
            <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;background:#d1fae5;color:#166534;border:1px solid #a7f3d0;">In Archive</span>
          </div>
        </div>
      </div>
    </div>`;

  const tracker = d.tracker;
  const allLogs = (tracker && Array.isArray(tracker.movement_history) ? tracker.movement_history.slice() : []).sort(compareMovementEntries);
  if (!allLogs.length) {
    // "Never tracked" is an empty history, not a failure.
    container.innerHTML = `${inArchiveHomeRow}<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">No movement history available for this file.</div>`;
    container.dataset.loaded = '1';
    return;
  }

  const approvalPurposes = ['recommendation', 'approval'];
  const movementLogs = allLogs.filter(e => !approvalPurposes.includes(String(e.purpose || '').toLowerCase()));
  const approvalLogs = allLogs.filter(e =>  approvalPurposes.includes(String(e.purpose || '').toLowerCase()));

  // Request Purpose / Timeline / Expected Return Date belong to the current tracker
  // (one per tracking cycle), not the individual movement entry.
  const trackerMeta = {
    officerPhotos: {},
    requestPurposeName: tracker.request_purpose_name || '',
    timelineStatus: tracker.timeline_status || null,
    daysUntilDeadline: tracker.days_until_deadline ?? null,
    expectedReturnDate: tracker.deadline || null,
  };

  container.innerHTML = `
    <div style="margin-top:12px;font-size:13px;font-weight:700;color:var(--text);">Movement timeline</div>
    <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
      ${inArchiveHomeRow}
      ${movementLogs.length ? movementLogs.map(e => renderMovementRow(e, trackerMeta)).join('')
        : `<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">No physical movement entries found. Approval steps may still be present below.</div>`}
    </div>
    ${approvalLogs.length ? `
      <div style="margin-top:16px;font-size:13px;font-weight:700;color:var(--text);">Workflow approvals</div>
      <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
        ${approvalLogs.map(renderApprovalRow).join('')}
      </div>` : ''}`;
  container.dataset.loaded = '1';
}

function compareMovementEntries(a, b) { return movementEntryTimestamp(a) - movementEntryTimestamp(b); }
function movementEntryTimestamp(entry) {
  const parse = (date, time) => {
    const d = (date || '').toString().trim();
    if (!d) return null;
    const t = (time || '').toString().trim() || '00:00';
    const p = Date.parse(`${d} ${t}`);
    return Number.isNaN(p) ? null : p;
  };
  const inTs = parse(entry.log_in_date, entry.log_in_time);
  if (inTs !== null) return inTs;
  const outTs = parse(entry.log_out_date, entry.log_out_time);
  if (outTs !== null) return outTs;
  return Number.POSITIVE_INFINITY;
}
function toAmPm(timeStr) {
  if (!timeStr) return '';
  const parts = timeStr.toString().trim().split(':');
  if (parts.length < 2) return timeStr;
  let h = parseInt(parts[0], 10);
  const m = parts[1].padStart(2, '0');
  if (isNaN(h)) return timeStr;
  const period = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${m} ${period}`;
}
function formatMovementDate(date, time) {
  const d = (date || '').toString().trim();
  if (!d) return '—';
  if (!time) return esc(d);
  return `${esc(d)} ${esc(toAmPm(time))}`;
}
function resolveMovementStatus(entry) {
  const rawOverride = (entry.status_label || entry.new_status || '').toString().trim();
  const rawStatus = (entry.status || '').toString().trim().toLowerCase();
  const normalize = v => v.toLowerCase().replace(/_/g, ' ');
  if (rawOverride) {
    switch (normalize(rawOverride)) {
      case 'log-in': case 'log in':
        return { label: 'Log-in',  style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
      case 'log-out': case 'log out':
        return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
      case 'pending acceptance': case 'in-transit': case 'in transit':
        return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
      case 'rejected':
        return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
      case 'cancelled': case 'canceled':
        return { label: 'Cancelled', style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;' };
      default:
        return { label: rawOverride, style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
    }
  }
  switch (rawStatus) {
    case 'pending_acceptance': return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
    case 'active':             return { label: 'Log-out',    style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
    case 'completed':          return { label: 'Log-in',     style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
    case 'rejected':           return { label: 'Rejected',   style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
    default: return { label: String(entry.status || 'Completed').replace(/_/g, ' '), style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
  }
}
// Green/Amber/Red timeline label + colour for a tracker — mirrors
// FileTracker::getTimelineStatusAttribute() / the desktop day-count badge.
function formatTimelineMeta(meta) {
  const byStatus = {
    green:   { color: '#166534', bg: '#d1fae5', border: '#a7f3d0' },
    amber:   { color: '#78350f', bg: '#fef9c3', border: '#fde68a' },
    red:     { color: '#b91c1c', bg: '#fee2e2', border: '#fecaca' },
    // The clock has not started: the file is still being searched for.
    pending: { color: '#475569', bg: '#e2e8f0', border: '#cbd5e1' },
  };
  if (!meta || !meta.timelineStatus || !byStatus[meta.timelineStatus]) return null;
  if (meta.timelineStatus === 'pending') return { label: 'Pending', icon: 'fa-clock', ...byStatus.pending };

  const days = meta.daysUntilDeadline;
  let label;
  if (days === null || days === undefined) {
    label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timelineStatus];
  } else if (days > 0) {
    label = `${days} day${days === 1 ? '' : 's'} left`;
  } else if (days === 0) {
    label = 'Due today';
  } else {
    const abs = Math.abs(days);
    label = `${abs} day${abs === 1 ? '' : 's'} overdue`;
  }
  return { label, ...byStatus[meta.timelineStatus] };
}
function formatExpectedReturnDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return esc(String(value).slice(0, 10));
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

function renderMovementRow(entry, trackerMeta) {
  const office = esc(entry.office_name || entry.office || 'Unknown');
  const officerNameRaw = entry.receiving_officer_name || '-';
  // Face per log row, from the same avatar helper the "Receiving Officer (holder)"
  // line above the timeline uses. Rows that name a place carry no officer id and
  // fall back to initials.
  const officerHtml = mobAvatarRow(officerNameRaw, null, 26) || esc(officerNameRaw);
  const status = resolveMovementStatus(entry);
  const hasLogIn = status.label === 'Log-in' || status.label === 'Completed';
  const inDate = hasLogIn ? formatMovementDate(entry.log_in_date, entry.log_in_time) : '-';
  const outDate = entry.log_out_date
    ? formatMovementDate(entry.log_out_date, entry.log_out_time)
    : ['active', 'pending_acceptance', 'in-transit', 'in transit'].includes((entry.status || '').toString().trim().toLowerCase())
      ? 'In transit' : '-';
  const notes = entry.notes ? `<div style="margin-top:8px;font-size:12px;color:var(--muted);">${esc(entry.notes)}</div>` : '';

  // Request Purpose / Timeline / Expected Return Date (tracker-level, same for every
  // row) and Delay Reason (per-entry) — Request Purpose always precedes Timeline,
  // per the desktop File Log Table column order.
  const timelineMeta = formatTimelineMeta(trackerMeta);
  const requestPurposeName = trackerMeta?.requestPurposeName ? esc(trackerMeta.requestPurposeName) : '—';
  const expectedReturnDate = formatExpectedReturnDate(trackerMeta?.expectedReturnDate);
  const delayReason = entry.delay_reason ? esc(entry.delay_reason) : '—';

  return `
    <div style="position:relative;padding:0 0 14px 26px;margin-left:2px;">
      <span style="position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border);"></span>
      <span style="position:absolute;left:2px;top:6px;width:14px;height:14px;border-radius:50%;background:var(--primary);border:3px solid var(--surface);box-shadow:0 0 0 1px var(--primary-soft);"></span>
      <div style="padding:0 4px 0 0;">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
          <div style="min-width:0;flex:1 1 65%;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Office</div>
            <div style="font-size:13px;color:var(--text);font-weight:700;line-height:1.35;">${office}</div>
          </div>
          <div style="min-width:0;flex:1 1 30%;text-align:right;">
            <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;${status.style}">${esc(status.label)}</span>
          </div>
        </div>
        <div style="margin-top:10px;display:grid;gap:9px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Receiving Officer</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${officerHtml}</div>
          </div>
          <div style="display:grid;gap:7px;">
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Log In</div>
              <div style="font-size:13px;color:var(--text);font-weight:600;">${inDate}</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Log Out</div>
              <div style="font-size:13px;color:var(--text);font-weight:600;">${outDate}</div>
            </div>
          </div>
        </div>
        <div style="margin-top:10px;display:grid;gap:9px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Request Purpose</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${requestPurposeName}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Timeline</div>
            <div style="font-size:13px;">${timelineMeta
              ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>`
              : `<span style="color:var(--muted);">—</span>`}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Expected Return Date</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${expectedReturnDate}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Delay Reason</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${delayReason}</div>
          </div>
        </div>
        ${notes}
      </div>
    </div>`;
}

function renderApprovalRow(entry) {
  const office = esc(entry.office_name || entry.office || 'Unknown');
  const officer = esc(entry.receiving_officer_name || '—');
  const eventDate = formatMovementDate(entry.log_in_date, entry.log_in_time);
  const status = resolveMovementStatus(entry);
  const purposeLabel = String(entry.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval';
  const notes = entry.notes ? `<div style="margin-top:8px;font-size:12px;color:var(--muted);">${esc(entry.notes)}</div>` : '';
  return `
    <div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-3);">
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
        <div style="min-width:0;flex:1 1 65%;">
          <div style="font-size:12px;font-weight:700;color:var(--text);">${purposeLabel}</div>
          <div style="margin-top:6px;font-size:12px;color:var(--muted);">${eventDate}</div>
        </div>
        <div style="min-width:0;flex:1 1 30%;text-align:right;">
          <span style="padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;${status.style}">${esc(status.label)}</span>
        </div>
      </div>
      <div style="margin-top:10px;display:grid;gap:10px;font-size:12px;color:var(--text);">
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Office</div><div>${office}</div></div>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Officer</div><div>${officer}</div></div>
      </div>
      ${notes}
    </div>`;
}

/* ══════════════════════════════════════════════════════════════════════════
   9. FILE DIGITAL LIBRARY (cover card + fullscreen gallery)
   ══════════════════════════════════════════════════════════════════════════ */
let digFiles = [], digIndex = 0, digRotation = 0;

function loadFsDigital(fileNo, registry = null) {
  const box = document.getElementById('fsDigital');
  if (!box) return;
  const d = lastFileSearch || {};
  digFiles = Array.isArray(d.digital) ? d.digital : [];
  if (!digFiles.length) {
    box.innerHTML = `<div class="dig-empty"><i class="fas fa-image"></i> No digital copy found for this file${registry ? ` in ${esc(registry)}` : ''}.</div>`;
    return;
  }
  box.innerHTML = `
    <div class="dig-cover" onclick="openDigViewer(0)">
      <div class="dig-cover-stack">
        <div class="dig-cover-page"><img src="${esc(digFiles[0].url)}" alt=""></div>
      </div>
      <div class="dig-cover-meta">
        <div class="dig-cover-title"><i class="fas fa-book-open"></i> File Digital Library</div>
        <div class="dig-cover-sub">${digFiles.length} page${digFiles.length === 1 ? '' : 's'} scanned${registry ? ` · ${esc(registry)}` : ''}</div>
        <div class="dig-cover-cta">Tap to open <i class="fas fa-arrow-right"></i></div>
      </div>
      <i class="fas fa-chevron-right dig-cover-chev"></i>
    </div>`;
}
function openDigViewer(i) {
  if (!digFiles.length) return;
  digIndex = i; digRotation = 0;
  document.getElementById('digViewer').classList.add('open');
  renderDigViewer(); buildDigStrip();
}
function closeDigViewer() { document.getElementById('digViewer').classList.remove('open'); }
function digNext() { if (digIndex < digFiles.length - 1) { digIndex++; digRotation = 0; renderDigViewer(); } }
function digPrev() { if (digIndex > 0) { digIndex--; digRotation = 0; renderDigViewer(); } }
function digGoto(i) { digIndex = Math.max(0, Math.min(i, digFiles.length - 1)); digRotation = 0; renderDigViewer(); }
function digRotate(dir) { digRotation = (digRotation + dir * 90 + 360) % 360; applyImgRotation(); }
function applyImgRotation() {
  const img = document.querySelector('#digStageInner img');
  if (img) img.style.transform = `rotate(${digRotation}deg)`;
}
function renderDigViewer() {
  const f = digFiles[digIndex];
  document.getElementById('digVName').textContent = f.name;
  document.getElementById('digVCount').textContent = `${digIndex + 1} of ${digFiles.length}`;
  document.getElementById('digStageInner').innerHTML = `<img src="${esc(f.url)}" alt="${esc(f.name)}">`;
  document.getElementById('digNavPrev').disabled = digIndex === 0;
  document.getElementById('digNavNext').disabled = digIndex === digFiles.length - 1;
  applyImgRotation();
  document.querySelectorAll('.dig-strip-item').forEach((el, i) => el.classList.toggle('active', i === digIndex));
}
function buildDigStrip() {
  document.getElementById('digStrip').innerHTML = digFiles.map((f, i) => `
    <div class="dig-strip-item ${i === digIndex ? 'active' : ''}" onclick="digGoto(${i})">
      <img src="${esc(f.url)}" alt=""><span class="dig-strip-n">${i + 1}</span>
    </div>`).join('');
}

/* ══════════════════════════════════════════════════════════════════════════
   10. FILE REQUESTS — OFS "My FRs" + SCB Monitor inbox / history
   ══════════════════════════════════════════════════════════════════════════ */

// ─── OFS: the requests this user raised ──────────────────────────────────
async function loadMyRequests() {
  const box = document.getElementById('myReqContainer');
  if (!box) return;
  box.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  await wait(300);
  if (!DEMO_MY_REQUESTS.length) {
    box.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-inbox fa-2x"></i><p style="margin-top:12px;">You haven\'t sent any file requests yet.</p></div>';
    return;
  }
  box.innerHTML = DEMO_MY_REQUESTS.map(renderMyRequestCard).join('');
}

function renderMyRequestCard(fr) {
  const m = FSR_STATUS_META[fr.status] || FSR_STATUS_META.PENDING;
  // Logged-out outcome: green "to your office" callout if it's out to this user,
  // otherwise a neutral note showing where the file currently is.
  let loggedOut = '';
  if (fr.logged_out) {
    loggedOut = fr.logged_out_to_me
      ? `<div style="margin-top:8px;display:flex;align-items:center;gap:7px;background:rgba(16,185,129,0.12);border:1px solid #10b98155;border-radius:10px;padding:8px 10px;font-size:11.5px;font-weight:700;color:#059669;"><i class="fas fa-building-circle-check"></i> Logged out to your office${fr.logged_out_office ? ' · ' + esc(fr.logged_out_office) : ''}</div>`
      : `<div style="margin-top:8px;display:flex;align-items:center;gap:7px;background:rgba(245,158,11,0.12);border:1px solid #f59e0b55;border-radius:10px;padding:8px 10px;font-size:11.5px;font-weight:700;color:#b45309;"><i class="fas fa-arrow-right-from-bracket"></i> Logged out${fr.logged_out_office ? ' to ' + esc(fr.logged_out_office) : ''}${fr.handler ? ' · ' + esc(fr.handler) : ''}</div>`;
  }
  return `
    <div class="result-card" style="margin-bottom:12px;${fr.is_ofs ? 'border-left:4px solid #f59e0b;' : ''}">
      <div style="padding:14px 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
          <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
          <span style="white-space:nowrap;font-size:10px;font-weight:800;padding:3px 9px;border-radius:30px;background:${m.bg};color:${m.fg};">${esc(m.label)}</span>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">${esc(fr.file_title || '—')}</div>
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:${fr.is_dfr ? '#e5e7eb' : (fr.is_blind ? '#fee2e2' : '#e0f2fe')};color:${fr.is_dfr ? '#374151' : (fr.is_blind ? '#b91c1c' : '#0369a1')};"><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}"></i> ${esc(fr.request_type || 'Open Request')}</span>
          <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:2px 8px;border-radius:30px;">${esc(fr.request_no)}</span>
        </div>
        ${fr.feedback_note ? `<div style="margin-top:8px;font-size:11.5px;color:var(--muted);background:var(--surface-2);border-radius:8px;padding:7px 9px;"><i class="fas fa-comment-dots" style="margin-right:5px;color:var(--primary);"></i>${esc(fr.feedback_note)}</div>` : ''}
        ${loggedOut}
        ${fr.current_location && !fr.logged_out ? `<div style="font-size:11px;color:var(--faint);margin-top:6px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--primary);"></i>${esc(fr.current_location)}</div>` : ''}
        ${fr.created_at ? `<div style="font-size:11px;color:var(--faint);margin-top:6px;"><i class="fas fa-clock" style="margin-right:4px;color:var(--primary);"></i>Sent ${esc(fr.created_at)}${fr.responded_at ? ' · Responded ' + esc(fr.responded_at) : ''}</div>` : ''}
      </div>
    </div>`;
}

// ─── SCB Monitor: view toggle (Open inbox / FSR History) ─────────────────
// Render the File Requests inbox as the compact SCB list rather than full cards.
// Always on for SCB Monitors; for super admins only on the "SCB View" tab.
let frCompact = !IS_SUPER_ADMIN;
let frView = 'open';
let frOpenTotal = null, frLogTotal = null;

function setFrView(view) {
  frView = view;
  document.querySelectorAll('.fr-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.frview === view));
  const open = document.getElementById('frListContainer');
  const log  = document.getElementById('frLogContainer');
  if (open) open.style.display = view === 'open' ? 'block' : 'none';
  if (log)  log.style.display  = view === 'log'  ? 'block' : 'none';
  if (view === 'open') loadFileRequests();
  else { loadFsrLog(); loadFsToday(); }
}

// Filter a File Request list by the search box term.
function frMatches(fr) {
  const q = (document.getElementById('frSearch')?.value || '').trim().toLowerCase();
  if (!q) return true;
  return [fr.file_number, fr.file_title, fr.request_no, fr.requester, fr.receiving_officer, fr.current_location]
    .some(v => (v || '').toString().toLowerCase().includes(q));
}
function updateFrCounts() {
  const openEl = document.getElementById('frCountOpen');
  const logEl  = document.getElementById('frCountLog');
  if (openEl) openEl.textContent = frOpenTotal != null ? frOpenTotal : DEMO_FR_OPEN.length;
  if (logEl)  logEl.textContent  = frLogTotal  != null ? frLogTotal  : DEMO_FR_LOG.length;
}
function onFrSearch() {
  const clearBtn = document.getElementById('frSearchClear');
  if (clearBtn) clearBtn.classList.toggle('show', !!(document.getElementById('frSearch')?.value || '').trim());
  frView === 'log' ? renderFsrLog() : renderFileRequests();
}
function clearFrSearch() {
  const input = document.getElementById('frSearch');
  if (input) input.value = '';
  onFrSearch();
}

async function loadFsrLog() {
  const container = document.getElementById('frLogContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  await wait(280);
  frLogTotal = DEMO_FR_LOG.length;
  updateFrCounts();
  renderFsrLog();
}
function renderFsrLog() {
  const container = document.getElementById('frLogContainer');
  if (!container) return;
  const list = DEMO_FR_LOG.filter(frMatches);
  if (!list.length) {
    container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-list fa-2x"></i><p style="margin-top:12px;">No file requests yet</p></div>';
    return;
  }
  container.innerHTML = list.map(fr => {
    const meta = FSR_STATUS_META[fr.status] || FSR_STATUS_META.PENDING;
    // For a Not Found outcome, spell out the kind (Missing / Pending) on the badge.
    const nft = (fr.status === 'NOT_FOUND' && fr.not_found_type) ? String(fr.not_found_type).toUpperCase() : '';
    const badgeLabel = nft ? `${meta.label} · ${nft === 'MISSING' ? 'Missing' : 'Pending'}` : meta.label;
    return `
      <div class="result-card" style="margin-bottom:12px;">
        <div style="padding:14px 16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
            <span style="font-size:9px;font-weight:800;padding:3px 9px;border-radius:30px;background:${meta.bg};color:${meta.fg};">${badgeLabel}</span>
          </div>
          <div style="font-size:12px;color:var(--text);margin-top:4px;">${esc(fr.file_title || '—')}</div>
          <div style="font-size:12px;color:var(--text);margin-top:8px;display:flex;flex-direction:column;gap:5px;">
            <span><i class="fas fa-hashtag" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_no)}</span>
            <span><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_type || 'Open Request')}</span>
            <span><i class="fas fa-user" style="width:14px;color:var(--primary);"></i> By ${esc(fr.requester || '—')}</span>
            ${fr.current_location ? `<span><i class="fas fa-map-marker-alt" style="width:14px;color:var(--primary);"></i> ${esc(fr.current_location)}</span>` : ''}
            <span><i class="fas fa-clock" style="width:14px;color:var(--primary);"></i> Sent ${esc(fr.created_at || '—')}</span>
            ${fr.responded_at ? `<span><i class="fas fa-reply" style="width:14px;color:var(--primary);"></i> ${meta.label} ${esc(fr.responded_at)}${fr.responder ? ' · ' + esc(fr.responder) : ''}</span>` : ''}
            ${fr.feedback_note ? `<span style="font-style:italic;">"${esc(fr.feedback_note)}"</span>` : ''}
          </div>
          ${(IS_SUPER_ADMIN && fr.can_revert) ? `
          <div data-fr="${fr.id}" style="margin-top:12px;">
            <button class="btn" style="width:100%;padding:11px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;" onclick="revertFr(${fr.id}, this)"><i class="fas fa-rotate-left"></i> Revert response</button>
          </div>` : ''}
        </div>
      </div>`;
  }).join('');
}

// Revert (undo) an accidental Found / Not-Found response, putting the request back
// in the Open queue. Only offered while the Front Desk hasn't acted yet.
async function revertFr(id, btn) {
  if (!confirm('Revert this response and send the request back to the open queue?')) return;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reverting…';
  await wait(500);
  const i = DEMO_FR_LOG.findIndex(r => r.id === id);
  if (i > -1) {
    const fr = DEMO_FR_LOG.splice(i, 1)[0];
    fr.status = 'PENDING'; delete fr.responded_at; delete fr.responder; delete fr.not_found_type;
    DEMO_FR_OPEN.unshift(fr);
  }
  toast('Response reverted — back in the open queue');
  setFrView('open');
}

// ─── SCB Monitor: File Requests inbox ────────────────────────────────────
async function loadFileRequests() {
  const container = document.getElementById('frListContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  await wait(280);
  frOpenTotal = DEMO_FR_OPEN.length;
  frLogTotal  = DEMO_FR_LOG.length;
  renderFsToday(DEMO_TODAY);
  updateFrCounts();
  renderFileRequests();
}

// The Request Details panel — shared by the admin card (inside its own <details>)
// and the compact SCB row (already inside the row's <details>).
function frDetailsBody(fr) {
  const purpose = fr.request_purpose_name ? esc(fr.request_purpose_name) : '—';
  const timelineMeta = formatTimelineMeta({ timelineStatus: fr.timeline_status, daysUntilDeadline: fr.days_until_deadline });
  const timelineHtml = timelineMeta
    ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>`
    : '<span style="color:var(--muted);">—</span>';
  const delayReason = fr.delay_reason ? esc(fr.delay_reason) : '—';
  // The Expected Return Date is deliberately NOT the stored request date — a request
  // has no return window until the file is handed over. The row stays visible but
  // reads "Pending" until the file is logged out.
  const pendingChip = `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569;border:1px solid #cbd5e1;"><i class="fas fa-clock"></i>Pending</span>`;
  return `
    <div style="margin-top:8px;padding:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;display:flex;flex-direction:column;gap:7px;">
      <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Request Purpose:</span> <span style="font-size:12px;color:var(--text);">${purpose}</span></div>
      <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Timeline:</span> ${timelineHtml}</div>
      <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Expected Return Date:</span> ${pendingChip}</div>
      <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Delay Reason:</span> <span style="font-size:12px;color:var(--text);">${delayReason}</span></div>
    </div>`;
}
function frDetailsSection(fr) {
  return `<details style="margin-top:10px;"><summary style="cursor:pointer;font-size:11.5px;font-weight:700;color:var(--primary);">Request Details</summary>${frDetailsBody(fr)}</details>`;
}
// Compact "who is asking" block — the requester details selected in Quick Search.
function frRequesterDetails(fr) {
  const rows = [
    ['fa-user',     fr.receiving_officer,    'Requested by'],
    ['fa-building', fr.requester_office,     'Office'],
    ['fa-sitemap',  fr.requester_department, 'Department'],
  ].filter(([, val]) => val && String(val).trim() && String(val).trim() !== '—');
  if (!rows.length) return '';
  return `<div style="margin-top:8px;display:flex;flex-direction:column;gap:3px;">` + rows.map(([icon, val, label]) =>
    `<div style="font-size:12px;color:var(--text);"><i class="fas ${icon}" style="width:13px;margin-right:5px;color:var(--primary);"></i><span style="color:var(--text);">${label}:</span> ${esc(val)}</div>`).join('') + `</div>`;
}

// Slim list row used by SCB Monitors: collapsed it is just the file number and
// title. Tapping it opens the details AND the Found / Not Found / delete actions,
// so the queue stays one thin line per request.
function frListRow(fr, histIds) {
  const mix = histIds.has(fr.id)
    ? `<span title="Also in FSR History" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;vertical-align:middle;"></span>` : '';
  const title = (fr.file_title && String(fr.file_title).trim()) || '';
  const loc = fr.current_location && String(fr.current_location).trim();
  const meta = [fr.receiving_officer, fr.created_at].filter(v => v && String(v).trim() && String(v).trim() !== '—');
  return `
    <div class="result-card fr-row" style="margin-bottom:5px;${fr.is_ofs ? 'border-left:3px solid #f59e0b;' : ''}" data-fr="${fr.id}">
      <details style="padding:7px 10px;">
        <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:12.5px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${mix}${esc(fr.file_number)}</div>
            ${title ? `<div style="font-size:10px;color:var(--muted);margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(title)}</div>` : ''}
          </div>
          <i class="fas fa-chevron-down fr-row-chev" style="flex:0 0 auto;font-size:10px;color:var(--faint);"></i>
        </summary>
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid var(--border);font-size:11px;color:var(--text);display:flex;flex-direction:column;gap:3px;">
          ${meta.length ? `<div><span style="color:var(--muted);">Requested by:</span> ${meta.map(esc).join(' · ')}</div>` : ''}
          <div><span style="color:var(--muted);">Type:</span> ${esc(fr.request_type || 'Open Request')}${fr.is_ofs ? ' · OFS' : ''}</div>
          ${loc ? `<div><span style="color:var(--muted);">Location:</span> ${esc(loc)}</div>` : ''}
          ${fr.requester_office ? `<div><span style="color:var(--muted);">Office:</span> ${esc(fr.requester_office)}</div>` : ''}
        </div>
        ${frDetailsBody(fr)}
        <div style="display:flex;gap:6px;margin-top:8px;">
          <button class="btn" style="flex:1;width:auto;min-width:0;padding:9px;font-size:12px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFr(${fr.id}, 'found', this, ${fr.is_blind ? 'true' : 'false'})"><i class="fas fa-check"></i> Found</button>
          <button class="btn ghost-btn" style="flex:1;width:auto;min-width:0;padding:9px;font-size:12px;box-shadow:none;" onclick="respondFr(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
          ${IS_SUPER_ADMIN ? `<button class="btn" style="flex:0 0 auto;width:auto;padding:9px 12px;font-size:12px;box-shadow:none;background:#fee2e2;color:#991b1b;" onclick="deleteFr(${fr.id}, this)" title="Delete request"><i class="fas fa-trash"></i></button>` : ''}
        </div>
      </details>
    </div>`;
}

function renderFileRequests() {
  const container = document.getElementById('frListContainer');
  if (!container) return;
  const list = DEMO_FR_OPEN.filter(frMatches);
  if (!list.length) {
    container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-check fa-2x"></i><p style="margin-top:12px;">No open file requests</p></div>';
    return;
  }
  // IDs present in FSR History — a request here AND in Open is a mix-up (green dot).
  const histIds = new Set(DEMO_FR_LOG.map(r => r.id));
  // SCB Monitors work through a queue, so they get the stripped-down list view with
  // the Found / Not Found / delete actions. Super admins keep the full card here.
  if (frCompact) { container.innerHTML = list.map(fr => frListRow(fr, histIds)).join(''); return; }
  container.innerHTML = list.map(fr => `
    <div class="result-card" style="margin-bottom:12px;${fr.is_ofs ? 'border-left:4px solid #f59e0b;background:rgba(245,158,11,0.06);' : ''}" data-fr="${fr.id}">
      <div style="padding:14px 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
          <div style="font-size:15px;font-weight:800;">${histIds.has(fr.id) ? `<span title="Also in FSR History — this request is in both tabs at once" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#10b981;margin-right:7px;vertical-align:middle;box-shadow:0 0 0 3px rgba(16,185,129,0.25);"></span>` : ''}${esc(fr.file_number)}</div>
          <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:3px 9px;border-radius:30px;">${esc(fr.request_no)}</span>
        </div>
        <div style="font-size:12px;color:var(--text);margin-top:4px;">${esc(fr.file_title || '—')}</div>
        <div style="margin-top:6px;"><span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:${fr.is_dfr ? '#e5e7eb' : (fr.is_blind ? '#fee2e2' : '#e0f2fe')};color:${fr.is_dfr ? '#374151' : (fr.is_blind ? '#b91c1c' : '#0369a1')};"><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}"></i> ${esc(fr.request_type || 'Open Request')}</span>${fr.is_ofs ? `<span style="margin-left:5px;display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;"><i class="fas fa-crown"></i> OFS${fr.ofs_rank ? ' · ' + esc(fr.ofs_rank) : ''}</span>` : ''}</div>
        ${fr.current_location ? (
          String(fr.current_location).trim().toLowerCase() === 'not indexed'
            ? `<div style="margin-top:8px;"><span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:30px;background:#fee2e2;color:#b91c1c;"><i class="fas fa-circle-exclamation"></i> Not indexed</span></div>`
            : `<div style="font-size:12px;color:var(--text);margin-top:6px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--primary);"></i>${esc(fr.current_location)}</div>`
        ) : ''}
        ${frRequesterDetails(fr)}
        ${fr.created_at ? `<div style="font-size:12px;color:var(--text);margin-top:6px;"><i class="fas fa-clock" style="margin-right:4px;color:var(--primary);"></i>Sent ${esc(fr.created_at)}</div>` : ''}
        ${frDetailsSection(fr)}
      </div>
    </div>`).join('');
}

/* ─── "Not Found" / blind-"Found" type choosers ──────────────────────────── */
function pickSheet(title, subtitle, icon, iconColor, options) {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:10050;background:rgba(0,0,0,0.55);display:flex;align-items:flex-end;justify-content:center;';
    overlay.innerHTML = `
      <div style="background:var(--card);width:100%;max-width:480px;border-radius:18px 18px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px rgba(0,0,0,0.4);">
        <div style="width:42px;height:4px;border-radius:4px;background:var(--faint);opacity:.5;margin:0 auto 16px;"></div>
        <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px;"><i class="fas ${icon}" style="color:${iconColor};margin-right:6px;"></i> ${title}</div>
        <div style="font-size:12px;color:var(--faint);margin-bottom:16px;">${subtitle}</div>
        ${options.map(o => `<button class="btn" data-pick="${o.value}" style="width:100%;padding:14px;font-size:14px;box-shadow:none;background:${o.background};color:#fff;margin-bottom:10px;text-align:left;"><i class="fas ${o.icon}" style="margin-right:8px;"></i> ${o.label}${o.hint ? `<span style="display:block;font-size:11px;font-weight:500;opacity:.9;margin-top:2px;">${o.hint}</span>` : ''}</button>`).join('')}
        <button class="btn ghost-btn" data-pick="" style="width:100%;padding:12px;font-size:13px;box-shadow:none;margin-top:4px;">Cancel</button>
      </div>`;
    function close(val) { overlay.remove(); resolve(val || null); }
    overlay.addEventListener('click', e => {
      if (e.target === overlay) return close(null);
      const b = e.target.closest('[data-pick]');
      if (b) close(b.getAttribute('data-pick'));
    });
    document.body.appendChild(overlay);
  });
}
// When the SCB Monitor marks a request Not Found, they must say which kind:
// MISSING (file genuinely can't be located) or PENDING (search not concluded).
function pickNotFoundType() {
  return pickSheet('Not Found — which type?',
    'Tell the requester whether the file is missing or the search is still pending.',
    'fa-triangle-exclamation', '#ef4444', [
      { value: 'missing', label: 'Missing', hint: 'File not given to us', icon: 'fa-ban', background: 'linear-gradient(135deg,#ef4444,#dc2626)' },
      { value: 'pending', label: 'Pending', hint: '', icon: 'fa-hourglass-half', background: 'linear-gradient(135deg,#f59e0b,#d97706)' },
    ]);
}
// Blind-request Found picker: either the file is already indexed (can be logged),
// or physically found and queued for indexing first.
function pickBlindFoundType() {
  return pickSheet('Found — select type',
    'Choose whether the file is already indexed or should wait in the indexing queue.',
    'fa-circle-check', '#10b981', [
      { value: 'indexed',  label: 'Found (Indexed)',    icon: 'fa-check-double', background: 'linear-gradient(135deg,#10b981,#059669)' },
      { value: 'indexing', label: 'Found for Indexing', icon: 'fa-layer-group',  background: 'linear-gradient(135deg,#f59e0b,#d97706)' },
    ]);
}

async function respondFr(id, result, btn, isBlindRequest = false) {
  let foundType = null;
  if (result === 'found' && isBlindRequest) {
    foundType = await pickBlindFoundType();
    if (!foundType) return;
  }
  let notFoundType = null;
  if (result === 'not_found') {
    notFoundType = await pickNotFoundType();
    if (!notFoundType) return;   // cancelled — leave the request open
  }
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b => b.disabled = true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  await wait(500);

  const i = DEMO_FR_OPEN.findIndex(r => r.id === id);
  if (i > -1) {
    const fr = DEMO_FR_OPEN.splice(i, 1)[0];
    fr.status = result === 'found' ? 'FOUND' : 'NOT_FOUND';
    if (notFoundType) fr.not_found_type = notFoundType.toUpperCase();
    fr.responded_at = new Date().toLocaleString('en-GB').replace(',', '');
    fr.responder = CURRENT_USER.name;
    fr.can_revert = true;
    DEMO_FR_LOG.unshift(fr);
  }
  if (result === 'found') toast(foundType === 'indexing' ? 'Marked as Found for Indexing ✅' : 'Marked as Found (Indexed) ✅');
  else toast(`Marked as Not Found — ${notFoundType === 'missing' ? 'Missing' : 'Pending'}`, 'error');
  // Responded → the request leaves Open; move the user to the FSR History tab.
  setFrView('log');
}

// Respond to an open File Request straight from the File Search result.
async function respondFrFromSearch(id, result, btn, isBlindRequest = false) {
  let foundType = null;
  if (result === 'found' && isBlindRequest) {
    foundType = await pickBlindFoundType();
    if (!foundType) return;
  }
  let notFoundType = null;
  if (result === 'not_found') {
    notFoundType = await pickNotFoundType();
    if (!notFoundType) return;
  }
  const wrap = btn.closest('[data-fr]');
  if (wrap) wrap.querySelectorAll('button').forEach(b => b.disabled = true);
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  await wait(500);

  const i = DEMO_FR_OPEN.findIndex(r => r.id === id);
  if (i > -1) {
    const fr = DEMO_FR_OPEN.splice(i, 1)[0];
    fr.status = result === 'found' ? 'FOUND' : 'NOT_FOUND';
    if (notFoundType) fr.not_found_type = notFoundType.toUpperCase();
    fr.responded_at = new Date().toLocaleString('en-GB').replace(',', '');
    fr.responder = CURRENT_USER.name;
    fr.can_revert = true;
    DEMO_FR_LOG.unshift(fr);
  }
  const found = result === 'found';
  const nftLabel = notFoundType ? ` — ${notFoundType === 'missing' ? 'Missing' : 'Pending'}` : '';
  const foundLabel = foundType === 'indexing' ? 'Found for Indexing' : 'Found (Indexed)';
  toast(found ? `Marked as ${foundLabel} ✅` : 'Marked as Not Found' + nftLabel);
  if (wrap) {
    wrap.innerHTML = `<div style="font-size:12px;font-weight:700;color:${found ? '#10b981' : '#ef4444'};">
      <i class="fas fa-${found ? 'check-circle' : 'times-circle'}"></i> Response recorded: ${found ? foundLabel : 'Not Found' + nftLabel}</div>`;
  }
}

async function deleteFr(id, btn) {
  if (!confirm('Delete this file request? This cannot be undone.')) return;
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b => b.disabled = true);
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  await wait(400);
  const i = DEMO_FR_OPEN.findIndex(r => r.id === id);
  if (i > -1) DEMO_FR_OPEN.splice(i, 1);
  toast('File request deleted');
  if (card) card.remove();
  frOpenTotal = DEMO_FR_OPEN.length;
  updateFrCounts();
}

/* ══════════════════════════════════════════════════════════════════════════
   11. QR SCANNER                          // API → POST /tracker/scan-and-log
   ══════════════════════════════════════════════════════════════════════════ */
let html5QrCode = null, isScannerRunning = false, flashEnabled = false;

async function startScanner() {
  // Camera requires HTTPS in production.
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    updateScanStatus('Camera requires HTTPS (or localhost). Use "Simulate a scan" in this demo.', 'error');
    return;
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof Html5Qrcode === 'undefined') {
    updateScanStatus('Camera not supported in this browser or requires HTTPS.', 'error');
    return;
  }
  try {
    const devices = await Html5Qrcode.getCameras();
    if (!devices.length) { updateScanStatus('No camera found on this device.', 'error'); return; }
    document.getElementById('scanPlaceholder').hidden = true;
    html5QrCode = new Html5Qrcode('qr-reader');
    await html5QrCode.start(devices[0].id, { fps: 10, qrbox: 250 }, onScanSuccess, () => {});
    isScannerRunning = true;
    updateScanStatus('Camera ready — scanning', 'success');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-sync"></i> Restart';
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e || '');
    document.getElementById('scanPlaceholder').hidden = false;
    if (msg.includes('NotAllowed') || msg.includes('Permission')) updateScanStatus('Camera permission denied. Please allow camera access in your browser.', 'error');
    else if (msg.includes('NotFound')) updateScanStatus('No camera found on this device.', 'error');
    else updateScanStatus('Camera error: ' + (msg || 'Could not access camera.'), 'error');
  }
}
async function stopScanner() {
  if (html5QrCode && isScannerRunning) {
    await html5QrCode.stop();
    isScannerRunning = false;
    document.getElementById('scanPlaceholder').hidden = false;
    updateScanStatus('Scanner stopped', 'info');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-play"></i> Start';
  }
}
async function toggleFlash() {
  if (!html5QrCode || !isScannerRunning) { updateScanStatus('Start camera first', 'info'); return; }
  flashEnabled = !flashEnabled;
  try { await html5QrCode.applyVideoConstraints({ advanced: [{ torch: flashEnabled }] }); } catch (e) {}
  const b = document.getElementById('flashToggleBtn');
  b.classList.toggle('flash-on', flashEnabled);
  b.innerHTML = flashEnabled ? '<i class="fas fa-bolt"></i> Flash ON' : '<i class="fas fa-bolt"></i> Flash';
}
function updateScanStatus(msg, type) {
  const el = document.getElementById('scannerStatus');
  const icon = type === 'success' ? '<i class="fas fa-check-circle" style="color:#10b981;"></i>'
    : type === 'error' ? '<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>'
    : '<i class="fas fa-camera"></i>';
  el.innerHTML = `${icon} <span>${msg}</span>`;
}
async function onScanSuccess(text) {
  const trimmed = String(text).trim();
  updateScanStatus(`Scanned: ${trimmed.substring(0, 35)}`, 'success');
  if (isScannerRunning && html5QrCode) html5QrCode.pause(true);
  scanAndLog(trimmed);
  setTimeout(() => { if (isScannerRunning && html5QrCode) html5QrCode.resume(); }, 2500);
}
// Demo stand-in for a QR read: picks one of the five demo tracking ids.
function simulateScan() {
  const ids = Object.keys(DEMO_TRACKING_IDS);
  const id = ids[Math.floor(Math.random() * ids.length)];
  updateScanStatus(`Scanned: ${id}`, 'success');
  scanAndLog(id);
}
// Mirrors MobileTrackerController::scanAndLog — resolve the code, advance the
// workflow step, then report which office the file just moved to.
function scanAndLog(code) {
  const fileNo = DEMO_TRACKING_IDS[code.toUpperCase()]
    || (Object.keys(DEMO_FILES).find(k => k.toUpperCase() === code.toUpperCase()) || null);
  if (!fileNo) { toast(`No file tracker found for: ${code}`, 'error'); return; }
  const f = DEMO_FILES[fileNo];
  // The live app resolves the next office from a configured workflow map.
  const nextOffice = f.status === 'IN_TRANSIT' ? 'Commission Section' : 'Land Registry';
  toast(`✅ ${f.file_title} — logged at ${nextOffice}`);
  document.getElementById('manualScanResult').innerHTML = trackerResultCard(f, code, nextOffice);
}
function trackerResultCard(f, trackingId, office) {
  return `
    <div class="result-card" style="padding:14px;margin-top:10px;">
      <div style="font-weight:700;font-size:14px;">${esc(f.file_title || '—')}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:6px;">
        <span style="margin-right:12px;"><i class="fas fa-hashtag" style="color:var(--primary);"></i> ${esc(f.file_number)}</span>
        <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> ${esc(office || f.current_location)}</span>
      </div>
      <div style="font-size:11px;color:var(--faint);margin-top:6px;">
        Tracking: ${esc(trackingId)} &nbsp;|&nbsp; Registry: ${esc(f.registry || '—')} &nbsp;|&nbsp; Status: ${esc((LOC_STATUS_META[f.status] || {}).label || f.status)}
      </div>
    </div>`;
}
async function manualLookup() {
  const input = document.getElementById('manualScanInput');
  const resultEl = document.getElementById('manualScanResult');
  const val = input.value.trim();
  if (!val) { resultEl.innerHTML = '<p style="color:#ef4444;font-size:12px;margin-top:6px;">Please enter a Tracking ID or File Number.</p>'; return; }

  const btn = document.getElementById('manualScanBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  await wait(400);

  const fileNo = DEMO_TRACKING_IDS[val.toUpperCase()]
    || (Object.keys(DEMO_FILES).find(k => k.toUpperCase() === val.toUpperCase()) || null);
  if (fileNo) {
    const f = DEMO_FILES[fileNo];
    const tid = Object.keys(DEMO_TRACKING_IDS).find(k => DEMO_TRACKING_IDS[k] === fileNo) || val;
    resultEl.innerHTML = trackerResultCard(f, tid, f.current_location);
    input.value = '';
  } else {
    resultEl.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> No file found for "<strong>${esc(val)}</strong>".</p>`;
  }
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i> Look up';
}

/* ══════════════════════════════════════════════════════════════════════════
   12. ROLE GATING + TAB ROUTER
   ══════════════════════════════════════════════════════════════════════════ */

// Rebuild the tab bar and the role-only blocks for the active role. In the live app
// this is Blade @if gating rendered server-side.
function applyRoleVisibility() {
  const show = (sel, on) => document.querySelectorAll(sel).forEach(el => { el.hidden = !on; });

  // SCB Monitors work the request queue rather than raising searches, so Quick
  // Search is hidden for them (super admins keep it).
  show('.tab-item[data-tab="files"]',        !IS_SCB_MONITOR || IS_SUPER_ADMIN);
  show('.tab-item[data-tab="myreq"]',        IS_OFS);
  show('.tab-item[data-tab="requests"]',     IS_SCB_MONITOR);
  show('.tab-item[data-tab="requests-scb"]', IS_SCB_MONITOR && IS_SUPER_ADMIN);
  show('#fsTodayCard',                       IS_SCB_MONITOR);
  show('[data-qa="search"]',                 !IS_SCB_MONITOR || IS_SUPER_ADMIN);
  show('[data-qa="scb"]',                    IS_SCB_MONITOR && !IS_SUPER_ADMIN);

  document.getElementById('activityPanelTitle').textContent =
    IS_SCB_MONITOR ? 'Recent File Search Activity' : 'Recent Activity';

  // The compact SCB list is always on for SCB Monitors; super admins get it only on
  // the "SCB View" tab.
  frCompact = !IS_SUPER_ADMIN;
}

function setActiveTab(tabId) {
  if (tabId !== 'scanner') stopScanner();
  // "requests-scb" is the same File Requests screen rendered in SCB (compact) mode —
  // a preview tab for super admins, who otherwise see the full cards.
  frCompact = IS_SUPER_ADMIN ? (tabId === 'requests-scb') : true;
  const screenId = tabId === 'requests-scb' ? 'requests' : tabId;

  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(`${screenId}-screen`).classList.add('active');
  document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
  document.querySelector(`.tab-item[data-tab="${tabId}"]`)?.classList.add('active');

  if (tabId === 'dashboard') renderDashboard();
  if (tabId === 'scanner')   updateScanStatus('Click Start to begin scanning', 'info');
  if (tabId === 'create')    initCreateForm();
  if (tabId === 'requests' || tabId === 'requests-scb') {
    // Compact/SCB view reads as "File Request-SCB View"; the full card view reads
    // "File Request".
    const frTitle = document.getElementById('frScreenTitle');
    if (frTitle) frTitle.textContent = frCompact ? 'File Request-SCB View' : 'File Request';
    setFrView(frView);
  }
  if (tabId === 'myreq') loadMyRequests();
}

// Set the role flags without moving the user — used at boot by the session
// restore as well as by the Profile role chips.
function applyRole(role) {
  const r = DEMO_ROLES[role] || DEMO_ROLES.admin;
  IS_SCB_MONITOR = r.scb; IS_OFS = r.ofs; IS_SUPER_ADMIN = r.admin;
  document.getElementById('profileRole').textContent = r.label;
  document.querySelectorAll('.role-chip').forEach(c => c.classList.toggle('active', c.dataset.role === role));
  applyRoleVisibility();
  loadNotifications();
  return r;
}
function setDemoRole(role) {
  const r = applyRole(role);
  // Land somewhere the new role can actually see.
  setActiveTab('profile');
  toast(`Now viewing as ${r.label}`);
}

// Ends the demo session and returns to the login screen, the way
// MobileController::logout invalidates the session and redirects.
function demoLogout() {
  try {
    sessionStorage.removeItem(SESSION_KEY);
    localStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem('alaes-splash-shown');
  } catch (e) {}
  window.location.href = '/alaes';
}

/* ══════════════════════════════════════════════════════════════════════════
   13. EVENT LISTENERS + BOOT
   ══════════════════════════════════════════════════════════════════════════ */
document.getElementById('fileSearchBtn')?.addEventListener('click', searchFile);
document.getElementById('fileSearchSelect')?.addEventListener('change', searchFile);
// Blind request: Enter runs the lookup; pausing after typing auto-loads the result.
document.getElementById('fileSearchManual')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); clearTimeout(blindAutoLoadTimer); searchFile(); }
});
document.getElementById('fileSearchManual')?.addEventListener('input', onBlindManualInput);

document.getElementById('startCameraBtn')?.addEventListener('click', startScanner);
document.getElementById('stopCameraBtn')?.addEventListener('click', stopScanner);
document.getElementById('flashToggleBtn')?.addEventListener('click', toggleFlash);
document.getElementById('manualScanBtn')?.addEventListener('click', manualLookup);
document.getElementById('manualScanInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') manualLookup(); });

document.getElementById('notificationBell')?.addEventListener('click', function (e) {
  e.stopPropagation();
  document.getElementById('notificationPanel').classList.toggle('show');
});
document.addEventListener('click', () => document.getElementById('notificationPanel')?.classList.remove('show'));
document.getElementById('markAllRead')?.addEventListener('click', function (e) {
  e.stopPropagation();
  notifications.forEach(n => n.is_read = true);
  DEMO_NOTIFICATIONS.forEach(n => n.is_read = true);
  renderNotifications(); updateNotificationBadge();
});

document.querySelectorAll('.tab-item[data-tab]').forEach(tab => {
  tab.addEventListener('click', () => setActiveTab(tab.getAttribute('data-tab')));
});
document.getElementById('frRefreshBtn')?.addEventListener('click', () => (frView === 'log' ? loadFsrLog() : loadFileRequests()));

// Priority chip selector (Log a File)
document.querySelectorAll('#create-screen .prio-chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#create-screen .prio-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
  });
});
// Demo role chips (Profile)
document.querySelectorAll('.role-chip').forEach(chip => {
  chip.addEventListener('click', () => setDemoRole(chip.dataset.role));
});
// Show the picked receiving officer's picture under the select on the Create form.
document.getElementById('receivingOfficer')?.addEventListener('change', function () {
  const box = document.getElementById('receivingOfficerProfile');
  const opt = this.options[this.selectedIndex];
  const name = opt && this.value ? (opt.getAttribute('data-name') || opt.textContent.trim()) : '';
  if (!name) { box.style.display = 'none'; box.innerHTML = ''; return; }
  // Photo only — the officer's name is already in the select directly above.
  box.innerHTML = mobPassportTap(opt.getAttribute('data-photo') || null, name, 76);
  box.style.display = 'flex';
});
// Gallery keyboard nav
document.addEventListener('keydown', e => {
  if (!document.getElementById('digViewer').classList.contains('open')) return;
  if (e.key === 'Escape') closeDigViewer();
  if (e.key === 'ArrowRight') digNext();
  if (e.key === 'ArrowLeft') digPrev();
});

// ─── Splash — shown ONCE per app-open (browser session) ──────────────────
(function () {
  const KEY = 'alaes-splash-shown';
  const el = document.getElementById('alaes-splash');
  if (!el) return;
  let alreadyShown = false;
  try { alreadyShown = !!sessionStorage.getItem(KEY); } catch (e) {}
  if (alreadyShown) { el.remove(); return; }   // refresh / inter-page nav — no splash
  try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
  el.style.display = 'flex';
  setTimeout(() => {
    el.classList.add('hide');
    setTimeout(() => el.remove(), 550);
  }, 1600);
})();

// ─── Boot ────────────────────────────────────────────────────────────────
applyTheme(document.documentElement.getAttribute('data-theme') || 'light');

// Pick up whoever signed in on login.html. Opening this page directly with no
// session is allowed (it is a UI clone, not a guarded app) — it just falls back
// to the default Super Admin user rather than bouncing to the login screen.
let bootRole = 'admin';
try {
  const session = JSON.parse(sessionStorage.getItem(SESSION_KEY) || localStorage.getItem(SESSION_KEY) || 'null');
  if (session && session.username) {
    CURRENT_USER.name = session.name || CURRENT_USER.name;
    CURRENT_USER.username = session.username;
    if (DEMO_ROLES[session.role]) bootRole = session.role;
  }
} catch (e) {}

document.getElementById('profileName').textContent = CURRENT_USER.name;
document.getElementById('profileUsername').textContent = CURRENT_USER.username;
populateCreateForm();
populateFilePicker();
applyRole(bootRole);

// Honour a deep-link hash (e.g. #files) so a link can open a specific screen.
const VALID_TABS = ['dashboard', 'files', 'myreq', 'requests', 'scanner', 'profile', 'create'];
const bootTab = (location.hash || '').replace('#', '');
setActiveTab(VALID_TABS.includes(bootTab) ? bootTab : 'dashboard');
