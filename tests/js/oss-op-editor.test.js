const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const elements = new Map();
function element(id) {
    if (!elements.has(id)) {
        const classes = new Set();
        elements.set(id, {
            value: '', options: [], disabled: false,
            classList: {
                add(name) { classes.add(name); },
                remove(name) { classes.delete(name); },
                contains(name) { return classes.has(name); },
                toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
            },
        });
    }
    return elements.get(id);
}
const context = vm.createContext({
    document: { querySelector: () => null, addEventListener() {}, getElementById: element },
    window: {}, console,
});
vm.runInContext(fs.readFileSync('public/js/lands-one-stop-shop/applications.js', 'utf8'), context);

for (const editId of ['', '37594']) {
    element('oss_id').value = editId;
    context._ossResetOccupancyPermit();
    context._ossApplyOccupancyPermitFromPra({ op_record_id: 119796, op_serial_number: null, file_number: 'RES-2025-6064' });
    assert.equal(element('oss_opd_section').classList.contains('hidden'), false);
    assert.equal(element('oss_opd_op_serial_number').disabled, false);
    assert.equal(context._ossOpdRecordId, 119796);
    assert.equal(element('ossSubmitBtn').disabled, false);

    context._ossApplyOccupancyPermitFromPra({ op_record_id: 119796, op_serial_number: '109' });
    assert.equal(element('oss_opd_op_serial_number').value, '109');
    assert.equal(element('oss_opd_op_serial_number').disabled, true);
    context.ossToggleOpdEdit();
    assert.equal(element('oss_opd_op_serial_number').disabled, false);

    context._ossApplyOccupancyPermitFromPra({ op_record_id: 0, op_serial_number: '999' });
    assert.equal(element('oss_opd_section').classList.contains('hidden'), true);
    assert.equal(element('ossSubmitBtn').disabled, !editId);
}
console.log('PASS: existing OP editor is visible without a serial in create and edit; missing OP stays hidden.');
