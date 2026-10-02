{{--
    The Master JSI sheet, as the Ministry writes it.

    Two layouts on one sheet:
      - a legacy sheet (no purpose children) prints the old single measurement
        table from MasterJsiPortionTemplates, so nothing about older records here
        changes shape;
      - everything newer prints each of the ticked purposes with its own section,
        the persons present, the site measurements and the findings.

    Browser print, not dompdf — every land memo here is an A4 HTML page printed
    from the browser (see deeds/parcel_update/print/subdivision_recommendation).
--}}
@php
    use App\Models\MasterJsiPurpose;
    use App\Support\ParcelSizeSummary;

    $legacy = $template !== null;
    $heading = $legacy
        ? ($template['heading'] ?? ucwords(str_replace('_', ' ', $report->parcel_update_type)))
        : ($report->purposeSlugs()
            ? collect($report->purposeSlugs())->map(fn ($s) => MasterJsiPurpose::PURPOSES[$s] ?? ucfirst($s))->implode(' and ')
            : ucwords(str_replace('_', ' ', $report->parcel_update_type)));

    $sqm = function ($value) {
        return ($value === null || (float) $value <= 0) ? null : ParcelSizeSummary::number((float) $value) . ' m²';
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->jsi_ref }} — Joint Site Inspection</title>
    <style>
        @page { size: A4; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            color: #000;
            background: #f1f5f9;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 16mm;
            background: #fff;
        }

        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 15pt; margin: 0; text-transform: uppercase; letter-spacing: .5px; }
        .head h2 { font-size: 12pt; margin: 4px 0 0; font-weight: normal; text-transform: uppercase; }
        .head .ref { font-size: 10pt; margin-top: 6px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11pt; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .k { width: 38mm; font-weight: bold; }

        .sub { font-size: 11pt; text-transform: uppercase; letter-spacing: .4px; margin: 16px 0 4px; border-bottom: 1px solid #999; padding-bottom: 2px; }

        .narrative { line-height: 1.7; text-align: justify; margin-bottom: 6px; }
        .points { line-height: 1.9; margin: 0 0 12px; padding-left: 18px; }
        .points li { margin-bottom: 2px; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 6px 8px; font-size: 11pt; }
        table.grid th { background: #f3f4f6; text-align: left; text-transform: uppercase; font-size: 9.5pt; letter-spacing: .4px; }
        table.grid td.sn { width: 12mm; text-align: center; }
        table.grid td.num { white-space: nowrap; }

        tr.derived td { font-weight: bold; background: #fafafa; }

        .totals { display: flex; justify-content: space-between; gap: 10mm; margin-top: 8px; font-size: 10.5pt; }
        .totals .t { flex: 1; }

        .sign { margin-top: 22mm; display: flex; justify-content: space-between; gap: 14mm; }
        .sign div { flex: 1; text-align: center; }
        .sign .line { border-top: 1px solid #000; padding-top: 5px; font-size: 10pt; }

        .foot { margin-top: 10mm; font-size: 8.5pt; color: #555; text-align: center; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 16mm 14mm; }
            .no-print { display: none !important; }
        }

        .no-print { text-align: center; padding: 12px; }
        .no-print button {
            padding: 9px 22px; font-size: 13px; font-weight: bold; cursor: pointer;
            background: #dc2626; color: #fff; border: 0; border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Print this sheet</button>
</div>

<div class="sheet">
    <div class="head">
        <h1>Joint Site Inspection Report</h1>
        <h2>{{ $heading }}</h2>
        <div class="ref">
            <strong>{{ $report->jsi_ref }}</strong>
            &nbsp;·&nbsp; {{ $report->category }}
            @if ($report->inspection_date) &nbsp;·&nbsp; {{ $report->inspection_date->format('d F Y') }} @endif
        </div>
    </div>

    <table class="meta">
        @foreach ([
            ['File Number', $report->file_number],
            ['File Title', $report->file_title],
            ['Applicant', \App\Support\PersonName::display($report->applicant_name)],
            ['Plot Number', $report->plot_number],
            ['Location', $report->location],
            ['District / LGA', trim(collect([$report->district, $report->lga])->filter()->implode(' / '))],
            ['Inspection Officer', $report->inspection_officer],
        ] as [$k, $v])
            @if ($v !== null && $v !== '')
            <tr><td class="k">{{ $k }}:</td><td>{{ $v }}</td></tr>
            @endif
        @endforeach
        @if (!$legacy)
            @if ($report->site_existing_dimensions)
            <tr><td class="k">Existing Site Dimensions (m):</td><td>{{ $report->site_existing_dimensions }}</td></tr>
            @endif
            @if ($report->site_existing_area_sqm)
            <tr><td class="k">Existing Site Area:</td><td>{{ $sqm($report->site_existing_area_sqm) }}</td></tr>
            @endif
            @if ($report->recommendedTotalSiteAreaSqm())
            <tr><td class="k">Recommended Total:</td><td>{{ $sqm($report->recommendedTotalSiteAreaSqm()) }}<tr>
            @endif
        @endif
    </table>

    @if ($legacy)

    <p class="narrative">{{ $template['narrative'] ?? '' }}</p>

    <ul class="points">
        @if ($report->available_on_ground)
            <li>The site was found to be <strong>{{ strtolower($report->available_on_ground) }}</strong> on ground.</li>
        @endif
        @if ($report->boundary_description)
            <li>The site was bounded by — {{ $report->boundary_description }}</li>
        @endif
        @if ($report->number_of_units)
            <li>The site will be divided into <strong>{{ $report->number_of_units }}</strong> number of plots.</li>
        @endif
        @if ($report->average_size)
            <li>The site has an average size of <strong>{{ $report->average_size }}</strong>.</li>
        @endif
        @if ($report->prevailing_land_use)
            <li>The prevailing land use is <strong>{{ $report->prevailing_land_use }}</strong>.</li>
        @endif
        @if ($report->existing_land_use || $report->recommended_land_use)
            <li>
                Change of purpose from <strong>{{ $report->existing_land_use ?: '—' }}</strong>
                to <strong>{{ $report->recommended_land_use ?: '—' }}</strong> land use.
            </li>
        @endif
        @if ($report->conformity !== null)
            <li>
                The site was found
                <strong>{{ $report->conformity ? 'to conform' : 'not to conform' }}</strong>
                with the surrounding land use.
            </li>
        @endif
        @if ($report->road_reservation)
            <li>Road reservation: {{ $report->road_reservation }}</li>
        @endif
        @if ($report->additional_observations)
            <li>{{ $report->additional_observations }}</li>
        @endif
    </ul>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:12mm;text-align:center;">S/N</th>
                <th>Portion</th>
                <th style="width:50mm;">Dimension</th>
                <th style="width:52mm;">Measurement (m²/ha)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $derivedRoles = collect($template['rows'] ?? [])
                    ->filter(fn ($r) => $r['derived'] ?? false)
                    ->pluck('role')
                    ->all();
            @endphp
            @forelse ($report->portions as $portion)
            <tr class="{{ in_array($portion->role, $derivedRoles, true) ? 'derived' : '' }}">
                <td class="sn">{{ $portion->sn }}</td>
                <td>{{ $portion->label }}</td>
                <td>
                    {{ $portion->dimensions
                        ?: ($portion->land_use ?: ($portion->unit_count !== null ? $portion->unit_count : '')) }}
                </td>
                <td class="num">{{ $portion->measurementText() }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:18px;">No measurements recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    @else

    <ul class="points">
        @if ($report->available_on_ground)
            <li>The site was found to be <strong>{{ strtolower($report->available_on_ground) }}</strong> on ground.</li>
        @endif
        @if ($report->boundary_description)
            <li>The site was bounded by — {{ $report->boundary_description }}</li>
        @endif
        @if ($report->prevailing_land_use)
            <li>The prevailing land use is <strong>{{ $report->prevailing_land_use }}</strong>.</li>
        @endif
        @if ($report->conformity !== null)
            <li>
                The site was found
                <strong>{{ $report->conformity ? 'to conform' : 'not to conform' }}</strong>
                with the surrounding land use.
            </li>
        @endif
        @if ($report->road_reservation)
            <li>Road reservation: {{ $report->road_reservation }}</li>
        @endif
        @if ($report->location_coordinates)
            <li>Location coordinates: {{ $report->location_coordinates }}</li>
        @endif
        @if ($report->additional_observations)
            <li>{{ $report->additional_observations }}</li>
        @endif
    </ul>

    {{-- Persons present --}}
    <div class="sub">Persons Present</div>
    <table class="grid">
        <thead><tr><th style="width:12mm;">S/N</th><th>Role</th><th>Name</th><th>Phone</th><th>Capacity / Relationship</th></tr></thead>
        <tbody>
            @forelse ($report->participants as $person)
            <tr>
                <td class="sn">{{ $loop->iteration }}</td>
                <td>{{ $person->isRepresentative() ? 'Representative' : 'Applicant' }}</td>
                <td><strong>{{ $person->name }}</strong></td>
                <td class="num">{{ $person->phone ?: '—' }}</td>
                <td>{{ $person->relationship ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:14px;">None recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Merger --}}
    @if ($report->hasPurpose('merger'))
    <div class="sub">Merger</div>
    <table class="grid">
        <thead><tr><th style="width:12mm;">S/N</th><th>Property File Number</th><th>Plot Number</th><th>Area</th></tr></thead>
        <tbody>
            @forelse ($report->mergerProperties as $property)
            <tr>
                <td class="sn">{{ $loop->iteration }}</td>
                <td><strong>{{ $property->property_file_number }}</strong></td>
                <td>{{ $property->plot_number ?: '—' }}</td>
                <td class="num">{{ $property->areaText() ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:14px;">No properties recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="totals">
        <div class="t"><strong>Total area being merged:</strong> {{ $sqm($report->totalMergerAreaSqm()) ?: '—' }}</div>
        @if ($report->proposed_merged_plot_number)
        <div class="t"><strong>Proposed merged plot:</strong> {{ $report->proposed_merged_plot_number }}</div>
        @endif
        @if ($report->recommended_merged_area_sqm)
        <div class="t"><strong>Recommended merged area:</strong> {{ $sqm($report->recommended_merged_area_sqm) }}</div>
        @endif
    </div>
    @if ($report->merger_remarks)
    <p style="font-size:11pt;">{{ $report->merger_remarks }}</p>
    @endif
    @endif

    {{-- Extension --}}
    @if ($report->hasPurpose('extension'))
    <div class="sub">Extension</div>
    <table class="grid">
        <thead><tr><th style="width:12mm;">#</th><th>Description of Portion</th><th>Area</th></tr></thead>
        <tbody>
            @forelse ($report->extensionPortions as $extension)
            <tr>
                <td class="sn">{{ $loop->iteration }}</td>
                <td>{{ $extension->description ?: '—' }}</td>
                <td class="num">{{ $extension->areaText() ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center;padding:14px;">No extension portions recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="totals">
        <div class="t"><strong>Total extension area:</strong> {{ $sqm($report->totalExtensionAreaSqm()) ?: '—' }}</div>
        <div class="t"><strong>Recommended total:</strong> {{ $sqm($report->recommendedTotalSiteAreaSqm()) ?: '—' }}</div>
    </div>
    @if ($report->extension_remarks)
    <p style="font-size:11pt;">{{ $report->extension_remarks }}</p>
    @endif
    @endif

    {{-- Subdivision / Separation --}}
    @if ($report->wantsSubdivisionSection())
    <div class="sub">Subdivision</div>
    <table class="grid">
        <thead><tr><th style="width:12mm;">S/N</th><th>Plot Number</th><th>Area</th><th>Remarks</th></tr></thead>
        <tbody>
            @forelse ($report->subdivisionPlots as $plot)
            <tr>
                <td class="sn">{{ $loop->iteration }}</td>
                <td><strong>{{ $plot->plot_number ?: '—' }}</strong></td>
                <td class="num">{{ $plot->areaText() ?: '—' }}</td>
                <td>{{ $plot->remarks ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:14px;">No plots recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="totals">
        <div class="t"><strong>Total allocated:</strong> {{ $sqm($report->totalSubdivisionAreaSqm()) ?: '—' }}</div>
        <div class="t"><strong>Remaining (unallocated):</strong> {{ $sqm($report->subdivisionRemainingAreaSqm()) ?: '—' }}</div>
        <div class="t"><strong>Recommended site area:</strong> {{ $sqm($report->site_recommended_area_sqm) ?: $sqm($report->recommendedTotalSiteAreaSqm()) ?: '—' }}</div>
    </div>
    @endif

    {{-- Change of purpose --}}
    @if ($report->hasPurpose('change_of_purpose'))
    <div class="sub">Change of Purpose</div>
    <table class="grid">
        <thead><tr><th style="width:12mm;">S/N</th><th>Current Land Use</th><th>Proposed Land Use</th><th>Area</th><th>Remarks</th></tr></thead>
        <tbody>
            @forelse ($report->purposeChanges as $change)
            <tr>
                <td class="sn">{{ $loop->iteration }}</td>
                <td>{{ $change->current_land_use ?: '—' }}</td>
                <td><strong>{{ $change->proposed_land_use ?: '—' }}</strong></td>
                <td class="num">{{ $change->areaText() ?: '—' }}</td>
                <td>{{ $change->remarks ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:14px;">No changes recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    @endif

    {{-- Findings --}}
    @if ($report->finding_site_suitable !== null || $report->finding_site_accessible !== null || $report->development_status || $report->officer_recommendation || $report->further_directions)
    <div class="sub">Findings</div>
    <ul class="points">
        @if ($report->finding_site_suitable !== null)
            <li>The site was found <strong>{{ $report->finding_site_suitable ? 'suitable' : 'not suitable' }}</strong> for the proposed use.</li>
        @endif
        @if ($report->finding_site_accessible !== null)
            <li>The site was found <strong>{{ $report->finding_site_accessible ? 'accessible' : 'not accessible' }}</strong>.</li>
        @endif
        @if ($report->development_status)
            <li>Development status: <strong>{{ ucfirst(str_replace('_', ' ', $report->development_status)) }}</strong>.</li>
        @endif
        @if ($report->officer_recommendation)
            <li>Recommendation: {{ $report->officer_recommendation }}</li>
        @endif
        @if ($report->further_directions)
            <li>Further directions: {{ $report->further_directions }}</li>
        @endif
    </ul>
    @endif

    @if ($report->supervisor_decision)
    <div class="sub">Supervisor</div>
    <ul class="points">
        <li>Decision: <strong>{{ strtoupper($report->supervisor_decision) }}</strong>{{ $report->returned_at ? ' on ' . $report->returned_at->format('d F Y') : '' }}.</li>
        @if ($report->supervisor_remarks)
            <li>{{ $report->supervisor_remarks }}</li>
        @endif
    </ul>
    @endif

    @endif

    <div class="sign">
        <div>
            <div class="line">
                {{ $report->inspection_officer ?: 'Physical Planning Officer' }}<br>
                <span style="font-size:9pt;">Inspection Officer {{ $report->officer_designation ? '— ' . $report->officer_designation : '' }}</span>
            </div>
        </div>
        <div>
            <div class="line">
                <span style="font-size:9pt;">Director, Physical Planning</span>
            </div>
        </div>
        @if (!$legacy)
        <div>
            <div class="line">
                {{ $report->supervisor_decision ? strtoupper($report->supervisor_decision) : 'Supervisor' }}<br>
                <span style="font-size:9pt;">Supervisor</span>
            </div>
        </div>
        @endif
    </div>

    <div class="foot">
        {{ $report->jsi_ref }} · generated {{ now()->format('d M Y H:i') }}
        @if ($report->sent_to_deeds_at) · sent to Deeds {{ $report->sent_to_deeds_at->format('d M Y') }} @endif
    </div>
</div>

</body>
</html>
