# CODEX Task Handoff — FEFR OP/Transfer-of-Title party correction

Prepared: 2026-08-30 (Africa/Lagos)  
Workspace: `C:\xampp\htdocs\klas`

## 1. Task summary

### Original request

Investigate wrong/mixed parties shown at:

`/lands-one-stop-shop/applications/op-resettlement?source=lands-one-stop-shop&type=change-of-name&record_type=fefr`

The initial examples were `RES-2025-6118` / `TEMP-126807` and `RES-2025-6119` / `TEMP-126736`. The user asked whether the cause was user entry or a system error, including a log check; they later asked for correction and a careful code fix.

### Intended outcome and scope

Correct the two affected PRA Transfer-of-Title records, correct the source OP party for RES-2025-6118 after the user clarified the authoritative holder, prevent future linked OP/ToT Party 1 corruption, and verify the card payload. Scope was limited to the OP Change-of-Name workflow and its `pra` history rows.

## 2. Findings

### Confirmed local/application findings

- The card is populated by `OpResettlementApplicationController::praTransactions()` and returns the stored `pra` values; it was not a front-end-only display mix-up.
- The Match OP endpoint is `lands-one-stop-shop.applications.match-op`; controller method: `OpResettlementApplicationController::matchOp()`.
- The edit endpoint is `lands-one-stop-shop.applications.update-details`; controller method: `OpResettlementApplicationController::updateDetails()`.
- The Match OP form is in `resources/views/lands_one_stop_shop/applications.blade.php`. Its override mode made the source allottee input editable and posted it as `allottee`.
- `updateDetails()` accepted nullable `party_1_name`, normalized omitted values to `null`, and unconditionally wrote that value to a selected Transfer-of-Title row. Therefore an edit could blank Party 1. It also did not enforce the linked OP as the authoritative source.

### Confirmed database findings

All records below are in SQL Server table `pra`.

| PRA ID | File | Role | Confirmed source/linkage | Final parties |
|---:|---|---|---|---|
| 119813 | RES-2025-6118 | OP | source OP for ToT #202332 | Party 1: Kano State Government; Party 2: Malam Murtala |
| 202332 | RES-2025-6118 | Transfer of Title | `source_op_id=119813`, `source_op_table=pra` | Party 1: Malam Murtala; Party 2: Aminu Shuaibu |
| 180915 | RES-2025-6119 | OP | source OP for ToT #202301 | Party 2: Umaru Muhammad |
| 202301 | RES-2025-6119 | Transfer of Title | `source_op_id=180915`, `source_op_table=pra` | Party 1: Umaru Muhammad; Party 2: Aminu Shuaibu |

The separate temporary file numbers and distinct `prop_id` values are expected current lineage design: a ToT receives a fresh `prop_id` and distinct temp file number while recording `parent_prop_id` and `source_op_id`. Diagnostic flags for `tot_temp_fileno_mismatch` and `prop_id_divergence` therefore remain informational for these two rows; they are not evidence of a collision.

### Root cause

**Confirmed:** a user edit was able to write the wrong/blank party values because the server trusted the submitted Transfer-of-Title Party 1 and allowed it to be null.

**Attribution evidence:** the affected ToT rows had `source='Match OP'`, `system_source='OSSOPCHANGEOFNAME'`, and `created_by`/`updated_by` `101524` (Abdulsamad Ado Salisu / `AASALISU`) at the time of the original edits. This does not prove intent; it proves which account performed the stored write.

**Important clarification:** RES-2025-6118 was initially repaired to Aminu based on the then-stored OP Party 2. The user subsequently supplied the authoritative correction: the OP Party 2 and ToT Party 1 must be **Malam Murtala**. The final database state follows that clarification.

### Logs and environment

- Retained application logs did not contain the original 2026-08-21 Match OP requests; available retained daily logs started after that date. The original HTTP payload cannot be reconstructed from logs.
- A repair audit entry was written to `storage/logs/laravel-2026-08-27.log`:
  `Corrected FEFR OP Transfer-of-Title Party 1 values from linked OP holders`.
- A subsequent log entry was also written for the RES-2025-6118 authoritative-holder correction.
- Local Laravel `APP_ENV` was `local`, with default MySQL configuration. The workflow’s PRA reads/writes explicitly use `DB::connection('sqlsrv')`; that SQL Server connection was the data source changed. No secrets are included here.

## 3. Work completed

### Application code modified

Modified file:

`C:\xampp\htdocs\klas\app\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController.php`

In `updateDetails()` near the Transfer-of-Title party update logic:

- When the selected ToT has `source_op_table='pra'` and `source_op_id`, the code reads the linked OP’s `Grantee`, falling back to `party_2`.
- It makes that linked holder authoritative for ToT `Grantor`/`party_1`.
- A submitted different Party 1 is ignored and logged with `Log::warning(...)`.
- For an unlinked legacy ToT where the form omits Party 1, the existing stored `Grantor`/`party_1` is retained instead of being set to null.

Relevant implementation excerpt:

```php
if ($linkedOriginalHolder !== '') {
    $linkedOriginalHolder = strtoupper($linkedOriginalHolder);
    if ($party1Name !== null && strcasecmp($party1Name, $linkedOriginalHolder) !== 0) {
        Log::warning('OP Change-of-Name edit: ignored mismatched Transfer of Title Party 1', [...]);
    }
    $party1Name = $linkedOriginalHolder;
}
```

No routes, views, JavaScript, CSS, migrations, models, or SQL script files were changed. No files were deleted. No deployment package was created.

### Database changes already performed

