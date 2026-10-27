<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use Illuminate\Support\Facades\DB;

/**
 * Registry's "Update EDMS" when the main file comes back from Land (transcript, 2026-10-16):
 * the file has grown (Commissioner, PS and DL signatures on the recommendation and R of O), so
 * the new pages are scanned and uploaded (EDMS Scan More), then page-typed (Type More), before
 * the file goes on. The pages go through EDMS as scannings rows, so Page Typing sees them.
 *
 * The task is ticked by hand once the new pages are uploaded. Page typing is not a condition
 * (user, 2026-10-27: "why must I do page typing?"): it stays on the Page Typing page and can be
 * done after the file moves on; the count of typed pages is shown for information.
 */
class EdmsUpdate
{
    public const TASK = 'edms_updated';

    /** When the file arrived at its current step (the move into it, not a head-approval hand-over). */
    public function since(CadastralCase $case): ?\DateTimeInterface
    {
        $m = CadastralCaseMovement::query()->where('cadastral_case_id', $case->id)
            ->where('to_step', $case->current_step)
            ->where(fn ($q) => $q->whereNull('from_step')->orWhereColumn('from_step', '!=', 'to_step'))
            ->orderByDesc('id')->first(['acted_at']);

        return $m?->acted_at;
    }

    /**
     * @return array{indexed:bool, new_pages:int, typed:int, complete:bool, typed_all:bool, scan_url:?string, type_url:?string}
     */
    public function status(CadastralCase $case): array
    {
        $id = (int) $case->file_indexing_id;
        $out = ['indexed' => $id > 0, 'new_pages' => 0, 'typed' => 0, 'complete' => false, 'typed_all' => false, 'scan_url' => null, 'type_url' => null];
        if (!$id) {
            return $out;
        }
        $out['scan_url'] = route('scanning.index', ['url' => 'scmore', 'file_indexing_id' => $id]);
        $out['type_url'] = route('pagetyping.index', ['url' => 'ptmore', 'file_indexing_id' => $id]);

        $since = $this->since($case);
        $db = DB::connection('sqlsrv');
        $scans = $db->table('scannings')->where('file_indexing_id', $id)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->pluck('id');
        $out['new_pages'] = $scans->count();
        if ($scans->isNotEmpty()) {
            $out['typed'] = $db->table('pagetypings')->whereIn('scanning_id', $scans)->whereNull('deleted_at')
                ->distinct()->count('scanning_id');
        }
        // Uploaded is enough to move on; typed_all only says whether page typing has caught up.
        $out['complete'] = $out['new_pages'] > 0;
        $out['typed_all'] = $out['new_pages'] > 0 && $out['typed'] >= $out['new_pages'];

        return $out;
    }
}
