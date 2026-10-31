const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');

// Add Property Transaction Details > File History Summary: on an SLTR file with a Sales
// Agreement (not a Deed of Assignment) and a CofO but no Transfer of Title, the card previews the
// Transfer of Title the server generates on save (App\Services\Sltr\SltrTotGenerator).
// fhSystemTotRow() is read straight out of the Blade's x-data.
const blade = fs.readFileSync('resources/views/fileindexing/partial/property_transaction_modal.blade.php', 'utf8');
const m = /\n\s*fhSystemTotRow\(rows\) \{\r?\n([\s\S]*?)\r?\n            \},/.exec(blade);
assert.ok(m, 'fhSystemTotRow not found');
const fhSystemTotRow = new Function('rows', m[1]);

// The x-data attribute is double-quoted: the method must not contain one.
test('no double quote inside the x-data method', () => {
    assert.equal(m[1].includes('"'), false);
});

const component = (fileNumber) => ({
    fileIndexingData: { file_number: fileNumber },
    fhDestinationLabel: () => 'File history',
    fhSignature: (...parts) => parts.join('|'),
    fhSystemTotRow,
});
const row = (instrument, p1, p2, ts, extra = {}) => ({ instrument, p1, p2, sortTs: ts, date: ts ? new Date(ts).toISOString().slice(0, 10) : '', derived: false, ...extra });

const SA_2000 = row('Sale Agreement', 'ALH.HASSAN ABUBAKAR', 'ABDULSSALAM MALAM BAZE', Date.UTC(2000, 2, 4));
const COFO_2017 = row('Certificate of Occupancy', 'KANO STATE GOVERNMENT', 'ABDULSSALAM BAZE', Date.UTC(2017, 4, 29));

test('previews the transfer: SA buyer to the name on the CofO, just before the CofO', () => {
    const tot = component('SLTR-91811').fhSystemTotRow([SA_2000, COFO_2017]);
    assert.equal(tot.instrument, 'Transfer of Title');
    assert.equal(tot.status, 'SYSTEM');
    assert.equal(tot.statusLabel, 'System generated');
    assert.equal(tot.p1, 'ABDULSSALAM MALAM BAZE');
    assert.equal(tot.p2, 'ABDULSSALAM BAZE');
    assert.equal(tot.date, '');
    assert.ok(tot.sortTs < COFO_2017.sortTs && tot.sortTs > SA_2000.sortTs);
    assert.match(tot.note, /system/i);
});

test('only a Sale Agreement counts, and the latest one is used', () => {
    const later = row('Sale Agreement', 'X', 'NEW BUYER', Date.UTC(2010, 0, 1));
    assert.equal(component('SLTR-1').fhSystemTotRow([SA_2000, later, COFO_2017]).p1, 'NEW BUYER');
    // A Deed of Assignment is not the agreement (user, 2026-10-30), nor an affidavit about one.
    const assignment = row('Deed of Assignment', 'ew', 'ASSIGNEE', Date.UTC(2012, 0, 1));
    assert.equal(component('SLTR-1').fhSystemTotRow([SA_2000, assignment, COFO_2017]).p1, 'ABDULSSALAM MALAM BAZE');
    assert.equal(component('SLTR-1').fhSystemTotRow([assignment, COFO_2017]), null);
    assert.equal(component('SLTR-1').fhSystemTotRow([row('Affidavit for loss of Sale Agreement', 'A', 'B', 1), COFO_2017]), null);
    // Undated rows do not break the ordering (no NaN from -Infinity).
    const undated = row('SALE AGREEMENT', 'X', 'UNDATED BUYER', null);
    assert.equal(component('SLTR-1').fhSystemTotRow([undated, later, COFO_2017]).p1, 'NEW BUYER');
});

test('nothing when it does not apply', () => {
    const c = component('SLTR-91811');
    assert.equal(component('RES-1981-308').fhSystemTotRow([SA_2000, COFO_2017]), null);
    assert.equal(c.fhSystemTotRow([SA_2000]), null);
    assert.equal(c.fhSystemTotRow([COFO_2017]), null);
    assert.equal(c.fhSystemTotRow([SA_2000, COFO_2017, row('Transfer of Title', 'A', 'B', Date.UTC(2015, 0, 1))]), null);
    assert.equal(c.fhSystemTotRow([row('Sale Agreement', 'A', '', 1), COFO_2017]), null);
    // The certificate itself, not an affidavit about it; the SLTR spelling counts.
    assert.equal(c.fhSystemTotRow([SA_2000, row('AFFIDAVIT FOR LOSST OF ORIGINAL CERTIFICATE OF OCCUPANCY & DOCUMENTS', 'A', 'B', 2)]), null);
    assert.equal(c.fhSystemTotRow([SA_2000, { ...COFO_2017, instrument: 'SLTR Certificate of Occupancy' }]).p2, 'ABDULSSALAM BAZE');
    // Derived context rows (File Commissioning) are not dealings.
    assert.equal(c.fhSystemTotRow([{ ...SA_2000, derived: true }, COFO_2017]), null);
});
