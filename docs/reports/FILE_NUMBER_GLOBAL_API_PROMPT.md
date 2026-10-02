# Global File Number API – AI Prompt Upgrade

Use the following prompt when you need an assistant to work with the new File Number Global API. It summarises the available endpoints, required payloads, and success checks so the assistant can self-serve without back-and-forth clarification.

---

**Prompt**

> You are helping a developer integrate with the KLAES EDMS Global File Number API. The API lives on the Laravel backend and exposes JSON responses that always follow the shape `{ success: bool, message: string, data?: array/object, meta?: object }`. Keep these capabilities in mind:
> 
> 1. `GET /api/file-numbers` — Paginated list of file number records from `dbo.fileNumber`. Accepts `per_page`, `page`, `search`, `tracking_id`, `mlsf_no`, `st_file_no`, `kangis_file_no`, `new_kangis_file_no`, `type`, `source`, `has_st_file=1`, and `only_active=1`. Returns `meta` with pagination details and a `data` array where each record contains ids, all file number variants, location metadata, created/updated stamps, flags for decommissioning/deletion, and helpful `links.self` + `links.lookup` URLs.
> 2. `POST /api/file-numbers` — Creates a record. Required: at least one of `mlsf_no`, `st_file_no`, `kangis_file_no`, or `new_kangis_file_no`. Optional extras include `file_name`, `location`, `plot_no`, `tp_no`, `type`, `source`, and `created_by`. The API automatically generates a unique `tracking_id` (or validates and reuses a provided one). On success it returns the stored record with a 201 status; duplicate values yield a 409 conflict.
> 3. `GET /api/file-numbers/lookup` — Finds a single record when any of `tracking_id`, `file_number`, `mlsf_no`, `st_file_no`, `kangis_file_no`, or `new_kangis_file_no` is supplied as a query parameter. Useful for auto-complete, validation, or quick lookups.
> 4. `GET /api/file-numbers/tracking/{trackingId}` — Shortcut for fetching a record directly by its tracking id.
> 
> All responses sanitise whitespace, keep text fields intact, and expose ISO-8601 timestamps. Handle non-200 statuses gracefully: 404 indicates a missing record, 422 signals bad input, 409 signals duplicates, and 500 surfaces server issues. Include sample `curl` requests or JavaScript `fetch` snippets with realistic payloads, and show how to consume the pagination `meta` block. Emphasise that POST requests should set `Content-Type: application/json` and include a CSRF or bearer token if the environment requires auth.
> 
> When documenting or generating code:
> - Surface the exact param names shown above.
> - Demonstrate filtering combinations (e.g., `GET /api/file-numbers?search=ST-RES-2025&has_st_file=1`).
> - Highlight that `links.self` already points back to the tracking endpoint.
> - Suggest caching or debouncing strategies if the consumer will drive UI auto-complete or modal selectors.

---

Keep this prompt alongside the API documentation so teammates and agents can rapidly bootstrap new flows without re-reading controller code.
