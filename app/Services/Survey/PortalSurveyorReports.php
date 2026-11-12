<?php

namespace App\Services\Survey;

use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyBeaconNumber;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Models\Survey\SurveyItsObservation as Obs;
use App\Models\Survey\SurveyorPortalAccount;
use App\Services\SurveyItsAnchors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The Surveyor's Report on the KSSGOF portal (user, 2026-11-09). The report is a Surveyor's Report (Private)
 * row, so it is on Survey's own Surveyor's Report table as soon as it is written. It starts from an I to S
 * issued to the surveyor (Survey Update 5d, 2026-11-10: "My Issued Beacons" removed, every survey has an
 * I to S); beacons picked under Beacons to update ride along as rows to fill. Its List of Coordinates and Field
 * Observations use the same tables as the staff editors (anchor "private-<id>"), and a beacon on the list
 * becomes Used with its X / Y / Z and location: that is what moves it into the Beacon Register and off the
 * balance. A new beacon request waits until every issued beacon is used.
 *
 * Rows are saved quietly: under the portal guard SurveyModel's hooks would stamp the portal account id into
 * created_by / updated_by, which hold staff ids.
 */
class PortalSurveyorReports
{
    public const SUBMITTED = 'Submitted';

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** Every beacon issued to the account (issued or accepted requests), in issue order. */
    public function issuedBeacons(SurveyorPortalAccount $a): Collection
    {
        return SurveyBeaconNumber::query()
            ->whereHas('register.request', fn ($q) => $q->where('surveyor_portal_account_id', $a->id)
                ->whereIn('status', [SurveyBeaconRequest::ISSUED, SurveyBeaconRequest::ACCEPTED]))
            ->with('register.request')
            ->orderBy('survey_beacon_register_id')->orderBy('beacon_seq')->orderBy('id')
            ->get();
    }

    /** Like the PHS token balance: issued, used, left. */
    public function balance(SurveyorPortalAccount $a): array
    {
        $all = $this->issuedBeacons($a);
        $used = $all->filter(fn ($b) => (bool) $b->used)->count();

        return ['issued' => $all->count(), 'used' => $used, 'left' => $all->count() - $used];
    }

    /** I to S issued to this surveyor: Survey's own by the surveyors list entry, Cadastral's by the SURCON entry. */
    public function itsChoices(SurveyorPortalAccount $a): array
    {
        $out = [];
        if ($a->surveyors_list_id) {
            foreach (SurveyIts::whereNotNull('its_number')->where('status', '!=', SurveyIts::CANCELLED)->where('surveyor_id', $a->surveyors_list_id)->orderByDesc('id')->get() as $i) {
                $out['survey-' . $i->id] = $i;
            }
        }
        if ($a->cadastral_surveyor_id) {
            foreach (CadastralSurveyJob::whereNotNull('its_number')->where('cadastral_surveyor_id', $a->cadastral_surveyor_id)->orderByDesc('id')->get() as $j) {
                $out['cadastral-' . $j->id] = $j;
            }
        }

        return $out;
    }

    /** The I to S's details as the report fields. */
    public function itsPrefill(string $key, $its): array
    {
        $anchor = app(SurveyItsAnchors::class)->resolve($key);

        return $anchor ? array_intersect_key($anchor['prefill'], array_flip(['its_number', 'file_number', 'grantee_name', 'plot_no', 'district', 'lga'])) : [];
    }

    public function reports(SurveyorPortalAccount $a): Collection
    {
        return CR::where('surveyor_portal_account_id', $a->id)->orderByDesc('id')->get();
    }

    public function mine(SurveyorPortalAccount $a, int $id): CR
    {
        $r = CR::where('surveyor_portal_account_id', $a->id)->find($id);
        abort_unless($r, 404);

        return $r;
    }

    public function editable(CR $r): bool
    {
        return ! $r->submitted_at && ! $r->isIssued();
    }

    public function anchor(CR $r): array
    {
        return app(SurveyItsAnchors::class)->resolve('private-' . $r->id);
    }

