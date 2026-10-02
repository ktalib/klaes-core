<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ToTStagingController extends Controller
{
    /** pra.system_source stamped by the Match OP flow — see OpHolderMatchService. */
    private const SYSTEM_GENERATED_SOURCE = 'OPHOLDERMATCH';

    private function isGovernmentEntity($name)
    {
        if (!$name) return false;
        $name = strtoupper(trim($name));
        $patterns = [
            'GOVERNMENT',
            'JUDICIARY',
            'STATE',
            'FEDERAL',
            'MINISTRY',
            'DEPARTMENT',
            'AGENCY',
            'COMMISSION',
            'AUTHORITY'
        ];
        // Word boundaries, not substrings: 'STATE' inside 'THE ESTATE OF LATE ALHAJI ...'
        // is not a government body, and the substring form silently barred every
        // deceased-holder estate from ever being transferred.
        foreach ($patterns as $pattern) {
            if (preg_match('/\b' . $pattern . '\b/', $name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every Transfer of Title on record.
     *
     * This screen used to list `pra_tot_staging` — candidates queued for bulk ToT
     * generation during the migration. That migration is finished, and transfers are
     * now written one file at a time through Match OP, where the officer sees the
     * file's whole chain and confirms both names before anything is recorded. So the
     * page has stopped being a work queue and become the register of what was
     * written: every live Transfer of Title in pra, newest first.
     *
     * The two bulk buttons are disabled in the view for the same reason.
     *
     * Read from pra alone because that is where a Transfer of Title is recorded. The
     * other registers hold assignments and conveyances, which move a title too but
     * are not this instrument, and folding them in would make the count answer a
     * different question than the page asks.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $filter = $request->query('filter');   // 'system' | 'captured' | null

        $base = fn () => DB::connection('sqlsrv')->table('pra')
            ->where(function ($q) {
                $q->where('transaction_type', 'LIKE', '%Transfer of Title%')
                  ->orWhere('instrument_type', 'LIKE', '%Transfer of Title%');
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });

        // Counted off the same base as the list, so the cards and the table can never
        // disagree about what a Transfer of Title is.
        $total = $base()->count();
        $systemGenerated = $base()->where('system_source', self::SYSTEM_GENERATED_SOURCE)->count();

        $query = $base();

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('mlsFNo', 'LIKE', $like)
                  ->orWhere('fileno', 'LIKE', $like)
                  ->orWhere('kangisFileNo', 'LIKE', $like)
                  ->orWhere('NewKANGISFileno', 'LIKE', $like)
                  ->orWhere('party_1', 'LIKE', $like)
                  ->orWhere('party_2', 'LIKE', $like);
            });
        }

        if ($filter === 'system') {
            $query->where('system_source', self::SYSTEM_GENERATED_SOURCE);
        } elseif ($filter === 'captured') {
            $query->where(function ($q) {
                $q->whereNull('system_source')
                  ->orWhere('system_source', '<>', self::SYSTEM_GENERATED_SOURCE);
            });
        }

        $records = $query
            ->orderByDesc('id')
            ->select([
                'id', 'mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno',
                'party_1', 'party_2', 'transaction_type', 'instrument_type',
                'transaction_date', 'regNo', 'prop_id', 'source', 'system_source',
                'created_at',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('maintenance.tot_staging', [
            'records'         => $records,
            'search'          => $search,
            'filter'          => $filter,
            'total'           => $total,
            'systemGenerated' => $systemGenerated,
            'captured'        => max(0, $total - $systemGenerated),
        ]);
    }

    public function generate(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }

        $results = [];
        $processedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $id) {
            $staging = DB::connection('sqlsrv')->table('pra_tot_staging')->where('id', $id)->first();
            if (!$staging || $staging->is_processed)
                continue;

            if ($this->isGovernmentEntity($staging->ro_name)) {
                $skippedCount++;
                continue;
            }

            // Fetch OP record for full data
            $opRecord = DB::connection('sqlsrv')->table('pra')->where('id', $staging->op_id)->first();
            if (!$opRecord)
                continue;

            // Prepare ToT record
            $totData = (array) $opRecord;
            // Drop PK and SQL Server computed/managed columns that can't be inserted
            unset(
                $totData['id'],
                $totData['resolved_fileno'],
                $totData['updated_at'],
                $totData['deleted_at']
            );

            $totData['transaction_type'] = 'Transfer Of Title (OP)';
            $totData['Grantor'] = $staging->op_name;
            $totData['Grantee'] = $staging->ro_name;
            $totData['party_1'] = $staging->op_name;
            $totData['party_2'] = $staging->ro_name;
            $totData['created_at'] = now();
            $totData['remarks'] = (isset($totData['remarks']) ? $totData['remarks'] : '') . " | Auto-generated ToT from OP/RO mismatch (Staging ID: {$id})";

            // Insert into pra
            DB::connection('sqlsrv')->table('pra')->insert($totData);

            // Update staging
            DB::connection('sqlsrv')->table('pra_tot_staging')->where('id', $id)->update([
                'status' => 'processed',
                'is_processed' => 1,
                'processed_at' => now(),
                'processed_by' => auth()->id() ?? 1,
                'remarks' => 'Successfully generated ToT record.'
            ]);

            $processedCount++;
        }

        $msg = "Successfully generated {$processedCount} ToT records.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} government/judiciary records skipped)";
        }

        return response()->json([
            'success' => true,
            'message' => $msg
        ]);
    }

    public function ignore(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }

        DB::connection('sqlsrv')->table('pra_tot_staging')->whereIn('id', $ids)->update([
            'status' => 'ignored',
            'is_processed' => 1,
            'processed_at' => now(),
            'processed_by' => auth()->id() ?? 1
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ignored " . count($ids) . " records."
        ]);
    }
}


