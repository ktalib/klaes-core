# Sectional Titling Updates — 2026-01-14

## CofO Details
- resources/views/sectionaltitling/action_modals/cofo_details_modal.blade.php – refreshed modal wiring and unit selection flow (latest CofO detail tweaks).
- resources/views/recertification/js/js_fixed.blade.php – ensure recertification shortcuts open the available CofO modal helper.
- resources/views/recertification/js/js.blade.php – mirror the fallback logic for the legacy JS bundle.

## Capture Extant CofO Modal
- resources/views/sectionaltitling/action_menu/info_pro_action.blade.php – point "Capture Extant CofO Details" to openCaptureExtantCofoModal.
- resources/views/sectionaltitling/action_modals/capture_extant_cofo_details.blade.php – exposes open/close helpers under the new modal id.
- resources/views/sectionaltitling/primary.blade.php – include capture_extant_cofo_details partial so the helper loads with the primary grid.
- app/Http/Controllers/SectionalTitlingController.php – make the CofO lookup resilient by matching on np_fileno/fileno and selecting only columns that exist on dbo.CofO.

## RoFO Details
- app/Http/Controllers/RofoController.php – adjust memo detection to recognise SUA records, align responses, and preserve SUA land use data when loading the form.
- resources/views/sectionaltitling/action_modals/rofo_details_modal.blade.php – modal refinements tied to the updated controller payload.
- resources/views/sectionaltitling/action_menu/info_pro_action.blade.php – menu ordering and RoFO button wiring updated alongside CofO changes.
- resources/views/programmes/partials/rofo_form.blade.php – populate SUA property fields and unlock empty plot/plan/fee inputs for editing.
- resources/views/programmes/partials/rofo_form.blade.php – show clear SUA/PUA badge, default memo-derived term years, and unlocked field changes above.
- app/Http/Controllers/RofoController.php – allow SUA submissions without a parent application id and skip unnecessary unit lookups when the id is missing.
- resources/views/programmes/partials/rofo_form.blade.php – add page-mode submission spinner to signal processing after validation.
- resources/views/programmes/certificates.blade.php – restyle certificate eligibility tabs with compact PuA pills while keeping SUA handling separated from the RoFO grid.

## Certificates Dashboard
- resources/views/programmes/certificates.blade.php – return the SUA dashboard to the single-table flow, surface memo/RoFO readiness, and keep CofO actions per unit without batch prompts.
- app/Http/Controllers/CofoController.php – load SUA memo and RoFO metadata, loosen status filters, and expose readiness flags for the refreshed dashboard.
