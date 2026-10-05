<?php

namespace App\Services;

use App\Models\BillBalance;
use App\Models\ConsentApplication;
use App\Models\SltrRecommendation;
use App\Models\ValuationReport;
use Carbon\Carbon;
use App\Support\ConsolidatedReportFormatting as ReportFormat;
use Illuminate\Support\Facades\DB;

class ConsolidatedReportService
{
    public function generate(string $report, array $filters): array
    {
        switch ($report) {
            case 'bill-balance':
                $table = (new BillBalance)->getTable();
                $query = BillBalance::query()->leftJoin('billing as b', 'b.id', '=', "$table.billing_id")
                    ->select("$table.*", 'b.bill_balance_reciept as receipt');
                $this->filter($query, $filters, "$table.created_at", ["$table.file_number", "$table.applicant_name", "$table.reference", 'b.bill_balance_reciept']);
                if (($filters['status'] ?? '') === 'paid') {
                    $query->where('b.Payment_Status', 'Paid');
                } elseif (($filters['status'] ?? '') === 'pending') {
                    $query->where(fn ($q) => $q->where('b.Payment_Status', '!=', 'Paid')->orWhereNull('b.Payment_Status'));
                }
                $labels = ['reference' => 'Bill Ref ID', 'receipt' => 'Receipt No', 'file_number' => 'File Number', 'applicant_name' => 'Applicant', 'location' => 'Location', 'amount' => 'Amount (NGN)', 'date' => 'Date Created'];
                $rows = $query->orderBy("$table.created_at")->orderBy("$table.ID")->get()->map(fn ($r) => [
                    'reference' => $r->reference, 'receipt' => $r->receipt, 'file_number' => $r->file_number, 'applicant_name' => $r->applicant_name,
                    'location' => ReportFormat::location($r->loc_district ?: $r->district, $r->loc_lga, $r->loc_state, $r->location_station, $r->loc_plot_number), 'amount' => $r->amount,
                    'date' => $this->date($r->created_at),
                ]);
                break;
            case 'valuation':
                $query = ValuationReport::query();
                $this->filter($query, $filters, 'created_at', ['file_number', 'full_name', 'address']);
                $this->printFilter($query, $filters, 'print_count');
                $labels = ['file_number' => 'File Number', 'owner' => 'Owner/Client', 'property_type' => 'Property Type', 'address' => 'Property Location', 'value' => 'Valuation (NGN)', 'inspection_date' => 'Inspection Date', 'date' => 'Date Generated'];
                $rows = $query->orderBy('created_at')->orderBy('id')->get()->map(fn ($r) => [
                    'file_number' => $r->file_number, 'owner' => $r->full_name, 'property_type' => $r->property_type,
                    'address' => ReportFormat::location($r->town_city, $r->lga, null, $r->address, $r->plot_no), 'value' => $r->value_figures,
                    'inspection_date' => $this->date($r->inspection_date), 'date' => $this->date($r->created_at),
                ]);
                break;
            case 'consent':
                $query = ConsentApplication::query();
                $this->filter($query, $filters, 'created_at', ['file_number', 'applicant_name', 'party_name', 'application_tracking_no', 'property_description']);
                if (!empty($filters['status'])) $query->where('consent_type', $filters['status']);
                $labels = ['tracking' => 'Tracking Number', 'file_number' => 'File Number(s)', 'type' => 'Consent Type', 'applicant' => 'Applicant(s)', 'party' => 'Other Parties', 'consideration' => 'Consideration (NGN)', 'bill_total' => 'Bill Total (NGN)', 'status' => 'Status', 'date' => 'Date Generated'];
                $rows = $query->orderBy('created_at')->orderBy('id')->get()->map(fn ($r) => [
                    'tracking' => $r->application_tracking_no,
                    'file_number' => $this->join(array_merge([$r->file_number], array_column($r->additional_properties ?? [], 'file_number'))),
                    'type' => $r->consent_type,
                    'applicant' => $this->join(array_merge([$r->applicant_name], array_column($r->additional_applicants ?? [], 'name'))),
                    'party' => $this->join(array_merge([$r->party_name], array_column($r->additional_parties ?? [], 'name'))),
                    'consideration' => $r->consideration, 'bill_total' => $r->bill_total, 'status' => $r->status,
                    'date' => $this->date($r->created_at),
                ]);
                break;
            case 'st-fc':
                // Match the register: one current (latest) conveyance per application.
                $latest = DB::connection('sqlsrv')->table('final_conveyance')
                    ->select('application_id', DB::raw('MAX(id) as latest_id'))->groupBy('application_id');
                $query = DB::connection('sqlsrv')->table('final_conveyance as fc')
                    ->joinSub($latest, 'latest_fc', fn ($join) => $join->on('fc.id', '=', 'latest_fc.latest_id'))
                    ->join('mother_applications as a', 'a.id', '=', 'fc.application_id')
                    ->select('a.*', 'fc.generated_date as report_date', 'fc.status as conveyance_status');
                $this->activeApplications($query, 'a.');
                $this->filter($query, $filters, 'fc.generated_date', ['a.fileno', 'a.np_fileno', 'a.first_name', 'a.surname', 'a.corporate_name', 'a.multiple_owners_names']);
                $labels = ['file_number' => 'File Number', 'applicant' => 'Applicant', 'land_use' => 'Land Use', 'location' => 'Location', 'status' => 'Conveyance Status', 'date' => 'Date Generated'];
                $rows = $query->orderBy('fc.generated_date')->orderBy('fc.id')->get()->map(fn ($r) => [
                    'file_number' => $r->fileno ?: $r->np_fileno, 'applicant' => $this->applicant($r), 'land_use' => $r->land_use,
                    'location' => ReportFormat::location($r->property_district ?? null, $r->property_lga ?? null, $r->property_state ?? null),
                    'status' => $r->conveyance_status, 'date' => $this->date($r->report_date),
                ]);
                break;
            case 'st-commissioning':
                $query = DB::connection('sqlsrv')->table('st_file_numbers')->whereNotNull('date_commissioned');
                $this->filter($query, $filters, 'date_commissioned', ['fileno', 'np_fileno', 'mls_fileno', 'first_name', 'surname', 'corporate_name', 'multiple_owners_names', 'property_address']);
                if (!empty($filters['status'])) $query->where('file_no_type', $filters['status']);
                $labels = ['file_number' => 'File Number', 'mother_file' => 'Primary File Number', 'mls_file' => 'MLPP File Number', 'type' => 'File Type', 'applicant' => 'Applicant', 'land_use' => 'Land Use', 'location' => 'Location', 'date' => 'Date Commissioned'];
                $records = $query->orderBy('date_commissioned')->orderBy('id')->get();
                $locate = $this->stCommissioningLocator($records);
                $rows = $records->map(fn ($r) => [
                    'file_number' => $r->fileno ?: $r->np_fileno, 'mother_file' => $r->np_fileno, 'mls_file' => $r->mls_fileno,
                    'type' => $r->file_no_type, 'applicant' => $this->applicant($r), 'land_use' => $r->land_use,
                    'location' => $locate($r),
                    'date' => $this->date($r->date_commissioned),
                ]);
                break;
            case 'st-applications':
                $labels = ['file_number' => 'File Number', 'type' => 'Application Type', 'applicant' => 'Applicant', 'land_use' => 'Land Use', 'location' => 'Location', 'status' => 'Application Status', 'planning' => 'Planning Status', 'date' => 'Date Captured'];
                $rows = collect();
                foreach (['mother_applications', 'subapplications'] as $table) {
                    $primary = $table === 'mother_applications';
                    $type = $filters['status'] ?? '';
                    if (($type === 'PRIMARY' && !$primary) || (in_array($type, ['PUA', 'SUA']) && $primary)) continue;
                    $query = DB::connection('sqlsrv')->table($table);
                    $this->activeApplications($query);
                    $this->filter($query, $filters, DB::raw('COALESCE(sys_date, created_at)'), ['fileno', 'np_fileno', 'first_name', 'surname', 'corporate_name', 'multiple_owners_names']);
                    if ($type === 'SUA') $query->where('is_sua_unit', 1);
                    if ($type === 'PUA') $query->where(fn ($q) => $q->where('is_sua_unit', 0)->orWhereNull('is_sua_unit'));
                    $rows = $rows->concat($query->orderBy('sys_date')->orderBy('id')->get()->map(fn ($r) => [
                        'file_number' => $r->fileno ?: $r->np_fileno,
                        'type' => $primary ? 'Primary' : ((int) $r->is_sua_unit === 1 ? 'Standalone Unit' : 'Parented Unit'),
                        'applicant' => $this->applicant($r), 'land_use' => $r->land_use,
                        'location' => $primary ? ReportFormat::location($r->property_district ?? null, $r->property_lga ?? null, $r->property_state ?? null) : ReportFormat::location($r->unit_district ?? null, $r->unit_lga ?? null, $r->unit_state ?? null, $r->property_location ?? ''),
                        'status' => $r->application_status, 'planning' => $r->planning_recommendation_status,
                        'date' => $this->date($r->sys_date ?: $r->created_at),
                    ]));
                }
                $rows = $rows->sortBy('date')->values();
                break;
            case 'st-rofo':
                $query = DB::connection('sqlsrv')->table('rofo as r')
                    ->join('subapplications as a', 'a.id', '=', 'r.sub_application_id')
                    ->leftJoin('mother_applications as m', 'm.id', '=', 'a.main_application_id')
                    ->select('r.*', 'a.fileno', 'a.land_use', 'a.applicant_type', 'a.applicant_title', 'a.first_name', 'a.middle_name', 'a.surname', 'a.corporate_name', 'a.multiple_owners_names', 'a.is_sua_unit', 'a.unit_district', 'a.unit_lga', 'a.unit_state', 'a.property_location',
                        'm.property_district as parent_district', 'm.property_lga as parent_lga', 'm.property_state as parent_state');
                $this->activeApplications($query, 'a.');
                $this->filter($query, $filters, 'r.created_at', ['a.fileno', 'r.rofo_no', 'a.first_name', 'a.surname', 'a.corporate_name', 'a.multiple_owners_names', 'r.location']);
                $this->printFilter($query, $filters, 'r.print_counter');
                $labels = ['file_number' => 'File Number', 'rofo_no' => 'RofO Number', 'land_use' => 'Landuse', 'term' => 'Term (Years)', 'type' => 'Unit Type', 'applicant' => 'Applicant', 'location' => 'Location', 'date' => 'Date Generated'];
                $rows = $query->orderBy('r.created_at')->orderBy('r.id')->get()->map(fn ($r) => [
                    'file_number' => $r->fileno, 'rofo_no' => $r->rofo_no, 'land_use' => $r->land_use, 'term' => $r->term_years,
                    'type' => (int) $r->is_sua_unit === 1 ? 'Standalone Unit' : 'Parented Unit',
                    'applicant' => $this->applicant($r),
                    'location' => ReportFormat::location($r->unit_district ?: $r->parent_district, $r->unit_lga ?: $r->parent_lga, $r->unit_state ?: $r->parent_state, $r->property_location ?: $r->location, $r->plot_no ?? ''),
                    'date' => $this->date($r->created_at),
                ]);
                break;
            case 'sltr-rofo':
                $query = SltrRecommendation::query()->where('status', SltrRecommendation::STATUS_APPROVED)
                    ->where('rofo_status', SltrRecommendation::ROFO_GENERATED);
                $this->filter($query, $filters, 'rofo_generated_at', ['sltr_number', 'applicant_name', 'location']);
                $this->printFilter($query, $filters, 'rofo_print_count');
                $labels = ['file_number' => 'File Number', 'land_use' => 'Land Use', 'term' => 'Term (Years)', 'applicant' => 'Applicant', 'location' => 'Location', 'date' => 'Date Generated'];
                $rows = $query->orderBy('rofo_generated_at')->orderBy('id')->get()->map(fn ($r) => [
                    'file_number' => $r->sltr_number, 'land_use' => $r->land_use, 'term' => $r->term,
                    'applicant' => $r->applicant_name, 'location' => ReportFormat::location(null, $r->lga, null, $r->location, $r->plot_number),
                    'date' => $this->date($r->rofo_generated_at),
                ]);
                break;
            default:
                throw new \InvalidArgumentException('Unknown consolidated report.');
        }

        $columns = [['key' => 'sn', 'label' => 'S/N', 'pdfWidth' => 10]];
        foreach ($labels as $key => $label) {
            $column = ['key' => $key, 'label' => $label, 'pdfWidth' => in_array($key, ['applicant', 'applicant_name', 'owner', 'location', 'address', 'party']) ? 45 : 30];
            if ($report === 'st-rofo') {
                $column['pdfWidth'] = ['file_number' => 40, 'rofo_no' => 40, 'land_use' => 22, 'term' => 18, 'type' => 28, 'applicant' => 45, 'location' => null, 'date' => 24][$key];
                if (in_array($key, ['file_number', 'rofo_no'])) $column['wrap'] = false;
            }
            $columns[] = $column;
        }
        $data = $rows->values()->map(fn ($row, $i) => ReportFormat::row(['sn' => $i + 1] + $row))->all();
        return ['columns' => $columns, 'data' => $data, 'count' => count($data)];
    }

