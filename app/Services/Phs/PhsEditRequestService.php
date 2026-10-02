<?php

namespace App\Services\Phs;

use App\Models\Phs\PhsEditRequest;
use App\Models\Phs\PhsMember;
use App\Models\Phs\PhsSearchLog;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The PHS correction workflow: a member reports a bad search result, PHS-P Admin
 * corrects the records, and the member gets ONE re-run that costs no token.
 *
 * Every state transition lives here rather than in the controllers, because the
 * end of this workflow hands out a free search. Spreading that decision across
 * a portal controller and an admin controller is how a free-search rule turns
 * into a free-search hole.
 *
 * The three rules that must never be relaxed:
 *   1. Only READY_FOR_RERUN authorises a free search (PhsEditRequest::authorisesFreeRerun).
 *   2. The re-run must be for the SAME file number the complaint was about.
 *   3. The authorisation is consumed atomically, so a double-submit or two
 *      browser tabs cannot spend it twice.
 */
class PhsEditRequestService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * Member reports a result as wrong.
     *
     * $originalResult is the report payload the member actually saw, stored so
     * the admin corrects against what was complained about rather than against
     * whatever the record looks like when they get to it.
     */
    public function open(
        PhsMember $member,
        string $fileNumber,
        string $reasonCategory,
        string $reason,
        ?array $originalResult = null,
        ?PhsSearchLog $searchLog = null,
        ?string $ipAddress = null
    ): PhsEditRequest {
        $fileNumber = trim($fileNumber);

        // An open request already covers this file - reuse it rather than
        // creating a second one. The filtered unique index would refuse the
        // insert anyway; catching it here gives the member a sane message.
        $existing = PhsEditRequest::query()
            ->where('phs_member_id', $member->id)
            ->where('file_number', $fileNumber)
            ->open()
            ->whereNull('rerun_search_log_id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $request = PhsEditRequest::create([
            'phs_institution_id' => $member->phs_institution_id,
            'phs_member_id'      => $member->id,
            'requester_name'     => $this->memberName($member),
            'requester_email'    => $member->email ?? null,
            'search_log_id'      => $searchLog->id ?? null,
            'reference_no'       => $searchLog->reference_no ?? null,
            'file_number'        => $fileNumber,
            'reason_category'    => $reasonCategory,
            'reason'             => Str::limit($reason, 4000, ''),
            'original_result'    => $originalResult ? json_encode($originalResult) : null,
            'status'             => PhsEditRequest::STATUS_EDIT_REQUESTED,
            'requested_at'       => now(),
            'ip_address'         => $ipAddress,
        ]);

        $this->log('CREATED', $request, null, [
            'file_number'     => $request->file_number,
            'reason_category' => $request->reason_category,
            'reference_no'    => $request->reference_no,
        ]);

        return $request;
    }

    /**
     * Admin has corrected the records and returns the request to the member.
     *
     * This is the transition that authorises the free re-run, so it refuses to
     * run on anything but an open EDIT_REQUESTED row — re-returning an already
     * returned request would otherwise reset a spent authorisation.
     */
    public function returnForRerun(PhsEditRequest $request, $adminUser, ?string $response = null): bool
    {
        if ($request->status !== PhsEditRequest::STATUS_EDIT_REQUESTED) {
            return false;
        }

        $before = ['status' => $request->status];

        $request->fill([
            'status'         => PhsEditRequest::STATUS_READY_FOR_RERUN,
            'reviewed_by'    => $adminUser->id ?? null,
            'reviewer_name'  => $this->userName($adminUser),
            'admin_response' => $response !== null ? Str::limit($response, 4000, '') : $request->admin_response,
            'corrected_at'   => now(),
        ])->save();

        $this->log('UPDATED', $request, $before, [
            'status'        => $request->status,
            'reviewer_name' => $request->reviewer_name,
        ]);

        return true;
    }

    /**
     * Admin found nothing to correct. No free re-run is granted.
     */
    public function decline(PhsEditRequest $request, $adminUser, string $response): bool
    {
        if ($request->status !== PhsEditRequest::STATUS_EDIT_REQUESTED) {
            return false;
        }

        $before = ['status' => $request->status];

        $request->fill([
            'status'         => PhsEditRequest::STATUS_DECLINED,
            'reviewed_by'    => $adminUser->id ?? null,
            'reviewer_name'  => $this->userName($adminUser),
            'admin_response' => Str::limit($response, 4000, ''),
            'corrected_at'   => now(),
        ])->save();

        $this->log('UPDATED', $request, $before, ['status' => $request->status]);

        return true;
    }

    /**
     * The open authorisation for this member + file, or null.
     *
     * Called on the search path to decide whether to charge a token. Matching on
     * file number is rule 2: an authorisation earned on one file must not pay
     * for a search of a different one.
     */
    public function findAuthorisation(PhsMember $member, string $fileNumber): ?PhsEditRequest
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        $candidate = PhsEditRequest::query()
            ->where('phs_member_id', $member->id)
            ->where('status', PhsEditRequest::STATUS_READY_FOR_RERUN)
            ->whereNull('rerun_search_log_id')
            // Case/format-insensitive: the member re-runs by clicking a button,
            // but a hand-typed number should still match.
            ->whereRaw('UPPER(REPLACE(REPLACE(file_number, \'/\', \'-\'), \' \', \'\')) = ?',
                [strtoupper(str_replace(['/', ' '], ['-', ''], $fileNumber))])
            ->orderBy('id')
            ->first();

        return ($candidate && $candidate->authorisesFreeRerun()) ? $candidate : null;
    }

    /**
     * Spend the authorisation, atomically.
     *
     * Returns true only if THIS call was the one that consumed it. The UPDATE is
     * conditional on rerun_search_log_id still being null, so two concurrent
     * re-runs cannot both be told they were free — the loser gets false and the
     * caller charges a token as normal.
     */
    public function consume(PhsEditRequest $request, ?int $searchLogId, $member): bool
    {
        $claimed = DB::connection('sqlsrv')->table('phs_edit_requests')
            ->where('id', $request->id)
            ->whereNull('rerun_search_log_id')
            ->where('status', PhsEditRequest::STATUS_READY_FOR_RERUN)
            ->update([
                'rerun_search_log_id' => $searchLogId,
                'rerun_at'            => now(),
                'rerun_by'            => $member->id ?? null,
                'status'              => PhsEditRequest::STATUS_COMPLETED,
                'updated_at'          => now(),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        $request->refresh();

        $this->log('UPDATED', $request, ['status' => PhsEditRequest::STATUS_READY_FOR_RERUN], [
            'status'              => PhsEditRequest::STATUS_COMPLETED,
            'rerun_search_log_id' => $searchLogId,
            'tokens_charged'      => 0,
        ]);

        return true;
    }

    /**
     * Best-effort audit write.
     *
     * AuditService::logAction rethrows on failure; nothing in this workflow is
     * worth failing a member's search or an admin's correction over, so the
     * throw is swallowed here and logged instead.
     */
    private function log(string $action, PhsEditRequest $request, ?array $before, array $after): void
    {
        try {
            $this->audit->logAction(
                $action,
                'phs_edit_request',
                $request->id,
                $before,
                $after,
                'PHS edit request ' . $request->id . ' (' . $request->file_number . ')'
            );
        } catch (\Throwable $e) {
            Log::warning('PhsEditRequestService: audit write failed', [
                'edit_request_id' => $request->id,
                'action'          => $action,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function memberName($member): ?string
    {
        $name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        return $name !== '' ? $name : ($member->name ?? $member->email ?? null);
    }

    private function userName($user): ?string
    {
        if (!$user) {
            return null;
        }
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->name ?? $user->email ?? null);
    }
}
