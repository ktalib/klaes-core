/**
 * Applicant auto-fill for the ST commissioning tabs.
 *
 * File indexing stores a single free-text file title (e.g. "SALISU ABDULKADIR
 * KAFINTA KARKASARA" or "IVBIARO PROGRESSIVE UNION"). These helpers work out
 * whether that title is a person, a company or several owners, split a personal
 * name into its parts, and drive the Applicant Information section accordingly.
 *
 *   applyApplicantBackfill('primary', indexingRow)
 */

/** Words that mark a file title as an organisation rather than a person. */
var CORPORATE_NAME_KEYWORDS = [
    'LTD', 'LIMITED', 'PLC', 'INC', 'LLC', 'ENTERPRISE', 'ENTERPRISES', 'VENTURE', 'VENTURES',
    'COMPANY', 'CO', 'CORPORATION', 'GROUP', 'HOLDINGS', 'INVESTMENT', 'INVESTMENTS',
    'UNION', 'ASSOCIATION', 'SOCIETY', 'COOPERATIVE', 'CO-OPERATIVE', 'MULTIPURPOSE',
    'FOUNDATION', 'TRUST', 'TRUSTEES', 'COMMUNITY', 'CLUB', 'COUNCIL', 'COMMISSION',
    'MINISTRY', 'GOVERNMENT', 'AUTHORITY', 'AGENCY', 'BOARD', 'BUREAU', 'DEPARTMENT',
    'CHURCH', 'MOSQUE', 'JAMA\'A', 'MISSION', 'SCHOOL', 'SCHOOLS', 'COLLEGE', 'ACADEMY',
    'UNIVERSITY', 'INSTITUTE', 'HOSPITAL', 'CLINIC', 'PHARMACY', 'BANK', 'INSURANCE',
    'RESOURCES', 'SERVICES', 'CONSTRUCTION', 'CONSTRUCTIONS', 'ENGINEERING', 'INDUSTRIES',
    'INDUSTRY', 'TECHNOLOGY', 'TECHNOLOGIES', 'GLOBAL', 'INTERNATIONAL', 'NIGERIA', 'NIG',
    'STORES', 'MOTORS', 'HOTEL', 'HOTELS', 'ESTATE', 'ESTATES', 'PROPERTIES', 'DEVELOPMENT',
    'DEVELOPERS', 'FARMS', 'SONS', 'BROTHERS', 'AND SONS'
];

/**
 * Decide which Applicant Type an indexed file title belongs to.
 * An RC number on the record is treated as conclusive evidence of a company.
 *
 * @param {string} name
 * @param {Object} record  the file_indexings row (optional)
 * @returns {'Individual'|'Corporate'|'Multiple'}
 */
function detectApplicantType(name, record) {
    var title = String(name || '').trim().toUpperCase();
    if (!title) return 'Individual';

    if (record && String(record.rc_no || '').trim() !== '') {
        return 'Corporate';
    }

    var words = title.split(/[\s,.]+/).filter(Boolean);
    var isCorporate = words.some(function (word) {
        return CORPORATE_NAME_KEYWORDS.indexOf(word) !== -1;
    });
    if (isCorporate) return 'Corporate';

    // Two or more people joined by "&" / "AND" — e.g. "AUDU BAKO & SANI ALI".
    if (/\s(?:&|AND)\s/.test(title)) return 'Multiple';

    return 'Individual';
}
window.detectApplicantType = detectApplicantType;

/** Honorifics that may lead a file title and belong in the Title dropdown. */
var NAME_TITLE_PREFIXES = [
    'MR', 'MRS', 'MISS', 'MS', 'DR', 'PROF', 'ENGR', 'ENG', 'ARCH', 'BARR', 'HON',
    'ALHAJI', 'ALHAJA', 'HAJIYA', 'MALLAM', 'MALAM', 'CHIEF', 'SIR', 'LADY', 'PASTOR',
    'REV', 'IMAM', 'SHEIKH', 'CAPT', 'COL', 'GEN', 'MAJ', 'LT'
];

