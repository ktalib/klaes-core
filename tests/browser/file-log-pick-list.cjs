// Requires playwright (NODE_PATH may point to a temporary install). Run: node tests/browser/file-log-pick-list.cjs
// Searchable dropdowns on File Movement (Department) > Log a file manually:
// window.fileLogPickList (public/js/secretariat-file-log.js) + secretariat_file_log/partials/pick_list.blade.php.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const partial = fs.readFileSync(path.join(root, 'resources/views/secretariat_file_log/partials/pick_list.blade.php'), 'utf8');
// Render the partial the way @include would, for one model.
const face = (model, placeholder, required) => partial
    .replace(/\{\{--[\s\S]*?--\}\}/, '')
    .replace(/@if \(!empty\(\$required\)\)\s*([\s\S]*?)\s*@else\s*([\s\S]*?)\s*@endif/, (m, yes, no) => (required ? yes : no))
    .split('{{ $model }}').join(model)
    .split('{{ $placeholder }}').join(placeholder);

const html = `<!doctype html><html><head><meta charset="utf-8"><style>.hidden{display:none}.relative{position:relative}.absolute{position:absolute}.z-30{z-index:30}.w-full{width:100%}.bg-white{background:#fff}#tools{position:fixed;right:0;top:0;width:300px}body>div>div.relative{width:300px}</style></head><body>
<div x-data="page()">
  <div id="dept" class="relative" x-data="fileLogPickList(() => manual.from_department)" @click.outside="psClose()">
    <select x-ref="sel" x-model="manual.from_department" @change="fromDepartmentChanged()" class="hidden">
      <option value="">— Select the department —</option>
      <template x-for="d in departments()" :key="d"><option :value="d" x-text="d"></option></template>
      <option value="other">Other (Specify)…</option>
    </select>
    ${face('manual.from_department', "'— Select the department —'", true)}
  </div>
  <div id="office" class="relative" x-data="fileLogPickList(() => manual.from_office)" @click.outside="psClose()">
    <select x-ref="sel" x-model="manual.from_office" class="hidden">
      <option value="">— Select —</option>
      <template x-for="o in officesIn(manual.from_department)" :key="o.code"><option :value="o.code" x-text="o.name + ' (' + o.code + ')'"></option></template>
      <option value="other">Other</option>
    </select>
    ${face('manual.from_office', "manual.from_department ? '— Select the office —' : '— Select the department first —'", true)}
  </div>
  <div id="sendto" class="relative" x-data="fileLogPickList(() => manual.send_to_office)" @click.outside="psClose()">
    <select x-ref="sel" x-model="manual.send_to_office" class="hidden">
      <option value="">— Select the office —</option>
      <template x-for="g in groups()" :key="g.department"><optgroup :label="g.department">
        <template x-for="o in g.items" :key="o.code"><option :value="o.code" x-text="o.name + ' (' + o.code + ')'"></option></template>
      </optgroup></template>
    </select>
    ${face('manual.send_to_office', "'— Select the office —'", true)}
  </div>
  <div id="tools"><button id="prefill" @click="manual.send_to_office = 'CAD-1'">prefill</button>
  <button id="reset" @click="manual = { from_department: '', from_office: '', send_to_office: '' }">reset</button>
  <pre id="state" x-text="JSON.stringify(manual)"></pre></div>
</div>
<script>
window.page = () => ({
  offices: [
    { code: 'LND-1', name: 'Director Lands', department: 'Lands' },
    { code: 'LND-2', name: 'Deeds Registry', department: 'Lands' },
    { code: 'CAD-1', name: 'Survey Records', department: 'Cadastral' },
  ],
  changes: 0,
  manual: { from_department: '', from_office: '', send_to_office: '' },
  departments() { return [...new Set(this.offices.map(o => o.department))]; },
  officesIn(d) { return this.offices.filter(o => !d || o.department === d); },
  groups() { return this.departments().map(d => ({ department: d, items: this.officesIn(d) })); },
  fromDepartmentChanged() { this.changes++; this.manual.from_office = this.manual.from_department === 'other' ? 'other' : ''; },
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
    const input = id => page.locator(`#${id} input[x-ref="psInput"]`);
    const options = async id => { await page.waitForTimeout(60); return page.locator(`#${id} button[data-ps-hi]:visible`).allTextContents(); };

    // Closed and empty: the placeholder, red border.
    assert.equal(await input('office').getAttribute('placeholder'), '— Select the department first —');
    assert.match(await input('dept').getAttribute('class'), /border-red-300/);

    // Typing filters; "Other" always stays at the foot.
    await input('dept').click();
    assert.deepEqual(await options('dept'), ['Lands', 'Cadastral', 'Other (Specify)…']);
    await input('dept').fill('cada');
    assert.deepEqual(await options('dept'), ['Cadastral', 'Other (Specify)…']);
    await input('dept').fill('zzz');
    assert.deepEqual(await options('dept'), ['Other (Specify)…']);
    await page.locator('#dept').getByText('No match.').waitFor({ state: 'visible', timeout: 2000 });

    // Picking fires the select's change: x-model updates and @change runs.
    await input('dept').fill('lan');
    await page.keyboard.press('Enter');
    let s = await state();
    assert.equal(s.from_department, 'Lands');
    assert.equal(await page.evaluate(() => document.querySelector('[x-data="page()"]')._x_dataStack[0].changes), 1);
    assert.equal(await input('dept').inputValue(), 'Lands');
    assert.match(await input('dept').getAttribute('class'), /border-gray-300/);

    // The office list follows the department.
    assert.equal(await input('office').getAttribute('placeholder'), '— Select the office —');
    await input('office').click();
    assert.deepEqual(await options('office'), ['Director Lands (LND-1)', 'Deeds Registry (LND-2)', 'Other']);
    await input('office').fill('reg');
    await page.locator('#office button[data-ps-hi]', { hasText: 'Deeds Registry' }).click();
    assert.equal((await state()).from_office, 'LND-2');
    assert.equal(await input('office').inputValue(), 'Deeds Registry (LND-2)');

    // Changing the department clears the office (fromDepartmentChanged), and the face follows.
    await input('dept').click();
    await input('dept').fill('cad');
    await page.keyboard.press('Enter');
    s = await state();
    assert.equal(s.from_department, 'Cadastral');
    assert.equal(s.from_office, '');
    assert.equal(await input('office').inputValue(), '');

    // Optgroups show as headings; a search matches the department name too; arrows move the highlight.
    await input('sendto').click();
    await page.waitForTimeout(60);
    assert.deepEqual(await page.locator('#sendto div[x-show="row.heading"]:visible').allTextContents(), ['Lands', 'Cadastral']);
    await input('sendto').fill('lands');
    assert.deepEqual(await options('sendto'), ['Director Lands (LND-1)', 'Deeds Registry (LND-2)']);
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    assert.equal((await state()).send_to_office, 'LND-2');

    // Escape closes without changing; the shown text returns.
    await input('sendto').click();
    await input('sendto').fill('survey');
    await page.keyboard.press('Escape');
    assert.equal((await state()).send_to_office, 'LND-2');
    assert.equal(await input('sendto').inputValue(), 'Deeds Registry (LND-2)');
    assert.equal(await page.locator('#sendto div[x-ref="psList"]').isVisible(), false);

    // Click outside closes.
    await input('dept').click();
    await page.waitForTimeout(60);
    await page.click('#state');
    await page.waitForTimeout(60);
    assert.equal(await page.locator('#dept div[x-ref="psList"]').isVisible(), false);

    // Code that sets the model (officer pick pre-fills Send to; the form reset) updates the face.
    await page.click('#prefill');
    assert.equal(await input('sendto').inputValue(), 'Survey Records (CAD-1)');
    await page.click('#reset');
    for (const id of ['dept', 'office', 'sendto']) assert.equal(await input(id).inputValue(), '');

    assert.deepEqual(errors, []);
    await browser.close();
    console.log('file-log-pick-list: all checks passed');
})().catch(e => { console.error(e); process.exit(1); });
