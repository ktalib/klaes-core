{{-- Property Index Card preview: the sample card, or ?file='s card built from the records
     (PropertyIndexCards::build). Standalone, like the other Cadastral prints; the card itself is
     cadastral_dept.pic._card, which the filed PDF uses too. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Property Index Card · {{ $pic['file_number'] ?? 'Preview' }}</title>
  <style>
    @page { size: A5 landscape; margin: 6mm; }
    body { margin: 0; background: #e5e7eb; font-family: Helvetica, Arial, sans-serif; }
    .bar { background: #111827; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 13px; }
    .bar b { margin-right: auto; }
    .bar a, .bar button { background: #fff; color: #111827; border: 0; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; }
    .bar form { margin: 0; }
    .bar .go { background: #2563eb; color: #fff; }
    .sheet { width: 196mm; margin: 16px auto; padding: 6mm; background: #fff; box-shadow: 0 4px 18px rgba(0,0,0,.18); overflow-x: auto; }
    .note { max-width: 208mm; margin: 0 auto 24px; font-size: 12px; color: #374151; padding: 0 16px; }
    @media print { body { background: #fff; } .bar, .note { display: none; } .sheet { margin: 0; padding: 0; box-shadow: none; } }
  </style>
</head>
<body>
  <div class="bar">
    <b>Property Index Card · {{ $isSample ? 'template sample' : 'preview of ' . $pic['file_number'] }}</b>
    <a href="{{ route('cadastral-dept.pic.preview', array_filter(['file' => $isSample ? null : $pic['file_number'], 'format' => 'pdf'])) }}" target="_blank">Open as PDF</a>
    @if (! $isSample && $card && $card->path)
      <a href="{{ route('cadastral-dept.pic.show', ['file' => $pic['file_number']]) }}" target="_blank">Filed card</a>
    @endif
    @if ($canGenerate)
      <form method="POST" action="{{ route('cadastral-dept.pic.store') }}" onsubmit="return confirm('File this card to the EDMS folder, replacing any earlier card?')">
        @csrf
        <input type="hidden" name="file_number" value="{{ $pic['file_number'] }}">
        <button class="go">Generate and file</button>
      </form>
    @endif
    <button type="button" onclick="window.print()">Print</button>
  </div>
  <div class="sheet">
    @include('cadastral_dept.pic._card', ['pic' => $pic])
  </div>
  @if ($isSample)
    <p class="note">Sample values are copied from the paper card for RES-1973-372 (R. of O. RES/83/372); the three transactions are placeholders to show the layout.</p>
  @else
    <p class="note">Built now from the file index and the Legal Search records (PRA, File History, CofO, Deeds Registration). Not filed until generated.</p>
  @endif
</body>
</html>
