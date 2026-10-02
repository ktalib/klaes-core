/**
 * The Create/Edit User permission grid, run against the shipped blade source.
 *
 * Two things are pinned here, both of which have already broken once:
 *
 *  1. The MODAL LOADER ORDER. tailwind-modal.js injects the modal with innerHTML, which never
 *     executes <script> tags. The grid declares its Alpine component inline —
 *     x-data="permissionGrid(...)" with `function permissionGrid` further down the same
 *     fragment — so if Alpine initialises before those scripts run, it evaluates x-data,
 *     finds no such function, throws, and the component silently never mounts. That shipped:
 *     the grid drew its headers and filters while every row and the counter stayed blank.
 *
 *  2. The grid's own behaviour — filtering, the view/action dependency, and the bulk buttons.
 *
 * No browser and no Alpine: the factory is a plain object, so it can be driven directly.
 */

const fs = require('fs');

let pass = 0;
let total = 0;

function check(label, actual, expected) {
  total++;
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a === e) {
    pass++;
    console.log(`  ok   ${label}`);
  } else {
    console.log(`  FAIL ${label}\n         got      ${a}\n         expected ${e}`);
  }
}

// ---- 1. The loader must run injected scripts before initialising Alpine ------------------

const loader = fs.readFileSync('public/js/tailwind-modal.js', 'utf8');

const scriptsAt = loader.indexOf('executeScripts(modalContent)');
const alpineAt = loader.indexOf('Alpine.initTree');

check('loader calls executeScripts', scriptsAt !== -1, true);
check('loader calls Alpine.initTree', alpineAt !== -1, true);
check('scripts run BEFORE Alpine initialises', scriptsAt < alpineAt, true);

// Nested components must not be initialised twice: initTree walks the whole subtree.
check(
  'nested x-data is skipped so it is not mounted twice',
  loader.includes("el.parentElement.closest('[x-data]')"),
  true
);

// ---- 2. The grid factory, lifted out of the shipped partial -----------------------------

const blade = fs.readFileSync('resources/views/user/partials/permission-grid.blade.php', 'utf8');

const start = blade.indexOf('function permissionGrid(');
if (start === -1) {
  console.log('  FAIL permissionGrid() not found in the partial');
  process.exit(1);
}

// Walk the braces so the whole function comes out regardless of what follows it.
let depth = 0;
let i = blade.indexOf('{', start);
let begun = false;
for (; i < blade.length; i++) {
  if (blade[i] === '{') { depth++; begun = true; }
  else if (blade[i] === '}') { depth--; if (begun && depth === 0) break; }
}

// eslint-disable-next-line no-eval
const permissionGrid = eval(`(${blade.slice(start, i + 1)})`);

function row(key, name, deptId, dept, actions = {}, approvable = false) {
  return {
    key,
    name,
    deptId,
    dept,
    userType: 'Operations',
    level: 'High',
    registered: true,
    held: false,
    approvable,
    // Mirrors what the blade emits: columns that do not apply to this module.
    lockedActions: approvable ? [] : ['approve'],
    actions: Object.assign(
      { view: false, create: false, edit: false, approve: false, delete: false, print: false, export: false },
      actions
    ),
  };
}

function grid(canGrantDelete = true) {
  return permissionGrid(
    [
      row('survey - records', 'Survey - Records', '4', 'Survey'),
      row('survey - approvals', 'Survey - Approvals', '4', 'Survey', {}, true),
      row('billing', 'Billing', '7', 'Account/Finance'),
    ],
    canGrantDelete
  );
}

// -- filtering ---------------------------------------------------------------------------

let g = grid();
check('all rows visible by default', g.visibleRows.length, 3);

g = grid();
g.deptFilter = '4';
check('department filter narrows to that department', g.visibleRows.map((r) => r.name), [
  'Survey - Records',
  'Survey - Approvals',
]);

g = grid();
g.query = 'bill';
check('search matches on name, case-insensitively', g.visibleRows.map((r) => r.name), ['Billing']);

g = grid();
g.deptFilter = '4';
g.query = 'approv';
check('department and search combine', g.visibleRows.map((r) => r.name), ['Survey - Approvals']);

g = grid();
g.rows[0].actions.view = true;
g.showFilter = 'granted';
check('granted filter shows only granted', g.visibleRows.map((r) => r.name), ['Survey - Records']);
g.showFilter = 'ungranted';
check('ungranted filter is the complement', g.visibleRows.length, 2);

