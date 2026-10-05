<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralBill;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralIndexCard;
use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\Cadastral\CadastralReport;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Cadastral\CadastralSurveyor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Every figure the four unit dashboards draw.
 *
 * It lives in one class because a dashboard's job is to be trusted: the same
 * definition of "awaiting registration" has to hold on the Registry dashboard,
 * the module landing and any report built later. A count re-derived in three
 * controllers becomes three different numbers.
 *
 * All aggregation happens in SQL. The tables are empty today but will not stay
 * that way, and a dashboard that pulls rows into PHP to count them is a
 * dashboard that stops loading in a year.
 *
 * sqlsrv notes: date bucketing groups by the CAST expression via groupByRaw
 * (the builder cannot alias into GROUP BY here), and every series is
 * back-filled in PHP so a day with no rows draws a zero rather than
 * disappearing and compressing the axis.
 */
class DashboardMetrics
{
    /** Days of history the trend charts show. */
    public const TREND_DAYS = 14;

    /*
    | The shared definitions. The sidebar badges, the module dashboard and
    | Analytics all read these, so "open" means the same thing on all three.
    |
    | 'Queued' sits beside 'Received' because Phase 2 renames the intake states
    | (Queued -> In Progress -> Correspondence Done); listing both keeps the
    | count right on either side of that change.
    */
    public const QUEUED_RECEIPT_STATUSES  = ['Received', 'Queued'];
    public const CLOSED_REPORT_STATUSES   = ['Dispatched', 'Rejected'];
    public const ACTIVE_JOB_STATUSES      = ['Issued', 'In Field', 'Submitted'];
    public const COMPLETED_CHART_STATUSES = ['Charted', 'Checked', 'Approved'];
    public const RAISED_BILL_STATUSES     = ['Issued', 'Paid'];

    /** Seconds the sidebar badges are cached; the sidebar renders on every page. */
    public const BADGE_TTL = 60;

    /* ===================== MODULE DASHBOARD ===================== */

    /**
     * The single module dashboard: six tiles, the latest open report's stage
     * chain, and a merged activity feed.
     */
    public function overview(): array
    {
        return [
            'tiles' => [
                'intake'     => $this->queuedReceipts()->count(),
                'reports'    => $this->openReports()->count(),
                'jobs'       => CadastralSurveyJob::whereIn('status', self::ACTIVE_JOB_STATUSES)->count(),
                'cards'      => CadastralIndexCard::count(),
                // grand_total survives the Phase 7 bill reshape as the bill total.
                'feesYtd'    => (float) CadastralBill::whereIn('status', self::RAISED_BILL_STATUSES)
                    ->where('issued_at', '>=', now()->startOfYear())
                    ->sum('grand_total'),
                'duplicates' => CadastralFileReceipt::where('duplicate_flag', true)->count(),
            ],
            'latestReport' => $this->latestOpenReport(),
            'activity'     => $this->recentActivity(),
        ];
    }

    /**
     * Pending counts for the sidebar. Cached, because the sidebar renders on
     * every page in KLAES and these are the module's two busiest tables.
     *
     * @return array{intake: int, reports: int}
     */
    public function sidebarBadges(): array
    {
        return Cache::remember('cadastral-module:sidebar-badges', self::BADGE_TTL, fn () => [
            'intake'  => $this->queuedReceipts()->count(),
            'reports' => $this->openReports()->count(),
        ]);
    }

    /**
     * The newest report still in flight, with its stage chain resolved for the
     * vertical tracker. Null when nothing is open.
     */
    public function latestOpenReport(): ?array
    {
        $report = $this->openReports()
            ->with('steps')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $report) {
            return null;
        }

        $stages = $report->steps->map(fn ($s) => [
            'no'    => $s->step_no,
            'name'  => $s->step_name,
            'state' => in_array($s->status, ['done', 'active', 'returned', 'skipped'], true) ? $s->status : 'waiting',
            'actor' => $s->actor_name,
            'at'    => $s->completed_at ?? $s->started_at,
        ])->values();

