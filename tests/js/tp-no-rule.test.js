// Plain Node test: node tests/js/tp-no-rule.test.js
//
// The Capture OP form (public/js/instruments-capture.js) checks an OP's TP No before it submits, with a
// copy of App\Support\TpNumberKey::problem(). This runs that copy on the same numbers as
// tests/Unit/Survey/TpNumberKeyTest.php, so the two cannot drift apart unnoticed.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/instruments-capture.js'), 'utf8');

function slice(startMarker, endMarker) {
  const start = source.indexOf(startMarker);
  assert.ok(start >= 0, `missing ${startMarker}`);
  const end = source.indexOf(endMarker, start);
  assert.ok(end > start, `missing ${endMarker} after ${startMarker}`);
  return source.slice(start, end);
}

const code = slice('const TP_AGENCIES', 'function isOpCapture()');
const sandbox = {};
vm.runInNewContext(code + '\nthis.tpNoProblem = tpNoProblem;', sandbox);
const problem = sandbox.tpNoProblem;

const good = ['TP/KN/215M', 'TP/K/358E', 'TP/MLPP/DKD/001', 'TP/K/UC/DKD/14', 'TP/KNUPDA/UC/DTF/17',
  'TP/KAS/277', 'TP/KASEPPA/244', 'BLM/PP/005', 'TP/UDB/5A', 'TP/MB/4', 'tp/kn/215m', ' TP / KN / 215M ', 'TP/MLPP/KBT/256G'];

const bad = [
  ['', 'required'],
  ['   ', 'required'],
  ['TP/MLPP/DKD/0O1', 'not a layout number'],
  ['TP/,LPP/DKD/001', 'not a known agency'],
  ['TP/KNP/215M', 'not a known agency'],
  ['TP/MLLP/KBT/338G', 'not a known agency'],
  ['TP/UC/GZ/02', 'not a known agency'],
  ['TP.MLPP/KBT/338G', 'must start with TP/'],
  ['TP\\KN\\UC\\DTF\\16', 'must start with TP/'],
  ['TP/KASEPPA|244', 'needs the agency and the layout number'],
  ['TP/KNUPDA', 'needs the agency and the layout number'],
  ['TP/MLPP//KBT/307D', 'empty part'],
  ['TP/MLPP/KBT3070', 'not a layout number'],
  ['TP/KN/UC/G2/02', 'not an area code'],
  ['RDKN 123', 'must start with TP/'],
  ['__other__', 'must start with TP/'],
];

let n = 0;
for (const tp of good) {
  assert.strictEqual(problem(tp), '', `should accept ${JSON.stringify(tp)}`);
  n++;
}
for (const [tp, why] of bad) {
  const got = problem(tp);
  assert.ok(got.includes(why), `${JSON.stringify(tp)}: expected "${why}", got "${got}"`);
  n++;
}

// The agency list must be the PHP one, spelling for spelling.
const php = fs.readFileSync(path.join(__dirname, '../../app/Support/TpNumberKey.php'), 'utf8');
const phpBlock = php.slice(php.indexOf('private const AGENCIES'), php.indexOf('];', php.indexOf('private const AGENCIES')));
const phpAgencies = [...phpBlock.matchAll(/'([A-Z]+)'\s*=>/g)].map((m) => m[1]).sort();
const jsAgencies = [...sandbox.TP_AGENCIES || vm.runInNewContext(code + '\nTP_AGENCIES')].sort();
assert.deepStrictEqual(jsAgencies, phpAgencies, 'TP_AGENCIES in instruments-capture.js differs from TpNumberKey::AGENCIES');

console.log(`tp-no-rule: ${n + 1} checks passed`);
