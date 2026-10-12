<?php

namespace App\Services\Laas;

use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Services\LegalSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Where a portal file sits in its parcel's history: was it retired, into what,
 * and which files came before it. See docs/plans/LAAS_PORTAL_REBUILD_PLAN.md §3–4.
 *
 * READ AT VIEW TIME. Retirement and successors come from Legal Search's own
 * resolver (LegalSearchService::lineageFor, over decommissioned_files), parents
 * also from file_indexings.parent_prop_id. Nothing is copied into LAAS tables
 * except laas_applications.lifecycle_status, a cache for listings that
 * refresh() keeps in step. So a parcel update that is corrected or undone can
 * never leave the portal showing the old story.
 *
 * WHO SEES WHAT (§3.2). A related file on the SAME account links through, folio
 * and all. A related file on ANOTHER account, or on none, shows only its file
 * number and property facts (Plot No., District, LGA, Location) and the event:
 * never the other holder's name, phone or documents. The mother's folio carries
 * the previous owner's passport.
 *
 * Fail-open: any error returns an empty lineage, never an exception.
 */
class LaasLineageService
{
    public const RELATION_PARENT = 'parent';
    public const RELATION_SUCCESSOR = 'successor';

    /** Memoised lineageFor() per file number, for one request / run. */
    private array $cache = [];

    public function __construct(private LegalSearchService $legalSearch)
    {
    }

    /** Is the file retired by a parcel update (a real decommissioning, not a title-status flag)? */
    public function isRetired(string $fileNumber): bool
    {
        return (bool) ($this->raw($fileNumber)['is_superseded'] ?? false);
    }

