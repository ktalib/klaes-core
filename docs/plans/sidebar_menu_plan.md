# Sidebar Menu Refactor Plan

## Goals
- Make the sidebar Blade markup maintainable by splitting the monolithic `resources/views/admin/menu.blade.php` into focused partials (minimum of six, targeting key feature clusters).
- Preserve existing role-based visibility while improving readability and reusability.
- Fix the active-item behavior so parent modules/submodules automatically expand for the current route without requiring a click.

## Proposed File Structure
Partial views will live under `resources/views/admin/menu/partials` to keep the namespace consistent with the current menu location:

1. `partials/sidebar_header.blade.php` – Logo and top banner.
2. `partials/sidebar_footer.blade.php` – User avatar block and dropdown.
3. `partials/modules/dashboard.blade.php`
4. `partials/modules/crm.blade.php`
5. `partials/modules/edms.blade.php`
6. `partials/modules/digital_archive.blade.php`
7. `partials/modules/programmes.blade.php`
8. `partials/modules/legacy.blade.php`
9. `partials/modules/system_admin.blade.php`
10. `partials/scripts.blade.php` – Lucide init + sidebar JS helpers (optional extraction if the script stays large).

`menu.blade.php` will keep the shared helpers (`$hasRole`, route checks) and glue the partials together via `@include` calls. Additional partials can be added later without bloating the parent.

## Active-State & Auto-Expand Strategy
1. **Server-side helpers**: add small PHP helpers (e.g., `isRouteActive($patterns, $callback = null)`) that return booleans for each module. These booleans control:
   - `hidden` vs. `block` class on each `[data-content]` container.
   - Chevron rotation state (`rotate-90`).
2. **Consistent data attributes**: each module wrapper keeps `data-module="slug"` and sections keep `data-section="slug"` so both PHP (for default classes) and JS (for click toggles) talk the same language.
3. **Progressive enhancement JS**:
   - On load, find `.sidebar-item.active` elements.
   - Walk up to their nearest `[data-content]` parents and remove the `hidden` class; add `rotate-90` to matching chevrons.
   - Trigger `scrollToActiveItem()` after DOM is ready so the selected link is visible.

This dual approach ensures the sidebar is expanded even if JS is disabled (thanks to server-side default classes) while maintaining interactive collapsing when users click around.

## Implementation Steps
1. **Extract header/footer partials** to remove repeated markup from the main file.
2. **Carve out module partials** listed above, keeping their internal role checks intact. Each partial receives the shared helpers via closure scope.
3. **Wire `menu.blade.php`** to include the partials, define the `$activeStates` array, and pass state flags (e.g., `['module' => 'crm', 'isOpen' => $activeStates['crm']]`).
4. **Refine JavaScript**: reuse existing toggle functions but add the auto-expand routine and guard clauses.
5. **Manual verification**: load a few representative routes (or simulate via `php artisan tinker`/unit tests if available) to ensure PHP renders without errors and the correct modules open.

This plan keeps behavioral changes scoped while meeting the requirement of splitting the sidebar into multiple logical files and improving the active-menu UX.
