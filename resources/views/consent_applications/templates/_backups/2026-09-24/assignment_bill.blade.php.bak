{{--
    Consent to Assign — valuation-linked bill letter (2 pages).

    Laid out to mirror the supplied reference letter: centred crest, organisation
    and department heading, ruled footer, underlined "OUR REF:" line, justified
    underlined subject, an unnumbered opening paragraph, then numbered clauses
    with the fee schedule at clause 2.

    Expected view data
    ------------------
    $application  ConsentApplication  file_number, c_of_o_no, applicant_name,
                                      applicant_address, party_name,
                                      application_dated, print_count, id
    $bill         array|object        reference, valuation_reference, valuation_amount,
                                      issue_date, lines[], total, total_paid,
                                      outstanding_balance, payment_reference,
                                      receipt_references, property{plot_no,
                                      cadastral_zone, district}
    $letter       array               ministry / signatory / approved wording.
                                      Set preprinted_letterhead => true to suppress
                                      the drawn heading and reserve blank space for
                                      pre-printed stationery instead.
    $draft        bool                true = preview; stamps DRAFT and never claims
                                      a payment has been received

    Every money figure is read from $bill. This template does not recalculate a
    fee, so a reprint keeps its original figures after rates change.
--}}
@php
    $b = fn($k, $d = null) => data_get($bill ?? null, $k, $d);
    $L = fn($k, $d = '') => data_get($letter ?? null, $k, $d);
    $money = fn($v) => number_format((float) $v, 2);

    $draft = $draft ?? true;

    // Fee lines come from the bill in their stored order, so the letter can never
    // drift from the billed set. Each line: label, amount, basis (optional note).
    $lines = $b('lines', []);
    $numerals = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];

    $total       = (float) $b('total', 0);
    $paid        = (float) $b('total_paid', 0);
    $outstanding = (float) $b('outstanding_balance', max($total - $paid, 0));
    // Half-kobo tolerance: a rounded snapshot must not read as part-paid forever.
    $fullyPaid   = !$draft && $total > 0 && $outstanding <= 0.005;
    $partPaid    = !$draft && $paid > 0 && !$fullyPaid;

    /* Clause figures. The wording of 3–6 follows the reference letter, with the
       amounts and the deadline pulled out as configuration so the Ministry can
       set its own without editing the template. Any clause can still be replaced
       wholesale through its clause_* key. */
    $deadlineDays      = $L('deadline_days', 84);
    $deadlineWords     = $L('deadline_days_words', 'eighty-four');
    $penaltyPerDay     = $L('penalty_per_day', 100);
    $penaltyWords      = $L('penalty_per_day_words', 'One Hundred Naira');
    $counterpartFee    = $L('counterpart_fee', 2000);
    $counterpartWords  = $L('counterpart_fee_words', 'Two Thousand Naira');
    $registryName      = $L('registry_name', 'Kano State Land Registry');
    $payeeName         = $L('payee_name', 'the Kano State Government');

    /* QR payload: the file's tracking id, the same identifier the file tracker
       scans on. A configured verify_url turns it into a link to a verification
       page; without one the code carries the bare id. Falls back to the file
       number when the file has no tracking id, so the code is never empty. */
    $qrValue = $b('tracking_id') ?: $application->file_number;

    if ($L('verify_url')) {
        $qrValue = rtrim($L('verify_url'), '/') . '/' . rawurlencode($qrValue);
    }

    /* The declared consideration, stated in the opening paragraph. It is the
       transaction value, not a charge, which is why it is no longer a line on
       the fee schedule. Omitted entirely when nothing was captured. */
    $considerationFigure = preg_replace('/[^0-9.]/', '', (string) $application->consideration);
    $considerationFigure = ($considerationFigure !== '' && (float) $considerationFigure > 0)
        ? number_format((float) $considerationFigure, 2)
        : null;

    /* Subject-line fragments — omit missing ones cleanly rather than leaving gaps.
       Plot and district only: the cadastral zone and the C of O number are
       deliberately not carried into the subject. */
    $subjectProperty = implode(' ', array_filter([
        $b('property.plot_no') ? 'Plot No. ' . $b('property.plot_no') : null,
        $b('property.district') ? $b('property.district') . ' District' : null,
    ]));
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consent to Assign — Bill — {{ $application->file_number }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        /* One A4 sheet. The letter used to run to a continuation page of an
           unconfirmed size; it now fits on a single sheet, so that question and
           its --cont-* variables are gone. */
        :root {
            --sheet-w: 210mm;
            --sheet-h: 297mm;

            --side-pad: 20mm;
            --ink-green: #14663a;
            --ink-red: #c1121f;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 13.3pt;
            line-height: 1.23;
            /* Times has no semibold, so a hairline stroke does the darkening. */
            -webkit-text-stroke: 0.1px currentColor;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .page-sheet {
            width: var(--sheet-w);
            min-height: var(--sheet-h);
            margin: 0 auto;
            padding: 6mm var(--side-pad) 4mm;
            position: relative;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        /* Everything above the footer band; lets the band sit at the foot of a
           short letter without stranding it past a page break. */
        .sheet-body {
            flex: 1 1 auto;
        }

        @media screen {
            body {
                background: #e5e7eb;
                padding: 20px 0;
            }

            .page-sheet {
                box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
                margin-bottom: 24px;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
                padding: 0;
            }

            /* Grow rather than clip. A fixed height with overflow:hidden kept
               the letter to one page by cutting off whatever did not fit, which
               silently removed the signature block and footer logo from the
               printed copy. An unusually long letter now runs on instead. */
            .page-sheet {
                height: auto;
                min-height: var(--sheet-h);
                overflow: visible;
                break-after: auto;
                page-break-after: auto;
            }
        }

        /* ---------------------------- letterhead ---------------------------- */

        .letterhead {
            position: relative;
            text-align: center;
            margin-bottom: 2mm;
        }

        .letterhead .crest {
            height: 15mm;
            width: auto;
            object-fit: contain;
        }

        /* Condensed so a long ministry name still fills one line at this size,
           the way the reference's heading does. */
        .letterhead .org {
            font-family: 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif;
            font-size: 23pt;
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: .2px;
            color: var(--ink-green);
            text-transform: uppercase;
            margin-top: 1mm;
        }

        .letterhead .dept {
            font-family: 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif;
            font-size: 13.5pt;
            font-weight: 700;
            color: var(--ink-red);
            text-transform: uppercase;
            line-height: 1.15;
        }

        .letterhead .addr {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            font-weight: 600;
            color: #111;
            margin-top: .4mm;
        }

        /* Blank reserve used instead of the drawn heading when printing onto
           pre-printed stationery. */
        .letterhead-space {
            min-height: 46mm;
        }

        /* Logo parked in the bottom-right corner of every sheet, as on the other
           KLAES letters. margin-top:auto on the flex column keeps it at the foot
           of a short page without stranding it past a break. */
        .letterfoot {
            margin-top: 1mm;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .letterfoot img {
            height: 8mm;
            width: auto;
            object-fit: contain;
        }

        /* QR pinned to the top-right of the letterhead. Absolute, so it cannot
           push the crest off centre however wide the caption runs. */
        .qr-block {
            position: absolute;
            /* Just below the sheet's top padding. Pulled any higher it runs into
               the paper edge and risks being cropped by the printer. */
            top: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .8mm;
        }

        /* Sized to the crest so it clears the ministry heading below: the
           heading is centred across the full width, so anything taller than
           the crest runs into it. */
        .qr-block canvas {
            width: 15mm;
            height: 15mm;
            display: block;
        }

        /* ------------------------------ letter ------------------------------ */

        .ref-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4mm;
        }

        .ref-row .our-ref {
            font-size: 12.6pt;
        }

        .ref-row .our-ref .lbl {
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .ref-row .date {
            font-weight: 700;
        }

        .recipient {
            line-height: 1.28;
            margin-bottom: 3.5mm;
        }

        .recipient .name {
            font-weight: 700;
        }

        .subject {
            font-weight: 700;
            font-size: 12.6pt;
            line-height: 1.42;
            text-align: justify;
            text-justify: inter-word;
            text-decoration: underline;
            text-underline-offset: 2.5px;
            text-transform: uppercase;
            margin-bottom: 3mm;
        }

        .para {
            text-align: justify;
            margin-top: 1.6mm;
        }

        /* Numbered clauses: the number hangs in the margin so wrapped text lines
           up under the first word, as in the reference letter. */
        .clause {
            display: grid;
            grid-template-columns: 7mm 1fr;
            column-gap: 1mm;
            text-align: justify;
            margin-top: 1.6mm;
        }

        .clause>.n {
            font-weight: 700;
        }

        .json-data {
            font-weight: 700;
        }

        /* Fee schedule. Borderless on purpose, matching the reference's typed
           layout. Auto-placement only — nothing spans a column, because an
           explicit grid-column on one cell drags every later cell out of line. */
        .fee-list {
            display: grid;
            grid-template-columns: 8mm 1fr 8mm 42mm;
            column-gap: 1mm;
            row-gap: .5mm;
            margin: 1.6mm 0 0 8mm;
        }

        .fee-list .rn {
            font-weight: 700;
            text-align: right;
        }

        .fee-list .dash {
            text-align: center;
        }

        /* ₦ pinned left, digits pushed right, so every naira sign lines up in a
           column and the figures align on their last digit. */
        .fee-list .amt {
            display: flex;
            justify-content: space-between;
            white-space: nowrap;
        }

        /* The reference rules only the money column, not the descriptions, so the
           schedule stays a typed list rather than turning into a table. */
        .fee-list .tot {
            font-weight: 700;
            padding-top: .9mm;
            margin-top: .9mm;
        }

        .fee-list .amt.tot {
            border-top: 1.1px solid #000;
            border-bottom: 3px double #000;
            padding-bottom: .4mm;
        }


        .sign-block {
            margin-top: 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Blank run for the wet signature, then the rule it is signed over. No
           signature image is embedded — that needs an authorised source. */
        .sign-block .gap {
            height: 8mm;
        }

        .sign-block .sign-rule {
            width: 68mm;
            border-top: 1px solid #000;
            margin-bottom: 1.4mm;
        }

        .sign-block .who {
            font-weight: 700;
            text-transform: uppercase;
        }

        .sign-block .role {
            font-size: 11.6pt;
            text-transform: uppercase;
            line-height: 1.23;
        }

        /* Coat of arms watermark, centred on the sheet. print-color-adjust
           keeps the browser from dropping it as decorative background on print. */
        .crest-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120mm;
            height: auto;
            opacity: .07;
            z-index: 0;
            pointer-events: none;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Lift the letter above the watermark; without their own stacking
           context these would render underneath it. */
        .page-sheet .sheet-body,
        .page-sheet .letterfoot,
        .page-sheet .letterhead {
            position: relative;
            z-index: 1;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 78px;
            color: rgba(190, 190, 190, .3);
            font-weight: bold;
            text-transform: uppercase;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #006633;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }

        .print-btn:hover {
            background: #004d26;
        }
    </style>
</head>

<body>

    @if ($draft)
        <div class="watermark">Draft — not for issue</div>
    @endif

    <button class="print-btn no-print" id="print-action" @if ($draft) disabled style="opacity:.5;cursor:not-allowed" @endif>
        <i class="fas fa-print"></i> {{ $draft ? 'Draft preview' : 'Print Document' }}
    </button>

    {{-- ============================== PAGE 1 ============================== --}}
    <div class="page-sheet">
        <img class="crest-watermark" src="{{ asset($L('crest_asset', 'assets/logo/Nigerian-Coat-of-Arms.png')) }}" alt="">
        <div class="sheet-body">

            @if ($L('preprinted_letterhead'))
                <div class="letterhead-space"></div>
            @else
                <div class="letterhead">
                    <div class="qr-block">
                        <canvas class="js-qr" width="200" height="200"></canvas>
                    </div>
                    <img class="crest" src="{{ asset($L('crest_asset', 'assets/logo/Nigerian-Coat-of-Arms.png')) }}" alt="">
                    <div class="org" data-fit-line>{{ $L('organisation_name', 'Ministry of Land and Physical Planning') }}</div>
                    <div class="dept">{{ $L('department_name', 'Department of Deeds') }}</div>
                    <div class="addr">{{ $L('office_address', 'No. 2 Dr Bala Muhammad Road, Nassarawa GRA, Kano State, Nigeria') }}</div>
                </div>
            @endif

            <div class="ref-row">
                <div class="our-ref"><span class="lbl">OUR REF:</span>
                    <strong>FILE NUMBER: {{ $application->file_number }}</strong>
                </div>
                <div class="date">
                    {{ optional($b('issue_date'))->format('jS F, Y') ?: now()->format('jS F, Y') }}
                </div>
            </div>

            <div class="recipient">
                <span class="name">{{ $application->applicant_name }}</span><br>
                {{-- Push the "…, Kano State." tail onto its own line; the stored
                     value may or may not carry a trailing full stop. --}}
                {!! preg_replace('/,\s*([^,]+\s+state)\s*\.?\s*$/i', ',<br>$1.', e(ucfirst(trim($application->applicant_address ?? '')))) !!}
            </div>

            <div class="subject">
                Conveyance of approval for consent to assign{{ $subjectProperty ? ' ' . $subjectProperty : '' }}
                covered by file no. {{ $application->file_number }} to {{ $application->party_name }}.
            </div>

            <div class="para">
                I have been directed to refer to your application
                @if ($application->application_dated)
                    dated <span class="json-data">{{ $application->application_dated->format('jS F, Y') }}</span>
                @endif
                on the above subject and convey the approval of
                {{ $L('approving_authority', 'the Honourable Commissioner') }} to assign the title in favour of
                <span class="json-data">{{ strtoupper($application->party_name) }}</span>@if ($considerationFigure)
                    at a Consideration of <span class="json-data">₦{{ $considerationFigure }}</span>@endif.
            </div>

            {{-- Clause 2 — the bill. Every figure is read from the saved snapshot. --}}
            <div class="clause">
                <div class="n">2.</div>
                <div>
                    The approval is subject to the following payments: -

                    <div class="fee-list">
                        @foreach ($lines as $i => $line)
                            <div class="rn">{{ $numerals[$i] ?? $i + 1 }}.</div>
                            <div>{{ data_get($line, 'label') }}</div>
                            <div class="dash">-</div>
                            <div class="amt"><span>₦</span><span>{{ $money(data_get($line, 'amount', 0)) }}</span></div>
                        @endforeach

                        <div class="rn tot"></div>
                        <div class="tot">{{ $L('fees_payable_label', 'Fees Payable') }}</div>
                        <div class="dash tot">-</div>
                        <div class="amt tot"><span>₦</span><span>{{ $money($total) }}</span></div>
                    </div>

                </div>
            </div>

            <div class="clause">
                <div class="n">3.</div>
                <div>{!! $L('clause_payment_and_deed') ?:
                    'The approval is further subject to (a) payment of the fees stipulated in paragraph (2) above and
                     (b) submission of a satisfactory Deed of Assignment [including counterpart copy(s)] for stamping
                     and registration, both to be complied with within ' . $deadlineWords . ' (' . $deadlineDays . ')
                     calendar days from the date of conveyance of this approval.' !!}</div>
            </div>

            @if ($fullyPaid)
                <div class="clause">
                    <div class="n">4.</div>
                    <div>
                        Payment of <span class="json-data">₦{{ $money($paid) }}</span> is recorded against this bill
                        under receipt reference(s) <span class="json-data">{{ $b('receipt_references', '—') }}</span>.
                        {!! $L('clause_documents_after_payment') ?:
                            'The Deed of Assignment [including counterpart copy(s)] should be submitted to the '
                            . e($registryName) . ' for stamping and registration.' !!}
                    </div>
                </div>
            @else
                <div class="clause">
                    <div class="n">4.</div>
                    <div>
                        You are by this letter required to pay
                        @if ($partPaid)
                            the outstanding balance of <span class="json-data">₦{{ $money($outstanding) }}</span>
                            (of the <span class="json-data">₦{{ $money($total) }}</span> assessed)
                        @else
                            the sum of <span class="json-data">₦{{ $money($total) }}</span>
                        @endif
                        {!! $L('payment_instructions') ?:
                            'to ' . e($payeeName) . ' by visiting the Customer Care Service Center in
                             <strong>' . e($L('payment_centre_name', 'KANGIS')) . '</strong> or the Bank Branch.' !!}
                        {!! $L('receipt_submission_instruction') ?:
                            'Receipts evidencing such payment must be presented to the ' . e($registryName) . ' before the
                             Deed of Assignment documents are stamped for registration.' !!}
                    </div>
                </div>
            @endif

        </div>




            <div class="clause">
                <div class="n">5.</div>
                <div>{!! $L('clause_counterpart_copies') ?:
                    'Registration of additional counterpart copy(s) attracts a fee of <strong>₦'
                    . $money($counterpartFee) . '</strong> (' . $counterpartWords . ') only each. You are therefore
                     advised to add the cost of any counterpart copy(s) to the total fees payable to avoid delay in the
                     registration of your documents.' !!}</div>
            </div>

            <div class="clause">
                <div class="n">6.</div>
                <div>{!! $L('clause_deadline_and_default') ?:
                    'Please note that if the Deed of Assignment instruments are not submitted within the stipulated '
                    . $deadlineWords . ' (' . $deadlineDays . ') calendar days, a penalty sum of <strong>₦'
                    . $money($penaltyPerDay) . '</strong> (' . $penaltyWords . ') shall be charged for each day of such
                     default in payment until compliance is attained.' !!}</div>
            </div>

            <div class="sign-block">
                <div class="gap"></div>
                <div class="sign-rule"></div>
                {{-- Name only when one is configured: an unsigned letter shows the
                     rule and the office, never a bracketed placeholder. --}}
                @if ($L('signatory_name'))
                    <div class="who">{{ $L('signatory_name') }}</div>
                @endif
                <div class="role">{{ $L('signatory_designation', 'Honourable Commissioner') }},</div>
                <div class="role">{{ $L('ministry_name', 'Ministry of Land and Physical Planning') }},</div>
                <div class="role">{{ $L('state_name', 'Kano State') }}</div>
            </div>

        <div class="letterfoot">
            <img src="{{ asset($L('footer_logo', 'assets/logo/klaes1.png')) }}" alt="">
        </div>
    </div>

    {{-- ========================= CONTINUATION PAGE ========================= --}}


    <script>
        // Keep the organisation name on a single line whatever its length, as in
        // the reference heading. Shrinks only; a short name stays at full size.
        document.querySelectorAll('[data-fit-line]').forEach(function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            el.style.whiteSpace = 'nowrap';
            while (el.scrollWidth > el.clientWidth && size > 9) {
                size -= 0.5;
                el.style.fontSize = size + 'px';
            }
        });

        // Same code on both sheets, drawn at 200px and scaled down by CSS so it
        // stays sharp at 20mm on paper. Level H tolerates a poor scan of a
        // photocopied letter.
        document.querySelectorAll('canvas.js-qr').forEach(function (canvas) {
            if (typeof QRious === 'undefined') {
                canvas.style.display = 'none';
                return;
            }
            new QRious({
                element: canvas,
                value: @json($qrValue),
                size: 200,
                level: 'H'
            });
        });

        var btn = document.getElementById('print-action');
        if (btn && !btn.disabled) {
            btn.addEventListener('click', async function () {
                try {
                    const response = await fetch('{{ route('consent-applications.log-print', $application->id) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result.success) {
                        window.print();
                        setTimeout(function () { window.location.reload(); }, 500);
                    } else {
                        alert('Error logging print: ' + result.message);
                    }
                } catch (error) {
                    console.error('Print logging error:', error);
                    alert('An error occurred while preparing for print.');
                }
            });
        }
    </script>
</body>

</html>
