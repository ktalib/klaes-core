{{--
    Duplex memo to the Permanent Secretary / Honourable Commissioner.

    Same sheet as the single-workflow memos (subdivision_recommendation.blade.php et al)
    so it reads as one of the family — but the prose and the lettered points are composed
    from the duplex's stages IN EXECUTION ORDER. A duplex is one instruction carrying
    several updates, so "seeks to subdivide the property into 3 plots" cannot be
    hard-coded: it becomes one clause per stage, and one lettered point per stage.
--}}
@php
    $sources   = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));
    $title     = $sources[0] ?? '';
    $applicant = Str::upper($duplex->applicant_name ?: $duplex->file_title ?: '');
    $situated  = Str::upper($duplex->district ?: $duplex->lga ?: $duplex->address ?: '');

    $landUseName = [
        'RES' => 'residential', 'COM' => 'commercial', 'IND' => 'industrial',
        'AGR' => 'agricultural', 'AG' => 'agricultural', 'AGRIC' => 'agricultural',
        'MIX' => 'mixed use',
    ];
    $currentUse = $landUseName[Str::upper((string) $duplex->land_use)] ?? Str::lower((string) $duplex->land_use);

    /**
     * The parcel sizes a stage records, as the sheet reads them: "1.5 + 2 + 3" with a
     * total. A blank size is skipped rather than printed as 0, so a partly-filled stage
     * still produces a sensible line.
     */
    // Square metres run to five figures, so they carry a thousands separator: a plot
    // reads "1,410" not "1410". Fractional metres are kept where a survey gives them.
    $num = function ($v) {
        $f = (float) $v;
        return $f == floor($f)
            ? number_format($f, 0, '.', ',')
            : rtrim(rtrim(number_format($f, 2, '.', ','), '0'), '.');
    };

    $sizesOf = function ($stage) use ($num) {
        $list = collect((array) data_get($stage->payload, 'plots', []))
            ->pluck('size')
            ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->values();

        if ($list->isEmpty()) {
            return ['list' => '', 'total' => '', 'has' => false];
        }

        return [
            'list'  => $list->map($num)->implode(' + '),
            'total' => $num($list->sum()),
            'has'   => true,
        ];
    };

    /**
     * The parcels' dimensions, lettered, the way the Ministry's memo states them:
     *
     *     A: 60.00m x 21.00m x 46.00m x 21.00m x 42.71m.
     *     B: 21.00m x 95.60m x 16.00m x 47.00m.
     *
     * Sides, not a length and a width — these are surveyed polygons. Printed only for
     * the parcels that have them, so a stage captured before dimensions existed reads
     * exactly as it did before.
     */
    $dimensionsOf = function ($stage) use ($num) {
        $letters = range('A', 'Z');
        $lines   = [];

        foreach (array_values((array) data_get($stage->payload, 'plots', [])) as $i => $plot) {
            $sides = collect((array) ($plot['dimensions'] ?? []))
                ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
                // Always two decimals, as the Ministry writes them: "60.00m", not "60m".
                // A survey figure carries its precision - dropping it makes a measured
                // side read like a rounded one.
                ->map(fn ($v) => number_format((float) $v, 2, '.', ',') . 'm')
                ->values();

            if ($sides->isEmpty()) {
                continue;
            }

            $lines[] = ($letters[$i] ?? ($i + 1)) . ': ' . $sides->implode(' x ') . '.';
        }

        return $lines;
    };

    // How many parcels each stage RECEIVES, walked forward through the chain. A merger
    // at rank 3 consumes what rank 2 produced, not the duplex's original source files —
    // reading the source count there would misstate the memo.
    $incoming = count($sources);

    $stages = [];
    foreach ($duplex->stageRows as $stage) {
        $plots   = $stage->plot_count ?: count(array_filter((array) data_get($stage->payload, 'plots', [])));
        $newUse  = Str::upper((string) $stage->newLandUseLabel());
        $newName = $landUseName[$newUse] ?? Str::lower($newUse);

        // The purpose being changed FROM is the CHANGING file's own, not the duplex's.
        // The duplex takes its land use from the first source file, which is often not
        // the file being changed — reading it from there printed "from residential to
        // residential" on a duplex whose first source happened to be residential.
        $fromCodes = array_values(array_unique(array_filter(array_map(
            fn ($r) => Str::upper(trim((string) ($r['current_land_use'] ?? ''))),
            $stage->copRows()
        ))));
        // A later Change of Purpose has no file rows: its parcels are minted by an
        // earlier stage, so the stage answers for them — from what was recorded on it,
        // or from what the previous Change of Purpose left those parcels as.
        $stageFrom = Str::upper((string) $stage->currentLandUseLabel());

        $fromName = count($fromCodes) === 1
            ? ($landUseName[$fromCodes[0]] ?? Str::lower($fromCodes[0]))
            : ($stageFrom !== '' ? ($landUseName[$stageFrom] ?? Str::lower($stageFrom)) : $currentUse);

        // Where the files do not share one answer, the point names them one by one
        // rather than stating a single purpose on behalf of all of them.
        $copDetail = $stage->hasMixedNewLandUses() || count($fromCodes) > 1
            ? collect($stage->copRows())->map(function ($r) use ($landUseName) {
                $to   = Str::upper(trim((string) ($r['new_land_use'] ?? '')));
                $from = Str::upper(trim((string) ($r['current_land_use'] ?? '')));
                return str_replace('-', '/', (string) ($r['file_no'] ?? ''))
                    . ($from !== '' ? ' from ' . ($landUseName[$from] ?? Str::lower($from)) : '')
                    . ' to ' . ($landUseName[$to] ?? Str::lower($to));
            })->filter()->implode(', ')
            : null;
        $applies = count((array) data_get($stage->payload, 'applies_to', [])) ?: $plots;
        $size    = $sizesOf($stage);

        // "of 1,500 + 2,000 + 3,000 m² (total 6,500 m²)" — appended wherever a parcel is named.
        $sizePhrase = $size['has'] ? ' of ' . $size['list'] . ' m²' : '';

        // What this stage acts on, named where it is known.
        // Cast: sqlsrv hands rank back as a STRING, so a strict === 1 never matched and
        // stage 1 printed "the resulting parcel" instead of naming the source files it
        // actually consumes. Same trap DuplexSummaryService already guards against.
        $actsOn = (int) $stage->rank === 1
            ? implode(', ', array_map(fn ($f) => Str::upper($f), $sources))
            : 'the resulting parcel' . ($incoming === 1 ? '' : 's');

        $stages[] = [
            'name'  => Str::upper($stage->label()),
            'dimensions' => $dimensionsOf($stage),
            // Just the count and the name — the memo lists what is being applied for,
            // it does not narrate it. The lettered points below carry the detail.
            'seeks' => match ($stage->type) {
                'merger'      => $incoming . ' ' . $stage->label(),
                'extension'   => '1 ' . $stage->label(),
                'change_of_purpose' => $applies . ' ' . $stage->label(),
                default       => $plots . ' ' . $stage->label(),
            },
            'point' => match ($stage->type) {
                'merger'      => 'Merger of ' . $actsOn
                                 . ($size['has'] ? ' measuring ' . $size['list'] . ' m²' : '')
                                 . ' into a single parcel in favour of ' . $applicant . '.',
                'subdivision' => 'Subdivision of plot no. ' . Str::upper((string) ($duplex->plot_no ?: $title))
                                 . ' into ' . $plots . ' parcels' . $sizePhrase
                                 . ' as per the attached plan in favour of ' . $applicant . '.',
                'separation'  => 'Separation of plot no. ' . Str::upper((string) ($duplex->plot_no ?: $title))
                                 . ' into ' . $plots . ' parcels' . $sizePhrase
                                 . ' as per the attached plan in favour of ' . $applicant . '.',
                'extension'   => 'Extension of the boundary of plot no. ' . Str::upper((string) ($duplex->plot_no ?: $title))
                                 . $sizePhrase . ' as per the attached plan in favour of ' . $applicant . '.',
                'change_of_purpose' => 'Change of purpose of ' . $applies . ' parcel' . ($applies === 1 ? '' : 's')
                                 . $sizePhrase
                                 . ($copDetail
                                     ? ' — ' . $copDetail
                                     : ' from ' . $fromName . ' to ' . $newName . ' use')
                                 . ' in favour of ' . $applicant . '.',
                default => $stage->label() . $sizePhrase . ' in favour of ' . $applicant . '.',
            },
        ];

        // A Change of Purpose renames some of what it receives and passes the rest on,
        // so the count going forward does not change; everything else resets it.
        $incoming = match ($stage->type) {
            'merger', 'extension' => 1,
            'change_of_purpose'   => $incoming,
            default               => $plots,
        };
    }

    // The subject names WHAT is applied for, so a type that runs twice is named once —
    // "CHANGE OF PURPOSE, MERGER, SUBDIVISION", the way the applicant's own letter reads,
    // not "…, CHANGE OF PURPOSE" again at the end. First-occurrence order is kept, and
    // the counts line below still reports both legs separately.
    $headline = implode(', ', array_unique(array_column($stages, 'name')));

    // "5 Subdivision, 3 Change of Purpose and 1 Merger"
    $parts = array_column($stages, 'seeks');
    $seeks = count($parts) > 1
        ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts)
        : ($parts[0] ?? '');
    $letters  = range('a', 'z');
    $single   = $duplex->isSingleStage();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplex Recommendation - KLAES GIS</title>
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
        /*
         * The memo's own letterhead, behind the sheet. This is the plain headed paper
         * the memo has always been printed on - the red spine down the left, the arms
         * and ministry name across the top - not the ref-box letterhead the conveyance
         * uses (duplex/print/conveyance.blade.php uses assets/letterhead/bg.png).
         *
         * Fixed 210mm x 297mm, NOT 100% 100%: a memo that runs to a second page grows
         * this box, and a stretched background would pull the spine off the margin the
         * text is set against. no-repeat keeps it on the first page.
         */
        .a4-page {
            background-color: var(--page-color);
            /* background-image: url('{{ asset('assets/letterhead/letterheader.jpeg') }}'); */
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: left top;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            width: 210mm;
            min-height: 297mm;
            display: flex;
            position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        /* Wide enough to clear the artwork, measured off it: the red spine runs to
           25mm and the green rule sits at 29.5mm-30.4mm. With .main-container's 40px
           padding the text starts at 36mm, ~5.5mm clear of the rule. */
        .left-sidebar { width: 25.4mm; flex-shrink: 0; }
        .main-container { flex: 1; display: flex; flex-direction: column; padding: 20px 40px 10px; }
        /* The artwork's own space at the head of the sheet, so the addressee starts
           below it rather than across it. The arms and ministry name run to 36mm;
           130px plus .main-container's 20px padding puts the first line at 41mm. */
        .header-block { height: 130px; margin-bottom: 5px; }
        /* Spacing kept tight so a two-stage memo with its dimension lines still
           fits one A4 sheet, signature blocks and logos included. */
        .addressee { font-weight: bold; text-decoration: underline; margin-top: 8px; margin-bottom: 8px; font-size: 1.1em; }
        .body-paragraph { text-align: justify; line-height: 1.3; margin-bottom: 8px; }
        .point-block { margin-top: 8px; margin-bottom: 4px; font-weight: bold; }
        .dimension-block { margin: 4px 0 6px; }
        .dimension-block p { margin: 0 0 2px; }
        .dimension-line { font-weight: bold; margin-left: 40px !important; }
        .signature-field-container { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; margin-bottom: 12px; }
        .sig-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; }
        .sig-item { display: flex; align-items: flex-end; width: 45%; }
        .line-label { font-weight: bold; white-space: nowrap; margin-right: 8px; }
        .input-line { border: none; background: transparent; border-bottom: 1px solid #000; font-size: 1em; font-family: inherit; flex-grow: 1; padding-bottom: 2px; }
        .approval-section { border-top: 1px solid black; padding-top: 8px; margin-top: 12px; padding-bottom: 6px; }
        .approval-section .sig-row { justify-content: flex-start; gap: 10px; }
        .approval-section .sig-item { width: auto; flex-grow: 1; }
        .approval-section .input-line { width: 150px; flex-grow: 0; }
        .red-tick { color: var(--accent-red); font-size: 1.4em; margin-left: 10px; vertical-align: middle; }
        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .red-text { color: var(--accent-red); font-weight: bold; }
        @media print {
            body { background: none; margin: 0; padding: 0; }
            /* 296mm, not 297mm: a box exactly equal to the page makes Chrome emit a
               trailing blank sheet. Still a min-height, so a memo too long for one
               page runs on rather than being clipped. */
            .a4-page { box-shadow: none; margin: 0; border: none; min-height: 296mm; }
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
            <div class="addressee">PERMANENT SECRETARY.</div>

            <div class="body-paragraph">
                At Page 1 is an application for <span class="bold-caps">{{ $headline }}</span> over a piece of
                land situated <span class="bold-caps">{{ $situated }}</span> covered by Certificate of occupancy
                no. <span class="bold-caps">{{ Str::upper($title) }}</span> in favour of
                <span class="bold-caps">{{ $applicant }}.</span>
            </div>

            <div class="body-paragraph">
                The application seeks {{ $seeks }}. Verification at Cadastral and Deeds Departments
                confirms that the title is free from encumbrances and suitable for the proposed
                {{ $single ? Str::lower($stages[0]['name']) : 'updates' }}.
            </div>

            <div class="body-paragraph">
                In view of the above, you may kindly wish to recommend this application for
                {{ $single ? Str::title($stages[0]['name']) : 'these updates' }} to
                <span class="bold-caps">Honourable Commissioner</span> for approval of:
            </div>

            @foreach ($stages as $i => $stage)
                <div class="point-block">{{ $letters[$i] }}.) {{ $stage['point'] }}</div>

                @if (!empty($stage['dimensions']))
                    {{-- "Find the recommended site plan at back cover showing the dimension as:"
                         — the parcels' sides, lettered, indented as on the Ministry's sheet. --}}
                    <div class="dimension-block">
                        <p>Find the recommended site plan at back cover showing the dimension as:</p>
                        @foreach ($stage['dimensions'] as $line)
                            <p class="dimension-line">{{ $line }}</p>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Name:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Counter Sign:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Rank:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Director Land:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; flex-grow: 1; min-width: 150px;"></span></div>
                </div>
            </div>

            <div class="addressee">HONOURABLE COMMISSIONER:</div>
            <div class="body-paragraph">The application is hereby recommended for your kind approval, please.</div>
            <div class="signature-field-container" style="margin-bottom: 10px;">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span></div>
                </div>
                <div class="sig-row"><div class="sig-item"><span class="line-label">Permanent Secretary.</span></div></div>
            </div>

            <div class="approval-section">
                <div class="addressee" style="margin-top: 0;">PERMANENT SECRETARY.</div>
                <div class="body-paragraph">The application is hereby Approved/<span style="text-decoration: line-through;">Not Approved:</span> <span class="red-tick"></span></div>
                <div class="signature-field-container" style="margin-bottom: 10px;">
                    <div class="sig-row">
                        <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                        <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span></div>
                    </div>
                    <div class="sig-row"><div class="sig-item"><span class="line-label">Honourable Commissioner.</span></div></div>
                </div>
            </div>

            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 8px; padding-bottom: 8px;">
                {{-- Same marks as the conveyance letter, and absolute for the same
                     reason: these sheets are printed from hosts that are not always
                     this app. KLAES left, LAnd ADmin right. --}}
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" style="height: 58px; width: auto; object-fit: contain;">
                {{-- 40.6px is 58px less 30%: this mark carries its own padding inside the file,
                     so at the KLAES logo's height it reads as the larger of the two. --}}
                <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="LAnd ADmin Enterprise System" style="height: 40.6px; width: auto; object-fit: contain;">
            </div>
        </div>
    </div>
</body>
</html>