/**
 * Split a personal name into title / first / middle / surname.
 *
 * The last word is always the surname and the one before it the middle name;
 * everything else is the first name. So:
 *   "SALISU ABDULKADIR KAFINTA KARKASARA" -> SALISU ABDULKADIR | KAFINTA | KARKASARA
 *   "SALISU KAFINTA KARKASARA"            -> SALISU            | KAFINTA | KARKASARA
 *   "SALISU KARKASARA"                    -> SALISU            |         | KARKASARA
 *   "SALISU"                              -> SALISU            |         |
 *
 * @param {string} fullName
 * @returns {{title: string, first_name: string, middle_name: string, surname: string}}
 */
function splitPersonName(fullName) {
    var parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    var result = { title: '', first_name: '', middle_name: '', surname: '' };

    if (!parts.length) return result;

    // Pull off a leading honorific so it doesn't get treated as a given name.
    var lead = parts[0].toUpperCase().replace(/\.$/, '');
    if (parts.length > 1 && NAME_TITLE_PREFIXES.indexOf(lead) !== -1) {
        result.title = parts.shift().replace(/\.$/, '');
    }

    if (parts.length === 1) {
        result.first_name = parts[0];
    } else if (parts.length === 2) {
        result.first_name = parts[0];
        result.surname = parts[1];
    } else {
        result.surname = parts[parts.length - 1];
        result.middle_name = parts[parts.length - 2];
        result.first_name = parts.slice(0, parts.length - 2).join(' ');
    }

    return result;
}
window.splitPersonName = splitPersonName;

/**
 * Select an Applicant Type radio and reveal its field group.
 *
 * @param {string} prefix  'primary' | 'pua' | 'sua'
 * @param {string} type    'Individual' | 'Corporate' | 'Multiple'
 */
function setApplicantType(prefix, type) {
    var radioId = prefix + '_' + type.toLowerCase();
    var radio = document.getElementById(radioId);
    if (!radio) return false;

    radio.checked = true;
    // The partial's own listener shows/hides the matching field group.
    radio.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
}
window.setApplicantType = setApplicantType;

function setFieldValue(id, value) {
    var field = document.getElementById(id);
    if (field) field.value = value || '';
}

/**
 * Select a Title dropdown option, tolerating case and trailing-period
 * differences ("Alhaji" vs "ALHAJI", "Dr." vs "Dr").
 */
function selectTitleOption(id, title) {
    var select = document.getElementById(id);
    if (!select || !title) return;

    var wanted = String(title).toUpperCase().replace(/\.$/, '');
    var match = Array.from(select.options).find(function (opt) {
        return opt.value.toUpperCase().replace(/\.$/, '') === wanted;
    });
    if (match) select.value = match.value;
}

/**
 * Fill the Applicant Information section from an indexed file record: pick the
 * right Applicant Type, then populate the fields that type actually uses.
 *
 * @param {string} prefix  'primary' | 'pua' | 'sua'
 * @param {Object} record  file_indexings row ({file_title, rc_no, ...})
 */
function applyApplicantBackfill(prefix, record) {
    record = record || {};
    var fullName = String(record.file_title || '').trim();
    if (!fullName) return null;

    var type = detectApplicantType(fullName, record);
    setApplicantType(prefix, type);

    if (type === 'Corporate') {
        setFieldValue(prefix + '_corporate_name', fullName);
        setFieldValue(prefix + '_rc_number', record.rc_no || '');
        // Clear the person fields so a previous selection doesn't linger.
        setFieldValue(prefix + '_first_name', '');
        setFieldValue(prefix + '_middle_name', '');
        setFieldValue(prefix + '_last_name', '');
        return type;
    }

    // "Multiple" fills owner 1 from the first person in the list; the rest are
    // added by the user, who knows how the remaining names should be split.
    var personName = type === 'Multiple'
        ? fullName.split(/\s(?:&|AND)\s/i)[0].trim()
        : fullName;

    var parts = splitPersonName(personName);
    var idBase = type === 'Multiple' ? prefix + '_owner' : prefix;

    selectTitleOption(idBase + '_title', parts.title);
    setFieldValue(idBase + '_first_name', parts.first_name);
    setFieldValue(idBase + '_middle_name', parts.middle_name);
    setFieldValue(idBase + '_last_name', parts.surname);
    setFieldValue(prefix + '_corporate_name', '');

    return type;
}
window.applyApplicantBackfill = applyApplicantBackfill;
