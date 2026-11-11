{{-- VMAL at the foot of every portal (CR 10112026: "Add VMAL as the footer of all the Portals"; user 2026-11-11, the
     logo assets/logo/VMAL.png), as the staff app's footer has it. Inline styles: the portals use different CSS.
     Pass dark = true on a dark footer, false (the default) on a light one. --}}
@php $vmalDark = $dark ?? false; @endphp
<div class="vmal-credit" style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;padding:12px 16px 14px;margin-top:12px;border-top:1px solid {{ $vmalDark ? 'rgba(255,255,255,.12)' : 'rgba(15,23,42,.08)' }};font-size:12px;line-height:1.4;color:{{ $vmalDark ? 'rgba(255,255,255,.72)' : '#64748b' }};text-align:center;">
    <img src="{{ asset('assets/logo/VMAL.png') }}" alt="VMAL logo" width="36" height="36" style="width:36px;height:36px;object-fit:contain;{{ $vmalDark ? 'background:#fff;border-radius:6px;padding:2px;' : '' }}">
    <span>Powered by VALUE MAPS &amp; APPS LIMITED (VMAL)</span>
</div>
