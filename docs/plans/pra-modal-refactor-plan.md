# PRA Modal Refactoring Plan

## Objectives
- Review and document the Add PRA modal implementation
- Replace Alpine.js behaviours with vanilla JavaScript modules under [public/js/pra/](public/js/pra/)
- Remove unused or dead code paths to reduce modal complexity
- Fix any broken bindings, validation, or PRA data flows uncovered during the refactor
- Split the modal form into cohesive Blade partials
- Retire the legacy file number selector so only the smart selector remains

## Current Implementation Snapshot
- Primary modal markup and bindings live in [resources/views/propertycard/partials/add_property_record.blade.php](resources/views/propertycard/partials/add_property_record.blade.php) with inline Alpine.js state
- PRA auto-fill logic, status banners, and helper functions are embedded in [resources/views/propertycard/js/javascript.blade.php](resources/views/propertycard/js/javascript.blade.php) alongside other property form scripts
- File number entry currently provides both the smart selector in [resources/views/propertycard/partials/smart_fileno_selector.blade.php](resources/views/propertycard/partials/smart_fileno_selector.blade.php) and the legacy manual selector in [resources/views/propertycard/partials/manual_fileno.blade.php](resources/views/propertycard/partials/manual_fileno.blade.php)
- PRA API responses are hydrated via [app/Http/Resources/Pra/PraRecordResource.php](app/Http/Resources/Pra/PraRecordResource.php); any field mapping issues should be addressed here or in the new JS module

## Refactor Workstreams

### 1. Audit & Planning
- Trace Alpine.js directives, x-data scopes, and watchers across the modal template to determine the exact stateful behaviours that must be ported
- Catalogue unused DOM elements, conditional blocks, and legacy helpers for removal during implementation
- Capture required events (open, close, PRA apply, validation) to drive the vanilla JS architecture

### 2. JavaScript Extraction
- Create a scoped entry module (for example [public/js/pra/modal.js](public/js/pra/modal.js)) that wires modal lifecycle events, PRA fetch/apply flows, and UI status helpers
- Move reusable helpers (DOM selectors, field mapping, banner updates) into supporting modules such as [public/js/pra/utils.js](public/js/pra/utils.js) to keep concerns small
- Remove inline `<script>` blocks from the Blade templates once equivalent vanilla JS modules are registered through Mix/Vite bundling

### 3. Alpine.js Decommissioning
- Replace x-data powered state with explicit initialisation functions invoked when the modal is inserted into the DOM
- Convert Alpine event bindings (for example x-on, x-model, x-show) to `addEventListener` handlers or class toggles managed by the new JS modules
- Ensure DOM queries are scoped to the modal container to avoid collisions with similarly named fields elsewhere on the page

### 4. Blade Partial Reorganisation
- Break the existing modal into focused partials under [resources/views/propertycard/partials/add_pra/](resources/views/propertycard/partials/add_pra/) such as:
  - header and metadata summary
  - applicant details
  - property description and address
  - document checklist / attachments
  - action footer with submit and cancel buttons
- Update [resources/views/propertycard/partials/add_property_record.blade.php](resources/views/propertycard/partials/add_property_record.blade.php) to include the new partials and drop redundant markup

### 5. File Number Selector Simplification
- Remove references to the manual selector partial in both Blade templates and JS initialisers
- Delete or archive [resources/views/propertycard/partials/manual_fileno.blade.php](resources/views/propertycard/partials/manual_fileno.blade.php) once no longer referenced
- Ensure the smart selector remains the single source of truth, updating any code that previously synchronised between selector modes

### 6. Validation & Bug Fixes
- While migrating, fix outstanding binding issues (for example PRA address fields not populating) by updating the mapping in the new JS module and, if needed, [app/Http/Resources/Pra/PraRecordResource.php](app/Http/Resources/Pra/PraRecordResource.php)
- Verify client-side validation flows still trigger when required fields are missing; implement vanilla JS feedback where Alpine previously handled it
- Retest PRA auto-fill, manual submit, and cancel paths to confirm no regressions

## Implementation Sequence
1. Complete the audit checklist and document required behaviours in this plan (update as discoveries occur)
2. Build the vanilla JS module scaffold in [public/js/pra/](public/js/pra/) and register it with the asset pipeline
3. Incrementally migrate Alpine-driven sections to the new JS, testing after each migration
4. Introduce the Blade partials and reassemble the modal using the new includes
5. Remove the manual selector, adjust dependencies, and retest PRA and smart selector interactions
6. Perform regression testing with PRA records covering different land uses and address completeness, then schedule code review

## Risks & Mitigations
- **DOM coupling:** Scope selectors to the modal wrapper and add defensive null checks to avoid runtime errors
- **Build tooling:** Confirm Mix/Vite bundle entries include the new PRA modules before removing inline scripts
- **User workflow changes:** Communicate the removal of the manual selector and ensure the smart selector provides equivalent or better functionality
- **Data discrepancies:** Continue logging PRA payloads during the transition so backend normalization gaps can be closed quickly

## Acceptance Criteria
- Modal renders with no Alpine.js attributes and initialises via vanilla JS modules only
- PRA data fetch/apply works consistently, including status banners and property description autofill
- Blade structure is modular, with each form section encapsulated in its own partial for easier maintenance
- Legacy file number selector is removed without breaking form submission or validation paths
- All console errors resolved; manual QA sign-off obtained for create/edit property flows using the new modal
