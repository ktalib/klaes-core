<?php

namespace App\Http\Controllers\IndexingPay;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IndexingPay\EarningsService;
use App\Services\IndexingPay\IndexingPaySettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Indexing piece-rate pay screens (docs/plans/INDEXING_PIECE_RATE_PAYROLL_PLAN.md):
 * an indexer's own progress and earnings, and the supervisor sheet.
 */
class IndexingEarningsController extends Controller
{
    public function __construct(private EarningsService $earnings, private IndexingPaySettings $settings)
    {
    }

    /** May this user see everyone's earnings? */
    public static function canViewAll(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $roles = array_map(fn ($r) => mb_strtolower(trim((string) $r)), $user->assignedRoleNames());

        // Activity Monitoring holders are the supervisors: its Indexing Earnings tab reads this too.
        return $user->isSuperAdmin() || (bool) array_intersect(['indexing earnings', 'activity monitoring'], $roles);
    }

    /** Months the programme covers, as Y-m => label. */
    private function programmeMonths(): array
    {
        [$start, $end] = $this->earnings->programme();
        $months = [];
        for ($m = Carbon::parse($start)->startOfMonth(); $m->lte(Carbon::parse($end)); $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('F Y');
        }

        return $months;
    }

    private function defaultMonth(): string
    {
        $months = array_keys($this->programmeMonths());
        $now = now()->format('Y-m');
        if (in_array($now, $months, true)) {
            return $now;
        }

        return $now < ($months[0] ?? $now) ? ($months[0] ?? $now) : (end($months) ?: $now);
    }

    /**
     * Users with the header earnings chip: those who earn, i.e. hold the indexing role AND have
     * an eligible staff type (MDC, MDCM; Configurable Entries > Indexing Pay). Not admins.
     */
    public static function isMdcIndexer(?User $user): bool
    {
        return $user && app(EarningsService::class)->isEligible($user);
    }

    /**
     * May this user see the progress screens? Indexers see their own; admins (Super Admin or
     * the "Indexing Earnings" role) see them too, as a preview, and can open any indexer's.
     */
    public static function canSeeProgress(?User $user): bool
    {
        return app(EarningsService::class)->isEligible($user) || self::canViewAll($user);
    }

    /**
     * Whose figures to show: the signed-in user, or (admins only) ?user=ID.
     * Returns [User target, bool viewingSomeoneElse].
     */
    private function target(Request $request): array
    {
        $me = Auth::user();
        $id = (int) $request->query('user');
        if ($id > 0 && $id !== (int) $me->id && self::canViewAll($me)) {
            $other = User::find($id);
            if ($other) {
                return [$other, true];
            }
        }

        return [$me, false];
    }

