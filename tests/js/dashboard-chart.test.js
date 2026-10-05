const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/dashboard/index.blade.php', 'utf8');
const nodes = new Map();
function node(tag = 'div') {
    return { tag, attrs: {}, children: [], textContent: '',
        setAttribute(key, value) { this.attrs[key] = String(value); },
        appendChild(child) { this.children.push(child); }, addEventListener() {},
        classList: { add() {}, remove() {} },
        set innerHTML(value) { this.children = []; },
    };
}
function get(id) { if (!nodes.has(id)) nodes.set(id, node()); return nodes.get(id); }
const context = vm.createContext({ document: { getElementById: get, createElementNS: (_, tag) => node(tag) }, console,
    hideTooltip() {}, showTooltip() {} });
vm.runInContext('let chartView="bar"; let hiddenSeries=new Set(); let maxChartValue=5; var enhancedChartData=[];', context);
function include(name, nextMarker) {
    const start = source.indexOf('        function ' + name + '(');
    vm.runInContext(source.slice(start, source.indexOf(nextMarker, start)), context);
}
include('generateEnhancedSVGChart', '        // Show enhanced tooltip');
include('updateChartTotals', '        // Toggle chart series');
include('refreshWeeklyChart', '        let analyticsRequest');
include('toggleChartView', '        // Show insights');
include('toggleChartSeries', '        // Toggle dropdown');
function descendants(element) { return element.children.flatMap(child => [child, ...descendants(child)]); }
context.refreshWeeklyChart([{ day: 'Sun', count: 401, approved: 200, sectional: 1, recertification: 0, allocation: 400 }]);
let marks = descendants(get('applications-chart')).filter(d => d.tag === 'rect');
assert.equal(marks.length, 3);
assert(marks.some(d => Number(d.attrs.height) > 0));
assert(marks.every(d => Number(d.attrs.y) >= 20));
assert.equal(get('total-applications').textContent, 401);
assert.equal(get('success-rate').textContent, '49.9%');
assert.equal(get('peak-day').textContent, 'Sun');
context.toggleChartSeries('allocation');
assert.equal(get('total-applications').textContent, 1);
context.toggleChartView();
assert(descendants(get('applications-chart')).some(d => d.tag === 'polyline'));
context.refreshWeeklyChart([{ day: 'Mon', count: 0 }]);
assert(descendants(get('applications-chart')).some(d => d.textContent === 'No applications in this period'));
assert.equal(get('success-rate').textContent, '—');
console.log('PASS: real source bars, dynamic scale, legend toggle, line view and empty state.');
