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
    // Neutral slates throughout: the single green parcel below is the one the
    // pin marks, and it only reads as meaningful because nothing else competes.
    $fills = [
        '#2A3138', '#232A30', '#313941', '#1F262B', '#2D343B',
        '#39414A', '#262D34', '#2F373E', '#1C2227', '#353D45',
        '#2B3239', '#3C444D', '#212830', '#303840', '#272E35',
        '#374049', '#1E242A', '#2E363D', '#333B43', '#252C33',
    ];
@endphp

<svg viewBox="0 0 640 520" class="h-auto w-full" role="presentation" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="laasPlate" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"   stop-color="#1B2228"/>
            <stop offset="100%" stop-color="#0F1418"/>
        </linearGradient>

        <linearGradient id="laasAccent" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%"   stop-color="#A9CFB9"/>
            <stop offset="100%" stop-color="#6FA98A"/>
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
              stroke="#414A53" stroke-width="1.5" opacity=".55"/>

        {{-- Parcels --}}
        @for($row = 0; $row < $n; $row++)
            @for($col = 0; $col < $n; $col++)
                @php
                    $x = $originX + $col * $cell;
                    $y = $originY + $row * $cell;
                    $isHighlight = ($col === $hiCol && $row === $hiRow);
                    $fill = $fills[($row * $n + $col) % count($fills)] ?? '#2B3239';
                @endphp

                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $size }}" height="{{ $size }}" rx="3"
                      fill="{{ $isHighlight ? 'url(#laasAccent)' : $fill }}"
                      stroke="{{ $isHighlight ? '#D6E8DD' : '#4A535C' }}"
                      stroke-width="{{ $isHighlight ? 2 : 0.9 }}"
                      opacity="{{ $isHighlight ? 1 : 0.95 }}"/>

                {{-- Survey beacon at each parcel's north-west corner --}}
                <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="#8B949D" opacity=".65"/>
            @endfor
        @endfor

        {{-- Access roads, drawn over the parcels along the gaps --}}
        @for($i = 1; $i < $n; $i++)
            <rect x="{{ $originX + $i * $cell - 4 }}" y="-166" width="4" height="332" fill="#C3CBD3" opacity=".14"/>
            <rect x="-166" y="{{ $originY + $i * $cell - 4 }}" width="332" height="4" fill="#C3CBD3" opacity=".14"/>
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
              fill="#6FA98A" stroke="#D6E8DD" stroke-width="2.5"/>
        <circle cx="0" cy="-28" r="8.5" fill="#12181D"/>
    </g>

    {{-- Floating file-number chip: the thing an applicant is actually waiting for --}}
    <g transform="translate(392 92)">
        <rect x="0" y="0" width="196" height="58" rx="12" fill="#1B2228" stroke="#3A434C" stroke-width="1.5"/>
        <rect x="14" y="15" width="28" height="28" rx="7" fill="#6FA98A"/>
        <path d="M22 24 h12 M22 29 h12 M22 34 h7" stroke="#12181D" stroke-width="2.4" stroke-linecap="round"/>
        <text x="54" y="27" fill="#98A2AC" font-family="Inter, sans-serif" font-size="10" font-weight="700"
              letter-spacing="1.4">YOUR FILE NUMBER</text>
        <text x="54" y="44" fill="#FFFFFF" font-family="Inter, sans-serif" font-size="16" font-weight="800"
              letter-spacing=".4">RES-2026-0417</text>
    </g>

    {{-- Approval chip --}}
    <g transform="translate(38 352)">
        <rect x="0" y="0" width="176" height="52" rx="12" fill="#1B2228" stroke="#3A434C" stroke-width="1.5"/>
        <circle cx="28" cy="26" r="13" fill="#6FA98A"/>
        <path d="M22 26 l4.5 4.5 L36 21" stroke="#12181D" stroke-width="3" stroke-linecap="round"
              stroke-linejoin="round" fill="none"/>
        <text x="50" y="22" fill="#98A2AC" font-family="Inter, sans-serif" font-size="9.5" font-weight="700"
              letter-spacing="1.3">RIGHT OF OCCUPANCY</text>
        <text x="50" y="38" fill="#FFFFFF" font-family="Inter, sans-serif" font-size="13.5" font-weight="800">Signed</text>
    </g>
</svg>
