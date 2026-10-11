const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');

// The mother-file batch categories live in three places that must agree: the
// controller's MOTHER_CATEGORIES (what the server lists and saves), the form's
// MOTHER_TYPES (what batch mode accepts), and the Application Type radio grid.
const controller = fs.readFileSync('app/Http/Controllers/LandRecommendationController.php', 'utf8');
const form = fs.readFileSync('resources/views/land_recommendations/form.blade.php', 'utf8');

function phpCategories() {
    const start = controller.indexOf('private const MOTHER_CATEGORIES = [');
    assert(start >= 0, 'MOTHER_CATEGORIES not found');
    const body = controller.slice(start, controller.indexOf('];', start));
    return [...body.matchAll(/^\s*'([^']+)'\s*=>\s*\[/gm)].map(m => m[1]);
}

function jsTypes() {
    const m = form.match(/var MOTHER_TYPES\s*=\s*(\[[\s\S]*?\]);/);
    assert(m, 'MOTHER_TYPES not found');
    return Function(`return ${m[1]};`)();
}

function radioTypes() {
    const m = form.match(/@foreach\(\[\s*([\s\S]*?)\]\s*as \$appType\)/);
    assert(m, 'Application Type radio list not found');
    return [...m[1].matchAll(/'([^']+)'/g)].map(x => x[1]);
}

test('server and form list the same mother categories', () => {
    assert.deepEqual([...jsTypes()].sort(), [...phpCategories()].sort());
});

test('every mother category is an Application Type the form offers', () => {
    const radios = radioTypes();
    for (const type of phpCategories()) {
        assert(radios.includes(type), `${type} missing from the Application Type radios`);
    }
});

test('Reinstatement and Revocation are offered', () => {
    const radios = radioTypes();
    assert(radios.includes('Reinstatement'));
    assert(radios.includes('Revocation'));
});
