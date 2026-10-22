<?php

namespace App\Services;

use App\Models\SpaApplication;
use App\Models\SpaNotice;
use App\Models\SpaNoticeResponse;
use Illuminate\Support\Facades\DB;

/**
 * Where a SPAS record stands in its workflow:
 *
 *   Record → Field Data → Contravention (bill + 1st Serve) → 2nd Serve →
 *   Response → Payment → Memo → Decision → Certificate
 *
 * Read-only — it reports what the tables say and never decides policy. Each
 * stage comes back as { state, message } where state is one of:
 *   done      cleared
 *   progress  started, not finished (bill part-paid, contravention half raised)
 *   pending   still to do
 *   na        does not apply (no contravention; a response made the 2nd Serve unnecessary)
 *   rejected  the Commissioner rejected the memo
 * "Skipped" (a later stage done before this one) is worked out by the painter.
 *
 * Batched: one query per table for any number of records, so a page of
 * table rows costs the same as one.
 */
class SpaPipelineService
{
    // Response before 2nd Serve: a reply within 14 days makes the 2nd Serve unnecessary.
    // No "Record" step: every SPAS record has been captured, so it is always done.
    // New File No. follows the settled bill: Change of Purpose or New Conversion.
    public const STAGES = ['field', 'contravention', 'response', 'second', 'payment', 'newfile', 'memo', 'decision', 'certificate'];

    public function __construct(private ?SpaBillingService $billing = null)
    {
        $this->billing ??= new SpaBillingService();
    }

    /** The marker a table cell carries; spas-pipeline.js paints it. */
    public static function marker($applicationId): string
    {
        return $applicationId
            ? '<span data-spa-pipeline="'.(int) $applicationId.'"></span>'
            : '<span class="text-gray-300 text-xs">—</span>';
    }

    /**
     * Where a record is now: the first applicable stage after the last one done.
     * 'complete' when nothing is left, 'cleared' when there was no contravention.
     */
    public static function position(array $stages): string
    {
        $keys = self::STAGES;
        $last = -1;
        foreach ($keys as $i => $k) {
            if (($stages[$k]['state'] ?? null) === 'done') {
                $last = $i;
            }
        }
        // A 2nd Serve going out means the reply did not come — the record still
        // waits for that response, it has not skipped it.
        if (($stages['second']['state'] ?? null) === 'done' && ($stages['response']['state'] ?? null) === 'pending'
            && $last === array_search('second', $keys, true)) {
            return 'response';
        }
        for ($i = $last + 1; $i < count($keys); $i++) {
            $state = $stages[$keys[$i]]['state'] ?? 'pending';
            if ($state !== 'na' && $state !== 'done') {
                return $keys[$i];
            }
        }

        return ($stages['contravention']['state'] ?? null) === 'na' ? 'cleared' : 'complete';
    }

    /**
     * Counts for the page stepper and dashboard: per stage how many records have
     * done it, and how many are waiting at it now. Cached briefly — every SPAS
     * page shows it, and it reads every record.
     *
     * @return array{total:int, stages:array<string, array{done:int, here:int}>, cleared:int, complete:int, positions:array<int,string>}
     */
    public function summary(bool $fresh = false): array
    {
        $build = function () {
            $out = ['total' => 0, 'stages' => array_fill_keys(self::STAGES, ['done' => 0, 'here' => 0]),
                    'cleared' => 0, 'complete' => 0, 'positions' => []];
            SpaApplication::query()->select('id')->orderBy('id')->chunk(500, function ($chunk) use (&$out) {
                foreach ($this->forApplications($chunk->pluck('id')->all()) as $id => $stages) {
                    $out['total']++;
                    foreach (self::STAGES as $k) {
                        if (($stages[$k]['state'] ?? null) === 'done') {
                            $out['stages'][$k]['done']++;
                        }
                    }
                    $pos = self::position($stages);
                    $out['positions'][$id] = $pos;
                    if (isset($out['stages'][$pos])) {
                        $out['stages'][$pos]['here']++;
                    } else {
                        $out[$pos]++;
                    }
                }
            });

            return $out;
        };

        if ($fresh) {
            return $build();
        }

        return \Illuminate\Support\Facades\Cache::remember('spas.pipeline.summary', 60, $build);
    }

