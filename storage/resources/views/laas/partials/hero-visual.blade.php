@php
    /**
     * Aerial cadastral parcel map — the hero visual.
     *
     * Drawn rather than photographed on purpose: it echoes the parcel grid in
     * the LAAS mark, stays sharp at any viewport, carries no licensing burden,
     * and weighs a few kB instead of a few hundred. If a licensed aerial photo
     * of Kano is supplied later it can replace this block outright — nothing
     * else in the hero depends on it.
     *
     * Isometric projection: x' = (x - y)·0.866, y' = (x + y)·0.42, expressed as
     * the matrix below. Purely decorative, so it is hidden from screen readers —
     * the headline beside it carries all the meaning.
     */
    $cell = 56;   // pitch
    $size = 52;   // parcel, leaving a 4-unit road between
    $n    = 5;    // grid is n × n
    $originX = $originY = -140;

    // The one parcel that is called out, with a pin above it.
    $hiCol = 3;
    $hiRow = 1;

    // Deliberately irregular fills so the grid reads as land, not as a chart.
    $fills = [
        '#0F5A38', '#124D33', '#166F49', '#0D4630', '#135C3C',
        '#1A7C53', '#0F5233', '#14653F', '#0C3F2A', '#177047',
        '#125738', '#1C8558', '#0E4A31', '#166644', '#0F5334',
        '#1A7A50', '#0D4229', '#14603E', '#187B51', '#115236',
    ];
@endphp

<svg viewBox="0 0 640 520" class="h-auto w-full" role="presentation" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="laasPlate" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"   stop-color="#0A3D27"/>
            <stop offset="100%" stop-color="#052517"/>
        </linearGradient>

        <linearGradient id="laasGold" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%"   stop-color="#FFD34D"/>
            <stop offset="100%" stop-color="#F5B301"/>
        </linearGradient>

        <filter id="laasGlow" x="-40%" y="-40%" width="180%" height="180%">
            <feGaussianBlur stdDeviation="18" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
    </defs>

    {{-- Ground plate --}}
    <g transform="matrix(0.866 0.42 -0.866 0.42 320 250)">
        <rect x="-166" y="-166" width="332" height="332" rx="10" fill="url(#laasPlate)"/>
        <rect x="-166" y="-166" width="332" height="332" rx="10" fill="none"
              stroke="#2E7D5B" stroke-width="1.5" opacity=".55"/>

        {{-- Parcels --}}
        @for($row = 0; $row < $n; $row++)
            @for($col = 0; $col < $n; $col++)
                @php
                    $x = $originX + $col * $cell;
                    $y = $originY + $row * $cell;
                    $isHighlight = ($col === $hiCol && $row === $hiRow);
                    $fill = $fills[($row * $n + $col) % count($fills)] ?? '#125738';
                @endphp

                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $size }}" height="{{ $size }}" rx="3"
                      fill="{{ $isHighlight ? 'url(#laasGold)' : $fill }}"
                      stroke="{{ $isHighlight ? '#FFE9A3' : '#3C9670' }}"
                      stroke-width="{{ $isHighlight ? 2 : 0.9 }}"
                      opacity="{{ $isHighlight ? 1 : 0.95 }}"/>

                {{-- Survey beacon at each parcel's north-west corner --}}
                <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="#7FD9AE" opacity=".7"/>
            @endfor
        @endfor

        {{-- Access roads, drawn over the parcels along the gaps --}}
        @for($i = 1; $i < $n; $i++)
            <rect x="{{ $originX + $i * $cell - 4 }}" y="-166" width="4" height="332" fill="#9BE3C1" opacity=".18"/>
            <rect x="-166" y="{{ $originY + $i * $cell - 4 }}" width="332" height="4" fill="#9BE3C1" opacity=".18"/>
        @endfor
    </g>

    @php
        // Projected screen position of the highlighted parcel's centre.
        $gx = $originX + $hiCol * $cell + $size / 2;
        $gy = $originY + $hiRow * $cell + $size / 2;
        $px = round(0.866 * $gx - 0.866 * $gy + 320, 1);
        $py = round(0.42  * $gx + 0.42  * $gy + 250, 1);
    @endphp

    {{-- Location pin over the called-out parcel --}}
    <g transform="translate({{ $px }} {{ $py }})" filter="url(#laasGlow)">
        <ellipse cx="0" cy="2" rx="13" ry="5" fill="#000" opacity=".28"/>
        <path d="M0,-4 C-13,-4 -22,-14 -22,-27 C-22,-41 -12,-51 0,-51 C12,-51 22,-41 22,-27 C22,-14 13,-4 0,-4 Z"
              fill="#F5B301" stroke="#FFE9A3" stroke-width="2.5"/>
        <circle cx="0" cy="-28" r="8.5" fill="#06301E"/>
    </g>

    {{-- Floating file-number chip: the thing an applicant is actually waiting for --}}
    <g transform="translate(392 92)">
        <rect x="0" y="0" width="196" height="58" rx="12" fill="#0E3A26" stroke="#2E7D5B" stroke-width="1.5"/>
        <rect x="14" y="15" width="28" height="28" rx="7" fill="#F5B301"/>
        <path d="M22 24 h12 M22 29 h12 M22 34 h7" stroke="#1A1200" stroke-width="2.4" stroke-linecap="round"/>
        <text x="54" y="27" fill="#9BE3C1" font-family="Inter, sans-serif" font-size="10" font-weight="700"
              letter-spacing="1.4">YOUR FILE NUMBER</text>
        <text x="54" y="44" fill="#FFFFFF" font-family="Inter, sans-serif" font-size="16" font-weight="800"
              letter-spacing=".4">RES-2026-0417</text>
    </g>

    {{-- Approval chip --}}
    <g transform="translate(38 352)">
        <rect x="0" y="0" width="176" height="52" rx="12" fill="#0E3A26" stroke="#2E7D5B" stroke-width="1.5"/>
        <circle cx="28" cy="26" r="13" fill="#34D399"/>
        <path d="M22 26 l4.5 4.5 L36 21" stroke="#04281A" stroke-width="3" stroke-linecap="round"
              stroke-linejoin="round" fill="none"/>
        <text x="50" y="22" fill="#9BE3C1" font-family="Inter, sans-serif" font-size="9.5" font-weight="700"
              letter-spacing="1.3">RIGHT OF OCCUPANCY</text>
        <text x="50" y="38" fill="#FFFFFF" font-family="Inter, sans-serif" font-size="13.5" font-weight="800">Signed</text>
    </g>
</svg>
