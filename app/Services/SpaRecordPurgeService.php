<?php

namespace App\Services;

use App\Models\SpaApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Master delete for one SPAS record: the land record and everything hanging
 * off it in the SPAS workspace — inspections (all visits), notices and their
 * responses, bills and bill lines, payments and payment lines, referrals,
 * certificates, memos — plus every photo file.
 *
 * There are no backups, so every row and photo is exported to
 * storage/app/audits/spas-master-delete/ before anything is removed.
 */
class SpaRecordPurgeService
{
    /** Rows that would go, table => collection, children before parents. */
    public function collect(SpaApplication $app): array
    {
        $db  = DB::connection('sqlsrv');
        $id  = $app->id;
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlsrv');

        // Notices / referrals / certificates may be filed by number without the
        // application link (free-style notices), so match those by number too.
        $refs = array_values(array_filter(array_unique([trim((string) $app->file_number), trim((string) $app->spa_id)])));
        $linked = function (string $table) use ($db, $id, $refs, $schema) {
            $q = $db->table($table)->where('spa_application_id', $id);
            if ($refs && $schema->hasColumn($table, 'file_number')) {
                $q->orWhere(fn ($w) => $w->whereNull('spa_application_id')->whereIn('file_number', $refs));
            }
            return $q->get();
        };
        $has = fn (string $t) => $schema->hasTable($t);

        $notices   = $linked('spa_notices');
        $bills     = $db->table('spa_bills')->where('spa_application_id', $id)->get();
        $billLines = $bills->isEmpty() ? collect() : $db->table('spa_bill_lines')->whereIn('spa_bill_id', $bills->pluck('id'))->get();
        $payments  = $db->table('spa_payments')
            ->where('spa_application_id', $id)
            ->when($bills->isNotEmpty(), fn ($q) => $q->orWhereIn('spa_bill_id', $bills->pluck('id')))
            ->get();
        $payLines  = $db->table('spa_payment_lines')
            ->when($payments->isNotEmpty(), fn ($q) => $q->whereIn('spa_payment_id', $payments->pluck('id')), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($billLines->isNotEmpty(), fn ($q) => $q->orWhereIn('spa_bill_line_id', $billLines->pluck('id')))
            ->get();

        $responses = collect();
        if ($has('spa_notice_responses')) {
            $responses = $db->table('spa_notice_responses')
                ->where('spa_application_id', $id)
                ->when($notices->isNotEmpty(), fn ($q) => $q->orWhereIn('spa_notice_id', $notices->pluck('id')))
                ->get();
        }

        return [
            'spa_payment_lines'        => $payLines,
            'spa_payments'             => $payments,
            'spa_bill_lines'           => $billLines,
            'spa_bills'                => $bills,
            'spa_notice_responses'     => $responses,
            'spa_notices'              => $notices,
            'spa_department_referrals' => $linked('spa_department_referrals'),
            'spa_certificates'         => $linked('spa_certificates'),
            'spa_memos'                => $db->table('spa_memos')->where('spa_application_id', $id)->get(),
            'spa_field_data'           => $db->table('spa_field_data')->where('spa_application_id', $id)->get(),
            'spa_applications'         => $db->table('spa_applications')->where('id', $id)->get(),
        ];
    }

    /**
     * The Change of Purpose this record raised on settling its bill, if any.
     *
     * Only one SPAS itself created (source = SPAS) — one already in progress
     * that SPAS merely linked to belongs to whoever raised it. And never once
     * commissioned: by then the MLS screen has issued the new file number and
     * linked it to the old file in the registry, which a SPAS delete must not
     * leave pointing at nothing.
     *
     * @return array{remove: ?object, keep: ?object, reason: ?string}
     */
    private function changeOfPurpose(SpaApplication $app): array
    {
        $none = ['remove' => null, 'keep' => null, 'reason' => null];
        if (! SpaNewFileService::supported() || ! $app->cop_application_id) {
            return $none;
        }
        $cop = DB::connection('sqlsrv')->table('change_of_purpose_applications')
            ->where('id', $app->cop_application_id)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->first();
        if (! $cop) {
            return $none;
        }
        if (strtolower((string) $cop->source_module) !== SpaNewFileService::SOURCE) {
            return ['remove' => null, 'keep' => $cop, 'reason' => 'not raised by SPAS'];
        }
        if ($cop->status === 'commissioned') {
            return ['remove' => null, 'keep' => $cop, 'reason' => 'already commissioned'.($app->new_file_number ? " to {$app->new_file_number}" : '').' — the new file number stays in the registry'];
        }

        return ['remove' => $cop, 'keep' => null, 'reason' => null];
    }

    /** Human counts for the confirm dialog. */
    public function summary(SpaApplication $app): array
    {
        $rows = $this->collect($app);
        $labels = [
            'spa_field_data'           => 'Inspections / visits',
            'spa_notices'              => 'Notices (1st / 2nd Serve)',
            'spa_notice_responses'     => 'Notice responses',
            'spa_bills'                => 'Bills',
            'spa_bill_lines'           => 'Bill lines',
            'spa_payments'             => 'Payments',
            'spa_payment_lines'        => 'Payment lines',
            'spa_department_referrals' => 'Department referrals',
            'spa_certificates'         => 'Certificates',
            'spa_memos'                => 'Memos',
        ];
        $counts = [];
        foreach ($labels as $t => $label) {
            if ($rows[$t]->count()) {
                $counts[] = ['label' => $label, 'count' => $rows[$t]->count()];
            }
        }

        $cop = $this->changeOfPurpose($app);
        if ($cop['remove']) {
            $counts[] = ['label' => 'Change of Purpose raised by SPAS', 'count' => 1];
        }

        return [
            'reference' => $app->spa_id ?: $app->file_number,
            'owner'     => $app->owner_name,
            'counts'    => $counts,
            'photos'    => count($this->photoPaths($rows)),
            // Shown in the dialog so nobody expects it gone.
            'kept'      => $cop['keep'] ? ['Change of Purpose for ' . $cop['keep']->file_no . ' is kept: ' . $cop['reason'] . '.'] : [],
        ];
    }

    /** Export, then delete in one transaction, then remove the photo files. */
    public function purge(SpaApplication $app, $user): array
    {
        $rows   = $this->collect($app);
        $photos = $this->photoPaths($rows);
        $cop    = $this->changeOfPurpose($app);
        $ref    = $app->spa_id ?: ($app->file_number ?: 'id-'.$app->id);

        // 1. Export every row and every photo (with its folder path kept).
        $dir = 'audits/spas-master-delete/'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $ref).'-'.now()->format('Ymd-His');
        $copied = 0;
        foreach ($photos as $p) {
            if (Storage::disk('public')->exists($p)) {
                Storage::disk('local')->put("$dir/photos/$p", Storage::disk('public')->get($p));
                $copied++;
            }
        }
        Storage::disk('local')->put("$dir/rows.json", json_encode([
            'deleted_at'  => now()->toIso8601String(),
            'deleted_by'  => ['id' => $user->id ?? null, 'name' => $user->name ?? null],
            'reference'   => $ref,
            'photo_paths' => $photos,
            'rows'        => $rows,
            'change_of_purpose' => ['removed' => $cop['remove'], 'kept' => $cop['keep'], 'reason' => $cop['reason']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // The export must exist before anything is removed.
        if (! Storage::disk('local')->exists("$dir/rows.json")) {
            throw new \RuntimeException('Could not write the export; nothing was deleted.');
        }

        // 2. Delete, children first, all or nothing.
        $deleted = [];
        DB::connection('sqlsrv')->transaction(function () use ($rows, $cop, $user, &$deleted) {
            // KLAES REV-M: Bill Master keeps its copy of each bill, flagged cancelled.
            foreach (($rows['spa_bills'] ?? collect()) as $spaBill) {
                app(\App\Services\Billing\Modules\SpasBillMirror::class)->cancel((int) $spaBill->id, (string) ($user->name ?? $user->id ?? 'system'));
            }
            foreach ($rows as $table => $set) {
                if ($set->isNotEmpty()) {
                    $deleted[$table] = DB::connection('sqlsrv')->table($table)->whereIn('id', $set->pluck('id'))->delete();
                }
            }
            // The Change of Purpose is marked deleted, as its own screen does it —
            // it lives in the shared register, not in the SPAS workspace.
            if ($cop['remove']) {
                $deleted['change_of_purpose_applications'] = DB::connection('sqlsrv')->table('change_of_purpose_applications')
                    ->where('id', $cop['remove']->id)
                    ->update(['is_deleted' => 1, 'deleted_by' => $user->id ?? null, 'deleted_at' => now(),
                        'remarks' => trim(($cop['remove']->remarks ?? '') . "\nRemoved with its SPAS record (master delete) on " . now()->toDateTimeString())]);
            }
        });

        // 3. Photo files, only after the rows are gone (copies are in the export).
        $removed = 0;
        foreach ($photos as $p) {
            if (Storage::disk('public')->delete($p)) {
                $removed++;
            }
        }

        return [
            'reference' => $ref,
            'deleted'   => $deleted,
            'photos'    => ['found' => count($photos), 'exported' => $copied, 'removed' => $removed],
            'export'    => 'storage/app/'.$dir,
        ];
    }

    /** Every photo path stored on the record and its visits. */
    private function photoPaths(array $rows): array
    {
        $paths = [];
        foreach ([$rows['spa_applications'], $rows['spa_field_data']] as $set) {
            foreach ($set as $r) {
                $list = json_decode((string) ($r->photos ?? ''), true);
                foreach ((array) $list as $p) {
                    if (is_string($p) && $p !== '') {
                        $paths[] = ltrim(preg_replace('#^.*?/storage/#', '', $p), '/');
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
