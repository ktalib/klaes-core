# Property Record Assistant (PRA) Global API Plan

## 1. Context and Goals
- The PRA module centralizes legacy property metadata in the `pra` table and mirrors prop_id-aware data to `file_history_staging`, `pic`, and CofO staging tables.
- Different flows (file indexing, manual property capture, CofO updates) already reuse `PropertyRecordController` and `PropertyIdAllocationService`, but downstream modules still query PRA data in siloed ways.
- Requirement: expose a reusable API surface that any Laravel controller, Blade view, or external integration can call to fetch, search, deduplicate, and persist property records without duplicating SQL logic.

## 2. Design Principles
- **Single source of truth**: every API method resolves records through a dedicated domain service that orchestrates `pra`, `PropID_Master`, `file_history_staging`, and related tables.
- **Prop-centric**: every response carries `prop_id` plus normalized file numbers so consumers can stitch other modules (caveats, CofO, tracking).
- **Composable**: endpoints keep payloads small and filterable, pushing heavy joins into dedicated aggregators (e.g., timeline, duplicates).
- **Secure and observable**: leverage Laravel auth middleware, permission gates, and structured logging.
- **Versioned**: namespace routes under `/api/pra/v1` with an upgrade path.

## 3. Primary Use Cases
1. **Quick lookup**: retrieve a property snapshot by any file identifier.
2. **Advanced search**: filter PRA rows by parties, location, land use, date ranges, instrument type, or `prop_id`.
3. **Duplicate detection**: detect conflicting entries (e.g., identical serial/page/volume or same parties with different `prop_id`).
4. **Record authoring**: create or update PRA rows from new workflows while reusing `PropertyIdAllocationService` and ensuring timelines stay in sync.
5. **History enrichment**: expose chronological transactions grouped by `prop_id` that also surface linked file-history snapshots.

## 4. Target Consumers
- Internal Laravel controllers (e.g., Caveat, File History, Legal Search).
- Blade/Alpine components (property modals, autosuggests).
- Background jobs (backfill, audits, dedupe scripts).
- Future external consumers (reporting microservices) via token-authenticated requests.

## 5. Data Sources and Dependencies
- `pra` (legacy property records).
- `PropID_Master` (canonical prop/file number map).
- `file_history_staging` (timeline snapshots).
- `pic` (property index card) for supplemental data.
- `CofO_staging` for certificate metadata.
- `PropertyIdAllocationService` for ID allocation and synchronization.
- `PropertyRecordController` logic for data normalization.

## 6. Implemented Architecture
- **Controller**: `App\Http\Controllers\Api\Pra\PraRecordController` (v1) exposes read/write endpoints and wraps responses in JSON.
- **Service**: `App\Services\Pra\PraRecordService` coordinates search, lookup, create/update, history, and duplicate resolution while reusing `PropertyIdAllocationService`.
- **Repositories**: `PraRecordRepository` and `PraHistoryRepository` encapsulate SQL Server access, join helpers, caching of schema metadata, and duplicate detection rules.
- **Resources**: `PraRecordResource` and `PraHistoryResource` normalize legacy column casing, aggregate party payloads, and attach master identifiers when present.
- **Validation**: `PraSearchRequest`, `PraStoreRequest`, and `PraUpdateRequest` guard payload shape and enforce permission checks (`pra.read`, `pra.write`).
- **Routes**: registered in `routes/app3.php` under `Route::prefix('api/pra/v1')->middleware('auth')` with granular `permission:pra.read`/`permission:pra.write` groups.

## 7. Endpoint Catalogue (v1)
| Endpoint | Verb | Purpose |
| --- | --- | --- |
| `/records/search` | POST | Paginated multi-filter search across PRA with optional master identifier enrichment.
| `/records/{prop_id}` | GET | Fetch canonical property snapshot keyed by `prop_id`.
| `/records/by-file/{fileNumber}` | GET | Convenience lookup by any supported file number (MLS, KANGIS, ST, temp).
| `/records/{prop_id}/history` | GET | Chronological PRA transaction timeline with party rollup.
| `/records/{prop_id}/duplicates` | GET | Potential duplicate PRA rows with confidence scoring and normalized payloads.
| `/records` | POST | Create PRA record, allocate/sync PropID, and invalidate cached lookups.
| `/records/{prop_id}` | PUT/PATCH | Update PRA record while keeping PropID, timeline, and cache state in sync.

