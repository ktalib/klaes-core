{{-- POS-format invoice (80 mm). NOT the official receipt: that is issued under Receipting.
     The invoice never shows payment status (no PAID stamp, no payment lines). --}}
@php
  $money = fn ($v) => '₦' . number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice {{ $bill->bill_ref_id }}</title>
  <style>
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; font-family: "Courier New", ui-monospace, monospace; color: #111; }
    .slip { position: relative; width: 74mm; margin: 12px auto; background: #fff; padding: 4mm 3mm; font-size: 11px; line-height: 1.35; }
    .center { text-align: center; }
    .logo { height: 34px; }
    h1 { font-size: 12px; margin: 4px 0 0; text-transform: uppercase; }
    h2 { font-size: 13px; margin: 6px 0; letter-spacing: 2px; }
    .rule { border-top: 1px dashed #111; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 6px; }
    .row span:first-child { color: #333; }
    .row span:last-child { text-align: right; }
    .items .row { margin: 2px 0; }
    .total { font-size: 14px; font-weight: bold; }
    .ref { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
    .note { font-size: 9.5px; color: #333; }
    .toolbar { text-align: center; margin: 10px; font-family: system-ui, sans-serif; }
    .toolbar button { padding: 8px 18px; border: 0; border-radius: 6px; background: #047857; color: #fff; font-weight: 600; cursor: pointer; }
    @media print { body { background: #fff; } .slip { margin: 0; width: auto; } .toolbar { display: none; } }
  </style>
</head>
<body>
  <div class="toolbar"><button type="button" onclick="window.print()">Print invoice</button></div>

  <div class="slip">
    <div class="center">
      <img class="logo" src="{{ asset('assets/logo/logo1.jpg') }}" alt="" onerror="this.style.display='none'">
      <h1>Kano State Ministry of Land and Physical Planning</h1>
      <div>KLAES REV-M</div>
      <h2>INVOICE</h2>
    </div>

    <div class="rule"></div>
    <div class="center">Bill Ref ID</div>
    <div class="center ref">{{ $bill->bill_ref_id }}</div>
    <div class="center note">Quote this Bill Ref ID when paying at the POS</div>
    <div class="rule"></div>

    <div class="row"><span>Bill type</span><span>{{ optional($bill->type)->name }}</span></div>
    <div class="row"><span>Date</span><span>{{ optional($bill->bill_date)->format('d/m/Y') }}</span></div>
    <div class="row"><span>File No.</span><span>{{ $bill->file_number ?: '—' }}</span></div>
    <div class="row"><span>Payer</span><span>{{ $bill->payer_name ?: '—' }}</span></div>
    <div class="row"><span>Plot No.</span><span>{{ $bill->plot_no ?: '—' }}</span></div>
    <div class="row"><span>District</span><span>{{ $bill->district ?: '—' }}</span></div>
    <div class="row"><span>LGA</span><span>{{ $bill->lga ?: '—' }}</span></div>
    <div class="row"><span>Location</span><span>{{ $bill->location ?: '—' }}</span></div>

    <div class="rule"></div>
    <div class="items">
      @foreach ($bill->items as $item)
        <div class="row"><span>{{ $loop->iteration }}. {{ $item->name }}</span><span>{{ $money($item->amount) }}</span></div>
      @endforeach
    </div>
    <div class="rule"></div>
    <div class="row total"><span>TOTAL</span><span>{{ $money($bill->amount) }}</span></div>

    <div class="rule"></div>
    <div class="center note">This invoice is not an official receipt. The official Ministry receipt is issued after payment.</div>
    <div class="center note">Printed {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</body>
</html>
