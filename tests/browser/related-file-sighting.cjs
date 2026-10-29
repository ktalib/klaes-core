// Requires playwright (NODE_PATH may point to a temporary install). Run: node tests/browser/related-file-sighting.cjs
// Related File (Physical File is Sighted) control on File Indexing rows (public/js/fileindexing/related-file-sighting.js).
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const html = `<!doctype html><html><head><meta charset="utf-8"><style>.hidden{display:none}</style></head><body>
<div id="related-files-wrapper">
  <div class="related-file-row flex gap-2"><div class="flex-grow"><input type="text" name="related_fileno[]" class="related-file-input" value="RES-1" readonly></div><div><button type="button" class="clear-related-btn">x</button></div></div>
  <div class="related-file-row flex gap-2"><div class="flex-grow"><input type="text" name="related_fileno[]" class="related-file-input" value="" readonly></div></div>
</div>
<input type="hidden" id="related-file-sightings" name="related_file_sightings"
  value='[{"file_number":"res-1 ","sighted":false,"cover_missing":null},{"file_number":"RES-7","sighted":false,"cover_missing":null}]'
  data-required-message="You must indicate whether the Related File physical file has been sighted."
  data-cover-text="Cover missing – contents sighted">
</body></html>`;

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => { errors.push(e.message); });
    page.on('dialog', d => d.dismiss());
    // Stub origin: the page and the "is it indexed?" endpoint (only RES-7 is indexed).
    await page.route('http://klaes.test/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/fileindexing/api/related-file-indexed') {
            const no = (url.searchParams.get('file_number') || '').toUpperCase();
            return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, file_number: no, indexed: no === 'RES-7' }) });
        }
        return route.fulfill({ contentType: 'text/html', body: html });
    });
    await page.goto('http://klaes.test/form');
    await page.addScriptTag({ path: path.resolve(__dirname, '../../public/js/fileindexing/related-file-sighting.js') });
    await page.waitForTimeout(100);

    const rows = page.locator('.related-file-row');
    const state = i => rows.nth(i).locator('.related-sighting').evaluate(b => ({
        hidden: b.classList.contains('hidden'),
        sighted: b.dataset.sighted,
        npf: !b.querySelector('.rf-npf-badge').classList.contains('hidden'),
        cover: !b.querySelector('.rf-cover').classList.contains('hidden'),
    }));

    // 1. Saved answer pre-fills (key ignores case/space); empty row has a hidden block.
    assert.deepEqual(await state(0), { hidden: false, sighted: '0', npf: true, cover: false });
    assert.equal((await state(1)).hidden, true);

    // 2. A file number set by script (no event) is picked up by the poll, unanswered.
    await rows.nth(1).locator('.related-file-input').evaluate(i => { i.value = 'RES-2'; });
    await page.waitForTimeout(1000);
    assert.deepEqual(await state(1), { hidden: false, sighted: '', npf: false, cover: false });

    // 3. Validation blocks while RES-2 is unanswered and flags the row.
    assert.equal(await page.evaluate(() => window.RelatedFileSighting.validate()), false);
    assert.equal(await rows.nth(1).locator('.rf-error').isVisible(), true);

    // 4. Yes + cover missing + note -> payload; error clears.
    await rows.nth(1).locator('.rf-choice[data-value="1"]').click();
    assert.equal(await rows.nth(1).locator('.rf-error').isVisible(), false);
    await rows.nth(1).locator('.rf-cover-cb').check();
    await rows.nth(1).locator('.rf-cover-note').fill('cover torn');
    // Clicking Yes again keeps the cover tick.
    await rows.nth(1).locator('.rf-choice[data-value="1"]').click();
    assert.equal(await rows.nth(1).locator('.rf-cover-cb').isChecked(), true);
    assert.equal(await page.evaluate(() => window.RelatedFileSighting.validate()), true);
    assert.deepEqual(JSON.parse(await page.evaluate(() => window.RelatedFileSighting.collect())), [
        { file_number: 'RES-1', sighted: false, cover_missing: null },
        { file_number: 'RES-2', sighted: true, cover_missing: 'cover torn' },
    ]);

    // 5. Switching to No drops the cover note.
    await rows.nth(1).locator('.rf-choice[data-value="0"]').click();
    assert.deepEqual(await state(1), { hidden: false, sighted: '0', npf: true, cover: false });

    // 6. A cloned row starts unanswered and leaves the original alone.
    await page.evaluate(() => {
        const w = document.getElementById('related-files-wrapper');
        const c = w.querySelector('.related-file-row').cloneNode(true);
        c.querySelector('.related-file-input').value = 'RES-3';
        w.appendChild(c);
    });
    await page.waitForTimeout(100);
    assert.equal(await page.locator('.related-file-row').count(), 3);
    assert.equal(await page.locator('.related-file-row').nth(2).locator('.related-sighting').count(), 1);
    assert.equal((await state(2)).sighted, '');
    assert.equal((await state(0)).sighted, '0');

    // 7. Picking another file in a row clears its answer.
    await rows.nth(0).locator('.related-file-input').evaluate(i => { i.value = 'RES-9'; i.dispatchEvent(new Event('input', { bubbles: true })); });
    await page.waitForTimeout(100);
    assert.equal((await state(0)).sighted, '');

    // 8. reload() swaps in another record's answers.
    await page.evaluate(() => window.RelatedFileSighting.reload([{ file_number: 'RES-9', sighted: true, cover_missing: 'Cover missing – contents sighted' }]));
    assert.deepEqual(await state(0), { hidden: false, sighted: '1', npf: false, cover: true });
    assert.equal(await rows.nth(0).locator('.rf-cover-cb').isChecked(), true);

    // 9. An indexed related file (RES-7) is Yes and locked, even over a saved No.
    await page.evaluate(() => {
        const w = document.getElementById('related-files-wrapper');
        const c = w.querySelector('.related-file-row').cloneNode(true);
        c.querySelector('.related-file-input').value = 'RES-7';
        w.appendChild(c);
    });
    await page.waitForTimeout(300);
    const locked = page.locator('.related-file-row').nth(3).locator('.related-sighting');
    assert.equal(await locked.evaluate(b => b.dataset.sighted), '1');
    assert.equal(await locked.locator('.rf-choice[data-value="0"]').isDisabled(), true);
    assert.equal(await locked.locator('.rf-indexed-note').isVisible(), true);
    assert.equal(JSON.parse(await page.evaluate(() => window.RelatedFileSighting.collect())).find(a => a.file_number === 'RES-7').sighted, true);
    // ...and an unindexed one in the same form stays open to answer.
    assert.equal(await page.locator('.related-file-row').nth(2).locator('.rf-choice[data-value="0"]').isDisabled(), false);

    assert.deepEqual(errors, []);
    await browser.close();
    console.log('related-file-sighting: all checks passed');
})().catch(e => { console.error(e); process.exit(1); });