    /**
     * The lineage panel for one portal application, as its holder may see it.
     *
     * @return array{
     *   retired: bool, retired_on: ?string, retired_reason: ?string, event: ?string,
     *   parents: array<int,array>, successors: array<int,array>
     * }
     */
    public function forApplication(LaasApplication $application): array
    {
        $empty = ['retired' => false, 'retired_on' => null, 'retired_reason' => null, 'event' => null, 'parents' => [], 'successors' => []];
        $fileNumber = trim((string) $application->file_number);
        if ($fileNumber === '') {
            return $empty;
        }

        try {
            $lineage = $this->raw($fileNumber);
            $viewer = $application->laas_applicant_id ? (int) $application->laas_applicant_id : null;

            $parents = [];
            foreach ($this->parentFileNumbers($fileNumber, $lineage) as $parent) {
                $parents[] = $this->relatedFile($parent, self::RELATION_PARENT, $viewer, $fileNumber);
            }

            $successors = [];
            foreach ($lineage['successor_files'] ?? [] as $successor) {
                $successors[] = $this->relatedFile((string) $successor['file_no'], self::RELATION_SUCCESSOR, $viewer, $fileNumber);
            }

            return [
                'retired'        => (bool) ($lineage['is_superseded'] ?? false),
                'retired_on'     => $lineage['decommission_date'] ?? null,
                'retired_reason' => $lineage['decommission_reason'] ?? null,
                'event'          => self::eventFromReason($lineage['decommission_reason'] ?? null),
                'parents'        => $parents,
                'successors'     => $successors,
            ];
        } catch (\Throwable $e) {
            Log::warning('LAAS lineage failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);

            return $empty;
        }
    }

    /**
     * Bring the cached lifecycle_status of these files' current portal rows in
     * line with the registry, and put a timeline entry on any that changed.
     * Called after a parcel update retires or restores files, and nightly.
     *
     * Never touches a transferred_out row: that is the old holder's history.
     *
     * @return array{retired:int,restored:int}
     */
    public function refresh(array $fileNumbers): array
    {
        $counts = ['retired' => 0, 'restored' => 0];

        foreach (array_unique(array_filter(array_map('trim', $fileNumbers))) as $fileNumber) {
            try {
                unset($this->cache[strtoupper($fileNumber)]);
                $lineage = $this->raw($fileNumber);
                $retired = (bool) ($lineage['is_superseded'] ?? false);

                $rows = LaasApplication::where('file_number', $fileNumber)->current()->get();
                foreach ($rows as $application) {
                    $was = $application->isRetired();
                    if ($was === $retired) {
                        continue;
                    }

                    $application->forceFill([
                        'lifecycle_status'     => $retired ? LaasApplication::LIFECYCLE_RETIRED : LaasApplication::LIFECYCLE_ACTIVE,
                        'lifecycle_changed_at' => now(),
                    ])->save();

                    LaasApplicationEvent::create([
                        'laas_application_id'  => $application->id,
                        'stage'                => $application->stage,
                        'title'                => $retired ? 'File retired' : 'File restored',
                        'body'                 => $retired
                            ? $this->retiredText($lineage)
                            : 'The registry restored this file. It is your live file again.',
                        'actor_type'           => 'system',
                        'visible_to_applicant' => true,
                    ]);

                    $counts[$retired ? 'retired' : 'restored']++;
                }
            } catch (\Throwable $e) {
                Log::warning('LAAS lineage refresh failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
            }
        }

        return $counts;
    }

    /** "Subdivision", "Merger", ... from the decommissioning reason text. */
    public static function eventFromReason(?string $reason): ?string
    {
        $reason = strtolower((string) $reason);
        if ($reason === '') {
            return null;
        }

        foreach ([
            'subdivi'            => 'Subdivision',
            'merger'             => 'Merger',
            'separation'         => 'Separation',
            'extension'          => 'Extension',
            'change of purpose'  => 'Change of Purpose',
            're-grant'           => 'Re-grant',
            'regrant'            => 'Re-grant',
            'st conversion'      => 'ST Conversion',
            'duplex'             => 'Duplex',
            'recertif'           => 'Recertification',
        ] as $needle => $label) {
            if (str_contains($reason, $needle)) {
                return $label;
            }
        }

        return 'Parcel update';
    }

    private function retiredText(array $lineage): string
    {
        $event = self::eventFromReason($lineage['decommission_reason'] ?? null) ?? 'Parcel update';
        $into = array_column($lineage['successor_files'] ?? [], 'file_no');

        return $event . ($into ? ': this file became ' . implode(', ', array_slice($into, 0, 10))
            . (count($into) > 10 ? ' and ' . (count($into) - 10) . ' more' : '') : '')
            . '. It stays here as part of your history.';
    }

    private function raw(string $fileNumber): array
    {
        $key = strtoupper(trim($fileNumber));

        return $this->cache[$key] ??= rescue(fn () => $this->legalSearch->lineageFor($fileNumber), [], false);
    }

    /**
     * Previous files: Legal Search's (decommissioned INTO this file, plus
     * related_fileno) and every parent_prop_id on the file's indexing row
     * (a merger lists several).
     *
     * @return array<int,string>
     */
    private function parentFileNumbers(string $fileNumber, array $lineage): array
    {
        $parents = [];
        foreach ($lineage['previous_file_nos'] ?? [] as $p) {
            $parents[strtoupper(trim((string) $p))] = trim((string) $p);
        }

        $db = DB::connection('sqlsrv');
        $raw = $db->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->whereNull('deleted_at')
            ->value('parent_prop_id');
        $propIds = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        if ($propIds) {
            $numbers = $db->table('file_indexings')
                ->whereIn('prop_id', $propIds)
                ->whereNull('deleted_at')
                ->pluck('file_number');
            foreach ($numbers as $p) {
                $p = trim((string) $p);
                if ($p !== '' && strcasecmp($p, $fileNumber) !== 0) {
                    $parents[strtoupper($p)] = $p;
                }
            }
        }

        unset($parents[strtoupper($fileNumber)]);

        return array_values($parents);
    }

    /**
     * One related file, cut to what this viewer may see.
     *
     * @return array{file_number:string,relation:string,plot_no:?string,district:?string,lga:?string,location:?string,
     *               on_this_account:bool,application_id:?int,reference_no:?string,in_scope:bool}
     */
    private function relatedFile(string $fileNumber, string $relation, ?int $viewerId, string $selfFileNumber): array
    {
        $facts = $this->propertyFacts($fileNumber);

        $portalRow = LaasApplication::where('file_number', $fileNumber)->current()->orderByDesc('id')
            ->first(['id', 'reference_no', 'laas_applicant_id']);
        $mine = $portalRow && $viewerId !== null && (int) $portalRow->laas_applicant_id === $viewerId;

        return [
            'file_number'     => $fileNumber,
            'relation'        => $relation,
            'plot_no'         => $facts['plot_no'],
            'district'        => $facts['district'],
            'lga'             => $facts['lga'],
            'location'        => $facts['location'],
            // Only a file on the viewer's own account links through to its page.
            'on_this_account' => $mine,
            'application_id'  => $mine ? (int) $portalRow->id : null,
            'reference_no'    => $mine ? $portalRow->reference_no : null,
            // Land / OSS (has a commissioning row) or outside the portal's scope (KANGIS, legacy).
            'in_scope'        => $facts['in_scope'],
        ];
    }

    /** Plot No., District, LGA, Location: commissioning row first, then indexing. */
    private function propertyFacts(string $fileNumber): array
    {
        $db = DB::connection('sqlsrv');

        $mls = $db->table('mls_file_no')
            ->where('full_file_number', $fileNumber)
            ->orderByDesc('id')
            ->first(['plot_no', 'district', 'lga', 'location']);
        $idx = $db->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->whereNull('deleted_at')
            ->first(['plot_number', 'district', 'lga', 'location']);

        $pick = fn (...$values) => collect($values)->map(fn ($v) => trim((string) $v))->first(fn ($v) => $v !== '' && $v !== '-') ?: null;

        return [
            'plot_no'  => $pick($mls->plot_no ?? null, $idx->plot_number ?? null),
            'district' => $pick($mls->district ?? null, $idx->district ?? null),
            'lga'      => $pick($mls->lga ?? null, $idx->lga ?? null),
            'location' => $pick($mls->location ?? null, $idx->location ?? null),
            'in_scope' => $mls !== null,
        ];
    }
}
