// node tests/js/deeds-pipeline-row-registration.test.js
// The Workflow R on a row about one dealing follows that row's own registration,
// not any registration on the file (user, 2026-11-05).
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const code = fs.readFileSync(path.join(__dirname, '../../public/js/deeds-pipeline.js'), 'utf8');

function fakeElement() {
    return {
        style: {}, title: '', textContent: '', innerHTML: '', children: [], dataset: {},
        appendChild(child) { this.children.push(child); },
    };
}

/** Loads the script against a stub DOM and paints the markers; resolves to their R dots. */
async function paintMarkers(markers, data) {
    const nodes = markers.map((dataset) => Object.assign(fakeElement(), { dataset }));
    const window = {};
    const document = {
        readyState: 'loading',
        addEventListener() {},
        querySelectorAll: () => nodes,
        createElement: fakeElement,
    };
    const fetch = async () => ({ json: async () => ({ success: true, data }) });
    vm.runInNewContext(code, { window, document, fetch, console, Object, Array, setTimeout });
    window.DeedsPipeline.refresh();
    await new Promise((resolve) => setTimeout(resolve, 10));

    return nodes.map((n) => n.children[0].children[3]);
}

const stages = (registrationDone) => ({
    valuation: { done: true },
    consent: { done: true },
    print: { done: true },
    registration: registrationDone ? { done: true, message: 'Registered' } : { done: false, message: 'Nothing' },
});
const GREEN = '#10b981';

(async () => {
    // RES-2026-2516: the CofO is registered on the file, this Assignment is not.
    let [dot] = await paintMarkers(
        [{ pipelineFile: 'RES-2026-2516', pipelineRegistration: '' }],
        { 'RES-2026-2516': stages(true) }
    );
    assert.ok(!dot.style.cssText.includes(GREEN), 'a registration of another dealing must not turn R green');
    assert.match(dot.title, /not been registered/);

    // The row's own registration turns R green even when the file lookup found none.
    [dot] = await paintMarkers(
        [{ pipelineFile: 'MLKN 2193', pipelineRegistration: '105/105/75' }],
        { 'MLKN 2193': stages(false) }
    );
    assert.ok(dot.style.cssText.includes(GREEN));
    assert.match(dot.title, /105\/105\/75/);

    // Two rows on one file keep their own answers.
    const [first, second] = await paintMarkers(
        [
            { pipelineFile: 'X', pipelineRegistration: '1/1/1' },
            { pipelineFile: 'X', pipelineRegistration: '' },
        ],
        { X: stages(false) }
    );
    assert.ok(first.style.cssText.includes(GREEN));
    assert.ok(!second.style.cssText.includes(GREEN));

    // Screens without the attribute (Valuation) stay per file.
    [dot] = await paintMarkers([{ pipelineFile: 'V' }], { V: stages(true) });
    assert.ok(dot.style.cssText.includes(GREEN));

    console.log('deeds-pipeline row registration: ok');
})().catch((e) => {
    console.error(e);
    process.exit(1);
});
