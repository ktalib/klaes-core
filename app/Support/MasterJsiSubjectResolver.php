<?php

namespace App\Support;

use App\Models\ChangeOfPurposeApplication;
use App\Models\DuplexParcelUpdate;
use App\Models\MasterJsiReport;
use App\Models\PlotExtensionApplication;
use App\Models\PlotMergerApplication;
use App\Models\PlotSeparationApplication;
use App\Models\PlotSubdivisionApplication;

/**
 * The record a Master JSI is about, whichever of the six registers it came from.
 *
 * The workflows do not share a base class or a column vocabulary — a duplex has
 * duplex_id and source_file_nos where the others have file_no and file_title, a
 * Change of Purpose has purpose/new_purpose and no file_title at all, an Extension
 * carries location. Rather than teach the controller and the form six shapes, this
 * flattens each one into the same handful of fields the inspection sheet asks for.
 *
 * The slugs are MasterJsiReport::SUBJECTS keys, which are the same slugs
 * ParcelUpdateNotificationService already speaks.
 */
class MasterJsiSubjectResolver
{
    /** slug => Eloquent model. */
    public const MODELS = [
        'subdivision'       => PlotSubdivisionApplication::class,
        'separation'        => PlotSeparationApplication::class,
        'merger'            => PlotMergerApplication::class,
        'extension'         => PlotExtensionApplication::class,
        'change_of_purpose' => ChangeOfPurposeApplication::class,
        'duplex'            => DuplexParcelUpdate::class,
    ];

    public static function modelClass(?string $subjectType): ?string
    {
        return self::MODELS[$subjectType] ?? null;
    }

    /** The record itself, or null when the slug or id does not resolve. */
    public static function find(?string $subjectType, $subjectId)
    {
        $class = self::modelClass($subjectType);

        if ($class === null || !$subjectId) {
            return null;
        }

        return $class::find($subjectId);
    }

