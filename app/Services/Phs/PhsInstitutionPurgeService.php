<?php

namespace App\Services\Phs;

use App\Models\Phs\PhsEmailHistory;
use App\Models\Phs\PhsFeedback;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsMember;
use App\Models\Phs\PhsOnboardingRequest;
use App\Models\Phs\PhsSearchLog;
use App\Models\Phs\PhsTokenTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Master delete for a PHS organization.
 *
 * Walks the whole PHS graph hanging off one institution — members, wallet
 * ledger, search logs, feedback, email history and the onboarding request that
 * created it — and removes every row plus the files those rows uploaded.
 * Irreversible: callers are expected to have taken a typed confirmation and to
 * write an audit record from the returned snapshot.
 */
class PhsInstitutionPurgeService
{
    /**
     * Row counts that a purge of this institution would remove.
     * Used to show the operator what they are about to destroy.
     */
    public function preview(PhsInstitution $institution): array
    {
        $requestIds = $this->requestIds($institution);

        return [
            'members'            => PhsMember::where('phs_institution_id', $institution->id)->count(),
            'transactions'       => PhsTokenTransaction::where('phs_institution_id', $institution->id)->count(),
            'search_logs'        => PhsSearchLog::where('phs_institution_id', $institution->id)->count(),
            'feedback'           => PhsFeedback::where('phs_institution_id', $institution->id)->count(),
            'email_histories'    => $this->emailHistoryQuery($institution, $requestIds)->count(),
            'onboarding_requests' => count($requestIds),
            'token_balance'      => (int) $institution->token_balance,
        ];
    }

    /**
     * Delete the institution and everything attached to it.
     *
     * @return array{counts: array, snapshot: array} counts removed, plus an
     *         audit-friendly snapshot of the institution as it was.
     */
    public function purge(PhsInstitution $institution): array
    {
        $requestIds = $this->requestIds($institution);
        $counts = $this->preview($institution);
        $snapshot = $this->snapshot($institution, $requestIds, $counts);
        $files = $this->filePaths($institution, $requestIds);

        DB::connection('sqlsrv')->transaction(function () use ($institution, $requestIds) {
            $this->emailHistoryQuery($institution, $requestIds)->delete();
            PhsFeedback::where('phs_institution_id', $institution->id)->delete();
            PhsSearchLog::where('phs_institution_id', $institution->id)->delete();
            PhsTokenTransaction::where('phs_institution_id', $institution->id)->delete();
            PhsMember::where('phs_institution_id', $institution->id)->delete();

            if (!empty($requestIds)) {
                // Drop the FK reference first: phs_onboarding_requests points at
                // phs_institutions, and SQL Server rejects the parent delete
                // while the child row still holds the id.
                PhsOnboardingRequest::whereIn('id', $requestIds)
                    ->update(['created_phs_institution_id' => null]);
                PhsOnboardingRequest::whereIn('id', $requestIds)->delete();
            }

            $institution->delete();
        });

        // Files go only after the rows are safely gone — a rolled-back
        // transaction must not leave the organization pointing at dead paths.
        $counts['files'] = $this->deleteFiles($files);

        return ['counts' => $counts, 'snapshot' => $snapshot];
    }

    /** Onboarding request ids that created / belong to this institution. */
    private function requestIds(PhsInstitution $institution): array
    {
        return PhsOnboardingRequest::where('created_phs_institution_id', $institution->id)
            ->pluck('id')
            ->all();
    }

    /**
     * Email history is written either against the institution or against the
     * onboarding request that preceded it (before the institution existed).
     * Matching on recipient address is deliberately avoided — two organizations
     * can share a contact person.
     */
    private function emailHistoryQuery(PhsInstitution $institution, array $requestIds)
    {
        return PhsEmailHistory::query()
            ->where(function ($q) use ($institution, $requestIds) {
                $q->where('phs_institution_id', $institution->id);
                if (!empty($requestIds)) {
                    $q->orWhereIn('phs_onboarding_request_id', $requestIds);
                }
            });
    }

    /** Every uploaded/generated file on the `public` disk owned by this graph. */
    private function filePaths(PhsInstitution $institution, array $requestIds): array
    {
        $paths = [$institution->logo_path, $institution->banner_path];

        $paths = array_merge($paths, PhsTokenTransaction::where('phs_institution_id', $institution->id)
            ->pluck('payment_proof_path')->all());

        if (!empty($requestIds)) {
            foreach (PhsOnboardingRequest::whereIn('id', $requestIds)->get() as $req) {
                $paths[] = $req->cac_document_path;
                $paths[] = $req->request_letter_path;
                $paths[] = $req->invoice_pdf_path;
                $paths[] = $req->lsa_signed_document_path;
                foreach ((array) $req->additional_documents as $doc) {
                    $paths[] = is_array($doc) ? ($doc['path'] ?? null) : $doc;
                }
            }
        }

        return collect($paths)
            ->filter(fn ($p) => is_string($p) && trim($p) !== '')
            ->map(fn ($p) => ltrim(str_replace('\\', '/', trim($p)), '/'))
            // Guard rail: PHS only ever stores under phs/, so anything else is
            // a stray value we refuse to delete.
            ->filter(fn ($p) => str_starts_with($p, 'phs/'))
            ->unique()
            ->values()
            ->all();
    }

    /** @return int number of files actually removed */
    private function deleteFiles(array $paths): int
    {
        $removed = 0;

        foreach ($paths as $path) {
            try {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $removed++;
                }
            } catch (\Throwable $e) {
                // A missing/locked file must not fail a completed purge.
                Log::warning('PHS purge could not delete file', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        return $removed;
    }

    /** Audit-trail snapshot of what was destroyed. */
    private function snapshot(PhsInstitution $institution, array $requestIds, array $counts): array
    {
        return [
            'id'                  => $institution->id,
            'name'                => $institution->name,
            'username'            => $institution->username,
            'email'               => $institution->email,
            'phone'               => $institution->phone,
            'type'                => $institution->type,
            'status'              => $institution->status,
            'token_balance'       => (int) $institution->token_balance,
            'created_at'          => optional($institution->created_at)->toDateTimeString(),
            'member_emails'       => PhsMember::where('phs_institution_id', $institution->id)
                                        ->pluck('email')->all(),
            'onboarding_request_ids' => $requestIds,
            'deleted_counts'      => $counts,
        ];
    }
}
