// Requires playwright (NODE_PATH may point to a temporary install). Run: node tests/browser/file-log-batch-pick.cjs
// File Movement (Department) > Batch receive / send: the "select a file" dropdown is searchable
// (user, 2026-11-04). The block is lifted from secretariat_file_log/index.blade.php itself, with
// the pick_list partial rendered the way @include would.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'resources/views/secretariat_file_log/index.blade.php'), 'utf8');
const partial = fs.readFileSync(path.join(root, 'resources/views/secretariat_file_log/partials/pick_list.blade.php'), 'utf8');

const start = view.indexOf('<div class="relative" x-data="fileLogPickList(() => batch.pick)"');
assert.ok(start > 0, 'batch pick block not found in the view');
const include = view.indexOf('@include(\'secretariat_file_log.partials.pick_list\'', start);
const includeEnd = view.indexOf('])', include) + 2;
const blockEnd = view.indexOf('</div>', includeEnd) + '</div>'.length;
const placeholder = view.slice(include, includeEnd).match(/'placeholder' => "([^"]*)"/)[1];
const face = partial
    .replace(/\{\{--[\s\S]*?--\}\}/, '')
    .replace(/@if \(!empty\(\$required\)\)\s*([\s\S]*?)\s*@else\s*([\s\S]*?)\s*@endif/, (m, yes, no) => no)
    .split('{{ $model }}').join('batch.pick')
    .split('{{ $placeholder }}').join(placeholder);
const block = view.slice(start, include) + face + view.slice(includeEnd, blockEnd);

const html = `<!doctype html><html><head><meta charset="utf-8"><style>.hidden{display:none}.relative{position:relative}.absolute{position:absolute}.w-full{width:100%}#pick{width:420px}</style></head><body>
<div x-data="page()">
  <div id="pick">${block}</div>
  <pre id="state" x-text="JSON.stringify({ items: batch.items.map(i => i.id), pick: batch.pick })"></pre>
</div>
<script>
window.page = () => ({
  batch: { items: [], pick: '', running: false },
  lists: { pending: [
    { id: 1, file_number: 'RES-1982-325(T)', file_title: 'A MUSA ISA' },
    { id: 2, file_number: 'RES-1981-770', file_title: 'SULEIMAN SAAD ABUBAKAR' },
    { id: 3, file_number: 'CON-RES-2011-399', file_title: 'SULEIMAN ADAMU' },
    { id: 4, file_number: null, related_file_number: 'SLTR-219306', file_title: 'HANEEF MEMORIAL ACADEMY KANO' },
  ] },
  mode: 'in',
  nice(v) { return String(v || '').toLowerCase().replace(/\\b\\w/g, c => c.toUpperCase()); },
  batchHas(id) { return this.batch.items.some(i => i.id === id); },
  batchPickable() { return this.lists.pending.filter(r => !this.batchHas(r.id)); },
  addToBatch(r) { this.batch.items.push({ id: r.id }); },
  addPicked() {
    const row = this.batchPickable().find(r => String(r.id) === String(this.batch.pick));
    if (row) this.addToBatch(row);
    this.batch.pick = '';
  },
});
</script>
</body></html>`;

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') errors.push(m.text()); });
    await page.route('http://klaes.test/**', route => route.fulfill({ contentType: 'text/html', body: html }));
    await page.goto('http://klaes.test/form');
    await page.addScriptTag({ path: path.join(root, 'public/js/secretariat-file-log.js') });
    await page.addScriptTag({ path: path.join(root, 'node_modules/alpinejs/dist/cdn.min.js') });
    await page.waitForTimeout(200);

    const state = async () => JSON.parse(await page.locator('#state').textContent());
    const input = page.locator('#pick input[x-ref="psInput"]');
    const options = async () => { await page.waitForTimeout(60); return page.locator('#pick button[data-ps-hi]:visible').allTextContents(); };

    // Closed: placeholder offers search.
    assert.equal(await input.getAttribute('placeholder'), '— Search or select a file —');

    // Opening lists every pending file, mails by their related file number.
    await input.click();
    assert.deepEqual(await options(), [
        'RES-1982-325(T) — A Musa Isa',
        'RES-1981-770 — Suleiman Saad Abubakar',
        'CON-RES-2011-399 — Suleiman Adamu',
        'SLTR-219306 — Haneef Memorial Academy Kano',
    ]);

    // Search by file number fragment, case-insensitive.
    await input.fill('res-2011');
    assert.deepEqual(await options(), ['CON-RES-2011-399 — Suleiman Adamu']);

    // Search by name, several words.
    await input.fill('suleiman saad');
    assert.deepEqual(await options(), ['RES-1981-770 — Suleiman Saad Abubakar']);

    // Enter adds the highlighted file to the batch and clears the pick.
    await input.press('Enter');
    await page.waitForTimeout(60);
    assert.deepEqual(await state(), { items: [2], pick: '' });

    // An added file leaves the list; a click adds another.
    await input.click();
    await input.fill('haneef');
    assert.deepEqual(await options(), ['SLTR-219306 — Haneef Memorial Academy Kano']);
    await page.locator('#pick button[data-ps-hi]:visible').first().dispatchEvent('mousedown');
    await page.waitForTimeout(60);
    assert.deepEqual(await state(), { items: [2, 4], pick: '' });
    await input.click();
    assert.deepEqual(await options(), ['RES-1982-325(T) — A Musa Isa', 'CON-RES-2011-399 — Suleiman Adamu']);

    // No match says so; Escape closes.
    await input.fill('zzz');
    assert.equal(await page.locator('#pick').getByText('No match.').isVisible(), true);
    await input.press('Escape');
    await page.waitForTimeout(60);
    assert.equal(await page.locator('#pick [x-ref="psList"]').isVisible(), false);

    assert.deepEqual(errors, []);
    await browser.close();
    console.log('file-log-batch-pick: all checks passed');
})().catch(e => { console.error(e); process.exit(1); });