check('department options are derived and sorted', grid().departmentOptions.map((d) => d.label), [
  'Account/Finance',
  'Survey',
]);

// -- the view dependency -------------------------------------------------------------------
//
// The gate refuses any action on a module the user cannot see, so storing one would be a
// grant that silently never applies. Unticking view has to take the rest with it.

g = grid();
g.rows[0].actions = { view: true, create: true, edit: true, delete: true, print: true, export: true };
g.rows[0].actions.view = false;
g.onToggle(g.rows[0], 'view');
check('unticking view clears every action on the row', g.rows[0].actions, {
  view: false, create: false, edit: false, delete: false, print: false, export: false,
});

g = grid();
g.rows[0].actions.create = true;
g.onToggle(g.rows[0], 'create');
check('ticking an action implies view', g.rows[0].actions.view, true);

// -- bulk actions ---------------------------------------------------------------------------

g = grid();
g.deptFilter = '4';
g.grantVisible(['view', 'create', 'edit', 'print', 'export']);
check('bulk grant applies to visible rows only', [
  g.rows[0].actions.create, g.rows[1].actions.create, g.rows[2].actions.create,
], [true, true, false]);
check('bulk "all but delete" leaves delete alone', g.rows[0].actions.delete, false);

g = grid();
g.grantVisible(['view']);
g.clearVisible();
check('clear empties the visible rows', g.rows.map((r) => r.actions.view), [false, false, false]);

g = grid();
g.toggleColumn('view');
check('column toggle turns a column on', g.rows.map((r) => r.actions.view), [true, true, true]);
g.toggleColumn('view');
check('column toggle turns it back off', g.rows.map((r) => r.actions.view), [false, false, false]);

// A column other than view only applies to rows that are actually granted.
g = grid();
g.rows[0].actions.view = true;
g.toggleColumn('print');
check('non-view column skips rows with no view', g.rows.map((r) => r.actions.print), [true, false, false]);

// -- delete is Super-Admin-only ---------------------------------------------------------------
//
// Without this, any account able to edit users could grant itself deletion of every record
// in the ministry. The disabled checkbox in the markup is a courtesy; this is the rule.

g = grid(false);
g.grantVisible(['view', 'delete']);
check('bulk grant cannot hand out delete when not permitted', g.rows.map((r) => r.actions.delete), [false, false, false]);

g = grid(false);
g.rows.forEach((r) => { r.actions.view = true; });
g.toggleColumn('delete');
check('column toggle cannot hand out delete either', g.rows.map((r) => r.actions.delete), [false, false, false]);

// -- approve: only where a module actually has an approval step -------------------------------
//
// Approving is an act of authority, and most modules have nothing to approve. A checkbox on
// those would read as "not granted" when the truth is "there is nothing here to approve", and
// the server drops any approve posted for such a module — so the grid must not offer it.

g = grid();
check('approve is locked on a module with no approval step', g.locked(g.rows[0], 'approve'), true);
check('approve is open on an approvals module', g.locked(g.rows[1], 'approve'), false);
check('other columns are never locked', g.locked(g.rows[0], 'edit'), false);

g = grid();
g.grantVisible(['view', 'create', 'edit', 'approve', 'print', 'export']);
check('bulk grant skips approve where it does not apply', g.rows.map((r) => r.actions.approve), [false, true, false]);
check('bulk grant still applies the other columns', g.rows.map((r) => r.actions.edit), [true, true, true]);

g = grid();
g.rows.forEach((r) => { r.actions.view = true; });
g.toggleColumn('approve');
check('column toggle skips locked rows', g.rows.map((r) => r.actions.approve), [false, true, false]);

// Delete is still withheld from a non-Super-Admin, independently of approve.
g = grid(false);
g.rows.forEach((r) => { r.actions.view = true; });
g.grantVisible(['approve', 'delete']);
check('approve is grantable while delete is not', [g.rows[1].actions.approve, g.rows[1].actions.delete], [true, false]);

// -- the counter ---------------------------------------------------------------------------

g = grid();
check('counter starts at zero', g.grantedCount, 0);
g.grantVisible(['view']);
check('counter reflects granted modules, not filters', g.grantedCount, 3);
g.deptFilter = '7';
check('counter ignores the department filter', g.grantedCount, 3);

console.log(`\n${pass}/${total} passed`);
process.exit(pass === total ? 0 : 1);