The following changes were committed in SQL Server transactions through Laravel Tinker. These are production/data changes if the configured SQL Server is production; it was treated as the live data source during the task.

1. `pra.id=202332` (RES-2025-6118 ToT): initially set `Grantor` and `party_1` to Aminu, then superseded by the authoritative final correction below.
2. `pra.id=202301` (RES-2025-6119 ToT): set `Grantor` and `party_1` to `UMARU MUHAMMAD`.
3. Final authoritative RES-2025-6118 correction:
   - `pra.id=119813` OP: set `Grantee` and `party_2` to `MALAM MURTALA`.
   - `pra.id=202332` linked ToT: set `Grantor` and `party_1` to `MALAM MURTALA`.

The ToT Party 2 / Grantee remained `AMINU SHUAIBU` for RES-2025-6118 and RES-2025-6119.

### Commands executed

Read-only diagnostics:

```powershell
php artisan oss:diagnose-op-records TEMP-126807 TEMP-126736 --out=storage/app/diagnostics/fefr-6118-6119.json
php artisan oss:diagnose-op-records TEMP-126807 TEMP-126736 --out=storage/app/diagnostics/fefr-6118-6119-after-repair.json
php artisan oss:diagnose-op-records TEMP-126807 --out=storage/app/diagnostics/fefr-6118-final.json
php -l app\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController.php
```

SQL Server update operations were executed inside `DB::connection('sqlsrv')->transaction(...)` via `php artisan tinker`, guarded by exact PRA IDs, file numbers, linked source IDs, and Transfer-of-Title/OP instrument type checks. Do not rerun the earlier initial RES-2025-6118-to-Aminu update; it was intentionally superseded.

## 4. Current status

- Both requested records now render correct final parties in the API/card payload.
- The server-side prevention change exists locally in the controller file.
- No automated feature test covers this edit behavior; that remains incomplete.
- The workspace is **not a Git repository**: `git status`, `git branch --show-current`, and `git rev-parse HEAD` returned `fatal: not a git repository`. No branch, commit hash, or reliable Git dirty-state is available.
- The code change has not been deployed by this task. Database changes were made through the configured SQL Server connection.
- No deployment archive/package exists.

## 5. Verification

### Passed

- `php -l` returned: `No syntax errors detected` for the modified controller.
- The read-only diagnostic after the first repair removed `blank_party_1` and `tot_party_1_not_op_allottee` flags.
- The final diagnostic endpoint payload for RES-2025-6118 confirmed:

```text
119813  Occupancy Permit (OP):  Party 1 KANO STATE GOVERNMENT; Party 2 MALAM MURTALA
202332  Transfer of Title (OP): Party 1 MALAM MURTALA; Party 2 AMINU SHUAIBU
```

- RES-2025-6119 had been verified after repair as ToT Party 1 `UMARU MUHAMMAD`, Party 2 `AMINU SHUAIBU`.

### Not performed

- No PHPUnit/feature test was added or run because no relevant existing test was found under `tests/`.
- No browser session was executed after the final correction; verification used the same real controller payload that the card consumes.
- No deployment, cache clear, or service restart was performed.

## 6. Deployment and rollback

### Deploy remaining code change

1. Back up the deployed controller file and back up the affected `pra` rows (at minimum IDs `119813`, `202332`, `180915`, `202301`).
2. Deploy only:
   `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php`
3. Clear/rebuild Laravel caches in the deployed application directory:

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Test an edit of a linked ToT: submit an empty or mismatched Party 1 and confirm the linked OP holder remains Party 1. Confirm a warning is logged for a mismatch.

No migration or SQL script is required for the code deployment.

### Rollback

1. Restore the prior controller file from the deployment backup (or a known-good source revision).
2. Run `php artisan optimize:clear`, then rebuild the caches listed above.
3. Do **not** revert the corrected party data unless an authorised data owner supplies different authoritative values. If a data rollback is required, restore the backed-up `pra` rows in a single transaction and re-run the diagnostic command.

## 7. Risks and recommendations

1. **Highest priority:** deploy the local controller fix before staff perform more ToT edits. Until deployed, the live code may still accept a blank/mismatched Party 1.
2. Add a feature test for `updateDetails()` proving that linked ToT Party 1 is sourced from the linked OP and cannot be blanked.
3. Add durable audit logging for request payload, actor, target PRA ID, old values, and new values for party edits. Retention was insufficient for the original incident.
4. Restrict/label Match OP override mode more clearly. It changes legal party data and currently can lead to human-data mistakes without a second confirmation.
5. Do not treat the two expected lineage flags (`tot_temp_fileno_mismatch`, `prop_id_divergence`) as defects without checking `source_op_id`/`parent_prop_id` first.
6. Do not rerun broad repair commands such as `oss:fix-op-transfer-grantor` without a scoped dry run and data-owner approval; this task used exact row IDs instead.

## 8. Evidence

Diagnostic JSON reports:

- `C:\xampp\htdocs\klas\storage\app\diagnostics\fefr-6118-6119.json` — pre-repair evidence.
- `C:\xampp\htdocs\klas\storage\app\diagnostics\fefr-6118-6119-after-repair.json` — intermediate verification; superseded for RES-2025-6118 by the user’s clarification.
- `C:\xampp\htdocs\klas\storage\app\diagnostics\fefr-6118-final.json` — final RES-2025-6118 verification.
- `C:\xampp\htdocs\klas\storage\logs\laravel-2026-08-27.log` — repair audit entries.

The user-provided screenshots showed the incorrect card parties. No screenshot files were created in the workspace. No passwords, tokens, connection strings, or other secrets are included in this report.