    /**
     * A record flattened into the fields the inspection sheet prefills from.
     *
     * Everything is nullable: a half-captured application still opens a usable
     * form, and the officer is inspecting the site rather than transcribing the
     * register.
     */
    public static function prefill(?string $subjectType, $record): array
    {
        if ($record === null) {
            return [];
        }

        // A duplex names itself by its DPX reference; the rest by their file number.
        $fileNumber = $subjectType === 'duplex'
            ? $record->duplex_id
            : ($record->file_no ?? null);

        // Change of Purpose has no file_title, so its purpose stands in as the
        // description on the sheet.
        $fileTitle = $record->file_title
            ?? ($subjectType === 'change_of_purpose' ? $record->purpose : null);

        return array_filter([
            'file_number'    => $fileNumber,
            'file_title'     => $fileTitle,
            'applicant_name' => $record->applicant_name ?? null,
            'plot_number'    => $record->plot_no ?? null,
            'district'       => $record->district ?? null,
            'lga'            => $record->lga ?? null,

            // The plot number has its own box, so it is stripped off the front of
            // the location rather than being stated twice.
            'location'       => self::locationWithoutPlot($record->location ?? null, $record->plot_no ?? null),

            // Change of Purpose is the one sheet that states both land uses, and
            // the application already knows what it is asking to become.
            'existing_land_use'    => $record->land_use ?? null,
            'recommended_land_use' => $subjectType === 'change_of_purpose'
                ? ($record->new_purpose ?? null)
                : null,
            'existing_purpose'     => $subjectType === 'change_of_purpose'
                ? ($record->purpose ?? null)
                : null,
            'recommended_purpose'  => $subjectType === 'change_of_purpose'
                ? ($record->new_purpose ?? null)
                : null,

            // Subdivision and Separation state how many plots come out of the site.
            'number_of_units' => in_array($subjectType, ['subdivision', 'separation'], true)
                ? ($record->num_plots ?? null)
                : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The location with the plot number taken off the front.
     *
     * Registry locations are stored as one line that opens with the plot number —
     * "327, KATSINA ROAD, DAKATA, KUMBOTSO, KANO" — but the inspection sheet has a
     * Plot Number box of its own, so carrying it in both prints it twice and lets
     * the two disagree the moment either is corrected.
     *
     * Only a leading plot number is removed, and only when it matches the record's
     * own: a road called "Plot 5 Road" further along the line is left alone, and a
     * location that never carried the plot number comes back untouched.
     */
    public static function locationWithoutPlot(?string $location, $plotNo): ?string
    {
        $location = trim((string) $location);
        $plotNo   = trim((string) $plotNo);

        if ($location === '' || $plotNo === '') {
            return $location ?: null;
        }

        // "327, KATSINA ROAD" / "327 KATSINA ROAD" / "PLOT 327, KATSINA ROAD"
        $pattern = '/^\s*(?:plot\s*(?:no\.?|number)?\s*)?'
            . preg_quote($plotNo, '/')
            . '\s*[,\-\/]?\s+/i';

        $stripped = preg_replace($pattern, '', $location, 1, $count);

        // Never hand back an empty line: a location that was ONLY the plot number
        // is better kept as it was than blanked.
        return ($count && trim((string) $stripped) !== '')
            ? trim((string) $stripped)
            : $location;
    }

    /** How the record names itself in a picker: "KN1234 — Musa Bello". */
    public static function label(?string $subjectType, $record): string
    {
        if ($record === null) {
            return '';
        }

        $ref  = $subjectType === 'duplex' ? $record->duplex_id : ($record->file_no ?? '');
        $name = $record->applicant_name ?? '';

        return trim($ref . ($name ? ' — ' . $name : ''));
    }

    /**
     * The categories a workflow belongs to: a duplex is an APU, everything else SPU.
     */
    public static function categoryFor(?string $subjectType): string
    {
        return $subjectType === 'duplex'
            ? MasterJsiReport::CATEGORY_APU
            : MasterJsiReport::CATEGORY_SPU;
    }

    /** The workflow slugs offered under a category. */
    public static function subjectsForCategory(string $category): array
    {
        return $category === MasterJsiReport::CATEGORY_APU
            ? ['duplex']
            : ['subdivision', 'separation', 'merger', 'extension', 'change_of_purpose'];
    }

    /**
     * The parcel-update application a FILE NUMBER belongs to, if there is one.
     *
     * The officer holds a file number, not a register row id — so the form asks for
     * the file number through the global selector and this resolves it back to the
     * application, which is what MasterJsiGate keys the clearance on. Without that
     * link an approved inspection clears nothing.
     *
     * A duplex is the exception: it has no file_no of its own, it has a DPX
     * reference and a JSON list of the real files it was built from, so both are
     * searched.
     *
     * Returns null when no application matches — which is legitimate, not an error.
     * An inspection may be carried out before the application is captured.
     */
    public static function findByFileNumber(string $subjectType, ?string $fileNumber)
    {
        $fileNumber = trim((string) $fileNumber);
        $class = self::modelClass($subjectType);

        if ($class === null || $fileNumber === '') {
            return null;
        }

        $query = $class::query()->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });

        if ($subjectType === 'duplex') {
            $query->where(function ($q) use ($fileNumber) {
                $q->where('duplex_id', $fileNumber)
                    // source_file_nos is a JSON array of the real registry files.
                    // LIKE is the only way at it on this connection; the exact
                    // match is confirmed in PHP below.
                    ->orWhere('source_file_nos', 'LIKE', '%' . $fileNumber . '%');
            });

            foreach ($query->orderByDesc('id')->limit(25)->get() as $candidate) {
                if ((string) $candidate->duplex_id === $fileNumber) {
                    return $candidate;
                }

                $sources = array_map(
                    fn ($s) => strtoupper(trim((string) $s)),
                    (array) ($candidate->source_file_nos ?? [])
                );

                if (in_array(strtoupper($fileNumber), $sources, true)) {
                    return $candidate;
                }
            }

            return null;
        }

        // A file can go through the same kind of update more than once; the most
        // recent application is the one being inspected.
        return $query->where('file_no', $fileNumber)->orderByDesc('id')->first();
    }

    /**
     * Search a workflow's register for the file picker.
     *
     * Deliberately narrow — file number, applicant and title only. The officer is
     * looking up a record they already hold on paper.
     */
    public static function search(string $subjectType, ?string $term, int $limit = 25): array
    {
        $class = self::modelClass($subjectType);

        if ($class === null) {
            return [];
        }

        $query = $class::query();

        // Every one of the six carries the soft-delete flag.
        $query->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });

        $term = trim((string) $term);

        if ($term !== '') {
            $refColumn = $subjectType === 'duplex' ? 'duplex_id' : 'file_no';

            $query->where(function ($q) use ($term, $refColumn, $subjectType) {
                $q->where($refColumn, 'LIKE', "%{$term}%")
                    ->orWhere('applicant_name', 'LIKE', "%{$term}%");

                // Change of Purpose has no file_title column to search.
                if ($subjectType !== 'change_of_purpose') {
                    $q->orWhere('file_title', 'LIKE', "%{$term}%");
                }
            });
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($record) => [
                'id'             => $record->id,
                'label'          => self::label($subjectType, $record),
                'file_number'    => $subjectType === 'duplex' ? $record->duplex_id : $record->file_no,
                'applicant_name' => $record->applicant_name ?? '',
                'prefill'        => self::prefill($subjectType, $record),
            ])
            ->all();
    }
}
