# Survey Module — implementation conventions

Read this before writing any code. The Projects vertical slice is already built and
**is the reference implementation** — match its shape.

Reference files:
- `app/Http/Controllers/Survey/ProjectController.php`
- `resources/views/survey_module/partials/sections/_page-projects.blade.php` (list)
- `resources/views/survey_module/partials/sections/_page-project-register.blade.php` (form)

---

## 1. Production safety — read first

`C:\xampp\htdocs\klas` **is live production**, and there is **no backup infrastructure**
— no binlog, no shadow copies, no backup job. Data loss here is unrecoverable.

- **Do not create or run migrations.** The schema is already in place (13 `survey_*`
  tables). If you believe a column is missing, say so in your final report — do not add it.
- **Never run `php artisan migrate`.** 27 unrelated migrations are pending and a bare
  migrate would run all of them against production.
- **`--pretend` is not a dry run here.** It executes for real on the `sqlsrv` connection.
- **Never write test rows outside a transaction.** Wrap every test in
  `DB::connection('sqlsrv')->beginTransaction()` / `rollBack()` and verify row counts
  return to their starting value.
- Do not touch tables that are not `survey_*`. In particular leave `valuation_compensations`,
  `vfc_projects` and `survey_report_requests` alone — they hold real records.

## 2. Architecture

- All survey tables live on the **`sqlsrv`** connection, not the default mysql one.
- Models are in `app/Models/Survey/`, all extending `SurveyModel`, which supplies:
  - `protected $connection = 'sqlsrv'`, `$guarded = ['id']`, soft deletes
  - automatic `created_by` / `updated_by` stamping
  - `static::nextRef($column, $prefix)` → `"C-2026-001"` style references
  - the `HasAddressBuilder` trait (see below)
- The module is **standalone**: it does not reference `vfc_projects` or
  `valuation_compensations`.

### The one rule that matters

**Compensation scheme type is set on the project, inherited by the case, and never mixed.**

- `survey_projects.scheme_type` is `'monetary'` or `'land'`.
- A case copies it into `survey_comp_cases.scheme_type` at creation and keeps it.
- Monetary ⇒ economic trees, cash, **no plot allocation**.
- Land-for-Land ⇒ 50:50 plot split, **no cash for trees**.
- Enforce this server-side, not just in the UI. See how `ProjectController::update()`
  strips `scheme_type` once cases exist.

50:50 split: odd plot goes to **Government** (`ceil`), farmer takes `floor`.
`SurveyCompCase::plotSplit()` already implements it — use it, don't re-derive it.

## 3. Address builder

Every location uses it. Two formats:

```
property location  ->  "District, LGA, State"            e.g. Nassarawa, Nassarawa, Kano
person address     ->  "Street, Plot|House, District, LGA, State"
```

- **Plot number wins over house number.** If both are filled only the plot appears;
  if only a house number is filled, that is used. Never both.
- Empty segments drop out — no stray commas.
- Property location **never contains the plot number**; plot lives in its own field.

Columns come in groups of eight, per the existing KLAES convention
(`LandsOneStopShopApplication` uses `res_addr_*`):

```
{prefix}house  {prefix}plot  {prefix}street  {prefix}street_other
{prefix}district  {prefix}district_other  {prefix}lga  {prefix}state
```

- `prop_` = property/parcel location · `addr_` = a person's address
- Values are stored as **strings**, not foreign keys.
- District and street are Select2 dropdowns with an **"Other (specify)"** option; choosing
  Other reveals a free-text box whose value wins when composing.

Code:
- `App\Support\AddressBuilder` — `propertyLocation()`, `personAddress()`, `rules($prefix)`,
  `columns($prefix)`, `fromPrefixed($model, $prefix)`
- Model accessors from `HasAddressBuilder`: `$model->property_location`, `$model->person_address`
- Blade: `@include('survey_module.partials._address_builder', ['prefix' => 'prop_', 'mode' => 'property', 'model' => $x, 'legend' => '...'])`
  — `mode` is `property` or `person`
