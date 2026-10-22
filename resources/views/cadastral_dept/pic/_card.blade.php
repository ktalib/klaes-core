{{-- Property Index Card (PIC). One card per file, with every transaction on it (user, 2026-10-16).
     Drawn from the Cadastral Registry's paper card (form SY-039, sample RES-1973-372), reworked:
     the R. of O. dates are gone (they belong to the RofO), Plan No. and Tracing No. sit in the
     file details, the header carries the ministry's two logos side by side, and the
     transactions are listed on the card, running on to further pages with the header row
     repeated.

     Used by the preview page and, later, the PDF filed to the Cadastral Registry PIC folio, so it
     is dompdf-safe: tables, sizes in mm, built-in fonts, logos as data URIs, and every cell
     width set inline (dompdf ignores class widths in some tables).

     $pic keys (all optional; a blank prints as an empty line):
       file_number, name, plot_no, district, lga, locality, approx_area_ha, final_area_ha,
       lands_file, comp_no, plan_no, tracing_no, tp_no, map_sheet, scale, coord_n, coord_e, lat, long,
       related_files[], transactions[[type, party1, party2, reg_datetime, reg_particulars, remarks]] (oldest first),
       form_no, generated_at, generated_by, sources[], qr (image data URI) --}}
@php
  $v = fn ($k) => trim((string) ($pic[$k] ?? ''));
  $acres = fn ($ha) => is_numeric($ha) ? number_format((float) $ha * 2.471, 4) : '';
  $tx = array_values((array) ($pic['transactions'] ?? []));
  $related = array_values(array_filter((array) ($pic['related_files'] ?? [])));
  // Logos are embedded, shrunk once to 240 px and kept in storage/app/pic, so each card's PDF
  // stays small (ministry2.png alone is 400 KB).
  $logo = function (string $file) {
      $path = public_path('assets/logo/' . $file);
      if (!is_file($path)) {
          return null;
      }
      $small = storage_path('app/pic/logo-' . pathinfo($file, PATHINFO_FILENAME) . '-240.png');
      if (!is_file($small) && function_exists('imagecreatefromstring')) {
          try {
              $src = imagecreatefromstring(file_get_contents($path));
              if ($src) {
                  $w = imagesx($src); $h = imagesy($src); $s = min(1, 240 / max($w, $h));
                  $dst = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
                  imagealphablending($dst, false); imagesavealpha($dst, true);
                  imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                  imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
                  if (!is_dir(dirname($small))) {
                      mkdir(dirname($small), 0775, true);
                  }
                  imagepng($dst, $small, 9);
              }
          } catch (\Throwable $e) {
              // Fall back to the original image.
          }
      }
      return is_file($small)
          ? 'data:image/png;base64,' . base64_encode(file_get_contents($small))
          : 'data:' . (str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($path));
  };
  $logos = [$logo('ministry1.jpg'), $logo('ministry2.png')];