## 8. Search and Filtering Model
- Request body accepts:
  - `file_numbers`: array of identifiers.
  - `prop_ids`: array.
  - `party`: text (matches grantor/grantee/assignor/assignee/mortgagor/mortgagee, etc.).
  - `location`, `district`, `land_use`, `instrument_type`.
  - `registration_date_from/to`, `transaction_date_from/to`.
  - `pagination`: `page`, `per_page`, `sort`, `order`.
- Service builds query via query builder with optional `JOIN` to `PropID_Master` for normalized file numbers and to `file_history_staging` for latest transaction metadata.
- Index hints: ensure covering indexes on `mlsFNo`, `prop_id`, `serialNo`, `grantor/grantee` columns (coordinate with DBA before rollout).

## 9. Duplicate Detection Strategy
- Rule engine with configurable checks:
  - **Registrar triple match**: same `serialNo`, `pageNo`, `volumeNo`, and `reg_date`.
  - **Party + location match**: same party pair and location within N days.
  - **PropID collision**: multiple PRA rows referencing identical identifiers but different `prop_id`.
- Implement as dedicated repository method returning grouped records with `confidence_score` and reasons.
- Provide manual override to mark false positives (store in `pra_duplicate_overrides` table).

## 10. Record Persistence Flow
1. Validate payload via `StorePraRecordRequest` (ensures required parties based on instrument, registration specifics).
2. Normalize identifiers and compute `prop_id` using `PropertyIdAllocationService::allocateOrRetrievePropId`.
3. Upsert into `pra` using transactions; keep create vs update paths separate.
4. Mirror updates to `file_history_staging` (via existing service) and optionally `PropID_Master`.
5. Emit Laravel events (`PraRecordCreated`, `PraRecordUpdated`) for downstream listeners (e.g., indexing autop-run, cache busting).

## 11. Security and Governance
- Authentication: Passport/Sanctum token for API usage; session-based for internal calls.
- Authorization: Spatie permission checks (`pra.read`, `pra.write`, `pra.audit`).
- Rate limiting: apply `throttle:60,1` for external tokens; lighter limits for internal traffic.
- Auditing: log every write with user context and diff; surface in `activity_logs` for compliance.

## 12. Performance Considerations
- Use read-only replicas for heavy search if available; otherwise, wrap queries with pagination and limit `per_page` to 100.
- Cache hot lookups (`/records/by-file`) in Redis keyed by normalized identifier for 5 minutes.
- Introduce database indexes after analyzing slow query log (e.g., composite index on `(mlsFNo, prop_id)` in PRA).

## 13. Implementation Snapshot
1. **Domain Layer**
   - `PraRecordService` orchestrates repositories, PropID allocation, cache invalidation, and history sync (complete).
   - Repositories upgraded to handle lookup normalization, duplicate heuristics, and master identifier hydration (complete).
2. **API Layer**
   - `/api/pra/v1` endpoints wired with auth + permission middleware and request validation (complete).
   - Error handling standardised (422 validation, 404 lookups, 500 fallback) with structured logging hooks (complete).
3. **Testing & QA**
   - HTTP feature tests stub the service to verify contract/permission behaviour (`tests/Feature/PraApiTest.php`).
   - Manual smoke checklist pending SQL Server fixture refresh (in progress).

## 14. Testing Strategy
- Unit tests for repositories and dedupe rules using in-memory datasets.
- Feature tests hitting SQL Server test database via refreshable fixtures.
- Contract tests for API responses (JSON schema validation).
- Performance tests (Artillery/Gatling) on search endpoints with realistic concurrency.
- Regression suite ensuring existing controllers still function (backward compatibility).

## 15. Risks and Mitigations
- **Legacy data anomalies**: inconsistent parties or missing identifiers; mitigation: keep normalization helpers tolerant and log anomalies.
- **PropID conflicts**: ensure all write flows go through `PropertyIdAllocationService`; add alerts on duplicate allocation attempts.
- **Query performance**: monitor after enabling aggregated searches; add covering indexes or precomputed views if needed.
- **Authorization gaps**: confirm new permissions assigned via Spatie and default-deny for write operations.

## 16. Open Questions
- Do we need public (non-authenticated) access for kiosks? If so, rate limit separately.
- Should duplicate resolution trigger automatic merges or remain manual?
- Do we expose CofO attachments/documents via the same API or separate module?

## 17. Next Steps
- Review plan with data governance and registry operations teams.
- Prioritize discovery tasks and assign owners.
- Prepare sample payloads/response JSON for documentation once schema is finalized.