    public function coordinates(CR $r): Collection
    {
        return Coord::where('survey_change_report_id', $r->id)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function observations(CR $r): Collection
    {
        return Obs::where('survey_change_report_id', $r->id)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Picked beacons with no coordinate yet on this report: they open as rows to fill. */
    public function pendingBeacons(CR $r): array
    {
        $listed = $this->coordinates($r)->map(fn ($c) => $this->key($c->beacon_id))->all();

        return array_values(array_filter($this->picked($r), fn ($b) => ! in_array($this->key($b), $listed, true)));
    }

    public function picked(CR $r): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $r->portal_beacons))));
    }

    public function create(SurveyorPortalAccount $a, array $fields, array $beacons, ?string $itsKey): CR
    {
        $own = $this->issuedBeacons($a)->keyBy(fn ($b) => $this->key($b->beacon_no));
        $picked = [];
        foreach ($beacons as $b) {
            $k = $this->key($b);
            if (! $own->has($k)) throw ValidationException::withMessages(['beacons' => "Beacon {$b} was not issued to you."]);
            if ($own[$k]->used) throw ValidationException::withMessages(['beacons' => "Beacon {$b} is already used."]);
            $picked[] = $own[$k]->beacon_no;
        }
        if ($itsKey === null || ! array_key_exists($itsKey, $this->itsChoices($a))) {
            throw ValidationException::withMessages(['its_key' => 'Choose an I to S issued to you.']);
        }

        $r = new CR($fields + [
            'surveyor_portal_account_id' => $a->id,
            'its_key' => $itsKey,
            'portal_beacons' => $picked ? mb_substr(implode(', ', $picked), 0, 4000) : null,
            'surveyor_name' => $fields['surveyor_name'] ?? $a->name,
            'discrepancies' => '',
            'required_changes' => '',
            'report_ref' => CR::nextRef('report_ref', 'SRC'),
            'status' => CR::DRAFT,
        ]);
        $this->quiet(fn () => $r->save());
        Log::info('Portal surveyor report created', ['report' => $r->report_ref, 'account' => $a->id]);

        return $r;
    }

    public function update(CR $r, array $fields): void
    {
        $this->assertEditable($r);
        $r->fill($fields);
        $this->quiet(fn () => $r->save());
    }

    /**
     * Save coordinate lines: each is a beacon issued to the surveyor or another point (Other). A beacon line
     * marks the beacon Used with its coordinates and the report's location.
     */
    public function addCoordinates(SurveyorPortalAccount $a, CR $r, array $lines): int
    {
        $this->assertEditable($r);
        $own = $this->issuedBeacons($a)->keyBy(fn ($b) => $this->key($b->beacon_no));
        $onList = $this->coordinates($r)->keyBy(fn ($c) => $this->key($c->beacon_id));
        $onOthers = Coord::whereNotNull('survey_change_report_id')->where('survey_change_report_id', '!=', $r->id)
            ->whereIn('survey_change_report_id', CR::where('surveyor_portal_account_id', $a->id)->pluck('id'))
            ->get(['beacon_id', 'survey_change_report_id'])->keyBy(fn ($c) => $this->key($c->beacon_id));
        $anchor = $this->anchor($r);

        $seen = [];
        foreach ($lines as $n => $line) {
            $k = $this->key($line['beacon_id']);
            $at = 'lines.' . $n . '.beacon_id';
            // Easting / Northing typed the wrong way round are put right (CR 11112026-2 item 10).
            [$line['easting'], $line['northing']] = SurveyCoordinates::orient($line['easting'], $line['northing']);
            if (isset($seen[$k]) || $onList->has($k)) throw ValidationException::withMessages([$at => "{$line['beacon_id']} is already on this list."]);
            if ($onOthers->has($k)) throw ValidationException::withMessages([$at => "{$line['beacon_id']} is on another of your reports."]);
            if ($line['is_beacon'] && ! $own->has($k)) throw ValidationException::withMessages([$at => "Beacon {$line['beacon_id']} was not issued to you."]);
            if (! $line['is_beacon'] && $own->has($k)) $line['is_beacon'] = true;
            $seen[$k] = $line;
        }

        $this->db()->transaction(function () use ($seen, $own, $r, $anchor) {
            $sort = (int) Coord::where('survey_change_report_id', $r->id)->max('sort_order');
            foreach ($seen as $k => $line) {
                $c = new Coord($anchor['keys'] + [
                    'its_number' => $anchor['prefill']['its_number'],
                    'file_number' => $anchor['prefill']['file_number'],
                    'sort_order' => ++$sort,
                    'beacon_id' => $line['is_beacon'] ? $own[$k]->beacon_no : $line['beacon_id'],
                    'northing' => $line['northing'],
                    'easting' => $line['easting'],
                    'elevation' => $line['elevation'],
                    'remarks' => $line['remarks'],
                    'source' => 'portal',
                ]);
                $this->quiet(fn () => $c->save());
                if ($line['is_beacon']) {
                    $b = $own[$k];
                    $b->fill(['used' => true, 'coord_x' => $line['easting'], 'coord_y' => $line['northing'], 'coord_z' => $line['elevation'],
                              'location_details' => mb_substr((string) $r->location, 0, 500) ?: null]);
                    $this->quiet(fn () => $b->save());
                }
            }
        });

        return count($seen);
    }

    /** Remove a coordinate; a beacon goes back to unused (and onto the balance). */
    public function removeCoordinate(SurveyorPortalAccount $a, CR $r, int $coordId): string
    {
        $this->assertEditable($r);
        $c = Coord::where('survey_change_report_id', $r->id)->findOrFail($coordId);
        $beacon = $this->issuedBeacons($a)->first(fn ($b) => $this->key($b->beacon_no) === $this->key($c->beacon_id));
        $this->db()->transaction(function () use ($c, $beacon) {
            $this->quiet(fn () => $c->delete());
            if ($beacon) {
                $beacon->fill(['used' => null, 'coord_x' => null, 'coord_y' => null, 'coord_z' => null, 'location_details' => null]);
                $this->quiet(fn () => $beacon->save());
            }
        });

        return (string) $c->beacon_id;
    }

    public function addObservations(CR $r, array $rows): int
    {
        $this->assertEditable($r);
        $anchor = $this->anchor($r);
        $this->db()->transaction(function () use ($rows, $r, $anchor) {
            $sort = (int) Obs::where('survey_change_report_id', $r->id)->max('sort_order');
            foreach ($rows as $row) {
                $o = new Obs($row + $anchor['keys'] + [
                    'its_number' => $anchor['prefill']['its_number'],
                    'file_number' => $anchor['prefill']['file_number'],
                    'sort_order' => ++$sort,
                    'source' => 'portal',
                ]);
                $this->quiet(fn () => $o->save());
            }
        });

        return count($rows);
    }

    public function removeObservation(CR $r, int $obsId): void
    {
        $this->assertEditable($r);
        $o = Obs::where('survey_change_report_id', $r->id)->findOrFail($obsId);
        $this->quiet(fn () => $o->delete());
    }

    /** Hand the report to the SG's office: the same fields the staff Issue asks for, plus the coordinates. */
    public function submit(CR $r): void
    {
        $this->assertEditable($r);
        $missing = [];
        foreach ([
            'grid_origin' => 'origin of the local grid', 'control_points' => 'the control points',
            'commenced_on' => 'the date the survey commenced', 'completed_on' => 'the date it was completed',
            'transport_by' => 'who provided transport', 'surveyor_name' => "the surveyor's name",
        ] as $field => $label) {
            if (blank($r->{$field})) $missing[] = $label;
        }
        if ($r->on_layout_plan === null) $missing[] = 'whether the plot is on an approved layout plan';
        if ($r->corners_demarcated === null) $missing[] = 'whether the corners were demarcated';
        if ($this->coordinates($r)->isEmpty()) $missing[] = 'the List of Coordinates';
        if ($this->pendingBeacons($r)) $missing[] = 'the coordinates of ' . implode(', ', $this->pendingBeacons($r));
        if ($missing) {
            throw ValidationException::withMessages(['submit' => 'Before submitting, fill in ' . implode(', ', $missing) . '.']);
        }
        $r->fill(['submitted_at' => now(), 'status' => self::SUBMITTED]);
        $this->quiet(fn () => $r->save());
        Log::info('Portal surveyor report submitted', ['report' => $r->report_ref]);
        if ($account = SurveyorPortalAccount::find($r->surveyor_portal_account_id)) {
            app(SurveyorPortalNotifier::class)->reportSubmitted($r, $account);
        }
    }

    /**
     * The balance as beacons (user, 2026-11-09: "where to select the beacon, and complete the update"): every
     * issued beacon not used yet, with the open report it is already picked on, if any.
     */
    public function unusedBeacons(SurveyorPortalAccount $a): Collection
    {
        $drafts = CR::where('surveyor_portal_account_id', $a->id)->whereNull('submitted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', CR::ISSUED))
            ->orderByDesc('id')->get();
        $pickedOn = [];
        foreach ($drafts as $d) {
            foreach ($this->picked($d) as $b) $pickedOn[$this->key($b)] ??= $d;
        }

        return $this->issuedBeacons($a)->reject(fn ($b) => (bool) $b->used)->values()
            ->each(fn ($b) => $b->setRelation('draftReport', $pickedOn[$this->key($b->beacon_no)] ?? null));
    }

    /** Used beacons for the Beacon Register: beacon, its request, and the report it is on. */
    public function usedBeacons(SurveyorPortalAccount $a): Collection
    {
        $reports = CR::where('surveyor_portal_account_id', $a->id)->get()->keyBy('id');
        $onReport = Coord::whereIn('survey_change_report_id', $reports->keys())->get(['beacon_id', 'survey_change_report_id'])
            ->keyBy(fn ($c) => $this->key($c->beacon_id));

        return $this->issuedBeacons($a)->filter(fn ($b) => (bool) $b->used)->values()
            ->each(function ($b) use ($onReport, $reports) {
                $c = $onReport->get($this->key($b->beacon_no));
                $b->setRelation('portalReport', $c ? $reports->get($c->survey_change_report_id) : null);
            });
    }

    private function assertEditable(CR $r): void
    {
        if (! $this->editable($r)) {
            throw ValidationException::withMessages(['report' => "{$r->report_ref} has been submitted and can no longer be changed."]);
        }
    }

    public function key(?string $beacon): string
    {
        return (string) SurveyBeaconRegister::normalise($beacon);
    }

    private function quiet(callable $fn)
    {
        return Model::withoutEvents($fn);
    }
}
