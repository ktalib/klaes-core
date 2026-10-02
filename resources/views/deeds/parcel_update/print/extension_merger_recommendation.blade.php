{{--
    Plot Extension and Merger memo to the Permanent Secretary / Honourable Commissioner.

    Composed the way merger_recommendation.blade.php composes its memo - that is the
    sheet the Ministry accepted, so the page furniture is identical: 60mm binding
    sidebar, blank letterhead block, justified body, the same signature ladder, and
    absolute footer marks that survive being printed from a host that is not this app.

    What this sheet carries that the merger and extension memos do not, because a
    combined application is minuted differently:
      - TWO certificate numbers, named together in the narrative and again in the point;
      - the file page references a reader needs to follow the minute (application,
        planning letter, site plan) - the Ministry reads this beside the physical file;
      - the boundary traverse and area from the recommended site plan;
      - the term granted and the residual term still to run.

    Every value falls back to a blank rule rather than printing "—" or an empty gap:
    a memo that reaches the Permanent Secretary with a missing certificate number must
    look unfinished, not look finished and be wrong.
--}}
@php
    use Carbon\Carbon;

    /**
     * A value for the sheet, or a ruled blank the registry can fill in by hand.
     */
    $slot = static function ($value, $width = '150px') {
        $value = trim((string) $value);

        return $value !== ''
            ? '<span class="bold-caps">' . e($value) . '</span>'
            : '<span class="blank-rule" style="width: ' . $width . ';"></span>';
    };

    // Certificates. A combined application covers more than one title, and the memo
    // names each of them - "CON/COM/2024/599 AND CON/COM/2024/600".
    $certificates = collect($certificates ?? data_get($record ?? null, 'certificates') ?? [])
        ->push(data_get($record ?? null, 'file_no'))
        ->filter(fn ($number) => trim((string) $number) !== '')
        ->map(fn ($number) => Str::upper(trim($number)))
        ->unique()
        ->values();

    $certificateList = $certificates->count() > 1
        ? $certificates->slice(0, -1)->implode(', ') . ' AND ' . $certificates->last()
        : $certificates->first();

    // Where the land is. Location leads, then district, then the LGA behind it.
    // data_get rather than -> throughout: the sheet is rendered both from a $record
    // and from explicit values, and a missing property must fall to a blank rule.
    $situated = Str::upper((string) ($situated
        ?? (data_get($record ?? null, 'location') ?: data_get($record ?? null, 'district') ?: '')));
    $lga = Str::upper((string) ($lga ?? data_get($record ?? null, 'lga') ?: ''));

    $applicant = Str::upper((string) ($applicant ?? data_get($record ?? null, 'applicant_name') ?: ''));

    // The traverse off the recommended site plan, printed as one chain of sides.
    $measurements = collect($measurements ?? data_get($record ?? null, 'measurements') ?? [])
        ->filter(fn ($side) => trim((string) $side) !== '')
        ->values();

    $areaSqm = $areaSqm ?? data_get($record ?? null, 'area_sqm');
    // Hectares follow from the square metres unless the survey supplied its own.
    // Truncated, not rounded: 5661.8m2 is 0.5661Ha on the Ministry's sheet, and an
    // area that rounds UP overstates the parcel.
    $areaHa = $areaHa ?? data_get($record ?? null, 'area_ha')
        ?? ($areaSqm ? floor(((float) $areaSqm / 10000) * 10000) / 10000 : null);

    // Term. The residual is what the Commissioner is actually being asked about, so
    // it is computed from the commencement date unless the file states it outright.
    $termYears = $termYears ?? data_get($record ?? null, 'term_years');
    $termStart = $termStart ?? data_get($record ?? null, 'term_commenced_at');
    $termStart = $termStart ? Carbon::parse($termStart) : null;

    $residualYears = $residualYears ?? data_get($record ?? null, 'residual_years');
    if ($residualYears === null && $termYears && $termStart) {
        $elapsed = (int) ceil($termStart->diffInDays(now()) / 365.25);
        $residualYears = max(0, (int) $termYears - $elapsed);
    }

    // File page references. The Ministry reads this memo beside the physical file.
    $pageApplication = $pageApplication ?? data_get($record ?? null, 'page_application');
    $pagePlanningLetter = $pagePlanningLetter ?? data_get($record ?? null, 'page_planning_letter');
    $pageSitePlan = $pageSitePlan ?? data_get($record ?? null, 'page_site_plan');

    $approvalYear = $approvalYear ?? now()->year;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension and Merger Recommendation - KLAES GIS</title>
    <style>
        :root {
            --page-color: #fdf6e3;
            --accent-red: #cc0000;
        }
        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            display: flex;
            justify-content: center;
        }
        .a4-page {
            background-color: var(--page-color);
            width: 210mm;
            min-height: 297mm;
            display: flex;
            position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .left-sidebar { width: 60px; flex-shrink: 0; }
        .main-container { flex: 1; display: flex; flex-direction: column; padding: 20px 40px; }
        .header-block { height: 100px; margin-bottom: 5px; }
        .addressee { font-weight: bold; text-decoration: underline; margin-top: 15px; margin-bottom: 15px; font-size: 1.1em; }
        .body-paragraph { text-align: justify; line-height: 1.45; margin-bottom: 13px; }
        .point-block { margin-top: 15px; margin-bottom: 10px; font-weight: bold; text-align: justify; line-height: 1.45; }
        .point-block .point-mark { margin-right: 8px; }
        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .red-text { color: var(--accent-red); font-weight: bold; }

        /* The traverse off the recommended site plan. It is the only measured fact on
           the sheet, so it sits on its own line rather than inside a paragraph, and
           the sides never break mid-chain. */
        .traverse {
            margin: 4px 0 13px;
            font-weight: bold;
            line-height: 1.6;
        }
        .traverse .side { white-space: nowrap; }
        .traverse .area { display: block; margin-top: 2px; }

        /* A value the file did not supply: a rule to complete in ink. */
        .blank-rule {
            display: inline-block;
            min-width: 90px;
            border-bottom: 1px solid #000;
            vertical-align: baseline;
        }

        .signature-field-container { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; }
        .sig-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; }
        .sig-item { display: flex; align-items: flex-end; width: 45%; }
        .line-label { font-weight: bold; white-space: nowrap; margin-right: 8px; }
        .input-line { border: none; background: transparent; border-bottom: 1px solid #000; font-size: 1em; font-family: inherit; flex-grow: 1; padding-bottom: 2px; }
        /* The role a countersignature belongs to - a caption, not a field. */
        .role-caption { font-weight: bold; text-align: center; width: 45%; }

        .approval-section { border-top: 1px solid black; padding-top: 15px; margin-top: 25px; padding-bottom: 20px; }
        .approval-section .sig-row { justify-content: flex-start; gap: 10px; }
        .approval-section .sig-item { width: auto; flex-grow: 1; }
        .approval-section .input-line { width: 150px; flex-grow: 0; }
        .red-tick { color: var(--accent-red); font-size: 1.4em; margin-left: 10px; vertical-align: middle; }

        /* Signature block that ends in a ruled line and the office beneath it. */
        .endorsement { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; margin-top: 18px; }
        .endorsement .dated { display: flex; align-items: flex-end; }
        .endorsement .office { width: 45%; text-align: center; }
        .endorsement .office .rule { border-bottom: 1px solid #000; height: 22px; }
        .endorsement .office .name { font-weight: bold; text-transform: uppercase; letter-spacing: .02em; padding-top: 4px; }

        .footer-marks { margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 10px; padding-bottom: 20px; }
        .footer-marks img { height: 58px; width: auto; object-fit: contain; }

        @media print {
            body { background: none; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; border: none; }
            @page { size: A4; margin: 0; }
            /* A logo that vanishes on paper is worse than none. */
            img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="a4-page">
        <div class="left-sidebar"></div>
        <div class="main-container">
            <div class="header-block"></div>

            <div class="addressee">THE PERMANENT SECRETARY</div>

            <div class="body-paragraph">
                At page {!! $slot($pageApplication, '40px') !!} is an application for
                <span class="bold-caps">extension and merger</span> in respect of properties covered by
                certificate No. {!! $slot($certificateList, '220px') !!} situated at
                {!! $slot($situated, '150px') !!} in {!! $slot($lga, '150px') !!} Local Government Area,
                submitted by {!! $slot($applicant, '220px') !!}.
            </div>

            <div class="body-paragraph">
                The application was referred to the Physical Planning Department for planning views;
                consequently, the Department recommended the application via its recommendation letter
                at page {!! $slot($pagePlanningLetter, '40px') !!}, in view of the fact that the site is
                adequate in size requirement and the use would complement the neighbourhood.
            </div>

            <div class="body-paragraph">
                However, this recommendation is based on the recommended site plan at page
                {!! $slot($pageSitePlan, '40px') !!} and back cover, with the following measurements.
            </div>

            <div class="traverse">
                @if ($measurements->isNotEmpty())
                    @foreach ($measurements as $side)
                        <span class="side">{{ $side }}</span>@if (! $loop->last) x @endif
                    @endforeach
                @else
                    <span class="blank-rule" style="width: 100%;"></span>
                @endif
                <span class="area">
                    @if ($areaSqm)
                        ({{ rtrim(rtrim(number_format((float) $areaSqm, 2, '.', ''), '0'), '.') }}m<sup>2</sup>/{{ rtrim(rtrim(number_format((float) $areaHa, 4, '.', ''), '0'), '.') }}Ha)
                    @else
                        (<span class="blank-rule" style="width: 90px;"></span>m<sup>2</sup>/<span class="blank-rule" style="width: 70px;"></span>Ha)
                    @endif
                </span>
            </div>

            <div class="body-paragraph">
                Meanwhile, the title was granted for a term of {!! $slot($termYears, '40px') !!} years
                commencing from {!! $slot($termStart?->format('jS F Y'), '140px') !!} and now has a
                residual term of {!! $slot($residualYears, '40px') !!} years to expire.
            </div>

            <div class="body-paragraph">
                In view of the above, you may kindly wish to recommend the following for approval of the
                <span class="bold-caps">Honourable Commissioner</span>:</div>

            <div class="point-block">
                <span class="point-mark">i.)</span>Consider and approve the application for extension and
                merger situated at {!! $slot($situated, '150px') !!} in {!! $slot($lga, '150px') !!} Local
                Government Area, covered by certificate No. {!! $slot($certificateList, '220px') !!} in
                favour of {!! $slot($applicant, '220px') !!} please.
            </div>

            {{-- Minuting officer on the left, Director Deeds countersigning on the right. --}}
            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Name:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Countersign:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Rank:</span> <input type="text" class="input-line"></div>
                    <div class="role-caption">Director Deeds</div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Date:</span> <input type="text" class="input-line"></div>
                    <div class="role-caption"></div>
                </div>
            </div>

            <div class="addressee">THE HONOURABLE COMMISSIONER</div>
            <div class="body-paragraph">The application is hereby recommended for your kind approval, please.</div>
            <div class="endorsement">
                <div class="dated">
                    <span class="line-label">Date:</span>
                    <input type="text" class="input-line" style="width: 150px; flex-grow: 0;"> {{ $approvalYear }}.
                </div>
                <div class="office">
                    <div class="rule"></div>
                    <div class="name">Permanent Secretary</div>
                </div>
            </div>

            <div class="approval-section">
                <div class="addressee" style="margin-top: 0;">PERMANENT SECRETARY</div>
                <div class="body-paragraph">The application is hereby APPROVED/NOT APPROVED.</div>
                <div class="endorsement">
                    <div class="dated">
                        <span class="line-label">Date:</span>
                        <input type="text" class="input-line" style="width: 150px; flex-grow: 0;"> {{ $approvalYear }}.
                    </div>
                    <div class="office">
                        <div class="rule"></div>
                        <div class="name">The Honourable Commissioner</div>
                    </div>
                </div>
            </div>

            <div class="footer-marks">
                {{-- Absolute URLs, as on the merger and duplex memos, and for the same
                     reason: these sheets are printed from hosts that are not always
                     this app. KLAES left, LAnd ADmin right. --}}
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES">
                <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="LAnd ADmin Enterprise System">
            </div>
        </div>
    </div>
</body>
</html>