        // Steps are seeded with the report, so this only fires on a report
        // whose seeding failed: draw the configured chain rather than nothing.
        if ($stages->isEmpty()) {
            $chain = config("cadastral_module.stage_chains.{$report->report_type}", []);

            $stages = collect($chain)->values()->map(fn ($stage, $i) => [
                'no'    => $i + 1,
                'name'  => $stage['name'],
                'state' => $stage['key'] === $report->current_step_key ? 'active' : 'waiting',
                'actor' => null,
                'at'    => null,
            ]);
        }

        return [
            'report' => $report,
            'stages' => $stages->all(),
            'done'   => $stages->where('state', 'done')->count(),
            'total'  => $stages->count(),
        ];
    }

    /**
     * Latest receipts, index-card commissions, survey jobs issued and duplicate
     * flags, merged newest first. Each source is capped in SQL before merging.
     *
     * @return array<int, array{kind: string, id: int, title: string, detail: string, at: \Carbon\CarbonInterface}>
     */
    public function recentActivity(int $limit = 10): array
    {
        $events = collect();

        CadastralFileReceipt::whereNotNull('received_at')
            ->orderByDesc('received_at')->limit($limit)
            ->get(['id', 'receipt_ref', 'file_number', 'source_registry', 'received_at'])
            ->each(fn ($r) => $events->push([
                'kind'   => 'receipt',
                'id'     => $r->id,
                'title'  => (string) $r->file_number,
                'detail' => trim(($r->source_registry ? $r->source_registry . ' · ' : '') . $r->receipt_ref, ' ·'),
                'at'     => $r->received_at,
            ]));

        CadastralIndexCard::whereNotNull('commissioned_at')
            ->orderByDesc('commissioned_at')->limit($limit)
            ->get(['id', 'card_ref', 'file_number', 'commissioned_at'])
            ->each(fn ($c) => $events->push([
                'kind'   => 'card',
                'id'     => $c->id,
                'title'  => (string) $c->file_number,
                'detail' => (string) $c->card_ref,
                'at'     => $c->commissioned_at,
            ]));

        CadastralSurveyJob::whereNotNull('issued_at')
            ->orderByDesc('issued_at')->limit($limit)
            ->get(['id', 'job_number', 'file_number', 'surveyor_name', 'firm_name', 'issued_at'])
            ->each(fn ($j) => $events->push([
                'kind'   => 'job',
                'id'     => $j->id,
                'title'  => (string) $j->job_number,
                'detail' => trim($j->file_number . ($j->surveyor_name || $j->firm_name ? ' · ' . ($j->surveyor_name ?: $j->firm_name) : '')),
                'at'     => $j->issued_at,
            ]));

        CadastralFileReceipt::where('duplicate_flag', true)->whereNotNull('received_at')
            ->orderByDesc('received_at')->limit($limit)
            ->get(['id', 'file_number', 'duplicate_note', 'received_at'])
            ->each(fn ($r) => $events->push([
                'kind'   => 'duplicate',
                'id'     => $r->id,
                'title'  => (string) $r->file_number,
                'detail' => (string) ($r->duplicate_note ?: 'Matched an existing record on arrival.'),
                'at'     => $r->received_at,
            ]));

        return $events->sortByDesc(fn ($e) => $e['at']->getTimestamp())->take($limit)->values()->all();
    }

    /* ===================== ANALYTICS ===================== */

    /**
     * The management view: four headline cards and the brief's metrics table,
     * each figure for the chosen period beside its all-time value.
     *
     * Periods filter on the date the event happened (received, opened,
     * commissioned, issued, charted). whereDate is used throughout so DATE and
     * DATETIME columns compare the same way on sqlsrv.
     */
    public function analytics(Carbon $from, Carbon $to): array
    {
        $in = fn ($q, string $column) => $q->whereDate($column, '>=', $from->toDateString())
            ->whereDate($column, '<=', $to->toDateString());

        $bills = fn () => CadastralBill::whereIn('status', self::RAISED_BILL_STATUSES);

        $cards = [
            'reports'    => $in(CadastralReport::query(), 'created_at')->count(),
            'dispatched' => $in(CadastralReport::where('status', 'Dispatched'), 'dispatched_at')->count(),
            // A duplicate caught at reception is a duplicate blocked: the file
            // cannot proceed until the flag is investigated.
            'duplicates' => $in(CadastralFileReceipt::where('duplicate_flag', true), 'received_at')->count(),
            'bills'      => $in($bills(), 'issued_at')->count(),
            'billsValue' => (float) $in($bills(), 'issued_at')->sum('grand_total'),
        ];

        $openOfType = fn (string $type) => fn () => $this->openReports()->where('report_type', $type);

        $definitions = [
            ['Intake queued', fn () => $this->queuedReceipts(), 'received_at',
                'Received in the period and still waiting to be registered.'],
            ['Correspondence', fn () => CadastralFileReceipt::where('correspondence_status', 'matched'), 'received_at',
                'Receipts whose correspondence file has been commissioned.'],
            ['Verification open', $openOfType(CadastralReport::TYPE_VERIFICATION), 'created_at',
                'Opened in the period, not yet dispatched or rejected.'],
            ['Customary open', $openOfType(CadastralReport::TYPE_CUSTOMARY), 'created_at',
                'Opened in the period, not yet dispatched or rejected.'],
            ['Statutory open', $openOfType(CadastralReport::TYPE_STATUTORY), 'created_at',
                'Opened in the period, not yet dispatched or rejected.'],
            ['Index cards', fn () => CadastralIndexCard::query(), 'commissioned_at',
                'Cards commissioned.'],
            ['Survey jobs active', fn () => CadastralSurveyJob::whereIn('status', self::ACTIVE_JOB_STATUSES), 'issued_at',
                'Issued, in the field or submitted — not yet accepted.'],
            ['Charts completed', fn () => CadastralChart::current()->whereIn('status', self::COMPLETED_CHART_STATUSES), 'charted_on',
                'Current chart versions charted, checked or approved.'],
            ['Fee bills', $bills, 'issued_at',
                'Bills issued or paid; cancelled and draft bills excluded.'],
        ];

        $rows = [];
        foreach ($definitions as [$label, $query, $column, $definition]) {
            $rows[] = [
                'label'      => $label,
                'period'     => $in($query(), $column)->count(),
                'total'      => $query()->count(),
                'definition' => $definition,
            ];
        }

        return ['cards' => $cards, 'rows' => $rows];
    }

    private function queuedReceipts()
    {
        return CadastralFileReceipt::whereIn('status', self::QUEUED_RECEIPT_STATUSES);
    }

    private function openReports()
    {
        return CadastralReport::whereNotIn('status', self::CLOSED_REPORT_STATUSES);
    }

    /* ===================== 4.1 REGISTRY ===================== */

    public function registry(): array
    {
        $awaiting = CadastralFileReceipt::where('status', 'Received')->count();

        return [
            // The one number the unit leads with, with a week-on-week delta.
            'hero' => [
                'label' => 'Awaiting registration',
                'value' => $awaiting,
                'delta' => $this->weekOnWeek(CadastralFileReceipt::class, 'received_at'),
                'hint'  => $awaiting === 0
                    ? 'Nothing is queued.'
                    : 'Files logged in but not yet registered.',
            ],

            'tiles' => [
                [
                    'label' => 'Received this week',
                    'value' => CadastralFileReceipt::where('received_at', '>=', now()->subDays(7))->count(),
                    'spark' => $this->dailySeries(CadastralFileReceipt::class, 'received_at'),
                ],
                [
                    'label' => 'Registered',
                    'value' => CadastralFileReceipt::where('status', 'Registered')->count(),
                    'spark' => $this->dailySeries(CadastralFileReceipt::class, 'registered_at'),
                ],
                [
                    'label' => 'Flagged duplicates',
                    'value' => CadastralFileReceipt::where('duplicate_flag', true)->count(),
                    'tone'  => 'critical',
                ],
                [
                    'label' => 'Correspondence pending',
                    'value' => CadastralFileReceipt::where('correspondence_status', 'pending')->count(),
                    'tone'  => 'warning',
                ],
            ],

            'intake'    => $this->dailySeries(CadastralFileReceipt::class, 'received_at'),
            'bySource'  => $this->countBy(CadastralFileReceipt::class, 'source_registry'),
            'byPurpose' => $this->countBy(CadastralFileReceipt::class, 'purpose'),

            // Part-to-whole: where the logged files currently sit.
            'pipeline' => $this->parts(
                $this->countBy(CadastralFileReceipt::class, 'status'),
                ['Received', 'Registered', 'Archived', 'Returned', 'Rejected']
            ),

            'fileClass' => $this->parts(
                $this->countBy(CadastralFileReceipt::class, 'file_class'),
                ['direct', 'conversion'],
                ['direct' => 'Direct', 'conversion' => 'Conversion']
            ),

            'queues' => [
                'flagged' => CadastralFileReceipt::where('duplicate_flag', true)
                    ->orderByDesc('id')->limit(6)->get(),
                'oldest' => CadastralFileReceipt::where('status', 'Received')
                    ->orderBy('received_at')->limit(6)->get(),
                'pendingCorrespondence' => CadastralFileReceipt::where('correspondence_status', 'pending')
                    ->orderByDesc('id')->limit(6)->get(),
            ],
        ];
    }

    /* ===================== 4.3 INFORMATION ===================== */

    public function information(): array
    {
        $pending = CadastralChart::current()
            ->where('charting_required', true)
            ->where('status', 'Draft')
            ->count();

        return [
            'hero' => [
                'label' => 'Awaiting charting',
                'value' => $pending,
                'delta' => $this->weekOnWeek(CadastralChart::class, 'created_at'),
                'hint'  => 'Direct files charted as Draft. Conversion files are excluded — they are not charted.',
            ],

            'tiles' => [
                [
                    'label' => 'Current charts',
                    'value' => CadastralChart::current()->count(),
                    'spark' => $this->dailySeries(CadastralChart::class, 'created_at'),
                ],
                [
                    'label' => 'Index cards',
                    'value' => CadastralIndexCard::count(),
                    'spark' => $this->dailySeries(CadastralIndexCard::class, 'commissioned_at'),
                ],
                [
                    'label' => 'Conflicts flagged',
                    'value' => CadastralChart::current()->whereIn('conflict_status', ['suspected', 'confirmed'])->count(),
                    'tone'  => 'critical',
                ],
                [
                    'label' => 'Jobs in the field',
                    'value' => CadastralSurveyJob::whereIn('status', ['Issued', 'In Field'])->count(),
                    'tone'  => 'warning',
                ],
            ],

            'charted'     => $this->dailySeries(CadastralChart::class, 'created_at'),
            'chartStatus' => $this->countBy(CadastralChart::class, 'status', fn ($q) => $q->where('is_current', true)),

            'chartCategory' => $this->parts(
                $this->countBy(CadastralChart::class, 'chart_category', fn ($q) => $q->where('is_current', true)),
                ['direct', 'conversion'],
                ['direct' => 'Direct (charted)', 'conversion' => 'Conversion (not charted)']
            ),

            'cardStatus' => $this->countBy(CadastralIndexCard::class, 'file_status'),
            'jobStatus'  => $this->countBy(CadastralSurveyJob::class, 'status'),

            // How much of the beacon work is actually keyed — the number that
            // decides whether geometric conflict detection can work at all.
            'coverage' => $this->chartCoverage(),

            'queues' => [
                'unCharted' => CadastralChart::current()
                    ->where('charting_required', true)->where('status', 'Draft')
                    ->orderBy('created_at')->limit(6)->get(),
                'conflicts' => CadastralChart::current()
                    ->whereIn('conflict_status', ['suspected', 'confirmed'])
                    ->orderByDesc('id')->limit(6)->get(),
                'licences' => CadastralSurveyor::where('is_active', true)
                    ->whereNotNull('licence_expires_on')
                    ->whereDate('licence_expires_on', '<=', now()->addDays(60))
                    ->orderBy('licence_expires_on')->limit(6)->get(),
            ],
        ];
    }

    /* ===================== 4.2 REPORT ===================== */

    public function reports(array $myPosts = []): array
    {
        $inFlight = CadastralReport::whereNotIn('status', ['Dispatched', 'Rejected'])->count();

        $overdue = CadastralReport::whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['Dispatched', 'Rejected'])
            ->count();

        return [
            'hero' => [
                'label' => 'Reports in flight',
                'value' => $inFlight,
                'delta' => $this->weekOnWeek(CadastralReport::class, 'created_at'),
                'hint'  => $overdue > 0
                    ? "{$overdue} past the due date."
                    : 'None past its due date.',
                'tone'  => $overdue > 0 ? 'critical' : null,
            ],

            'tiles' => [
                [
                    'label' => 'Opened this week',
                    'value' => CadastralReport::where('created_at', '>=', now()->subDays(7))->count(),
                    'spark' => $this->dailySeries(CadastralReport::class, 'created_at'),
                ],
                [
                    'label' => 'Approved',
                    'value' => CadastralReport::where('status', 'Approved')->count(),
                ],
                [
                    'label' => 'Dispatched',
                    'value' => CadastralReport::where('status', 'Dispatched')->count(),
                ],
                [
                    'label' => 'Overdue',
                    'value' => $overdue,
                    'tone'  => 'critical',
                ],
            ],

            'opened' => $this->dailySeries(CadastralReport::class, 'created_at'),

            // The three streams are the subject here, so this is the one place
            // categorical colour is right.
            'byType' => $this->parts(
                $this->countBy(CadastralReport::class, 'report_type'),
                ['verification', 'customary', 'statutory'],
                CadastralReport::TYPES
            ),

            // Where the in-flight work is stuck. One series -> one colour; the
            // axis carries stage identity.
            'byStage' => $this->stageLoad(),

            'byDesk'  => $this->deskLoad(),
            'ageing'  => $this->ageingBuckets(),

            'queues' => [
                'mine' => $myPosts === []
                    ? collect()
                    : CadastralReport::whereIn('assigned_post', $myPosts)
                        ->whereNotIn('status', ['Dispatched', 'Rejected'])
                        ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date')
                        ->limit(8)->get(),
                'overdue' => CadastralReport::whereNotNull('due_date')
                    ->whereDate('due_date', '<', now())
                    ->whereNotIn('status', ['Dispatched', 'Rejected'])
                    ->orderBy('due_date')->limit(6)->get(),
                'returned' => CadastralReport::where('status', 'Returned')
                    ->orderByDesc('id')->limit(6)->get(),
            ],
        ];
    }

    /* ===================== 4.4 PLAN AND DESCRIPTION ===================== */

    public function planDescription(): array
    {
        $unbilled = CadastralPlanDescription::whereDoesntHave(
            'bills', fn ($q) => $q->where('status', 'Issued')
        )->count();

        return [
            'hero' => [
                'label' => 'Records not yet billed',
                'value' => $unbilled,
                'delta' => $this->weekOnWeek(CadastralPlanDescription::class, 'created_at'),
                'hint'  => 'Area and pillars recorded, no bill issued.',
            ],

            'tiles' => [
                [
                    'label' => 'Bills issued',
                    'value' => CadastralBill::where('status', 'Issued')->count(),
                    'spark' => $this->dailySeries(CadastralBill::class, 'issued_at'),
                ],
                [
                    'label' => 'Value billed',
                    'value' => (float) CadastralBill::where('status', 'Issued')->sum('grand_total'),
                    'money' => true,
                ],
                [
                    'label' => 'Paid',
                    'value' => (float) CadastralBill::where('status', 'Paid')->sum('grand_total'),
                    'money' => true,
                    'tone'  => 'good',
                ],
                [
                    'label' => 'Pillars recorded',
                    'value' => (int) \App\Models\Cadastral\CadastralPillar::count(),
                ],
            ],

            'billed'  => $this->dailySeries(CadastralBill::class, 'issued_at'),
            'byUse'   => $this->countBy(CadastralPlanDescription::class, 'land_use'),
            'byZone'  => $this->countBy(CadastralPlanDescription::class, 'location_zone'),

            // What the money is actually made of, summed across issued bills.
            'feeMix'  => $this->feeMix(),
            'areaMix' => $this->areaBands(),

            'queues' => [
                'unbilled' => CadastralPlanDescription::whereDoesntHave(
                        'bills', fn ($q) => $q->where('status', 'Issued')
                    )->orderByDesc('id')->limit(6)->get(),
                'noArea' => CadastralPlanDescription::whereNull('area_sqm')
                    ->orderByDesc('id')->limit(6)->get(),
                'failedChecks' => CadastralPlanDescription::where('validation_status', 'failed')
                    ->orderByDesc('id')->limit(6)->get(),
            ],
        ];
    }

    /* ===================== building blocks ===================== */

    /**
     * Daily counts for the trend window, zero-filled.
     *
     * Zero-filling matters: without it a quiet Sunday vanishes and the axis
     * silently compresses, which makes a flat week look busy.
     *
     * @return array<int, array{date: string, label: string, value: int, today: bool}>
     */
    public function dailySeries(string $model, string $column, int $days = self::TREND_DAYS): array
    {
        $from = now()->startOfDay()->subDays($days - 1);

        $rows = $model::query()
            ->whereNotNull($column)
            ->where($column, '>=', $from)
            ->selectRaw("CAST([{$column}] AS DATE) AS d, COUNT(*) AS n")
            ->groupByRaw("CAST([{$column}] AS DATE)")
            ->pluck('n', 'd');

        // sqlsrv hands the date back as 'Y-m-d' or a datetime string depending
        // on the driver build, so normalise before matching.
        $byDay = [];
        foreach ($rows as $day => $n) {
            $byDay[Carbon::parse($day)->toDateString()] = (int) $n;
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $key = $d->toDateString();

            $series[] = [
                'date'  => $key,
                'label' => $d->format('j M'),
                'short' => $d->format('j'),
                'value' => $byDay[$key] ?? 0,
                'today' => $d->isToday(),
            ];
        }

        return $series;
    }

    /**
     * Counts grouped by a column, biggest first, blanks dropped.
     *
     * @return Collection<int, array{key: string, label: string, value: int}>
     */
    public function countBy(string $model, string $column, ?callable $tap = null): Collection
    {
        $q = $model::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("[{$column}] AS k, COUNT(*) AS n")
            ->groupBy($column);

        if ($tap) {
            $tap($q);
        }

        return collect($q->get())
            ->map(fn ($r) => [
                'key'   => (string) $r->k,
                'label' => $this->humanise((string) $r->k),
                'value' => (int) $r->n,
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Part-to-whole segments in a FIXED order, so colour follows the category
     * and not its current rank — a filter that changes the counts must never
     * repaint the survivors.
     *
     * @return array{segments: array<int, array{key:string,label:string,value:int,percent:float}>, total: int}
     */
    public function parts(Collection $counts, array $order, array $labels = []): array
    {
        $byKey = $counts->keyBy('key');
        $total = (int) $counts->sum('value');

        $segments = [];
        foreach ($order as $key) {
            $value = (int) ($byKey[$key]['value'] ?? 0);

            $segments[] = [
                'key'     => $key,
                'label'   => $labels[$key] ?? $this->humanise($key),
                'value'   => $value,
                'percent' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
            ];
        }

        return ['segments' => $segments, 'total' => $total];
    }

    /** Week-on-week change, as a signed count plus a direction. */
    public function weekOnWeek(string $model, string $column): array
    {
        $thisWeek = $model::query()->whereNotNull($column)
            ->where($column, '>=', now()->subDays(7))->count();

        $lastWeek = $model::query()->whereNotNull($column)
            ->whereBetween($column, [now()->subDays(14), now()->subDays(7)])->count();

        $change = $thisWeek - $lastWeek;

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'change'    => $change,
            // "up" is not automatically good — the caller decides what it means.
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'percent'   => $lastWeek > 0 ? round(($change / $lastWeek) * 100) : null,
        ];
    }

    /** In-flight reports by the stage they are sitting on. */
    private function stageLoad(): Collection
    {
        $rows = CadastralReport::query()
            ->whereNotIn('status', ['Dispatched', 'Rejected'])
            ->whereNotNull('current_step_key')
            ->selectRaw('current_step_key AS k, COUNT(*) AS n')
            ->groupBy('current_step_key')
            ->pluck('n', 'k');

        // Ordered by the chain, not by size: a funnel read out of order is not
        // a funnel. Verification is the longest chain, so it defines the axis.
        $chain = config('cadastral_module.stage_chains.verification', []);

        return collect($chain)->map(fn ($stage) => [
            'key'   => $stage['key'],
            'label' => $stage['name'],
            'value' => (int) ($rows[$stage['key']] ?? 0),
        ]);
    }

    /** In-flight reports by the desk holding them. */
    private function deskLoad(): Collection
    {
        $rows = CadastralReport::query()
            ->whereNotIn('status', ['Dispatched', 'Rejected'])
            ->whereNotNull('assigned_post')
            ->selectRaw('assigned_post AS k, COUNT(*) AS n')
            ->groupBy('assigned_post')
            ->pluck('n', 'k');

        $posts = config('cadastral_module.posts', []);

        return collect($rows)
            ->map(fn ($n, $k) => [
                'key'   => $k,
                'label' => $posts[$k] ?? $k,
                'value' => (int) $n,
            ])
            ->sortByDesc('value')
            ->values();
    }

    /** How long in-flight reports have been open. */
    private function ageingBuckets(): Collection
    {
        $open = CadastralReport::whereNotIn('status', ['Dispatched', 'Rejected'])
            ->select(['id', 'created_at'])
            ->get();

        $buckets = ['0–7 days' => 0, '8–30 days' => 0, '31–90 days' => 0, 'Over 90 days' => 0];

        foreach ($open as $r) {
            $age = $r->created_at ? $r->created_at->diffInDays(now()) : 0;

            if ($age <= 7)       $buckets['0–7 days']++;
            elseif ($age <= 30)  $buckets['8–30 days']++;
            elseif ($age <= 90)  $buckets['31–90 days']++;
            else                 $buckets['Over 90 days']++;
        }

        return collect($buckets)->map(fn ($v, $k) => [
            'key' => $k, 'label' => $k, 'value' => $v,
        ])->values();
    }

    /**
     * What raised billing is made of: the official fee sheet's eight lines
     * (CadastralBill::LINES — investigation, beacons, area, delay, transport,
     * field work, office work, plan prints), summed from each line's own
     * {line}_amount column across Issued and Paid bills — the same bills Fees
     * YTD counts. Sheet order, fixed, so a line never moves.
     *
     * Bills issued before the fee sheet carry no per-line amounts and add
     * nothing here; before the fee-sheet columns exist every line is zero.
     */
    private function feeMix(): array
    {
        $keys = array_keys(CadastralBill::LINES);
        $b    = null;

        if (CadastralBill::feeSheetInstalled()) {
            $b = CadastralBill::whereIn('status', self::RAISED_BILL_STATUSES)
                ->selectRaw(implode(', ', array_map(fn ($k) => "SUM([{$k}_amount]) AS [{$k}]", $keys)))
                ->first();
        }

        $counts = collect($keys)->map(fn ($k) => [
            'key'   => $k,
            'label' => CadastralBill::LINES[$k],
            'value' => (int) round((float) ($b?->{$k} ?? 0)),
        ]);

        return $this->parts($counts, $keys, CadastralBill::LINES);
    }

    /** Parcels by size band — ordered, so it reads as a distribution. */
    private function areaBands(): Collection
    {
        $rows = CadastralPlanDescription::whereNotNull('area_sqm')
            ->select('area_sqm')->get();

        $bands = [
            'Under 500 sqm'   => 0,
            '500 – 1,000'     => 0,
            '1,000 – 5,000'   => 0,
            '5,000 – 1 ha'    => 0,
            'Over 1 hectare'  => 0,
        ];

        foreach ($rows as $r) {
            $a = (float) $r->area_sqm;

            if ($a < 500)        $bands['Under 500 sqm']++;
            elseif ($a < 1000)   $bands['500 – 1,000']++;
            elseif ($a < 5000)   $bands['1,000 – 5,000']++;
            elseif ($a < 10000)  $bands['5,000 – 1 ha']++;
            else                 $bands['Over 1 hectare']++;
        }

        return collect($bands)->map(fn ($v, $k) => [
            'key' => $k, 'label' => $k, 'value' => $v,
        ])->values();
    }

    /**
     * Beacon-ring coverage.
     *
     * Stated plainly because it is the honest limit on conflict detection:
     * a chart with no keyed ring cannot be compared geometrically with anything.
     */
    private function chartCoverage(): array
    {
        $total = CadastralChart::current()->where('charting_required', true)->count();

        $withRing = CadastralChart::current()
            ->where('charting_required', true)
            ->whereHas('coordinates')
            ->count();

        return [
            'total'    => $total,
            'with'     => $withRing,
            'without'  => max(0, $total - $withRing),
            'percent'  => $total > 0 ? round(($withRing / $total) * 100) : 0,
        ];
    }

    /** "semi_urban" -> "Semi urban", "RES" left alone. */
    private function humanise(string $value): string
    {
        if ($value === strtoupper($value) && strlen($value) <= 5) {
            return $value;
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
