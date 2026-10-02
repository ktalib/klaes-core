# Global File Number Fetch API Usage Guide

The Global File Number API exposes a unified set of endpoints for retrieving file-number data from the legacy `fileNumber` table and the newer `st_file_numbers` registry. These endpoints power the Global File Number modal and any integrations that need MLS, ST, KANGIS, or New KANGIS identifiers in a single place.

> **Base URL**: `/api/file-numbers`
>
> **Authentication**: The routes are registered in `routes/api.php` without additional middleware. Downstream callers should still send the `Accept: application/json` header and, if the environment enforces Sanctum or token guards, include the appropriate bearer token.

## At-a-glance

| Endpoint | Verb | Purpose |
| --- | --- | --- |
| `/api/file-numbers` | `GET` | Paginated list query across all file-number columns with rich filters. |
| `/api/file-numbers/lookup` | `GET` | Fetch a single record via query string (tracking ID or any of the file columns). |
| `/api/file-numbers/tracking/{trackingId}` | `GET` | Direct fetch by tracking ID path parameter. |
| `/api/file-numbers/mls` | `GET` | Latest MLS numbers (legacy source). |
| `/api/file-numbers/kangis` | `GET` | Latest classic KANGIS numbers. |
| `/api/file-numbers/newkangis` | `GET` | Latest New-KANGIS numbers. |
| `/api/file-numbers/st-all` | `GET` | Full detail dump from `st_file_numbers` with optional filters. |
| `/api/file-numbers/st-stats` | `GET` | Aggregate counts/summary of ST file numbers. |
| `/api/file-numbers/st-dropdown-data` | `GET` | Distinct lists for building dropdowns (land use, year, types, etc.). |

The controller (`App\Http\Controllers\FileNumberApiController`) also exposes `POST /api/file-numbers` for creating records, but this guide focuses on the fetch operations requested.

## 1. List file numbers (`GET /api/file-numbers`)

Retrieves a paginated collection across MLS, ST, KANGIS, and New KANGIS columns. Useful for powering the Global File Number modal search view.

### Query parameters

| Parameter | Type | Notes |
| --- | --- | --- |
| `per_page` | int (1-200, default 50) | Page size. |
| `page` | int | Pagination page number. |
| `order_by` | string | One of `fn.id`, `fn.created_at`, `fn.updated_at`, `fn.mlsfNo`, `fn.kangisFileNo`, `fn.NewKANGISFileNo`, `fn.st_file_no`, `fn.FileName`, `fn.tracking_id`. Defaults to `fn.id`. |
| `order_direction` | `asc` \| `desc` | Defaults to `desc`. |
| `search` | string | LIKE search across file numbers, tracking ID, and file name. |
| `tracking_id`, `mlsf_no`, `st_file_no`, `kangis_file_no`, `new_kangis_file_no` | string | Exact-match filters. |
| `type`, `source` | string | Legacy type/source filters. |
| `has_st_file` | bool | When `true`, only returns entries with a non-empty `st_file_no`. |
| `only_active` | bool | When `true`, excludes decommissioned records. |

### Sample request

```bash
curl -G https://your-domain.test/api/file-numbers \
  --data-urlencode "search=ST-COM-2025" \
  --data-urlencode "has_st_file=true" \
  --data-urlencode "per_page=25"
```

### Sample response (trimmed)

```json
{
  "success": true,
  "message": "File numbers fetched successfully.",
  "data": [
    {
      "id": 10234,
      "mlsf_no": null,
      "st_file_no": "ST-COM-2025-5-001",
      "kangis_file_no": null,
      "new_kangis_file_no": null,
      "file_name": "Acme Estates Ltd",
      "tracking_id": "TRK-AB12CD34-EFGH",
      "created_at": "2025-10-18T13:44:01+01:00",
      "links": {
        "self": "/api/file-numbers/tracking/TRK-AB12CD34-EFGH",
        "lookup": "/api/file-numbers/lookup?tracking_id=TRK-AB12CD34-EFGH"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 3,
    "has_more": false
  },
  "filters": {
    "search": "ST-COM-2025",
    "has_st_file": true
  }
}
```

## 2. Lookup helpers

### `GET /api/file-numbers/lookup`

Provide any of the identifiers as query parameters:

```bash
curl -G https://your-domain.test/api/file-numbers/lookup \
  --data-urlencode "tracking_id=TRK-AB12CD34-EFGH"
```

The endpoint accepts any combination of: `tracking_id`, `mlsf_no`, `st_file_no`, `kangis_file_no`, `new_kangis_file_no`, or a generic `file_number`. Returns `404` when nothing matches.

### `GET /api/file-numbers/tracking/{trackingId}`

Shortcut when you already have the tracking ID:

```bash
curl https://your-domain.test/api/file-numbers/tracking/TRK-AB12CD34-EFGH
```

## 3. Legacy source lookups

Each endpoint accepts an optional `limit` (default 100) and returns the most recent entries.

```bash
# Latest MLS numbers
curl https://your-domain.test/api/file-numbers/mls?limit=50

# Classic KANGIS numbers
curl https://your-domain.test/api/file-numbers/kangis

# New-KANGIS numbers
curl https://your-domain.test/api/file-numbers/newkangis
```

Responses share the shape:

```json
{
  "success": true,
  "files": [
    { "mlsFNo": "MLS/2025/001", "file_number": "MLS/2025/001", "id": 5012 }
  ]
}
```

## 4. ST file-number registry endpoints

These endpoints surface records from the newer `st_file_numbers` table.

### `GET /api/file-numbers/st-all`

Supports filters via query string:

- `search`
- `land_use`
- `year`
- `file_no_type` (`PRIMARY`, `SUA`, `PUA`)
- `status` (single value or comma-separated list)
- `applicant_type`
- `order_by` and `order_direction`
- `limit`

```bash
curl -G https://your-domain.test/api/file-numbers/st-all \
  --data-urlencode "file_no_type=PRIMARY" \
  --data-urlencode "land_use=COMMERCIAL" \
  --data-urlencode "limit=100"
```

Returns an object with `status`, `message`, `count`, and a `data` array that includes both raw columns and computed helpers (`display_name`, `full_file_number`).

### `GET /api/file-numbers/st-stats`

Returns aggregates such as total records per type and land use.

```json
{
  "status": "success",
  "data": {
    "total_records": 482,
    "primary_count": 210,
    "sua_count": 162,
    "pua_count": 110,
    "commercial_count": 135,
    "latest_year": 2025
  }
}
```

### `GET /api/file-numbers/st-dropdown-data`

Returns distinct lists for UI dropdowns:

```json
{
  "status": "success",
  "data": {
    "land_uses": ["COMMERCIAL", "RESIDENTIAL"],
    "years": [2025, 2024, 2023],
    "file_types": ["PRIMARY", "SUA", "PUA"],
    "statuses": ["ACTIVE", "RESERVED", "USED"],
    "applicant_types": ["Individual", "Corporate", "Multiple"]
  }
}
```

## 5. Error handling

- Validation failures (e.g., missing lookup criteria) return `422` with a `message` and `errors` bag.
- Unexpected exceptions return `500` with a friendly `message`. When `APP_DEBUG=true` the payload includes the exception message.

## 6. Versioning & change notes

- **Oct 2025**: Added ST registry endpoints (`st-all`, `st-stats`, `st-dropdown-data`).
- **Oct 2025**: `GET /api/file-numbers` gained rich filters (`has_st_file`, `only_active`, ordering safeguards).

Keep this file alongside `docs/` and update it whenever the controller gains new fetch capabilities (for example, additional filters or new legacy sources).
