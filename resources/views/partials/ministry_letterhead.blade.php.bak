{{--
    Vector/CSS reproduction of the MLPP Kano pre-printed letterhead.

    Replaces the scanned background image so print output stays crisp and the
    file stays small. Drop it as the FIRST child of a `.a4-page` (or any
    `position: relative` A4 box); it paints itself absolutely and never takes
    part in the page's flow.

    The body content of the host page must keep clear of it:
        padding-top:  ~42mm   (below the top rule)
        padding-left: ~34mm   (right of the vertical rule / red spine)

    Optional overrides:
        @include('partials.ministry_letterhead', [
            'lhMinistry' => 'Ministry of Land and Physical Planning',
            'lhState'    => 'Kano State',
        ])
--}}
@php
    // Kano State coat of arms. TODO: replace with a high-resolution transparent
    // PNG — this source is only 92x92 and prints soft at 20mm.
    $lhCrestRel  = 'assets/logo/ministry1.jpg';
    $lhCrestPath = public_path($lhCrestRel);
    $lhCrest = file_exists($lhCrestPath)
        ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($lhCrestPath))
        : asset($lhCrestRel);

    $lhMinistry = $lhMinistry ?? 'Ministry of Land and Physical Planning';
    $lhState    = $lhState    ?? 'Kano State';
    $lhSpine    = $lhSpine    ?? $lhMinistry;
@endphp

<style>
    .lh {
        --lh-green: #1b7a3d;
        --lh-red: #c1272d;
        --lh-rule: 3px;

        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        color-adjust: exact;
    }

    /* Vertical rule down the left, separating the red spine from the body. */
    .lh-rule-v {
        position: absolute;
        left: 30mm;
        top: 9mm;
        bottom: 9mm;
        border-left: var(--lh-rule) solid var(--lh-green);
    }

    /* Full-width rule at the crest's mid-height — the crest and the ministry
       name sit ON it, masking it out where they overlap (see .lh-head). It
       starts 12mm left of the vertical rule so the two cross. */
    .lh-rule-top {
        position: absolute;
        left: 18mm;
        right: 12mm;
        top: 30mm;
        border-top: var(--lh-rule) solid var(--lh-green);
    }

    /* Red spine: ministry name rotated to read bottom-to-top. */
    /* Starts below .lh-rule-top so the text never runs into the crossbar. */
    .lh-spine {
        position: absolute;
        left: 0;
        top: 38mm;
        bottom: 10mm;
        width: 30mm;
    }
    .lh-spine span {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%) rotate(-90deg);
        transform-origin: center center;
        white-space: nowrap;
        font-family: 'Arial Narrow', 'Helvetica Neue', Arial, sans-serif;
        /* Sized to fill the spine between the crossbar and the foot of the sheet. */
        font-size: 24pt;
        font-weight: 900;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--lh-red);
        line-height: 1;
    }

    /* Crest + ministry name, sitting above the top rule. */
    /* Crest + ministry name, bottom-aligned so .lh-rule-top runs directly
       beneath the title rather than through it. The crest is dropped below the
       baseline to straddle the rule, and carries a white background so the
       rule stops at its edges and resumes after it — as on the stationery. */
    .lh-head {
        position: absolute;
        left: 34mm;
        right: 12mm;
        top: 8mm;
        height: 22mm; /* bottom edge lands on .lh-rule-top at 30mm */
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
        gap: 0;
    }
    .lh-crest {
        height: 20mm;
        width: auto;
        object-fit: contain;
        flex-shrink: 0;
        background: #fff;
        padding: 0 3mm;
        margin-bottom: -6mm; /* straddles the rule */
    }
    .lh-titles {
        font-family: 'Arial Narrow', 'Helvetica Neue', Arial, sans-serif;
        color: var(--lh-green);
        text-transform: uppercase;
        text-align: center;
        line-height: 1.05;
        margin-bottom: 1mm;
    }
    /* Sized for plain Arial, not Arial Narrow — the condensed face is not
       installed everywhere and the fallback must still fit on one line. */
    .lh-title {
        font-size: 13pt;
        font-weight: 900;
        letter-spacing: 0.01em;
        white-space: nowrap; /* must stay on one line, as on the stationery */
    }
    .lh-subtitle {
        font-size: 13pt; /* matches .lh-title */
        font-weight: 900;
        letter-spacing: 0.16em;
        margin-top: 1mm;
    }

    @media print {
        .lh { overflow: visible; }
    }
</style>

<div class="lh" aria-hidden="true">
    <div class="lh-spine"><span>{{ $lhSpine }}</span></div>

    {{-- Rules first: .lh-head paints over them to mask the top rule. --}}
    <div class="lh-rule-v"></div>
    <div class="lh-rule-top"></div>

    <div class="lh-head">
        <img class="lh-crest" src="{{ $lhCrest }}" alt="">
        <div class="lh-titles">
            <div class="lh-title">{{ $lhMinistry }}</div>
            <div class="lh-subtitle">{{ $lhState }}</div>
        </div>
    </div>
</div>
