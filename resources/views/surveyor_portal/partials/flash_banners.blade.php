{{-- Flash banners (CR 11112026-2, item 11): NIS and SURCON notices rotating in a strip at the top of the
     MLPP-SG portal. Wording, links and on/off in Configurable Entries > Surveyor Portal. Turns every 7 s,
     pauses on hover or focus, and stays still for people who ask for reduced motion. --}}
@php
  $fbOn = (string) ($portalSettings['flash_banners_on'] ?? '1') === '1';
  $fbItems = $fbOn ? (array) ($portalSettings['flash_banners'] ?? []) : [];
  $fbTone = ['SURCON' => ['#0B3A7E', '#E8EFFA'], 'NIS' => ['#15803D', '#E7F6EC'], 'MLPP-SG' => ['#8A6A12', '#FBF3DC']];
  // Each body's logo beside its notice (user, 2026-11-12). MLPP-SG uses the portal's own seal.
  $fbLogo = [
    'SURCON' => ['assets/logo/surcon-logo.png', 'Surveyors Council of Nigeria (SURCON) logo'],
    'NIS' => ['assets/logo/nis-logo.png', 'Nigerian Institution of Surveyors (NIS) logo'],
    'MLPP-SG' => [$portalSettings['logo_seal'] ?? 'assets/logo/mlppsg-logo.png', 'MLPP-SG seal'],
  ];
  // A second notice from the same body shows that body's second logo, if it has one.
  $fbLogoAlt = ['SURCON' => 'assets/logo/surcon-logo-2.png'];
  $fbSeen = [];
@endphp
@if ($fbItems)
<div class="ks-notice" id="ksNotice" role="region" aria-roledescription="carousel" aria-label="NIS and SURCON notices">
  <style>
    .ks-notice { background: #0F172A; color: #fff; border-bottom: 1px solid rgba(255,255,255,.08); }
    .ks-notice-in { max-width: 1200px; margin: 0 auto; padding: 8px 16px; display: flex; align-items: center; gap: 12px; min-height: 44px; }
    .ks-notice-stack { flex: 1; min-width: 0; display: grid; }
    .ks-notice-item { grid-area: 1 / 1; display: flex; align-items: center; gap: 10px; min-width: 0; opacity: 0; visibility: hidden; transition: opacity .45s ease; }
    .ks-notice-item.is-on { opacity: 1; visibility: visible; }
    .ks-notice-logo { flex: none; width: 34px; height: 34px; object-fit: contain; border-radius: 50%; background: #fff; padding: 1px; }
    .ks-notice-badge { flex: none; font-size: 11px; font-weight: 800; letter-spacing: .06em; padding: 3px 8px; border-radius: 999px; }
    .ks-notice-copy { min-width: 0; font-size: 13.5px; line-height: 1.35; }
    .ks-notice-copy strong { font-weight: 700; margin-right: 6px; }
    .ks-notice-copy a { color: #FCD34D; font-weight: 700; white-space: nowrap; margin-left: 6px; }
    .ks-notice-nav { flex: none; display: flex; align-items: center; gap: 4px; }
    .ks-notice-nav button { background: rgba(255,255,255,.1); color: #fff; border: 0; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; font-size: 15px; line-height: 1; }
    .ks-notice-nav button:hover, .ks-notice-nav button:focus-visible { background: rgba(255,255,255,.22); }
    .ks-notice-count { font-size: 11px; color: rgba(255,255,255,.7); min-width: 34px; text-align: center; font-variant-numeric: tabular-nums; }
    @media (max-width: 640px) { .ks-notice-copy { font-size: 12.5px; } .ks-notice-badge { display: none; } }
    @media (prefers-reduced-motion: reduce) { .ks-notice-item { transition: none; } }
  </style>
  <div class="ks-notice-in">
    <div class="ks-notice-stack" aria-live="polite">
      @foreach ($fbItems as $i => $b)
        @php [$fbFg, $fbBg] = $fbTone[$b['org']] ?? ['#0F172A', '#E5E7EB']; @endphp
        <div class="ks-notice-item {{ $i === 0 ? 'is-on' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ count($fbItems) }}" @if ($i) aria-hidden="true" @endif>
          @php
            $fbNth = $fbSeen[$b['org']] = ($fbSeen[$b['org']] ?? 0) + 1;
            $fbSrc = $fbNth % 2 === 0 && isset($fbLogoAlt[$b['org']]) ? $fbLogoAlt[$b['org']] : ($fbLogo[$b['org']][0] ?? null);
          @endphp
          @if (isset($fbLogo[$b['org']]))
            <img class="ks-notice-logo" src="{{ asset($fbSrc) }}" alt="{{ $fbLogo[$b['org']][1] }}" width="34" height="34">
          @endif
          <span class="ks-notice-badge" style="background:{{ $fbBg }};color:{{ $fbFg }};">{{ $b['org'] }}</span>
          <span class="ks-notice-copy"><strong>{{ $b['title'] }}</strong>{{ $b['text'] }}@if ($b['link'])<a href="{{ $b['link'] }}" target="_blank" rel="noopener" @if ($i) tabindex="-1" @endif>Read more →</a>@endif</span>
        </div>
      @endforeach
    </div>
    @if (count($fbItems) > 1)
      <div class="ks-notice-nav">
        <button type="button" data-fb="prev" aria-label="Previous notice">‹</button>
        <span class="ks-notice-count" data-fb-count>1 / {{ count($fbItems) }}</span>
        <button type="button" data-fb="next" aria-label="Next notice">›</button>
      </div>
    @endif
  </div>
  @if (count($fbItems) > 1)
  <script>
  (function () {
    var box = document.getElementById('ksNotice'); if (!box) return;
    var items = box.querySelectorAll('.ks-notice-item'), count = box.querySelector('[data-fb-count]'), i = 0, timer = null, held = false;
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function show(n) {
      i = (n + items.length) % items.length;
      items.forEach(function (el, k) {
        var on = k === i;
        el.classList.toggle('is-on', on);
        el.setAttribute('aria-hidden', on ? 'false' : 'true');
        var a = el.querySelector('a'); if (a) a.tabIndex = on ? 0 : -1;
      });
      if (count) count.textContent = (i + 1) + ' / ' + items.length;
    }
    function start() { if (still || held) return; clearInterval(timer); timer = setInterval(function () { show(i + 1); }, 7000); }
    box.querySelector('[data-fb="prev"]').addEventListener('click', function () { show(i - 1); start(); });
    box.querySelector('[data-fb="next"]').addEventListener('click', function () { show(i + 1); start(); });
    box.addEventListener('mouseenter', function () { held = true; clearInterval(timer); });
    box.addEventListener('mouseleave', function () { held = false; start(); });
    box.addEventListener('focusin', function () { held = true; clearInterval(timer); });
    box.addEventListener('focusout', function () { held = false; start(); });
    start();
  })();
  </script>
  @endif
</div>
@endif
