{{-- Official Ministry receipt (Receipting), laid out to the Ministry's sample (2026-10-20):
     three copies, Original / Duplicate / Triplicate, on one A4 page to be cut apart, each on its own
     tint (green / blue / pink) with the Kano arms (logo1.jpg) as a faint watermark.
     Shown on screen as the e-Receipt (one copy). A print after the first is marked REPRINT. --}}
@php
  $paidAt = optional($payment)->paid_at ?? $receipt->issued_at;
  $paidAt = $paidAt ? \Illuminate\Support\Carbon::parse($paidAt) : null;
  $from = (int) ($bill->from_year ?? 0);
  $to = (int) ($bill->to_year ?? 0);
  $duration = $from && $to && $from !== $to ? "{$from} – {$to}" : (string) ($to ?: ($from ?: optional($paidAt ?? $receipt->issued_at)->format('Y')));
  $paymentFor = optional($bill->type)->name;
  if ($bill->description && $bill->description !== $paymentFor) {
      $paymentFor = trim($paymentFor . ' — ' . $bill->description, ' —');
  }
  // "Bank Name" on the sample: the channel for REV-M payments; unknown (—) for bank receipts captured in a module.
  $bankName = [
      'paystack' => 'Paystack', 'pos' => 'POS', 'bank_transfer' => 'Bank Transfer',
      'invoice' => 'Bank Transfer', 'online' => 'Online (portal)',
  ][optional($payment)->method] ?? '—';
  $amount = (float) $receipt->amount;
  $amountText = '₦' . number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt {{ $receipt->receipt_no }}</title>
  <style>
    @page { size: A4 portrait; margin: 7mm 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 12px; }
    .copy { position: relative; width: 194mm; height: 92mm; margin: 10px auto; background: #fff; padding: 5mm 7mm 4mm; overflow: hidden; }
    .copy + .copy { border-top: 1px dashed #9ca3af; }
    /* Each copy on its own tint, like the Ministry's carbon set: Original green, Duplicate blue, Triplicate pink. */
    .copy, .copy * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .copy.original { background: #eef5dc; }
    .copy.duplicate { background: #e4eff9; }
    .copy.triplicate { background: #fae3ea; }
    .copy .watermark { position: absolute; top: 50%; left: 50%; width: 62mm; transform: translate(-50%, -46%); opacity: .09; z-index: 0; pointer-events: none; }
    .copy .head img, .copy .watermark { mix-blend-mode: multiply; } /* no white box round the logos on the tint */
    .copy > .top, .copy > .head, .copy > .box, .copy > .lines, .copy > .sign { position: relative; z-index: 1; }
    .top { display: flex; justify-content: space-between; align-items: flex-start; font-weight: 700; }
    .top .label { font-size: 11px; letter-spacing: .3px; }
    .top .copy-no { text-align: right; font-size: 11px; text-transform: uppercase; }
    .top .copy-no .no { display: block; font-family: "Courier New", monospace; font-size: 12px; }
    .head { display: flex; align-items: center; justify-content: space-between; margin-top: 1mm; }
    .head img { height: 15mm; width: 15mm; object-fit: contain; }
    .head .title { flex: 1; text-align: center; font-family: "Times New Roman", Times, serif; padding: 0 4mm; }
    .head h1 { margin: 0; font-size: 15px; font-weight: 400; letter-spacing: .4px; text-transform: uppercase; }
    .head .addr { font-size: 10.5px; margin-top: 1px; }
    .box { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 1.5mm 4mm; border: 1px solid #111; padding: 2.5mm 4mm; margin: 2.5mm 0 2mm; }
    .box div:nth-child(3n) { text-align: right; }
    .lines div { margin: .8mm 0; }
    .lines .upper { text-transform: uppercase; }
    .sign { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 7mm; font-size: 11px; }
    .sign .line { border-top: 1px solid #111; padding-top: 1mm; text-align: center; }
    .sign .customer { width: 48mm; }
    .sign .officer { width: 80mm; }
    .sign .duration { align-self: flex-start; margin-top: -4mm; }
    .reprint { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); font-size: 60px; font-weight: 800;
               color: rgba(185, 28, 28, .10); letter-spacing: 8px; pointer-events: none; }
    .toolbar { text-align: center; margin: 12px; }
    .toolbar button { padding: 9px 20px; border: 0; border-radius: 6px; background: #047857; color: #fff; font-weight: 600; cursor: pointer; }
    @media print {
      body { background: #fff; }
      .copy { margin: 0; width: auto; height: 94mm; break-inside: avoid; page-break-inside: avoid; }
      .toolbar { display: none; }
    }
  </style>
</head>
<body>
  @unless ($screen)
    <div class="toolbar"><button type="button" onclick="window.print()">Print {{ count($copies) }} copies</button></div>
  @endunless

  @foreach ($copies as $copy)
    <div class="copy {{ ['Original' => 'original', 'Duplicate' => 'duplicate', 'Triplicate' => 'triplicate'][$copy] ?? 'original' }}">
      <img class="watermark" src="{{ asset('assets/logo/logo1.jpg') }}" alt="" onerror="this.remove()">
      @if ($reprint)<div class="reprint">REPRINT</div>@endif

      <div class="top">
        <div class="label">OFFICIAL RECEIPT</div>
        <div class="copy-no">{{ $copy }}<span class="no">{{ $receipt->receipt_no }}</span></div>
      </div>

      <div class="head">
        <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="" onerror="this.style.visibility='hidden'">
        <div class="title">
          <h1>Kano State Ministry of Land and Physical Planning</h1>
          <div class="addr">No. 2, Dr Bala Mohammed Road, P.M.B 3083, Kano Nigeria</div>
        </div>
        <img src="{{ asset('assets/logo/kangis.jpg') }}" alt="" onerror="this.style.visibility='hidden'">
      </div>

      <div class="box">
        <div>File Number: {{ $bill->file_number ?: '—' }}</div>
        <div>Bank Name: {{ $bankName }}</div>
        <div>Date Paid: {{ $paidAt ? $paidAt->format('d/m/Y') : '—' }}</div>
        <div>Payment ID: {{ $bill->bill_ref_id }}</div>
        <div>Teller Number: {{ optional($payment)->transaction_id ?: '—' }}</div>
        <div>Receipt Date: {{ optional($receipt->issued_at)->format('d/m/Y') }}</div>
      </div>

      <div class="lines">
        <div class="upper">Received From: {{ $bill->payer_name ?: '—' }}</div>
        <div>Amount in words: {{ \Illuminate\Support\Str::lower($words) }}</div>
        <div class="upper">Being Payment For: {{ $paymentFor ?: '—' }}</div>
        <div>Amount: {{ $amountText }}</div>
      </div>

      <div class="sign">
        <div class="line customer">Customer Signature</div>
        <div class="line officer">Revenue Officer Signature{{ $officerEmail ? '(' . $officerEmail . ')' : '' }}</div>
        <div class="duration">Duration: {{ $duration }}</div>
      </div>
    </div>
  @endforeach
</body>
</html>