@endphp
<style>
  .pic { width: 196mm; border: 0.6mm solid #1f2937; border-collapse: collapse; font-family: Helvetica, Arial, sans-serif; color: #1f2937; background: #fbf8f1; }
  .pic td { vertical-align: top; }
  .pic .band { border-bottom: 0.5mm solid #1f2937; }
  .pic .cell { padding: 2mm 3mm; vertical-align: top; }
  .pic .split-l { border-right: 0.4mm solid #1f2937; }
  .pic .row { width: 100%; border-collapse: collapse; }
  .pic .row td { padding: 0.8mm 0; vertical-align: bottom; }
  .pic .row td.cell { padding: 2mm 3mm; vertical-align: top; }
  .pic .row .l { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3pt; padding-right: 1.5mm; }
  .pic .row .v { border-bottom: 0.25mm dotted #4b5563; font-family: "Times New Roman", Times, serif; font-size: 10.5pt; font-weight: bold; color: #1e3a8a; }
  .pic .row .u { font-size: 7pt; font-weight: bold; padding-left: 1.5mm; }
  .pic .lbl { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; }
  .pic .val { font-family: "Times New Roman", Times, serif; font-size: 10pt; font-weight: bold; color: #1e3a8a; }
  .pic .h-gov { font-size: 7.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; color: #374151; }
  .pic .h-min { font-size: 10.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; }
  .pic .h-dept { font-size: 8pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; color: #374151; }
  .pic .h-title { font-size: 15pt; font-weight: bold; letter-spacing: 1.5pt; text-transform: uppercase; margin-top: 1mm; }
  .pic .row td.fileno { text-align: center; border-left: 0.4mm solid #1f2937; padding: 2mm; vertical-align: middle; }
  .pic .fileno .n { font-family: "Times New Roman", Times, serif; font-size: 13pt; font-weight: bold; color: #1e3a8a; }
  .pic.tx { width: 196mm; }
  .pic.tx th { background: #1f2937; color: #fff; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.2pt; text-align: left; padding: 1.3mm 1.5mm; }
  .pic.tx td { font-size: 8pt; padding: 1.3mm 1.5mm; border-bottom: 0.25mm solid #d1d5db; vertical-align: top; }
  .pic.tx .sn { text-align: right; color: #4b5563; }
  .pic.tx .vv { font-family: "Times New Roman", Times, serif; font-weight: bold; color: #1e3a8a; font-size: 9pt; }
  .pic.tx .none { text-align: center; color: #6b7280; padding: 4mm; }
  .pic-foot { width: 196mm; font-family: Helvetica, Arial, sans-serif; font-size: 6.5pt; color: #6b7280; margin-top: 1.5mm; }
</style>

<table class="pic">
  {{-- Header (user, 2026-10-16): logo, the ministry heading centred, logo. --}}
  <tr>
    <td class="band" style="padding: 0;">
      <table class="row">
        <tr>
          <td style="width: 22mm; padding: 2.5mm 1mm 2.5mm 3mm; vertical-align: middle; text-align: left;">
            @if (!empty($logos[0]))<img src="{{ $logos[0] }}" alt="Ministry logo" style="width: 19mm; height: 19mm;">@endif
          </td>
          <td style="width: 140mm; padding: 2.5mm 2mm; vertical-align: middle; text-align: center;">
            <div class="h-gov">Kano State Government</div>
            <div class="h-min">Ministry of Land and Physical Planning</div>
            <div class="h-dept">Cadastral Department</div>
          </td>
          <td style="width: 22mm; padding: 2.5mm 3mm 2.5mm 1mm; vertical-align: middle; text-align: right;">
            @if (!empty($logos[1]))<img src="{{ $logos[1] }}" alt="Ministry logo" style="width: 19mm; height: 19mm;">@endif
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Title, file number and the QR code. --}}
  <tr>
    <td class="band" style="padding: 0;">
      <table class="row">
        <tr>
          <td style="width: 100mm; padding: 2mm 4mm; vertical-align: middle;">
            <div class="h-title" style="margin-top: 0;">Property Index Card</div>
            <div class="h-dept" style="font-weight: normal;">Cadastral Registry</div>
          </td>
          <td class="fileno" style="width: 50mm;">
            <div class="lbl">File No.</div>
            <div class="n">{{ $v('file_number') }}</div>
          </td>
          <td class="fileno" style="width: 22mm; padding: 1.5mm;">
            @if ($v('qr'))
              <img src="{{ $pic['qr'] }}" alt="QR code" style="width: 19mm; height: 19mm;">
            @endif
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- File details. Property order: Plot No., District, LGA, Location. --}}
  <tr>
    <td class="band" style="padding: 0;">
      <table class="row">
        <tr>
          <td class="cell split-l" style="width: 92mm;">
            <table class="row"><tr><td class="l" style="width: 24mm;">For</td><td class="v">{{ $v('name') }}</td></tr></table>
            <table class="row"><tr><td class="l" style="width: 24mm;">Plot No.</td><td class="v">{{ $v('plot_no') }}</td></tr></table>
            <table class="row"><tr><td class="l" style="width: 24mm;">District</td><td class="v">{{ $v('district') }}</td></tr></table>
            <table class="row"><tr><td class="l" style="width: 24mm;">LGA</td><td class="v">{{ $v('lga') }}</td></tr></table>
            <table class="row"><tr><td class="l" style="width: 24mm;">Locality</td><td class="v">{{ $v('locality') }}</td></tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 24mm;">Approx. Area</td><td class="v">{{ $v('approx_area_ha') }}</td><td class="u" style="width: 8mm;">ha</td>
              <td class="v">{{ $acres($pic['approx_area_ha'] ?? null) }}</td><td class="u" style="width: 10mm;">acres</td>
            </tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 24mm;">Final Area</td><td class="v">{{ $v('final_area_ha') }}</td><td class="u" style="width: 8mm;">ha</td>
              <td class="v">{{ $acres($pic['final_area_ha'] ?? null) }}</td><td class="u" style="width: 10mm;">acres</td>
            </tr></table>
          </td>
          <td class="cell" style="width: 92mm;">
            <table class="row"><tr>
              <td class="l" style="width: 20mm;">Plan No.</td><td class="v">{{ $v('plan_no') }}</td>
              <td class="l" style="width: 20mm; padding-left: 2mm;">Tracing No.</td><td class="v">{{ $v('tracing_no') }}</td>
            </tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 20mm;">Lands File</td><td class="v">{{ $v('lands_file') }}</td>
              <td class="l" style="width: 20mm; padding-left: 2mm;">Comp. No.</td><td class="v">{{ $v('comp_no') }}</td>
            </tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 20mm;">Map Sheet</td><td class="v">{{ $v('map_sheet') }}</td>
              <td class="l" style="width: 20mm; padding-left: 2mm;">Scale</td><td class="v">{{ $v('scale') }}</td>
            </tr></table>
            <table class="row"><tr><td class="l" style="width: 20mm;">TP No.</td><td class="v">{{ $v('tp_no') }}</td></tr></table>
            <table class="row"><tr><td class="l" style="width: 40mm;">Coordinates</td><td class="v" style="border-bottom: 0;">&nbsp;</td></tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 20mm;">N</td><td class="v">{{ $v('coord_n') }}</td>
              <td class="l" style="width: 20mm; padding-left: 2mm;">E</td><td class="v">{{ $v('coord_e') }}</td>
            </tr></table>
            <table class="row"><tr>
              <td class="l" style="width: 20mm;">Lat.</td><td class="v">{{ $v('lat') }}</td>
              <td class="l" style="width: 20mm; padding-left: 2mm;">Long.</td><td class="v">{{ $v('long') }}</td>
            </tr></table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Related files. --}}
  <tr>
    <td class="band cell">
      <span class="lbl">Related Files:</span>
      <span class="val">{{ $related ? implode(' · ', $related) : '—' }}</span>
    </td>
  </tr>

</table>

{{-- Transactions: every dealing on the file, oldest first. A table of its own under the card's
     frame (its borders carry the frame on), so dompdf repeats the header row on each page. --}}
<table class="pic tx" style="border-top: 0;">
        <thead>
          <tr>
            <th class="sn" style="width: 6mm;">S/N</th>
            <th style="width: 27mm;">Transaction Type</th>
            <th style="width: 30mm;">Party 1</th>
            <th style="width: 30mm;">Party 2</th>
            <th style="width: 28mm;">Reg. Time/Date</th>
            <th style="width: 26mm;">Reg. Particulars</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tx as $t)
            <tr>
              <td class="sn">{{ $loop->iteration }}</td>
              <td>{{ $t['type'] ?? '' }}</td>
              <td class="vv">{{ $t['party1'] ?? '' }}</td>
              <td class="vv">{{ $t['party2'] ?? '' }}</td>
              <td>{{ $t['reg_datetime'] ?? '' }}</td>
              <td>{{ $t['reg_particulars'] ?? '' }}</td>
              <td>{{ $t['remarks'] ?? '' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="none">No transactions recorded on this file.</td></tr>
          @endforelse
        </tbody>
</table>
<table class="pic-foot">
  <tr>
    <td>{{ $v('form_no') ?: 'SY-039' }} · Property Index Card · Cadastral Registry · KLAES{{ $v('generated_by') ? ' · generated by ' . $v('generated_by') : '' }}</td>
    <td style="text-align: right;">{{ $v('generated_at') }}{{ !empty($pic['sources']) ? ' · from ' . implode(', ', (array) $pic['sources']) : '' }}</td>
  </tr>
</table>