    /** Eligible users as id => name, for the admin's "View indexer" picker. */
    private function indexerOptions(): array
    {
        $ids = $this->earnings->eligibleUserIds();

        return User::whereIn('id', $ids ?: [0])->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? ('User ' . $u->id))])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    /** My Indexing Earnings page (admins: any indexer's, via ?user=). */
    public function me(Request $request)
    {
        $me = Auth::user();
        $canViewAll = self::canViewAll($me);
        [$target, $viewingOther] = $this->target($request);
        $eligible = $this->earnings->isEligible($target);
        $canSee = $eligible || $canViewAll;

        $months = $this->programmeMonths();
        $month = array_key_exists($request->query('month'), $months) ? $request->query('month') : $this->defaultMonth();
        $summary = $canSee ? $this->earnings->summary($target, $month) : null;

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? $request->query('date') : now()->toDateString();
        $dayItems = $canSee ? $this->earnings->collect([$target->id], $date, $date) : [];
        $targetName = trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')) ?: ($target->email ?? '');

        return view('indexing_pay.me', [
            'PageTitle' => $viewingOther ? 'Indexing Earnings — ' . $targetName : 'My Indexing Earnings',
            'PageDescription' => 'Indexing progress and earnings',
            'eligible' => $eligible,
            'canSee' => $canSee,
            'summary' => $summary,
            'months' => $months,
            'month' => $month,
            'date' => $date,
            'dayItems' => $dayItems,
            'dayTotals' => EarningsService::totals($dayItems),
            'canViewAll' => $canViewAll,
            'modules' => $this->settings->current()['eligible_modules'],
            'target' => $target,
            'targetName' => $targetName,
            'viewingOther' => $viewingOther,
            'indexers' => $canViewAll ? $this->indexerOptions() : [],
        ]);
    }

    /** JSON for the Create File Indexing strip, the header chip and the profile card. */
    public function summary(Request $request)
    {
        [$target, $viewingOther] = $this->target($request);
        $eligible = $this->earnings->isEligible($target);
        if (! $eligible && ! self::canViewAll(Auth::user())) {
            return response()->json(['eligible' => false]);
        }

        $summary = $this->earnings->summary($target, $request->query('month'), $request->boolean('fresh'));
        unset($summary['today_items']);
        // An admin looking at the screens without being on indexing pay sees them as a preview.
        $summary['preview'] = ! $eligible;
        $summary['viewing_other'] = $viewingOther;

        return response()->json($summary);
    }

    /** One day of items (calendar click); admins may pass ?user=. */
    public function day(Request $request)
    {
        [$target] = $this->target($request);
        $date = (string) $request->query('date');
        $allowed = $this->earnings->isEligible($target) || self::canViewAll(Auth::user());
        if (! $allowed || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['items' => [], 'totals' => EarningsService::totals([])]);
        }
        $items = $this->earnings->collect([$target->id], $date, $date);

        return response()->json([
            'date' => $date,
            'label' => Carbon::parse($date)->format('l, j F Y'),
            'items' => $items,
            'totals' => EarningsService::totals($items),
        ]);
    }

    private function sheetRange(Request $request): array
    {
        $month = (string) $request->query('month', $this->defaultMonth());
        $from = (string) $request->query('from');
        $to = (string) $request->query('to');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $start = preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::parse($month . '-01') : now()->startOfMonth();
            $from = $start->toDateString();
            $to = $start->copy()->endOfMonth()->toDateString();
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, min($to, now()->toDateString()), $month];
    }

    /** Supervisor sheet: every eligible user for a month or date range. */
    public function sheet(Request $request)
    {
        abort_unless(self::canViewAll(Auth::user()), 403, 'The Indexing Earnings sheet needs the "Indexing Earnings" role.');
        [$from, $to, $month] = $this->sheetRange($request);
        $sheet = $this->earnings->sheet($from, $to);

        $search = mb_strtolower(trim((string) $request->query('q')));
        if ($search !== '') {
            $sheet['rows'] = array_values(array_filter($sheet['rows'], fn ($r) => str_contains(mb_strtolower($r['name']), $search)));
        }

        return view('indexing_pay.sheet', [
            'PageTitle' => 'Indexing Earnings',
            'PageDescription' => 'Piece-rate earnings for every indexer',
            'sheet' => $sheet,
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'months' => $this->programmeMonths(),
            'search' => $request->query('q'),
            'card' => $this->settings->current(),
        ]);
    }

    /** The sheet as JSON, for the Indexing Earnings tab on Activity Monitoring. */
    public function sheetData(Request $request)
    {
        abort_unless(self::canViewAll(Auth::user()), 403);
        [$from, $to] = $this->sheetRange($request);
        $sheet = $this->earnings->sheet($from, $to);
        [$pStart, $pEnd] = $this->earnings->programme();

        foreach ($sheet['rows'] as &$row) {
            $row['progress_url'] = route('indexing-earnings.me', ['user' => $row['user_id']]);
        }
        unset($row);

        return response()->json($sheet + [
            'from' => $from,
            'to' => $to,
            'programme' => ['start' => $pStart, 'end' => $pEnd],
            'sheet_url' => route('indexing-earnings.sheet', ['from' => $from, 'to' => $to]),
            'export_url' => route('indexing-earnings.sheet.export', ['from' => $from, 'to' => $to]),
        ]);
    }

    /** One indexer's items for the sheet's range (supervisor). */
    public function userDetail(Request $request, int $user)
    {
        abort_unless(self::canViewAll(Auth::user()), 403);
        [$from, $to] = $this->sheetRange($request);
        $items = $this->earnings->collect([$user], $from, $to);

        return response()->json(['items' => $items, 'totals' => EarningsService::totals($items)]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless(self::canViewAll(Auth::user()), 403);
        [$from, $to] = $this->sheetRange($request);
        $sheet = $this->earnings->sheet($from, $to);

        return response()->streamDownload(function () use ($sheet) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['S/N', 'Name', 'Days worked', 'Regular', 'SLTR', 'KANGIS Land', 'KANGIS', 'Files', 'Transactions', 'Ground Rent', 'Bill Balance', 'Base (N)', 'Records (N)', 'Total (N)']);
            foreach ($sheet['rows'] as $i => $r) {
                $t = $r['totals'];
                fputcsv($out, [
                    $i + 1, $r['name'], $r['days_worked'],
                    $t['by_type']['regular']['count'], $t['by_type']['sltr']['count'], $t['by_type']['kangis_land']['count'], $t['by_type']['kangis']['count'],
                    $t['files'], $t['by_record']['trans']['count'], $t['by_record']['gr']['count'], $t['by_record']['bb']['count'],
                    number_format($t['base_amount'], 2, '.', ''), number_format($t['records_amount'], 2, '.', ''), number_format($t['amount'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, "indexing-earnings_{$from}_to_{$to}.csv", ['Content-Type' => 'text/csv']);
    }
}
