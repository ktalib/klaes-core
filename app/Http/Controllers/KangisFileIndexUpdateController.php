<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dedicated update flow for KANGIS indexed files.
 *
 * Why this exists rather than reusing the create/edit pages:
 *
 * Most physical KANGIS folders carry the same printed file number, so one file
 * number legitimately maps to several physical files. They are told apart by
 * kangis_fileno_placeholder, and file_indexings.file_number carries an "_N" suffix
 * because that column is uniquely indexed. That is why the create path deliberately
 * skips the duplicate check (FileIndexingController::store, "$isKangisVariant"), and
 * why store() always calls KangisFileNoPlaceholderService::resolveForNewRecord() —
 * which unconditionally allocates the *next* suffix. There is no "resolve for an
 * existing record" counterpart, so saving an existing KANGIS variant through the
 * create form mints yet another variant instead of updating the one in hand.
 *
 * This controller is that missing counterpart:
 *   - file_number is pinned to the stored value and never re-resolved
 *   - no duplicate check runs (the row already exists)
 *   - the placeholder is mirrored into kangis_grouping, which
 *     FileIndexingController::update() does not do
 *
 * Everything else is delegated to FileIndexingController::update() so the two paths
 * cannot drift, and nothing in the existing create/edit workflow is modified.
 */
class KangisFileIndexUpdateController extends FileIndexUpdatePageController
{
    /**
     * Render the KANGIS update form: the same prepared payload as the generic update
     * screen, rendered through the KANGIS clone (which pins the file number and forces
     * the placeholder block open).
     */
    public function __invoke(Request $request, $id)
    {
        $prepared = $this->prepareUpdateForm($request, $id);

        if (!is_array($prepared)) {
            return $prepared;
        }

        return view($this->formView(), $prepared);
    }

    /** Kept so the route's ->name('kangis.file-index.edit') action reads naturally. */
    public function edit(Request $request, $id)
    {
        return $this->__invoke($request, $id);
    }

    protected function formView(): string
    {
        return 'fileindexing.addons.kangis_update_indexing';
    }

    protected function prepareUpdateForm(Request $request, $id)
    {
        $prepared = parent::prepareUpdateForm($request, $id);

        if (!is_array($prepared)) {
            return $prepared;
        }

        $prepared['PageTitle'] = 'Update KANGIS File Index';
        $prepared['PageDescription'] = 'Update an existing KANGIS indexed file';
        $prepared['returnTo'] = (string) $request->query('return_to', route('kangis.indexed-files'));

        return $prepared;
    }

    protected function backButton(Request $request): ?array
    {
        return [
            'label' => 'Back to KANGIS Indexed Files',
            'route' => route('kangis.indexed-files'),
        ];
    }

    /** The KANGIS screen never falls back to the legacy edit page. */
    protected function shouldUseLegacyForm(Request $request): bool
    {
        return false;
    }

    /**
     * Save the KANGIS variant. file_number is pinned; the rest is delegated to the
     * shared update path, then the KANGIS grouping row is brought back in step.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $fileId = (int) $id;

        $existing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $fileId)
            ->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'message' => 'KANGIS indexed file not found.',
            ], 404);
        }

        $pinnedFileNumber = trim((string) $existing->file_number);

        // Pin the identity fields before validation. The "_N" suffix distinguishes this
        // physical folder from its same-numbered siblings and must survive every edit;
        // letting the form change it would drag the whole collision/cascade-rename
        // problem back into a flow whose entire purpose is to avoid it.
        $merge = [
            'file_number' => $pinnedFileNumber,
            'general_registry' => $request->input('general_registry') ?: ($existing->general_registry ?? 'KANGIS'),
        ];

        // Preserve the temporary-file link. The create form has no temp-file input — it
        // infers temp status purely from a "(T)" suffix on the file number, and update()
        // then does an unconditional reset (has_temp_file = false, temp_file_no = null)
        // whenever no "(T)" reaches it. But a temp record stores the BASE number in
        // file_number and keeps the "(T)" only in temp_file_no, so nothing on this form
        // can carry it, and every save would silently sever the link. Feed it back so
        // update()'s detector sees it.
        $existingTempFileNo = trim((string) ($existing->temp_file_no ?? ''));
        if ($existingTempFileNo !== '' && trim((string) $request->input('temp_file_no', '')) === '') {
            $merge['temp_file_no'] = $existingTempFileNo;
        }

        $request->merge($merge);

        // Delegate the actual save. Reused rather than copied so this path picks up
        // every fix made to the shared one (bills, links, PRA, prop_id, entity/customer).
        $response = app(FileIndexingController::class)->update($request, $fileId);

        $payload = $response->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $response;
        }

        // The one thing the shared update path does not do: keep kangis_grouping in step.
        $placeholder = trim((string) $request->input('kangis_fileno_placeholder', ''));
        if ($placeholder !== '') {
            $this->syncKangisGrouping($pinnedFileNumber, $placeholder);
        }

        $payload['message'] = 'KANGIS file index updated successfully.';
        $payload['kangis'] = [
            'id' => $fileId,
            'file_number' => $pinnedFileNumber,
            'placeholder' => $placeholder,
            'redirect_url' => route('kangis.indexed-files'),
        ];

        return response()->json($payload, $response->getStatusCode());
    }

    /**
     * Mirror the placeholder onto this variant's own kangis_grouping row.
     *
     * Matching is on the FULL file number first ("KNML 1_2"), because store() clones a
     * grouping row per variant with kangis_awaiting_fileno set to the suffixed number.
     * Only when no such row exists does this fall back to the bare number — the case
     * where this record is the first (unsuffixed) one for that file number.
     *
     * The base-number fallback is deliberately guarded: stripping "_N" first and
     * updating the base row would stamp one variant's placeholder onto a different
     * physical file.
     */
    private function syncKangisGrouping(string $fileNumber, string $placeholder): void
    {
        try {
            if (!Schema::connection('sqlsrv')->hasTable('kangis_grouping')) {
                return;
            }

            $payload = ['kangis_fileno_placeholder' => $placeholder];

            $optional = [
                'kangis_fileno_resolved' => $fileNumber,
                'updated_at' => now(),
            ];

            foreach ($optional as $column => $value) {
                if (Schema::connection('sqlsrv')->hasColumn('kangis_grouping', $column)) {
                    $payload[$column] = $value;
                }
            }

            if (Schema::connection('sqlsrv')->hasColumn('kangis_grouping', 'updated_by')) {
                $payload['updated_by'] = Auth::user()->name ?? Auth::id();
            }

            $affected = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$fileNumber])
                ->update($payload);

            if ($affected === 0) {
                $baseFileNumber = preg_replace('/_\d+$/', '', $fileNumber);

                // Only fall back when this record IS the bare one — never map a suffixed
                // variant onto the base row.
                if ($baseFileNumber === $fileNumber) {
                    $affected = DB::connection('sqlsrv')
                        ->table('kangis_grouping')
                        ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$baseFileNumber])
                        ->update($payload);
                }
            }

            Log::info('KangisFileIndexUpdate::syncKangisGrouping', [
                'file_number' => $fileNumber,
                'placeholder' => $placeholder,
                'rows_updated' => $affected,
            ]);
        } catch (Throwable $e) {
            // A grouping mirror failure must not fail the save — the authoritative row
            // in file_indexings is already committed at this point.
            Log::warning('KangisFileIndexUpdate::syncKangisGrouping failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