    /**
     * Location for an ST commissioning row: district + LGA (+ state), never the plot.
     *
     * st_file_numbers carries its own property_* columns, but most rows were
     * commissioned without them (every PUA cut from a block, and nearly every
     * primary). The location is the block's, so fall back, in order, to:
     * the unit's subapplication, the primary's mother application (by id, then
     * by np_fileno), then the file's indexing (own number, then the primary's).
     * Each source is loaded once for the whole report.
     */
    private function stCommissioningLocator($records): \Closure
    {
        $db = DB::connection('sqlsrv');
        $has = fn ($d, $l) => trim((string) $d) !== '' || trim((string) $l) !== '';
        $chunks = fn ($values) => collect($values)->filter()->unique()->values()->chunk(1000);

        $subs = collect();
        foreach ($chunks($records->pluck('subapplication_id')->merge($records->pluck('fileno'))) as $chunk) {
            $subs = $subs->concat($db->table('subapplications')
                ->where(fn ($q) => $q->whereIn('id', $chunk->filter(fn ($v) => is_numeric($v))->all())->orWhereIn('fileno', $chunk->all()))
                ->get(['id', 'fileno', 'unit_district', 'unit_lga', 'unit_state'])
                ->filter(fn ($s) => $has($s->unit_district, $s->unit_lga)));
        }
        $subById = $subs->keyBy('id');
        $subByFile = $subs->keyBy(fn ($s) => strtoupper(trim((string) $s->fileno)));

        $mothers = collect();
        foreach ($chunks($records->pluck('mother_application_id')->merge($records->pluck('np_fileno'))) as $chunk) {
            $mothers = $mothers->concat($db->table('mother_applications')
                ->where(fn ($q) => $q->whereIn('id', $chunk->filter(fn ($v) => is_numeric($v))->all())->orWhereIn('np_fileno', $chunk->all()))
                ->orderBy('id')
                ->get(['id', 'np_fileno', 'property_district', 'property_lga', 'property_state'])
                ->filter(fn ($m) => $has($m->property_district, $m->property_lga)));
        }
        $motherById = $mothers->keyBy('id');
        $motherByNp = $mothers->keyBy(fn ($m) => strtoupper(trim((string) $m->np_fileno))); // latest id wins

        $indexed = collect();
        foreach ($chunks($records->pluck('fileno')->merge($records->pluck('np_fileno'))) as $chunk) {
            $indexed = $indexed->concat($db->table('file_indexings')
                ->whereIn('file_number', $chunk->all())
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('id')
                ->get(['file_number', 'district', 'lga'])
                ->filter(fn ($i) => $has($i->district, $i->lga)));
        }
        $indexByFile = $indexed->keyBy(fn ($i) => strtoupper(trim((string) $i->file_number)));

        $key = fn ($v) => strtoupper(trim((string) $v));

        return function ($r) use ($has, $key, $subById, $subByFile, $motherById, $motherByNp, $indexByFile) {
            if ($has($r->property_district ?? null, $r->property_lga ?? null)) {
                return ReportFormat::location($r->property_district, $r->property_lga, $r->property_state ?? null);
            }
            $sub = $subById->get($r->subapplication_id) ?? $subByFile->get($key($r->fileno));
            if ($sub) return ReportFormat::location($sub->unit_district, $sub->unit_lga, $sub->unit_state);

            $mother = $motherById->get($r->mother_application_id) ?? $motherByNp->get($key($r->np_fileno));
            if ($mother) return ReportFormat::location($mother->property_district, $mother->property_lga, $mother->property_state);

            $idx = $indexByFile->get($key($r->fileno)) ?? $indexByFile->get($key($r->np_fileno));
            if ($idx) return ReportFormat::location($idx->district, $idx->lga, null);

            return '';
        };
    }

