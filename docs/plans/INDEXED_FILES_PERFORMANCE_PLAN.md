# Indexed Files Performance Plan

## Background
- Indexed tab sits inside the main dashboard and loads ~10k records with eager relationships, causing 60+ second SQL Server queries and UI lockups.
- Dataset is projected to exceed 40k rows; current API hand-rolls pagination and returns heavy payloads.
- Aggregating counts and streaming full result sets in a single response amplifies the bottleneck and raises the `Maximum execution time of 60 seconds exceeded` risk.

## Objectives
- Deliver sub-second initial render for the dashboard by decoupling the indexed files view.
- Provide responsive server-side pagination, filtering, and statistics that scale past 40k records.
- Protect SQL Server with tuned queries, indexes, caching, and lean payloads.

## Architecture Overview
1. **Standalone Experience**
   - Route: `/indexed-files` (web) and `/api/indexed-files` (JSON).
   - Controller: `IndexedFileController` (new) dedicated to list/search endpoints.
   - Blade view: lightweight shell that hydrates via JS DataTable/AG Grid.
2. **Server-Side Data Layer**
   - Queries capped with `limit/offset`, safe sort columns, lean select list matching the indexed-files grid.
   - Optional join data fetched on demand (modals load relationships via separate endpoints).
3. **Fast Statistics**
   - Small dedicated endpoint (or inline API payload) returning cached totals for the cards before the grid renders.

## Backend Action Items
- [ ] Scaffold controller + routes (web + API namespace) for the new page.
- [ ] Refactor list endpoint to return paginated JSON (`data`, `meta`, `links`).
- [ ] Remove heavy `with()` relationships; calculate counts via subqueries or lazy modal fetch.
- [ ] Cache dashboard stats (`pending_files`, `indexed_today`, `total_indexed`) with Redis 60s TTL.
- [ ] Add slim statistics endpoint used by the new page to hydrate cards without blocking the grid.
- [ ] Implement request validation (page, per_page, filters) to avoid expensive wildcard queries.
- [ ] Add feature flag or env guard to toggle new experience if rollback is needed.
- [ ] Map API response fields to the required grid columns (tracking ID, shelf/rack, registry, registry batch, sys batch, MDC batch, group, file number, file name, plot number, indexed date/by, TP/LPKN, land use, district, LGA, status, actions metadata).

## Frontend Action Items
- [ ] Replace dashboard tab content with CTA linking to `/indexed-files`.
- [ ] Build standalone Blade view: statistics cards, filters bar, results grid container.
- [ ] Initialize chosen grid library with server-side mode.
- [ ] Implement debounced search + filter chips, persisting last selections in local storage.
- [ ] Provide detail modal hitting lightweight `/api/indexed-files/{id}` endpoint when user inspects a row.
- [ ] Render grid with the specified columns, ensuring column-level sorting and visibility toggles where necessary.

## Data Grid Options
1. **Yajra Laravel DataTables (Recommended)**
   - Mature, SQL Server friendly, easy Blade integration, server-side pagination/search out of the box.
   - Supports column visibility, responsive layout, and built-in loading states.
2. **AG Grid Community (Fallback for richer UX)**
   - Excellent virtual scrolling and column pinning; requires custom JSON endpoints.
   - Pure frontend library (MIT); pair with `IndexedFileController` JSON for data.
3. **Livewire + Laravel Scout (Optional)**
   - Consider if we need reactive UX plus full-text search (Algolia/Elastic backing).

## Database & Indexing Work
- [ ] Add composite indexes tuned to grid filters/sorts: `(created_at DESC)`, `(file_number)`, `(tracking_id)`, `(registry_batch_no)`, `(shelf_location)`, `(land_use_type)`.
- [ ] Review existing foreign keys and statistics; schedule nightly index rebuild/reorganize job.
- [ ] Benchmark new query with `SET STATISTICS IO/TIME` to confirm scan reductions.

## Observability & Safeguards
- [ ] Log slow query threshold at 2s for indexed file endpoints (Laravel Telescope or custom logging).
- [ ] Add circuit breaker: if request exceeds 10k records or 5s runtime, abort with user-friendly message.
- [ ] Dashboard health widget showing API latency + last successful stats refresh.

## Deliverables & Timeline
1. **Week 1**: Controller skeleton, paginated API, Yajra integration, minimal Blade page.
2. **Week 2**: Filters + caching, statistics endpoint/cards, SQL indexing.
3. **Week 3**: QA with seeded 50k dataset, accessibility review, documentation update, rollout toggle.

## Risks & Mitigations
- **SQL Server contention**: mitigate via indexes, caching, and lean response shapes.
- **User training**: communicate new navigation (CTA + banner on dashboard).
- **Feature creep**: defer advanced actions (exports, bulk edits) until baseline performance is stable.

## Docs & Follow-Up
- Update `API_REFERENCE_DOCUMENTATION.md` and create `INDEXED_FILES_IMPLEMENTATION.md` post-launch with setup, rollback, and benchmarking steps.
- Monitor logs post-release; adjust caching TTL and query batch sizes as data volume grows.
