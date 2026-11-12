{{-- The KLAES logo at the foot of an information product (CR 11112026-2, item 14): bottom-right, after the
     document's content, the size used on the I to S and the Surveyor's Report (11.5 mm high). Embedded as a
     data URI (15 KB, public/assets/logo/klaes-print.jpg) so it prints the same from the browser and from
     dompdf, which cannot fetch app URLs. Optional: $klaesLogoStyle (extra CSS on the wrapper), $klaesLogoHeight
     (a card that already fills its page carries a smaller one in its own footer). --}}
@php
  $klaesPrintLogoFile = public_path('assets/logo/klaes-print.jpg');
  $klaesPrintLogo = is_file($klaesPrintLogoFile) ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($klaesPrintLogoFile)) : null;
@endphp
@if ($klaesPrintLogo)
  <div class="klaes-print-logo" style="text-align:right;padding-top:4mm;line-height:0;page-break-inside:avoid;break-inside:avoid;{{ $klaesLogoStyle ?? '' }}">
    <img src="{{ $klaesPrintLogo }}" alt="KLAES" style="height:{{ $klaesLogoHeight ?? '11.5mm' }};width:auto;display:inline-block;">
  </div>
@endif
