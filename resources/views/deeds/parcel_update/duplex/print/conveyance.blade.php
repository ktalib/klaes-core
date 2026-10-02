{{--
    Conveyance letter — the official Ministry format.

    Deliberately NOT the tabular sheet the application and memo use: this is the letter
    that goes out to the applicant, so it follows the house layout exactly — the title
    number on the letterhead's own "Our Ref:" rule, date right, addressee block, an
    underlined RE line, then prose.

    A duplex can carry several updates, so the RE line and the body are composed from the
    stages in execution order rather than hard-coded to one workflow.
--}}
@php
    // File numbers print exactly as the registry holds them — COM-RC-1982-420, not
    // COM/RC/1982/420. The letter used to re-punctuate them into the ministry's older
    // house style, which read as a different number from the one on every screen and
    // on the memo that accompanies this letter (client, 2026-09-02). The dashes are
    // also load-bearing: the "-RC-" token marks a recertified land file.
    $sources = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));
    $title   = trim((string) ($sources[0] ?? ''));

    $landUseName = [
        'RES' => 'residential', 'COM' => 'commercial', 'IND' => 'industrial',
        'AGR' => 'agricultural', 'AG' => 'agricultural', 'AGRIC' => 'agricultural',
        'MIX' => 'mixed use',
    ];

    $currentUse = $landUseName[strtoupper((string) $duplex->land_use)] ?? strtolower((string) $duplex->land_use);

    /** Sizes as the letter reads them: "1,500 + 2,000 + 3,000 m²", blanks skipped. */
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

        return $list->isEmpty()
            ? ['list' => '', 'total' => '', 'has' => false]
            : ['list' => $list->map($num)->implode(' + '), 'total' => $num($list->sum()), 'has' => true];
    };

    /**
     * The new purpose, in words, for the conveyance.
     *
     * Where every file in the stage changes to the same purpose this is one word.
     * Where they differ the letter must name each file and what it becomes — "RES /
     * COM" is legible on a screen but says nothing on an instrument, and picking one
     * of them would misstate the others.
     */
    $words = fn ($code) => $landUseName[strtoupper((string) $code)] ?? strtolower((string) $code);

    /**
     * The purpose being changed FROM, for one stage.
     *
     * It is the CHANGING file's own land use, not the duplex's. The duplex takes its
     * land use from the first source file, which is very often not the file being
     * changed — this letter's own case is a COM file changing inside a duplex whose
     * first source is RES, and reading it from the duplex printed "from residential
     * to residential".
     *
     * Null when the files disagree, which is the caller's signal to name them one by one.
     */
    $copCurrentUse = function ($stage) {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) ($r['current_land_use'] ?? ''))),
            $stage->copRows()
        ))));

        if (count($codes) === 1) {
            return $codes[0];
        }

        // A LATER Change of Purpose has no file rows — its parcels do not exist yet
        // when it is planned. The stage answers for them: the purpose recorded on it,
        // or failing that the one the previous Change of Purpose left them with.
        return $stage->currentLandUseLabel();
    };

    /**
     * The whole change-of-purpose clause. Three shapes, in order of preference:
     *
     *   no per-file rows      "from residential to commercial use"   (as captured before
     *                                                                 per-file purposes)
     *   one from, one to      "from commercial to residential use"
     *   anything else         "(COM/RC/82/420 from commercial to residential, ...)"
     */
    $copClause = function ($stage, $of) use ($words, $currentUse, &$copCurrentUse, &$newUseWords) {
        if (empty($stage->copRows())) {
            $from = $copCurrentUse($stage);

            return 'change of purpose' . $of . ' from ' . ($from ? $words($from) : $currentUse)
                . ' to ' . $words($stage->newLandUseLabel()) . ' use';
        }

        $from = $copCurrentUse($stage);

        if ($from && !$stage->hasMixedNewLandUses()) {
            return 'change of purpose' . $of . ' from ' . $words($from)
                . ' to ' . $words($stage->newLandUseLabel()) . ' use';
        }

        return 'change of purpose' . $of . ' (' . $newUseWords($stage) . ')';
    };

    $newUseWords = function ($stage) use ($words) {
        $parts = [];

        foreach ($stage->copRows() as $row) {
            $file = trim((string) ($row['file_no'] ?? ''));
            $to   = $words($row['new_land_use'] ?? '');
            $from = trim((string) ($row['current_land_use'] ?? ''));

            if ($file === '' || $to === '') {
                continue;
            }

            $parts[] = $file
                . ($from !== '' ? ' from ' . $words($from) : '')
                . ' to ' . $to;
        }

        return $parts ? implode(', ', $parts) : $words($stage->newLandUseLabel());
    };

    // One clause per stage, in the order the duplex runs them. These read as prose in
    // the RE line and again in the body, so they are phrased as noun phrases — with the
    // parcel sizes named, which is what makes the letter checkable against the plan.
    $clauses = [];
    foreach ($duplex->stageRows as $stage) {
        $plots = $stage->plot_count ?: count(array_filter((array) data_get($stage->payload, 'plots', [])));
        $size  = $sizesOf($stage);
        $of    = $size['has'] ? ' of ' . $size['list'] . ' m²' : '';

        $clauses[] = match ($stage->type) {
            'merger' => 'merger of ' . count($sources) . ' titles'
                . ($size['has'] ? ' measuring ' . $size['list'] . ' m²' : '')
                . ' into one',
            'subdivision' => 'subdivision into ' . $plots . ' plots' . $of,
            'separation'  => 'separation into ' . $plots . ' plots' . $of,
            'extension'   => 'extension of the plot boundary' . $of,
            'change_of_purpose' => $copClause($stage, $of),
            default => $stage->label() . $of,
        };
    }

    $reLine = strtoupper(implode(', ', $clauses));

    $applied = $duplex->created_at ? $duplex->created_at->format('jS F, Y') : '';
    $issued  = ($duplex->conveyance_generated_at ?: now())->format('jS F, Y');
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Conveyance — {{ $duplex->duplex_id }}</title>
    <style>
        /* No page margin: the letterhead artwork has to print edge-to-edge. The
           insets the letter used to take from @page (25mm/22mm) are now .page-sheet
           padding, so the text still sits where it always did. */
        @page { size: A4 portrait; margin: 0; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12.5pt;
            line-height: 1.55;
            color: #000;
            margin: 0;
        }

        /*
         * The sheet the letter prints on, with the ministry's letterhead behind it.
         *
         * Fixed 210mm x 297mm background, NOT 100% 100%: a letter that runs long grows
         * this box, and a stretched background would drag the artwork's ref box away
         * from the number pinned on top of it. no-repeat keeps the artwork on the
         * first page, which is the only page a letterhead belongs on.
         *
         * Measured off the scan (4798x6735px = 210x297mm), the same calibration the
         * consent letters use (consent_applications/templates/assignment.blade.php).
         */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 0 22mm 34mm;
            box-sizing: border-box;
            position: relative;
            background-color: #fff;
            /* background-image: url('{{ asset('assets/letterhead/bg.png') }}'); */
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: left top;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        /* Clears the artwork. The letterhead's ref box has its bottom border 62.5mm
           down the sheet, so the letter opens below that rather than across it. This
           replaces the blank band the letter used to leave for pre-printed paper. */
        .letterhead-space { height: 68mm; }

        /*
         * bg.png is a scan of a letter that was already typed on, so it carries a
         * previous file's reference (LKN/RES/RC/81/316) burned into the "Our Ref:"
         * rule. Left alone every conveyance would go out quoting that number, so the
         * patches below cover it and .our-ref prints this file's own number in its
         * place. Every rect is measured off the scan (4798x6735px = 210x297mm).
         *
         * The old number sits ON the box's bottom border - its strokes run into the
         * line at 61.9mm and a few dip below it - so the patch goes straight THROUGH
         * the border and .ref-rule draws that segment back. Stopping the patch short
         * of the line instead left 0.05mm of clearance, which rounds onto the line
         * itself at screen resolution and printed it broken.
         *
         * #fdfdfd, not #fff: the scan's paper is off-white, and a pure white patch
         * reads as a rectangle on the page.
         */
        {{-- Commented out with the background: these patch bg.png's own burned-in
             reference and put back the stretch of border they take with it, so on
             real headed paper they would print a white block and a second line
             across the ministry's ref box. Uncomment them with the background. --}}
        {{--
        .ref-mask {
            position: absolute;
            background: #fdfdfd;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* The old number's cap tops and the dotted rule share a band, so this one
           starts to the RIGHT of the "Our Ref:" label (which ends at 96.1mm and runs
           down to 59.2mm) and takes both. */
        .ref-mask-rule { left: 96.5mm; width: 39.5mm; top: 58.3mm;  height: 1.05mm; }
        /* Below the label: the number, the border it sits on, and the strokes under it. */
        .ref-mask-body { left: 92.5mm; width: 43.5mm; top: 59.35mm; height: 3.65mm; }

        /*
         * The box's bottom border, redrawn across the patched span. The scanned line
         * is very slightly tilted - 61.94mm-62.42mm where this segment starts, and
         * 61.86mm-62.32mm where it ends - so it is drawn on the average of the two
         * and meets its neighbours within 0.08mm. #3d3b48 is the line's own colour,
         * sampled off the scan; it is not black.
         */
        .ref-rule {
            position: absolute;
            left: 92.5mm;
            width: 43.5mm;
            top: 61.92mm;
            height: 0.42mm;
            background: #3d3b48;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        --}}

        /*
         * Typed onto the rule, on the label's own line: it starts 1.3mm after the
         * colon (which ends at 95.9mm) and shares the label's baseline at 59.2mm.
         * The scan had it a whole line lower and half under the label, which is what
         * made it read as a second, separate number.
         *
         * top = baseline - ascent: Times' ascent is 0.891em, so at 11pt (3.881mm)
         * that is 3.46mm above the baseline. height matches ascent + descent exactly,
         * so the box ends on the descender and nothing is clipped.
         */
        .our-ref {
            position: absolute;
            left: 97.2mm;
            top: 55.74mm;
            width: 38mm;
            height: 4.3mm;
            line-height: 4.3mm;
            font-size: 11pt;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
        }

        .date       { text-align: right; margin-bottom: 30px; }

        .addressee  { margin-bottom: 26px; }
        .addressee div { line-height: 1.35; }

        /* The RE line is the one thing a reader scans for, hence bold + underlined,
           and justified so it fills the measure like the printed original. */
        .re {
            font-weight: bold;
            text-decoration: underline;
            text-align: justify;
            text-justify: inter-word;
            margin-bottom: 22px;
        }

        p { text-align: justify; text-justify: inter-word; margin: 0 0 18px; }

        .sign-off  { margin-top: 34px; }
        .signature { margin-top: 46px; font-style: italic; font-weight: bold; line-height: 1.4; }

        /*
         * The marks belong at the FOOT OF THE PAGE, not after the last line of text —
         * a short letter left them floating halfway up the sheet. `position: fixed`
         * pins them to the bottom of the page box (inside the @page margins) and
         * repeats them if the letter ever runs to a second page.
         *
         * body gets matching bottom padding so a long letter cannot run underneath it.
         */
        .page-footer {
            position: fixed;
            /* @page has no margin any more, so the footer carries the letter's own
               22mm side insets and stands 15mm off the foot of the sheet. */
            left: 22mm;
            right: 22mm;
            bottom: 15mm;
        }

        .footer-logo {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .footer-logo img { height: 58px; width: auto; }

        /* Ask the browser to print it — background images and colour are stripped by
           default, and a logo that vanishes on paper is worse than none. */
        @media print {
            .footer-logo img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* The sheet is the page on screen too, so the preview shows what will print. */
        @media screen {
            body { background: #e5e7eb; padding: 20px 0; }
            .page-sheet { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        }

        @media print { .no-print { display: none; } }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button {
            padding: 8px 18px; font-family: system-ui, sans-serif; font-size: 13px;
            font-weight: 600; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #2563eb; color: #fff; cursor: pointer;
        }
    </style>
</head>
{{-- Opens the print dialog on load, the same as the application and recommendation
     sheets (duplex/print/_layout.blade.php). This route is only ever reached by
     clicking Print on the register, so the click has already been made - asking for a
     second one on arrival was a step with nothing behind it. The button stays for the
     reprint: cancelling the dialog must not leave the page with no way to print. --}}
<body onload="window.print()">

<div class="no-print">
    <button onclick="window.print()">Print this conveyance</button>
</div>

<div class="page-sheet">

{{-- The patches cover the previous file's reference, which is part of the bg.png
     scan rather than something the letter can leave blank; .ref-rule puts back the
     stretch of the box's border they take with it, and the number that belongs to
     THIS file is printed on the rule in its place. --}}
{{--
<div class="ref-mask ref-mask-rule" aria-hidden="true"></div>
<div class="ref-mask ref-mask-body" aria-hidden="true"></div>
<div class="ref-rule" aria-hidden="true"></div>
--}}
<div class="our-ref" data-fit-ref>{{ $title }}</div>

{{-- Opens the letter below the letterhead artwork rather than across it. --}}
<div class="letterhead-space"></div>


<div class="date">{{ $issued }}</div>

<div class="addressee">
    <div>{{ strtoupper($duplex->applicant_name ?: $duplex->file_title) }}</div>
    @if ($duplex->address)
        <div>{{ strtoupper($duplex->address) }}</div>
    @else
        {{-- The postal address is not captured on the duplex; the parcel's location is
             printed instead so the letter is not left blank. --}}
        <div>{{ strtoupper(collect([$duplex->plot_no ? 'PLOT ' . $duplex->plot_no : null, $duplex->street_name, $duplex->district, $duplex->lga])->filter()->implode(', ')) }}</div>
    @endif
    <div>{{ strtoupper($duplex->state ?: 'KANO') }} STATE.</div>
</div>

<div class="re">
    RE: APPLICATION FOR {{ $reLine }} OVER TITLE NO {{ $title }}.
</div>

<p>
    Reference to your application{{ $applied ? ' dated ' . $applied : '' }} in connection with the above
    captioned, I am directed to inform you that, your application for
    {{ implode(', ', $clauses) }} has been recommended by Kano State Urban Planning and
    Development Authority (KAMMA) in view of the fact that, the site is adequate in size
    requirement, accessible and conforms with the existing land use in the area.
</p>

<p>Above is for your kind acceptance or otherwise, please.</p>

<div class="sign-off">Yours Faithfully</div>

<div class="signature">
    <div>{{ $duplex->approvedBy->name ?? '__________________________' }}</div>
    <div>Senior Land Officer</div>
    <div>For: Honourable Commission.</div>
</div>

<div class="page-footer">
    <div class="footer-logo">
        {{-- Absolute URL on purpose: this sheet is printed and shared from environments
             that are not always the app host. --}}
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES">
        <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="LAnd ADmin Enterprise System">
    </div>
</div>

</div>

{{-- The "Our Ref:" rule is a fixed 38mm of printed artwork, so a long file number
     is stepped down until it fits rather than being clipped by it. --}}
<script>
    document.querySelectorAll('[data-fit-ref]').forEach(function (el) {
        var size = 11;
        while (el.scrollWidth > el.clientWidth && size > 5) {
            size -= 0.25;
            el.style.fontSize = size + 'pt';
        }
    });
</script>

</body>
</html>
