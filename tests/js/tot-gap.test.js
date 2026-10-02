/**
 * The leg-walk, lifted out of the Alpine component and run against the plan's examples.
 * Keeps the logic honest without needing a browser.
 */
const fs = require('fs');

const blade = fs.readFileSync('resources/views/fileindexing/partial/property_transaction_modal.blade.php', 'utf8');

// Pull the methods straight out of the blade so this tests the SHIPPED source, not a copy.
function grab(name) {
  const start = blade.indexOf(`            ${name}(`);
  if (start === -1) throw new Error(`method not found: ${name}`);
  let depth = 0, i = blade.indexOf('{', start), begun = false;
  for (; i < blade.length; i++) {
    if (blade[i] === '{') { depth++; begun = true; }
    else if (blade[i] === '}') { depth--; if (begun && depth === 0) break; }
  }
  return blade.slice(start, i + 1).trim().replace(/,$/, '');
}

const src = ['totMovesOwnership', 'totIsGrant', 'totIsGovernment', 'totPersonKey', 'totSamePerson', 'totMissingLegs']
  .map(grab).join(',\n');

const component = new Function(`return { totIsConversionFile(){return true;}, fhSummaryRows(){return this._rows;}, ${src} };`)();

function run(label, rows, expected) {
  component._rows = rows.map((r) => ({
    derived: !!r.derived, instrument: r.i, p1: r.a || '', p2: r.b || '',
  }));
  const legs = component.totMissingLegs();
  const got = legs.map((l) => `${l.from} > ${l.to}`);
  const ok = JSON.stringify(got) === JSON.stringify(expected);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}`);
  console.log(`      expected: ${expected.length ? expected.join('  |  ') : '(none)'}`);
  if (!ok) console.log(`      got:      ${got.length ? got.join('  |  ') : '(none)'}`);
  return ok;
}

let pass = 0, total = 0;
const check = (...args) => { total++; if (run(...args)) pass++; };

// ── Example 1: a single missing leg ──────────────────────────────────────────
check('Ex1  one missing leg', [
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'AUDU BELLO' },
  { i: 'Deed of Assignment', a: 'MUSA IDRIS', b: 'HALIMA SANI' },
], ['AUDU BELLO > MUSA IDRIS']);

// ── Example 3: the gap is at the TOP of a long chain ─────────────────────────
check('Ex3  gap at the top of a long chain', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'Owner 1' },
  { i: 'Transfer of Title', a: 'Owner 2', b: 'Owner 3' },
  { i: 'Transfer of Title', a: 'Owner 3', b: 'Owner 4' },
  { i: 'Deed of Assignment', a: 'Owner 4', b: 'New owner' },
], ['Owner 1 > Owner 2']);

// ── Example 4: nothing missing; a mortgage opens no leg ──────────────────────
check('Ex4  complete chain + mortgage', [
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'ABBA SANI' },
  { i: 'Transfer of Title', a: 'ABBA SANI', b: 'YUSUF GARBA' },
  { i: 'Mortgage', a: 'YUSUF GARBA', b: 'First Bank plc' },
], []);

// ── Example 2: merger, second file's two-step chain ──────────────────────────
check('Ex2  Original > Intermediate > New, none recorded', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'Original owner' },
  { i: 'Deed of Gift', a: 'Intermediate owner', b: 'New owner' },
], ['Original owner > Intermediate owner']);

// ── An existing TOT is never asked for twice ─────────────────────────────────
check('Dup  leg already recorded is skipped', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'AUDU BELLO' },
  { i: 'Transfer of Title', a: 'AUDU BELLO', b: 'MUSA IDRIS' },
  { i: 'Deed of Assignment', a: 'MUSA IDRIS', b: 'HALIMA SANI' },
], []);

// ── Spelling drift must NOT raise a self-transfer ────────────────────────────
check('Spell  MOHD/MUHD is one person, not a leg', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'MOHD SANI ABUBAKAR' },
  { i: 'Deed of Assignment', a: 'MUHD SANI ABUBAKAR', b: 'HALIMA SANI' },
], []);

check('Spell  honorific only', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'KABIRU USMAN KULO' },
  { i: 'Deed of Assignment', a: 'ALH KABIRU USMAN KULO', b: 'HALIMA SANI' },
], []);

// ── Derived rows are context, not dealings ───────────────────────────────────
check('Derived  File Commissioning ignored', [
  { i: 'File Commissioning', a: 'Kano State Ministry', b: 'FARUK HARUNA', derived: true },
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'AUDU BELLO' },
  { i: 'Deed of Assignment', a: 'AUDU BELLO', b: 'HALIMA SANI' },
], []);

// ── Two separate gaps in one chain ───────────────────────────────────────────
check('Multi  two gaps reported separately', [
  { i: 'Occupancy Permit', a: 'Kano State Government', b: 'Owner 1' },
  { i: 'Deed of Assignment', a: 'Owner 2', b: 'Owner 3' },
  { i: 'Deed of Conveyance', a: 'Owner 4', b: 'Owner 5' },
], ['Owner 1 > Owner 2', 'Owner 3 > Owner 4']);

// ── A grant never receives a transfer ────────────────────────────────────────
check('Grant  no leg into a government grant', [
  { i: 'Deed of Assignment', a: 'Owner 1', b: 'Owner 2' },
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'Owner 3' },
], []);

// -- Regression: CON/RES/87/348, the file that reported 2 phantom ToTs --------
// Production, 2026-09-02. One assignment on the paper; the card demanded two
// Transfers of Title. Three separate defects stacked:
//   - the walk read EVERY row, so the KANGIS recertification opened a leg;
//   - 'Kano Geographic Information Service' was not read as an authority;
//   - the same assignment sat on the file twice, spelt Ahmed and Ahmad, and the
//     second copy was read as a transfer back to the assignor.
check('CON/RES/87/348  one assignment, no phantom legs', [
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'ALHAJI IBRAHIM GWADABE KABARA' },
  { i: 'Deed of Assignment', a: 'ALHAJI IBRAHIM GWADABE KABARA', b: 'Ahmed Shitu Abubakar' },
  { i: 'Deed of Assignment', a: 'ALHAJI IBRAHIM GWADABE KABARA', b: 'Ahmad Shitu Abubakar' },
  { i: 'Recertification', a: 'Kano Geographic Information Service', b: 'Ahmad Shitu Abubakar' },
], []);

// -- A mortgage is not a link in the chain (the plan says so in bold) ---------
// This is the shape that made the bug general rather than one file's problem:
// 1,126 conversion files carry a mortgage row.
check('Mortgage  a mortgage after an assignment opens no leg', [
  { i: 'Deed of Assignment', a: 'ALHAJI IBRAHIM GWADABE KABARA', b: 'Ahmad Shitu Abubakar' },
  { i: 'Deed of Mortgage', a: 'First Bank plc', b: 'Ahmad Shitu Abubakar' },
], []);

check('Lease  a lease between two strangers opens no leg', [
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'AUDU BELLO' },
  { i: 'Deed of Lease', a: 'SANI GARBA', b: 'HALIMA SANI' },
], []);

// -- ...but a real gap either side of a mortgage is still reported ------------
check('Mortgage  a real gap around a mortgage is still reported', [
  { i: 'Right of Occupancy', a: 'Kano State Government', b: 'AUDU BELLO' },
  { i: 'Deed of Mortgage', a: 'AUDU BELLO', b: 'First Bank plc' },
  { i: 'Deed of Assignment', a: 'MUSA IDRIS', b: 'HALIMA SANI' },
], ['AUDU BELLO > MUSA IDRIS']);

console.log(`\n${pass}/${total} passed`);
process.exit(pass === total ? 0 : 1);