    private function filter($query, array $filters, $dateColumn, array $searchColumns): void
    {
        if (!empty($filters['start_date'])) $query->where($dateColumn, '>=', Carbon::parse($filters['start_date'])->startOfDay());
        if (!empty($filters['end_date'])) $query->where($dateColumn, '<', Carbon::parse($filters['end_date'])->addDay()->startOfDay());
        if (trim($filters['search'] ?? '') !== '') {
            $query->where(function ($q) use ($filters, $searchColumns) {
                foreach ($searchColumns as $column) $q->orWhere($column, 'like', '%' . trim($filters['search']) . '%');
            });
        }
    }

    private function printFilter($query, array $filters, string $column): void
    {
        if (($filters['status'] ?? '') === 'printed') $query->where($column, '>', 0);
        if (($filters['status'] ?? '') === 'unprinted') $query->where(fn ($q) => $q->where($column, 0)->orWhereNull($column));
    }

    private function activeApplications($query, string $prefix = ''): void
    {
        $query->where(fn ($q) => $q->where($prefix . 'is_deleted', 0)->orWhereNull($prefix . 'is_deleted'));
    }

    private function applicant(object $record): string
    {
        if (!empty($record->corporate_name)) return trim($record->corporate_name);
        $names = json_decode($record->multiple_owners_names ?? '', true);
        if (is_array($names) && $names) {
            return $this->join(array_map(fn ($name) => is_array($name) ? ($name['name'] ?? $name['full_name'] ?? $this->join(array_intersect_key($name, array_flip(['title', 'first_name', 'middle_name', 'surname'])), ' ')) : $name, $names));
        }
        return $this->join([$record->applicant_title ?? '', $record->first_name ?? '', $record->middle_name ?? '', $record->surname ?? ''], ' ');
    }

    private function join(array $values, string $separator = ', '): string
    {
        return implode($separator, array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== '')));
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }
}
