<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use App\Support\AddressBuilder;
use Illuminate\Support\Collection;

/**
 * What Surveyor's Report (Private) reads from the Beacon Register (user, 2026-11-06): the Private lines, one
 * survey per file number, with a line that has no file number yet standing alone (each line holds one beacon,
 * so a row per line would say nothing). Those beacons are in the register by definition. Read only.
 */
class PrivateRegisterLines
{
    /** The same file however it was typed; null when the line has no file number. */
    public static function key(?string $fileNumber): ?string
    {
        $no = mb_strtoupper(trim((string) $fileNumber));

        return $no === '' ? null : $no;
    }

    /** @return Collection<string, array> newest first, keyed by file number or "#<line id>" */
    public function groups(): Collection
    {
        return SurveyBeaconRegister::where('layout_type', 'Private')->orderByDesc('id')->get()
            ->groupBy(fn ($l) => self::key($l->file_number) ?? '#' . $l->id)
            ->map(function (Collection $lines, string $key) {
                // The newest line that holds a value: one line's blank must not hide another's detail.
                $first = fn (callable $value) => $lines->map($value)->first(fn ($v) => filled($v));

                return [
                    'key'         => $key,
                    'line_id'     => $lines->first()->id,
                    'file_number' => $first(fn ($l) => trim((string) $l->file_number)),
                    'plan_no'     => $first(fn ($l) => $l->plan_no),
                    'surveyor'    => $first(fn ($l) => $l->surveyor_name),
                    'plot_no'     => $first(fn ($l) => $l->prop_plot),
                    'district'    => $first(fn ($l) => $l->resolvedDistrict('prop_')),
                    'lga'         => $first(fn ($l) => AddressBuilder::resolve($l->prop_lga)),
                    'location'    => $first(fn ($l) => $l->location),
                    'date'        => $lines->max('issue_date'),
                    'beacons'     => (int) $lines->sum(fn ($l) => max(1, (int) $l->total_issued)),
                    'points'      => $lines->filter(fn ($l) => $l->coord_x !== null && $l->coord_y !== null)->count(),
                    'numbers'     => $lines->flatMap(fn ($l) => [$l->beacon_from, $l->beacon_to])->filter()->unique()->implode(' '),
                ];
            });
    }

    /** A group shaped like ReportRecords::summary. The register holds no traverse, readings or sketch, so those stay empty. */
    public static function summary(array $group): array
    {
        return [
            'beacons' => $group['beacons'], 'in_register' => $group['beacons'], 'flagged' => 0, 'points' => $group['points'],
            'area' => null, 'perimeter' => null, 'readings' => 0, 'closed' => false, 'misclosure' => null, 'sketch' => null,
        ];
    }

    /** Where a group's two counts open: the registers, searched for its file number, or its one beacon. */
    public static function links(array $group): array
    {
        $term = $group['file_number'] ?: $group['numbers'];

        return [
            'beacons'     => route('survey-module.workflow.beacon-register.index', ['q' => $term]),
            'coordinates' => route('survey-module.workflow.coordinate-register.index', ['source' => 'beacon', 'q' => $term]),
        ];
    }
}