    /** @return array<int, array<string, array{state:string, message:string}>> keyed by application id */
    public function forApplications(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        $db   = DB::connection('sqlsrv');
        $apps = SpaApplication::whereIn('id', $ids)->get()->keyBy('id');

        // A Change of Purpose commissioned on the MLS screen since the last look
        // carries the new number; bring it onto the record before reporting.
        if (SpaNewFileService::supported()) {
            $newFile = app(SpaNewFileService::class);
            $apps->filter(fn ($a) => $a->new_file_route === SpaNewFileService::COP && ! $a->new_file_number)
                ->each(fn ($a) => $newFile->sync($a));
        }

        $visits = $db->table('spa_field_data')->whereIn('spa_application_id', $ids)
            ->selectRaw('spa_application_id, COUNT(*) AS n, MAX(inspection_date) AS last_date')
            ->groupBy('spa_application_id')->get()->keyBy('spa_application_id');

        $bills = $db->table('spa_bills')->whereIn('spa_application_id', $ids)
            ->orderBy('id')->get()->groupBy('spa_application_id');
        $paid = $db->table('spa_payments')->whereIn('spa_application_id', $ids)
            ->selectRaw('spa_application_id, SUM(amount_paid) AS total')
            ->groupBy('spa_application_id')->pluck('total', 'spa_application_id');

        $notices = SpaNotice::whereIn('spa_application_id', $ids)->orderBy('id')->get()->groupBy('spa_application_id');
        $responses = SpaNoticeResponse::supported()
            ? SpaNoticeResponse::whereIn('spa_application_id', $ids)->orderByDesc('id')->get()->groupBy('spa_application_id')
            : collect();

        $memos = $db->table('spa_memos')->whereIn('spa_application_id', $ids)
            ->orderByDesc('id')->get()->groupBy('spa_application_id');
        $certs = $db->table('spa_certificates')->whereIn('spa_application_id', $ids)
            ->orderByDesc('id')->get()->groupBy('spa_application_id');

        $out = [];
        foreach ($ids as $id) {
            $app = $apps->get($id);
            if (! $app) {
                continue;
            }
            $out[$id] = $this->stages(
                $app,
                $visits->get($id),
                collect($bills->get($id, [])),
                (float) ($paid[$id] ?? 0),
                collect($notices->get($id, [])),
                collect($responses->get($id, [])),
                collect($memos->get($id, [])),
                collect($certs->get($id, []))
            );
        }

        return $out;
    }

