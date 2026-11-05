<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyFarmer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The numbers on Capture Farm (CR 03112026 items 4-7; user, 2026-11-05: four separate fields):
 *
 *   Section No   the surveyor's next number, set once on the assessment
 *   Farmer ID    FMR-000001, one per person, for life
 *   Farm ID      FRM-000001, one per farm, kept when the farm is recaptured in a later section
 *   Farm No      the running number within the section
 *
 * All four are given by the server on save, so a phone working offline gets them when it syncs. Names
 * repeat, so a name alone never joins two people: the officer picks the existing farmer. A NIN is one
 * person, so an exact NIN does.
 */
class FarmIds
{
    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** The surveyor's next Section No. Inside the save's transaction. */
    public function nextSection(string $officer): int
    {
        $row = $this->db()->selectOne(
            'SELECT MAX(section_no) AS last_no FROM survey_comp_cases WITH (UPDLOCK, HOLDLOCK) WHERE UPPER(LTRIM(RTRIM(survey_officer))) = ?',
            [mb_strtoupper(trim($officer))]);

        return (int) ($row->last_no ?? 0) + 1;
    }

    /** Give a new farm row its numbers. Inside the save's transaction. */
    public function assign(SurveyCompCase $case, array $row): array
    {
        $farmer = $this->farmerFor($row);

        return [
            'survey_farmer_id' => $farmer->id,
            'farm_id_no'       => $this->farmIdFor($farmer, $row['farm_id_no'] ?? null),
            'farm_no'          => (int) $case->beneficiaries()->max('farm_no') + 1,
        ];
    }

    private function farmerFor(array $row): SurveyFarmer
    {
        if (! empty($row['survey_farmer_id'])) {
            $picked = SurveyFarmer::find((int) $row['survey_farmer_id']);
            if (! $picked) throw ValidationException::withMessages(['beneficiaries' => 'That farmer is no longer on record. Choose again.']);

            return $picked;
        }

        $nin = trim((string) ($row['nin'] ?? ''));
        if ($nin !== '' && ($same = SurveyFarmer::where('nin', $nin)->first())) {
            return $same;
        }

        $last = $this->db()->selectOne('SELECT MAX(farmer_id_no) AS last_no FROM survey_farmers WITH (UPDLOCK, HOLDLOCK)');
        $n = (int) substr((string) ($last->last_no ?? 'FMR-0'), 4) + 1;

        return SurveyFarmer::create([
            'farmer_id_no' => sprintf('FMR-%06d', $n),
            'full_name'    => trim((string) $row['full_name']),
            'phone'        => ($row['phone'] ?? null) ?: null,
            'nin'          => $nin ?: null,
        ]);
    }

    /** The farm being recaptured, if it is this farmer's; otherwise a new Farm ID. */
    private function farmIdFor(SurveyFarmer $farmer, ?string $wanted): string
    {
        $wanted = trim((string) $wanted);
        if ($wanted !== '') {
            if (! SurveyBeneficiary::where('survey_farmer_id', $farmer->id)->where('farm_id_no', $wanted)->exists()) {
                throw ValidationException::withMessages(['beneficiaries' => "{$wanted} is not a farm of {$farmer->full_name}."]);
            }

            return $wanted;
        }

        $last = $this->db()->selectOne('SELECT MAX(farm_id_no) AS last_no FROM survey_beneficiaries WITH (UPDLOCK, HOLDLOCK)');

        return sprintf('FRM-%06d', (int) substr((string) ($last->last_no ?? 'FRM-0'), 4) + 1);
    }

    /**
     * Farmers who may be the one being typed: exact NIN or phone first, then names containing what was
     * typed, each with their farms. Offered to the officer, never applied on their own.
     */
    public function matches(string $name, string $phone = '', string $nin = '', int $limit = 8): Collection
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $phone = trim($phone);
        $nin = trim($nin);
        if (mb_strlen($name) < 3 && $phone === '' && $nin === '') return collect();

        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $name) . '%';
        $farmers = SurveyFarmer::query()
            ->where(function ($q) use ($name, $like, $phone, $nin) {
                if (mb_strlen($name) >= 3) $q->orWhere('full_name', 'like', $like);
                if ($phone !== '') $q->orWhere('phone', $phone);
                if ($nin !== '') $q->orWhere('nin', $nin);
            })
            ->orderByRaw('CASE WHEN nin = ? OR phone = ? THEN 0 ELSE 1 END', [$nin ?: '-', $phone ?: '-'])
            ->orderBy('full_name')->limit($limit)->get();

        $farms = SurveyBeneficiary::whereIn('survey_farmer_id', $farmers->pluck('id'))->whereNotNull('farm_id_no')
            ->with('case:id,case_ref,section_no')->orderBy('id')->get()->groupBy('survey_farmer_id');

        return $farmers->map(fn ($f) => [
            'id' => $f->id, 'farmer_id_no' => $f->farmer_id_no, 'full_name' => $f->full_name, 'phone' => $f->phone, 'nin' => $f->nin,
            'farms' => $farms->get($f->id, collect())->unique('farm_id_no')->map(fn ($b) => [
                'farm_id_no' => $b->farm_id_no,
                'label' => $b->farm_id_no . ($b->case?->section_no ? ' · Section ' . $b->case->section_no : '') . ($b->case ? ' · ' . $b->case->case_ref : ''),
            ])->values(),
        ])->values();
    }
}
