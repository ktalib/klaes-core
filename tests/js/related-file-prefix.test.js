const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');

const source = fs.readFileSync('resources/views/generate_fileno/mls_js.blade.php', 'utf8');
function method(name, nextMarker) {
    const start = source.indexOf(`            ${name}(`);
    assert(start >= 0);
    const end = source.indexOf(nextMarker, start);
    assert(end > start);
    return Function(`return ({${source.slice(start, end)}}).${name};`)();
}
const changePrefix = method('handlePrefixChange', '            // Open GlobalFileNoModal to select related file');
const clearRelatedFile = method('clearRelatedFile', '            addRelatedFile(');

function state() {
    return {
        prefix: 'RES', allAllPrefixes: [{prefix: 'RES', land_use_id: 1}],
        landUses: [{id: 1, landuse: 'RES'}], landUseId: '', purposes: [],
        relatedFileNo: 'RES-RC-1982-1034', relatedFileTitle: 'Selected parent',
        relatedFileIndexingId: '120602', relatedFileType: 'Re-grant', relatedFileTypeOther: '',
        extraRelatedFiles: [{file_no: 'MLKN 41', title: 'Second parent', indexing_id: '133048'}],
        normalizeLandUseCode: value => value.split('-')[0],
        findLandUseByCode: code => code === 'RES' ? {id: 1, landuse: 'RES'} : null,
        fetchDependentData() {}, updatePreview() {}, clearRelatedFile,
    };
}
function related(s) {
    return JSON.stringify([s.relatedFileNo, s.relatedFileTitle, s.relatedFileIndexingId,
        s.relatedFileType, s.relatedFileTypeOther, s.extraRelatedFiles]);
}
test('changing a prefix preserves all selected parent details, including repeated watcher calls', () => {
    const s = state();
    const before = related(s);
    for (const prefix of ['RES', 'COM', 'RES-RC', 'RES', '', 'RES']) {
        s.prefix = prefix;
        changePrefix.call(s);
        changePrefix.call(s);
        assert.equal(related(s), before, `Related selections changed for ${prefix}`);
    }
    assert.equal(s.landUse, 'RES');
    assert.equal(s.customerType, 'Individual');
    assert.equal(s.isRecertificationPrefix, false);
});
test('recertification defaults the relationship type only when none was chosen', () => {
    const s = state();
    s.prefix = 'RES-RC';
    changePrefix.call(s);
    assert.equal(s.relatedFileType, 'Re-grant');
    s.relatedFileType = '';
    changePrefix.call(s);
    assert.equal(s.relatedFileType, 'Recertification');
    assert.equal(s.relatedFileNo, 'RES-RC-1982-1034');
});
test('explicit clearing still removes primary and additional selections', () => {
    const s = state();
    clearRelatedFile.call(s);
    assert.equal(s.relatedFileNo, '');
    assert.equal(s.relatedFileIndexingId, '');
    assert.deepEqual(s.extraRelatedFiles, []);
});
