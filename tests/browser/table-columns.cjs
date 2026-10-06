// Run with Node and playwright, jquery and datatables.net available in NODE_PATH.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const header = '<thead><tr><th>S/N</th><th>File Number</th><th>File Title</th><th>Remarks</th><th>Actions</th></tr></thead>';
const rows = '<tbody>' + Array.from({ length: 12 }, (_, i) => `<tr><td>${i + 1}</td><td>FILE-${i}</td><td>Title ${i}</td><td>Kano</td><td>Edit</td></tr>`).join('') + '</tbody>';
const fixture = `<!doctype html><meta name="table-columns-user" content="web:123"><style>.original td {padding:13px;color:rgb(20, 40, 60)}</style>
<table id="plain" class="original">${header}${rows}</table>
<table id="dt">${header}${rows}</table>
<table id="grouped"><thead><tr><th rowspan="2">S/N</th><th colspan="2">Property</th></tr><tr><th>Area</th><th>Location</th></tr></thead><tbody><tr><td>1</td><td>40</td><td>Kano</td></tr><tr><td colspan="3">No further records</td></tr></tbody></table>`;
(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('http://columns.test/**', route => route.fulfill({ contentType: 'text/html', body: fixture }));
    async function load(user) {
        await page.goto('http://columns.test/register');
        if (user) await page.locator('meta[name="table-columns-user"]').evaluate((el, value) => el.content = value, user);
        await page.addStyleTag({ path: path.join(root, 'public/css/table-columns.css') });
        await page.addScriptTag({ path: require.resolve('jquery') });
        await page.addScriptTag({ path: process.env.TABLE_COLUMNS_DATATABLES || require.resolve('datatables.net') });
        await page.evaluate(() => $('#dt').DataTable({ pageLength: 5 }));
        await page.addScriptTag({ path: path.join(root, 'public/js/table-columns.js') });
        await page.waitForFunction(() => document.querySelectorAll('.klaes-columns-toolbar').length === 3);
    }
    async function open(id) {
        await page.evaluate(id => {
            const table = document.getElementById(id);
            (table.closest('.dataTables_wrapper, .dt-container') || table).previousElementSibling.querySelector('button').click();
        }, id);
    }
    async function toggle(label, checked) {
        await page.locator('.klaes-columns-options label').filter({ hasText: new RegExp('^' + label + '$') }).locator('input').setChecked(checked);
    }
    async function done() { await page.getByRole('button', { name: 'Done', exact: true }).click(); }
    await load();
    await open('plain');
    assert.equal(await page.locator('.klaes-columns-options input:disabled').count(), 4);
    await toggle('Remarks', false);
    await done();
    assert.equal(await page.locator('#plain tbody tr:first-child td:nth-child(4)').isVisible(), false);
    assert.equal(await page.locator('#plain tbody tr:first-child td').first().evaluate(el => getComputedStyle(el).padding), '13px');
    await page.evaluate(() => document.querySelector('#plain tbody').innerHTML = '<tr><td>15</td><td>New</td><td>New title</td><td>New location</td><td>Edit</td></tr>');
    await page.waitForFunction(() => document.querySelector('#plain tbody td:nth-child(4)').classList.contains('klaes-column-hidden'));
    await open('dt');
    await toggle('Remarks', false);
    await done();
    assert.equal(await page.evaluate(() => $('#dt').DataTable().column(3).visible()), false);
    await page.evaluate(() => $('#dt').DataTable().page('next').draw(false));
    assert.equal(await page.locator('#dt tbody tr:first-child td').count(), 4);
    await page.evaluate(() => $('#dt').DataTable().column(1).visible(false));
    assert.equal(await page.evaluate(() => $('#dt').DataTable().column(1).visible()), true);
    await open('grouped');
    assert.equal(await page.locator('.klaes-columns-options label').filter({ hasText: 'Location' }).locator('input').isDisabled(), true);
    await toggle('Area', false);
    await done();
    assert.equal(await page.locator('#grouped thead tr:first-child th:nth-child(2)').getAttribute('colspan'), '1');
    assert.equal(await page.locator('#grouped tbody tr:last-child td').getAttribute('colspan'), '2');
    await load();
    assert.equal(await page.locator('#plain tbody tr:first-child td:nth-child(4)').isVisible(), false);
    assert.equal(await page.evaluate(() => $('#dt').DataTable().column(3).visible()), false);
    // A table upgraded to DataTables after the picker attaches keeps preferences.
    await page.evaluate(() => $('#plain').DataTable({ pageLength: 5 }));
    await page.waitForFunction(() => !$('#plain').DataTable().column(3).visible());
    await open('plain');
    await page.getByRole('button', { name: 'Show all', exact: true }).click();
    await done();
    assert.equal(await page.locator('#plain tbody tr:first-child td:nth-child(4)').isVisible(), true);
    await load('web:456');
    assert.equal(await page.evaluate(() => $('#dt').DataTable().column(3).visible()), true);
    await page.evaluate(() => {
        const table = document.getElementById('plain').cloneNode(true);
        table.id = 'dynamic';
        document.body.append(table);
    });
    await page.waitForFunction(() => document.querySelectorAll('.klaes-columns-toolbar').length === 4);
    await open('dynamic');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('dialog'));
    assert.equal(await page.locator('dialog').count(), 0);
    assert.deepEqual(errors, []);
    await browser.close();
    console.log('PASS: required fields, custom styles, row replacement, pagination, grouped headings, persistence, late DataTables, account isolation, dynamic tables, keyboard dismissal.');
})().catch(error => { console.error(error); process.exit(1); });
