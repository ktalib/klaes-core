// node tests/js/primaryform-format-currency.test.js
// Regression: formatCurrency stripped the decimal point, so a restored draft
// turned the initial bill's 10,000.00 into N1,000,000.
const assert = require('assert');
const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '../../public/js/primaryform/utilities.js'), 'utf8');
const fnSrc = src.match(/function formatCurrency[\s\S]*?\n}/)[0];
const formatCurrency = new Function(fnSrc + '; return formatCurrency;')();

function run(value, { readOnly = false, feeInput = false } = {}) {
    const input = { value, readOnly, classList: { contains: (c) => feeInput && c === 'fee-input' } };
    formatCurrency(input);
    return input.value;
}

assert.strictEqual(run('10,000.00', { readOnly: true, feeInput: true }), '10,000.00', 'readonly bill fee untouched');
assert.strictEqual(run('10,000.00', { feeInput: true }), '10,000.00', 'fee-input untouched');
assert.strictEqual(run('10,000.00'), '₦10,000.00', 'decimal point kept');
assert.strictEqual(run('1234567'), '₦1,234,567');
assert.strictEqual(run('₦12,345.678'), '₦12,345.67');
assert.strictEqual(run(''), '');

console.log('primaryform-format-currency: ok');
