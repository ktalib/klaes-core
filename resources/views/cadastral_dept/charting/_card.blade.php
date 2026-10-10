{{-- A saved charting card's answers, for the view page. Property order: Plot No., District, LGA, Location. --}}
@php $yesNo = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No'); @endphp
<dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
  <div><dt class="text-xs uppercase text-gray-500">File No.</dt><dd class="font-mono font-semibold">{{ $case->file_number }}</dd></div>
  <div class="sm:col-span-3"><dt class="text-xs uppercase text-gray-500">File Title</dt><dd>{{ $case->file_title ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Plot No.</dt><dd>{{ $case->plot_no ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">District</dt><dd>{{ $case->district ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">LGA</dt><dd>{{ $case->lga ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Location</dt><dd>{{ $case->location ?: '—' }}</dd></div>

  @if ($card->stage === 'check')
    <div><dt class="text-xs uppercase text-gray-500">Matches the record</dt><dd class="font-semibold">{{ $yesNo($card->matches_record) }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-xs uppercase text-gray-500">What does not match</dt><dd>{{ $card->match_note ?: '—' }}</dd></div>
  @else
    @if ($card->stage === 'second')
      <div><dt class="text-xs uppercase text-gray-500">Agrees with 1st</dt><dd class="font-semibold">{{ $yesNo($card->agrees_with_first) }}</dd></div>
      <div class="sm:col-span-3"><dt class="text-xs uppercase text-gray-500">Difference</dt><dd>{{ $card->agree_note ?: '—' }}</dd></div>
    @endif
    <div><dt class="text-xs uppercase text-gray-500">Charted</dt><dd class="font-semibold">{{ $yesNo($card->charted) }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-xs uppercase text-gray-500">Why not charted</dt><dd>{{ $card->not_charted_reason ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase text-gray-500">On existing plot</dt><dd class="font-semibold">{{ $yesNo($card->falls_on_existing) }}{{ $card->overlap_extent ? ' (' . $card->overlap_extent . ')' : '' }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-xs uppercase text-gray-500">Existing file(s)</dt><dd class="font-mono">{{ $card->existing_files ?: '—' }}</dd></div>
  @endif

  <div class="sm:col-span-4"><dt class="text-xs uppercase text-gray-500">Description</dt><dd>{{ $card->description ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Sheet</dt><dd>{{ $card->sheet_type ? ucfirst($card->sheet_type) : '—' }} {{ $card->sheet_no }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Layout</dt><dd>{{ $card->layout ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Approved plan / TP</dt><dd>{{ $card->plan_no ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Area</dt><dd>{{ $card->area_sqm ? number_format($card->area_sqm, 1) . ' m²' : '—' }}</dd></div>
  <div class="sm:col-span-4"><dt class="text-xs uppercase text-gray-500">Remarks</dt><dd>{{ $card->remarks ?: '—' }}</dd></div>
  <div class="sm:col-span-4"><dt class="text-xs uppercase text-gray-500">Charted by</dt><dd>{{ optional($card->officer)->name }} · {{ $card->charted_at->format('d M Y H:i') }}</dd></div>
</dl>
