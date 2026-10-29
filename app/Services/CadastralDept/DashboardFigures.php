<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralBill;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use App\Services\Cadastral\DashboardMetrics;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Cadastral dashboard's figures from the workflow's own files (user, 2026-10-29): the
 * dashboard at /cadastral-module/dashboard counted the old module's receipts, so it showed one
 * record while 54 files were in the department. Used by that dashboard and the workflow index.
 * Each figure is read on its own, so one missing table cannot blank the page. READ ONLY.
 */
class DashboardFigures
{
    public function __construct(private WorkflowDefinition $def)
    {
    }

    /**
     * The cards, in order. No Duplicates card for now (user, 2026-10-29).
     *
     * @return array<int, array{label:string, value:string, hint:string, icon:string, fa:string, url:string}>
     */
    public function kpis(): array
    {
        if (!CaseWorkflow::installed()) {
            return [];
        }
        $byDesk = $this->read(fn () => CadastralCase::query()->open()
            ->selectRaw('current_desk, COUNT(*) AS n')->groupBy('current_desk')->pluck('n', 'current_desk')->all(), []);
        $external = array_keys(array_filter($this->def->desks(), fn ($d) => empty($d['internal'])));

        return [
            $this->card('Open files', array_sum($byDesk), 'In the department now', 'folder-open', 'fa-folder-open',
                route('cadastral-dept.central-admin.index')),
            $this->card('Registry intake', (int) ($byDesk['registry'] ?? 0), 'Registered, not yet with D Cad', 'inbox', 'fa-inbox',
                route('cadastral-dept.desks.show', 'registry')),
            $this->card('Waiting over 7 days', $this->read(fn () => CadastralCase::query()->open()->where('updated_at', '<', now()->subDays(7))->count()),
                'No movement in a week', 'clock', 'fa-clock', route('cadastral-dept.central-admin.index', ['stuck' => 1])),
            $this->card('With other departments', array_sum(array_intersect_key($byDesk, array_flip($external))),
                'Survey, Land, PS, Planning', 'send', 'fa-paper-plane', route('cadastral-dept.central-admin.index')),
            $this->card('Index cards', $this->read(fn () => DB::connection('sqlsrv')->table('cadastral_property_index_cards')->count()),
                'Property Index Cards filed', 'id-card', 'fa-id-card', route('cadastral-dept.pic.index')),
            [
                'label' => 'Fees YTD',
                'value' => '₦' . number_format((float) $this->read(fn () => CadastralBill::query()
                    ->whereIn('status', DashboardMetrics::RAISED_BILL_STATUSES)
                    ->where('issued_at', '>=', now()->startOfYear())->sum('grand_total')), 2),
                'hint' => 'Bills issued since 1 January', 'icon' => 'banknote', 'fa' => 'fa-naira-sign',
                'url' => route('cadastral-dept.bills.index'),
            ],
        ];
    }

    /** The newest files registered. */
    public function latestCases(int $limit = 6): Collection
    {
        return CaseWorkflow::installed()
            ? $this->read(fn () => CadastralCase::query()->orderByDesc('id')->limit($limit)->get(), collect())
            : collect();
    }

    /**
     * The newest file movements, newest first: what happened, to which file, by whom, when.
     *
     * @return Collection<int, array{case_id:int, file_number:?string, text:string, detail:string, at:?\Carbon\Carbon}>
     */
    public function recentActivity(int $limit = 8): Collection
    {
        if (!CaseWorkflow::installed()) {
            return collect();
        }

        return $this->read(function () use ($limit) {
            $moves = CadastralCaseMovement::query()->with('actor')->orderByDesc('id')->limit($limit)->get();
            $cases = CadastralCase::query()->whereIn('id', $moves->pluck('cadastral_case_id')->unique())->get()->keyBy('id');

            return $moves->map(function ($m) use ($cases) {
                $case = $cases[$m->cadastral_case_id] ?? null;
                $to = $m->to_desk ? $this->def->deskShort($m->to_desk) : null;
                $text = match (true) {
                    $m->from_step === null => 'Registered',
                    $m->to_step === null => 'Closed',
                    $m->from_desk === $m->to_desk => 'Updated at ' . $to,
                    default => 'Sent to ' . $to,
                };
                $by = $m->actor ? trim((string) ($m->actor->name ?? trim(($m->actor->first_name ?? '') . ' ' . ($m->actor->surname ?? '')))) : '';

                return [
                    'case_id' => (int) $m->cadastral_case_id,
                    'file_number' => $case?->file_number,
                    'text' => $text,
                    'detail' => trim(($case?->case_ref ?? '') . ($by !== '' ? ' · ' . $by : '')),
                    'at' => $m->acted_at ?? $m->created_at,
                ];
            });
        }, collect());
    }

    private function card(string $label, int $value, string $hint, string $icon, string $fa, string $url): array
    {
        return ['label' => $label, 'value' => number_format($value), 'hint' => $hint, 'icon' => $icon, 'fa' => $fa, 'url' => $url];
    }

    private function read(callable $q, $fallback = 0)
    {
        try {
            return $q();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}