- Validation: `AddressBuilder::rules('prop_')` returns the full rule set including
  `required_if` on the `_other` boxes.

Districts are **not** mapped to LGAs in this database — the dropdowns are flat and
searchable, with no cascade. Do not try to build one.

## 4. Views

Each page is a section partial in
`resources/views/survey_module/partials/sections/_page-<id>.blade.php`,
included by a thin page view that extends `survey_module.layouts.klaes`
(which wraps the main KLAES app shell: sidebar, `admin.header`, `admin.footer`).

- **Do not edit** `layouts/klaes.blade.php`, `_prototype_css_scoped.blade.php`,
  `_address_builder*.blade.php`, or `routes/survey_module.php`. They are shared.
- Page views already set `$PageTitle` / `$PageDescription`; the global header renders
  the heading, so **section partials must not add their own `<h2>` page heading**.
  A `.page-header` div holding only action buttons is correct.
- Start every section that can flash a message with
  `@include('survey_module.partials._flash')`.
- Use the prototype's CSS classes — they are scoped under `.survey-proto` and already
  loaded. Available: `kpi-grid/kpi-card/kpi-label/kpi-value`, `table-wrapper/table-scroll/
  table-toolbar/table-footer`, `form-container/form-body/form-grid/form-group/form-actions`,
  `btn btn-primary|secondary|success|danger|outline btn-sm|xs`, `status-badge active|pending|
  review|completed|rejected` (each containing `<span class="dot"></span>`), `comp-type-note`,
  `comp-type-grid/comp-type-option`, `dash-card/card-header`, `action-icons`,
  `form-stepper/step-indicator`, `form-step`, `list-header/list-item`, `helper-text`,
  `required`, `section-cards/section-card`, `calc-grid/calc-card`, `wf-chain/wf-node`.
  Do not invent new CSS unless nothing fits; if you must, add a scoped `<style>` inside
  your own section partial.
- Empty states matter: every table needs a `@forelse ... @empty` branch with a helpful
  message and a link to the create action.
- Paginate lists (`->paginate(15)->withQueryString()`), and render `{{ $x->links() }}`
  inside `.table-footer > .pagination`.

## 5. Controllers

- Validate in the controller (see `ProjectController::validated()`), merging
  `AddressBuilder::rules($prefix)` into the rule array.
- Redirect after write with `->with('success', '...')`; use `->with('error', '...')` for
  refusals (e.g. deleting a parent that still has children).
- Guard destructive actions: refuse to delete a record that owns children and say why.
- Never trust a computed field from the client — recompute it. `SurveyCaseTree`
  recalculates `line_total` on save; do the same for any other derived value.
- Dashboards and reports must read **live queries**. No hard-coded counts or amounts
  anywhere — the prototype's `1,247` / `₦45.2M` literals must all become queries.

## 6. Testing — required

Write a throwaway PHP script under the scratchpad directory and run it with `php`.
Bootstrap like this:

```php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Auth::login(App\Models\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag);   // web middleware normally supplies this
DB::connection('sqlsrv')->beginTransaction();
// ... exercise controller methods directly, assert, then:
DB::connection('sqlsrv')->rollBack();
```

Cover, for each slice you own: create, list (with filters/search), edit, update, delete,
the validation failures, and any scheme/guard rule. Assert on **query results**, not on
rendered HTML where a flash message could give a false positive.

Also render every page you touch and confirm it does not throw:

```php
view('survey_module.<path>', [...])->render();
```

Run `php artisan view:clear` after editing Blade files.

## 7. Style

Match the surrounding code: 4-space indent, typed signatures where the codebase uses them,
short comments that explain *why* rather than restating the code. Keep comment density
similar to `ProjectController`. British/American spelling — follow what is already there.
