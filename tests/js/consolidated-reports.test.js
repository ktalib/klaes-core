const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function driver() {
    const elements = {};
    for (const id of ['recordsExportSearch', 'recordsExportStatus', 'recordsExportStartDate', 'recordsExportEndDate', 'recordsExportBody', 'recordsExportCount', 'recordsExportModal']) {
        elements[id] = { value: '', innerHTML: '', textContent: '', addEventListener() {}, classList: { add() {}, remove() {} } };
    }
    const alerts = [];
    const window = { location: { origin: 'https://example.test' }, recordsExportConfig: { endpoint: '/report', department: 'Deeds Department' } };
    const context = vm.createContext({ window, document: { addEventListener() {}, getElementById: id => elements[id], querySelector: () => ({ innerHTML: '' }) },
        URL, alert: message => alerts.push(message), fetch: async () => { throw Error('offline'); }, console });
    vm.runInContext(fs.readFileSync('public/js/records_export.js', 'utf8'), context);
    return { window, context, elements, alerts };
}

const payload = value => ({ ok: true, json: async () => ({ success: true, columns: [{ key: 'name', label: 'Name' }], data: [{ name: value }] }) });

test('failed refresh clears downloadable rows and count', async () => {
    const d = driver();
    d.context.fetch = async () => payload('First');
    await d.window.loadRecordsExportData();
    assert.equal(d.window.recordsExportData.length, 1);
    d.context.fetch = async () => { throw Error('offline'); };
    await d.window.loadRecordsExportData();
    assert.equal(d.window.recordsExportData.length, 0);
    assert.equal(d.elements.recordsExportCount.textContent, '0');
});

test('older response cannot overwrite a newer preview', async () => {
    const d = driver();
    let resolveFirst;
    d.context.fetch = () => new Promise(resolve => { resolveFirst = resolve; });
    const first = d.window.loadRecordsExportData();
    d.context.fetch = async () => payload('New');
    await d.window.loadRecordsExportData();
    resolveFirst(payload('Old'));
    await first;
    assert.equal(d.window.recordsExportData[0].name, 'NEW');
});

test('changed filters cannot export the old preview', async () => {
    const d = driver();
    d.context.fetch = async () => payload('First');
    await d.window.loadRecordsExportData();
    d.elements.recordsExportSearch.value = 'Different';
    d.window.downloadRecordsExportCsv();
    assert.match(d.alerts[0], /Refresh the preview/);
});

test('invalid date range makes no request and clears old data', async () => {
    const d = driver();
    let called = false;
    d.context.fetch = async () => { called = true; return payload('Row'); };
    d.elements.recordsExportStartDate.value = '2026-10-04';
    d.elements.recordsExportEndDate.value = '2026-10-03';
    await d.window.loadRecordsExportData();
    assert.equal(called, false);
    assert.match(d.elements.recordsExportBody.innerHTML, /End date must/);
});

test('PDF prints the configured department and report title', async () => {
    const d = driver();
    d.window.recordsExportConfig.reportTitle = 'Consent Consolidated Report';
    d.context.fetch = async () => payload('First');
    await d.window.loadRecordsExportData();
    const text = [];
    let resolveSaved;
    const saved = new Promise(resolve => { resolveSaved = resolve; });
    d.window.jspdf = { jsPDF: function () {
        return {
            internal: { pageSize: { getWidth: () => 297, getHeight: () => 210 }, getNumberOfPages: () => 1 },
            setFont() {}, setFontSize() {}, setTextColor() {}, setLineWidth() {}, line() {}, setPage() {},
            text: value => text.push(value), autoTable: options => options.didDrawPage(), save: resolveSaved,
        };
    } };
    // Missing logos are tolerated; the text heading must still be correct.
    d.context.fetch = async () => { throw Error('No logo'); };
    d.window.downloadRecordsExportPdf();
    await saved;
    assert.ok(text.includes('DEEDS DEPARTMENT'));
    assert.ok(text.includes('Consent Consolidated Report'));
    assert.ok(text.some(value => value.startsWith('Total Records: 1 | ')));
    assert.ok(!text.includes('LAND DEPARTMENT'));
});
