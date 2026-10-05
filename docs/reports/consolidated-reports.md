# Consolidated reports

The module registers have a **Consolidated Report** action using the existing records preview, CSV and ministry-branded PDF layout. Reports read the full filtered register, independently of the page's pagination. Department headings are configurable; the shared export driver no longer assumes every report belongs to Land.

| Report | Department | Date filter | Record scope |
| --- | --- | --- | --- |
| Bill Balance | Deeds Department | Created date | Bill metadata joined to billing; unmatched billing rows remain pending |
| Valuation | Deeds Department | Generated date | Valuation reports, optionally filtered by print status |
| Consent | Deeds Department | Generated date | One row per consent application, including additional file numbers and parties |
| Land / OSS RofO | Department of Land | Recommendation created date | Existing Land RofO export scope and filters |
| ST Final Conveyance | Sectional Titling Department | Generated date | Latest conveyance per non-deleted primary application |
| ST File Commissioning | Sectional Titling Department | Commissioned date | Commissioned ST file numbers; uncommissioned reservations excluded |
| ST Applications | Sectional Titling Department | System capture date, falling back to created date | Primary, parented units and standalone units; deleted applications excluded |
| ST RofO | Sectional Titling Department | RofO created date | Issued RofO records joined to non-deleted unit applications |
| SLTR RofO | SLTR Department | RofO generated date | Approved, generated recommendations; soft-deleted records excluded |

ST FC was ambiguous in the request, so both Final Conveyance and File Commissioning registers have report actions. ST and SLTR department labels follow existing application terminology.

The new report definitions live in `config/consolidated_reports.php`. Their JSON endpoint is `consolidated-reports.export` under the existing authenticated route group. Land RofO retains its existing export endpoint.

Report table values are uppercase across preview, CSV and PDF. Property location uses district, LGA and state only; plot/house numbers and street details are excluded. Saved administrative fields take precedence, with a recognizable comma-separated administrative suffix used for older records. Unknown components remain omitted rather than being invented. This formatting does not change stored property data.

Date ranges include the whole end date. Search and category filters apply before export. Invalid dates/categories return validation errors. Failed or superseded preview requests cannot leave stale rows downloadable, and changing filters requires a refreshed preview. PDF reports retain the ministry logos, watermark, count and page numbering.

Validation: `php vendor/phpunit/phpunit/phpunit tests/Feature/ConsolidatedReportTest.php` and `node --test tests/js/consolidated-reports.test.js`. All 15 affected Blade templates compiled and linted; all new report feeds were exercised against SQL Server with read-only queries. No database migration is required. Interactive browser/download verification remains a manual check.