    private function stages(SpaApplication $app, $visit, $bills, float $paid, $notices, $responses, $memos, $certs): array
    {
        $d   = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';
        $ngn = fn ($v) => '₦'.number_format((float) $v, 2);
        $s   = fn (string $state, string $message) => ['state' => $state, 'message' => $message];

        $contravenes = $this->billing->contravenes($app);
        $first  = $notices->firstWhere('notice_type', 'first');
        $second = $notices->firstWhere('notice_type', 'second');
        $bill   = $bills->firstWhere('source', 'contravention') ?? $bills->first();
        $resp   = $responses->first();
        $memo   = $memos->first();
        $cert   = $certs->first();

        $st = [];
        $st['record'] = $s('done', 'Captured '.$d($app->created_at).($app->created_by ? ' by '.$app->created_by : ''));

        $st['field'] = $visit
            ? $s('done', $visit->n.' visit'.($visit->n > 1 ? 's' : '').', last '.$d($visit->last_date))
            : $s('pending', 'No inspection yet');

        // Bill and First Serve are raised together on save, so one step.
        if ($bill && $first) {
            $st['contravention'] = $s('done', 'Bill '.$bill->reference_id.' · 1st Serve '.$d($first->served_date));
        } elseif ($bill || $first) {
            $st['contravention'] = $s('progress', $bill ? 'Bill raised, no 1st Serve' : '1st Serve served, no bill');
        } elseif ($contravenes) {
            $st['contravention'] = $s('pending', 'Contravention found, not yet raised');
        } else {
            $st['contravention'] = $s('na', 'Same use — no contravention');
        }

        if ($second) {
            $st['second'] = $s('done', 'Issued '.$d($second->served_date));
        } elseif ($resp) {
            $st['second'] = $s('na', 'Not needed — responded');
        } elseif (! $first) {
            $st['second'] = $s($contravenes ? 'pending' : 'na', $contravenes ? 'After the 1st Serve' : 'No contravention');
        } elseif (! $first->sms_sent) {
            $st['second'] = $s('pending', 'Waits for 1st Serve SMS');
        } else {
            $due = $first->scheduled_date ?? optional($first->served_date)->copy()?->addDays(SpaNotice::SECOND_SERVE_AFTER_DAYS);
            $st['second'] = $s('pending', $due && $due->isFuture() ? 'Due '.$d($due) : 'Due now');
        }

        $st['response'] = $resp
            ? $s('done', (SpaNoticeResponse::OPTIONS[$resp->response_option][0] ?? 'Responded')
                .($resp->director_decision ? ' · '.(SpaNoticeResponse::DECISIONS[$resp->director_decision] ?? $resp->director_decision) : ''))
            : $s($first ? 'pending' : ($contravenes ? 'pending' : 'na'), $first ? 'Awaiting response' : ($contravenes ? 'After the 1st Serve' : 'No contravention'));
        // Strip "Option N: " — the step label already says what this is.
        $st['response']['message'] = preg_replace('/^Option \d+:\s*/', '', $st['response']['message']);

        $billed = (float) $bills->sum('amount');
        $inRevm = \App\Services\Billing\Modules\SpasBillMirror::inBillMaster($bills->pluck('id'));
        if ($bills->count() && $inRevm->count() === $bills->count()) {
            // Paid under KLAES REV-M; shown for information, it holds nothing back.
            $revmPaid = (float) $inRevm->sum('amount_paid');
            $st['payment'] = $s($inRevm->every(fn ($b) => $b->status === 'paid') ? 'done' : 'progress',
                'In KLAES REV-M · ' . ($revmPaid > 0 ? 'Paid '.$ngn($revmPaid).' of '.$ngn($billed) : 'Unpaid '.$ngn($billed)));
        } elseif (! $bills->count()) {
            $st['payment'] = $s($contravenes ? 'pending' : 'na', $contravenes ? 'No bill yet' : 'No bill');
        } elseif ($bills->every(fn ($b) => $b->status === 'paid')) {
            $st['payment'] = $s('done', 'Paid '.$ngn($paid ?: $billed));
        } elseif ($paid > 0) {
            $st['payment'] = $s('progress', 'Part paid '.$ngn($paid).' of '.$ngn($billed));
        } else {
            $st['payment'] = $s('pending', 'Unpaid '.$ngn($billed));
        }

        if (! empty($app->new_file_number)) {
            $st['newfile'] = $s('done', $app->new_file_number
                . ($app->new_file_route === SpaNewFileService::NEW_CONVERSION ? ' · New Conversion' : ' · Change of Purpose'));
        } elseif (($app->new_file_route ?? null) === SpaNewFileService::COP) {
            $st['newfile'] = $s('progress', 'Change of Purpose ready to commission');
        } elseif (($app->new_file_route ?? null) === SpaNewFileService::NEW_CONVERSION) {
            $st['newfile'] = $s('progress', 'Awaiting New Conversion file no.');
        } else {
            $settled = $bills->count() && $bills->every(fn ($b) => $b->status === 'paid' || $inRevm->has((int) $b->id));
            $st['newfile'] = $s($contravenes ? 'pending' : 'na', ! $contravenes ? 'No contravention'
                : ($settled ? 'Send to Change of Purpose (Bills)' : 'After the bill is settled'));
        }

        $st['memo'] = $memo
            ? $s('done', $memo->memo_no.($memo->forwarded_to ? ' → '.$memo->forwarded_to : ''))
            : $s($contravenes ? 'pending' : 'na', $contravenes ? 'Not raised' : 'No contravention');

        if (! $memo) {
            $st['decision'] = $s($contravenes ? 'pending' : 'na', $contravenes ? 'Needs a memo' : 'No contravention');
        } elseif ($memo->commissioner_decision === 'approved') {
            $st['decision'] = $s('done', 'Approved '.$d($memo->decided_at));
        } elseif ($memo->commissioner_decision === 'rejected') {
            $st['decision'] = $s('rejected', 'Rejected '.$d($memo->decided_at));
        } else {
            $st['decision'] = $s('pending', 'Awaiting Commissioner');
        }

        $st['certificate'] = $cert
            ? $s('done', $cert->cert_number.' · '.$d($cert->issue_date))
            : $s(
                $contravenes && optional($memo)->commissioner_decision !== 'rejected' ? 'pending' : 'na',
                optional($memo)->commissioner_decision === 'approved' ? 'Ready to issue'
                    : (optional($memo)->commissioner_decision === 'rejected' ? 'Memo rejected' : ($contravenes ? 'Needs an approved memo' : 'No contravention'))
            );

        // Done always wins: a stage marked not applicable but actually carried
        // out (a memo raised without a contravention) shows as done above.
        return $st;
    }
}
