const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

// File Movement (Department) > Log a file manually: Timeline (Days), as on Log a File.
// manualTimelineDays() parses the box; manualTimelinePreview() gives the Expected
// Return Date and the On Track / Due Soon preview.
const sandbox = { window: {}, document: { querySelector: () => null }, localStorage: null };
vm.runInNewContext(fs.readFileSync('public/js/secretariat-file-log.js', 'utf8'), sandbox);
const page = sandbox.window.secretariatFileLog({ userId: 1, offices: [], officers: [], requestPurposes: [], urls: {} });

const withDays = (value) => { page.manual = { ...page.manual, timeline_days: value }; return page; };
const ddmmyyyy = (offset) => {
    const d = new Date();
    d.setDate(d.getDate() + offset);
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
};

test('blank is optional, whole numbers 0-365 pass, anything else is invalid', () => {
    assert.equal(withDays('').manualTimelineDays(), null);
    assert.equal(withDays('  ').manualTimelineDays(), null);
    assert.equal(withDays('0').manualTimelineDays(), 0);
    assert.equal(withDays('5').manualTimelineDays(), 5);
    assert.equal(withDays(365).manualTimelineDays(), 365);
    for (const bad of ['366', '-1', '2.5', 'abc', '1e2']) {
        assert.equal(withDays(bad).manualTimelineDays(), false, bad);
    }
});

test('preview: expected return date, day count and status', () => {
    assert.equal(withDays('').manualTimelinePreview(), null);
    assert.equal(withDays('x').manualTimelinePreview(), null);

    const five = withDays('5').manualTimelinePreview();
    assert.equal(five.date, ddmmyyyy(5));
    assert.equal(five.days, '5 days from today');
    assert.equal(five.label, 'On Track');

    const today = withDays('0').manualTimelinePreview();
    assert.equal(today.date, ddmmyyyy(0));
    assert.equal(today.days, 'Due today');
    assert.equal(today.label, 'Due Soon');

    assert.equal(withDays('1').manualTimelinePreview().days, '1 day from today');
});

// Tab tables: the Timeline column after Purpose / Notes uses the profile's badge.
test('timeline badge for a register row, and its due date', () => {
    assert.equal(page.timelineBadge({ timeline_status: null }), null);
    assert.equal(page.timelineBadge({}), null);
    assert.equal(page.timelineBadge(null), null);
    assert.equal(page.timelineBadge({ timeline_status: 'green', days_until_deadline: 120 }).label, '120 days left');
    assert.equal(page.timelineBadge({ timeline_status: 'amber', days_until_deadline: 1 }).label, '1 day left');
    assert.equal(page.timelineBadge({ timeline_status: 'amber', days_until_deadline: 0 }).label, 'Due today');
    assert.equal(page.timelineBadge({ timeline_status: 'red', days_until_deadline: -3 }).label, '3 days overdue');
    assert.match(page.timelineBadge({ timeline_status: 'red', days_until_deadline: -3 }).style, /#fee2e2/);
    assert.equal(page.dueDate('2027-02-26'), '26/02/2027');
    assert.equal(page.dueDate(null), '');
});
