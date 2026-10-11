const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

// BriefRec.analyseInk is the ink check run on a signed Brief Recommendation (mother)
// at upload. These pages are drawn in code: printed rows, rules and dotted lines in
// black on off-white paper, plus a pen stroke where a case needs one.
const sandbox = { window: {}, document: {} };
vm.runInNewContext(fs.readFileSync('public/js/brief-recommendation.js', 'utf8'), sandbox);
const { analyseInk } = sandbox.window.BriefRec;

const W = 1000, H = 1400;

function page() {
    const d = new Uint8ClampedArray(W * H * 4);
    for (let i = 0; i < d.length; i += 4) { d[i] = 236; d[i + 1] = 236; d[i + 2] = 226; d[i + 3] = 255; }
    return d;
}
function dot(d, x, y, [r, g, b], size = 1) {
    for (let yy = y; yy < y + size; yy++) for (let xx = x; xx < x + size; xx++) {
        if (xx < 0 || yy < 0 || xx >= W || yy >= H) continue;
        const i = (yy * W + xx) * 4; d[i] = r; d[i + 1] = g; d[i + 2] = b;
    }
}
const BLACK = [25, 25, 30];
// Printed text: rows of small dense glyph boxes, 16px tall, as print is at this scale.
function printRows(d, rows = 40) {
    for (let row = 0; row < rows; row++) {
        const y = 80 + row * 32;
        for (let x = 160; x < 900; x += 13) {
            if ((x + row) % 7 === 0) continue;                       // word gaps
            for (let gy = 0; gy < 14; gy++) for (let gx = 0; gx < 8; gx++) {
                if ((gx + gy) % 3) dot(d, x + gx, y + gy, BLACK);
            }
        }
        if (row % 6 === 5) for (let x = 160; x < 900; x++) dot(d, x, y + 18, BLACK, 2);   // underline
        if (row % 5 === 2) for (let x = 300; x < 700; x += 6) dot(d, x, y + 16, BLACK, 2); // dotted "Sign:...."
    }
}
// A continuous pen stroke: consecutive points joined, as ink on paper is.
function scribble(d, colour, x0, y0, height, len = 400) {
    const at = t => [x0 + t * 0.6, y0 + height / 2 * Math.sin(t / 9) + height / 4 * Math.sin(t / 3.1)];
    for (let t = 0; t < len; t++) {
        const [ax, ay] = at(t), [bx, by] = at(t + 1);
        const steps = Math.ceil(Math.max(Math.abs(bx - ax), Math.abs(by - ay))) + 1;
        for (let s = 0; s <= steps; s++) {
            dot(d, Math.round(ax + (bx - ax) * s / steps), Math.round(ay + (by - ay) * s / steps), colour, 3);
        }
    }
}

test('an unsigned printed page reads as unsigned', () => {
    const d = page(); printRows(d);
    assert.equal(analyseInk(d, W, H).detected, false);
});

test('a blue-pen signature is detected', () => {
    const d = page(); printRows(d); scribble(d, [40, 60, 170], 600, 1000, 50);
    const r = analyseInk(d, W, H);
    assert.equal(r.detected, true);
    assert.ok(r.colourMarks >= 1);
});

// As on the memo, the signature sits in the open space by a "Sign:" line, not across print.
test('a black-pen signature is detected (tall, loose stroke)', () => {
    const d = page(); printRows(d, 34); scribble(d, BLACK, 600, 1260, 90);
    const r = analyseInk(d, W, H);
    assert.equal(r.detected, true);
    assert.ok(r.darkMarks >= 1);
});

test('the green letterhead and the left band are not read as ink', () => {
    const d = page(); printRows(d);
    scribble(d, [40, 130, 70], 600, 1000, 50);          // green: letterhead colour, not pen
    scribble(d, [170, 30, 60], 10, 900, 200, 200);       // red, but inside the left band (x < 15%)
    assert.equal(analyseInk(d, W, H).detected, false);
});

test('ink above the signature area is ignored', () => {
    const d = page(); printRows(d); scribble(d, [40, 60, 170], 600, 200, 50);
    assert.equal(analyseInk(d, W, H).detected, false);
});
