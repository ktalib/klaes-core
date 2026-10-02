<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  @include('online_legal_search.partials.favicon')
  <title>Online Legal Search Report — KLAES</title>
  <style>
    body { background-color: #525659; font-family: Arial, sans-serif; margin: 0; padding: 10px; display: flex; flex-direction: column; align-items: center; }

    /* Action bar (hidden when printing) */
    .action-bar { width: 11in; max-width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
    .action-bar a, .action-bar button { font-family: Arial, sans-serif; font-size: 13px; font-weight: 600; padding: 9px 16px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-light { background: #e2e8f0; color: #0f172a; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-green { background: #16a34a; color: #fff; }

    /* Payment card */
    .pay-wrap { width: 520px; max-width: 100%; background: #fff; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,.25); overflow: hidden; }
    .pay-head { background: linear-gradient(135deg,#2563eb,#7c3aed); color: #fff; padding: 24px; }
    .pay-head h1 { margin: 0 0 4px; font-size: 20px; }
    .pay-head p { margin: 0; font-size: 13px; opacity: .9; }
    .pay-body { padding: 24px; color: #1f2937; }
    .pay-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eef2f7; font-size: 14px; }
    .pay-row .lbl { color: #6b7280; }
    .pay-row .val { font-weight: 700; }
    .pay-total { font-size: 26px; font-weight: 800; color: #16a34a; }
    .pay-btn { width: 100%; margin-top: 18px; padding: 13px; font-size: 16px; font-weight: 700; color: #fff; background: #16a34a; border: 0; border-radius: 10px; cursor: pointer; }
    .pay-btn:disabled { opacity: .6; cursor: not-allowed; }
    .pay-note { font-size: 12px; color: #6b7280; margin-top: 14px; text-align: center; }
    .pay-error { display:none; margin-top: 12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:10px; border-radius:8px; font-size:13px; }

    /* Payment + identification, side by side on desktop and stacked below 900px.
       Flex rather than grid so the two cards keep their own heights when the
       identification card grows with previews and validation messages. */
    .pay-columns { display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-start; justify-content: center; width: 100%; }
    /* The cards SHRINK to stay side by side rather than stacking as soon as they
       cannot both be full width. The identification step belongs next to the thing
       it unlocks; stacking pushes it below the fold, where it reads as optional.
       A 380px basis keeps the pair together down to ~760px — below that they are
       genuinely too narrow for the form, and stacking is the honest answer.
       min-width:0 lets a flex item shrink past its content, which it will not do
       by default. */
    .pay-columns .pay-wrap { flex: 1 1 380px; max-width: 520px; min-width: 0; }
    @media (max-width: 760px) {
      .pay-columns { flex-direction: column; align-items: center; }
      .pay-columns .pay-wrap { width: 100%; max-width: 520px; }
    }

    /* Collapsible IYC header. The <button> is restyled to sit flush as the card's
       own header rather than looking like a control dropped inside it. */
    /* box-sizing is explicit: this page has no global border-box, so width:100%
       against .pay-head's 24px padding would otherwise overflow and be clipped by
       .pay-wrap's overflow:hidden. */
    .id-head-toggle { display: flex; align-items: center; gap: 12px; width: 100%; box-sizing: border-box; text-align: left; border: 0; cursor: pointer; font-family: inherit; }
    .id-head-toggle:focus-visible { outline: 3px solid #93c5fd; outline-offset: -3px; }
    .id-head-text { flex: 1 1 auto; min-width: 0; }
    .id-head-chevron { flex: 0 0 auto; font-size: 18px; line-height: 1; transition: transform .2s ease; }
    .id-head-status { flex: 0 0 auto; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: rgba(255,255,255,.22); color: #fff; white-space: nowrap; }
    /* Collapsed: body hidden outright rather than height-animated, so a tall image
       preview can never leave a clipped or half-open card behind. */
    #idCard.is-collapsed .pay-body { display: none; }
    #idCard.is-collapsed .id-head-chevron { transform: rotate(-90deg); }

    /* Identification card controls */
    .id-label { display: block; margin-top: 14px; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #374151; }
    .id-input { width: 100%; box-sizing: border-box; padding: 11px 12px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 10px; color: #1f2937; background: #fff; font-family: inherit; }
    .id-input:focus { outline: 2px solid #93c5fd; outline-offset: 1px; }
    .id-uploads { display: flex; gap: 12px; margin-top: 6px; }
    .id-uploads .id-upload { flex: 1 1 0; min-width: 0; }
    .id-preview-wide { height: 150px; }
    .id-preview { height: 104px; border: 1px dashed #d1d5db; border-radius: 10px; background: #f9fafb; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 6px; }
    .id-preview img { width: 100%; height: 100%; object-fit: cover; }
    .id-preview-empty { font-size: 11px; color: #9ca3af; }
    .id-file { width: 100%; box-sizing: border-box; font-size: 11px; color: #4b5563; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px; background: #fff; }
    .id-clear { margin-top: 6px; background: none; border: 0; padding: 0; font-size: 11px; color: #dc2626; cursor: pointer; }
    /* The "please specify" follow-up under a select. Indented against a tinted
       rail so it reads as belonging to the choice above it, not as a new question. */
    .id-specify { margin-top: 10px; padding: 10px 12px; border-left: 3px solid #93c5fd; background: #f8fafc; border-radius: 0 8px 8px 0; }
    .id-status { margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 13px; line-height: 1.45; }
    .id-status.ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .id-status.warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .id-status.bad { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Report page (from ONLINE-demo.html) */
    .page { background-color: #fff; width: 11in; min-height: 8.5in; padding: 0.2in 0.4in; position: relative; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; }
    .watermark { position: absolute; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); white-space: nowrap; font-size: 52px; font-weight: 900; letter-spacing: 2px; color: rgba(0,0,0,.14); text-transform: uppercase; text-align: center; pointer-events: none; z-index: 0; }
    .watermark.wm-top { top: 50%; }
    .bg-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 45%; max-width: 460px; opacity: .06; pointer-events: none; z-index: 0; }
    header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 6px; position: relative; z-index: 1; }
    .header-top { display: flex; justify-content: space-between; align-items: center; }
    .logo-box img { width: 75px; height: auto; }
    .title-block { text-align: center; flex: 1; }
    .title-block h1 { font-size: 19px; color: #007a33; margin: 0; font-weight: bold; }
    .title-block h2 { font-size: 15px; margin: 2px 0; font-weight: bold; }
    .title-block h3 { font-size: 13px; margin: 0; text-decoration: underline; font-weight: bold; }
    .date-line { text-align: right; font-size: 13px; font-weight: bold; margin-top: 2px; position: relative; z-index: 1; }
    .section-label { border: 1px solid #000; display: inline-block; padding: 1px 6px; font-weight: bold; font-size: 11px; margin: 3px 0; background: #fff; position: relative; z-index: 1; }
    .prop-details { font-size: 12px; margin-bottom: 5px; border-collapse: collapse; width: 100%; table-layout: fixed; position: relative; z-index: 1; }
    .prop-details td { padding: 1px 0; vertical-align: top; }
    .bold-lbl-left { font-weight: bold; width: 125px; }
    .bold-lbl-right { font-weight: bold; width: 110px; padding-left: 35px; }
    .transaction-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-bottom: 6px; position: relative; z-index: 1; }
    .transaction-table th, .transaction-table td { padding: 4px; vertical-align: top; border-bottom: .5px solid #eee; word-wrap: break-word; }
    .transaction-table .header-row th { font-weight: bold; border-bottom: 1.5px solid #000; text-align: left; }
    .transaction-table .instrument-cell { white-space: nowrap; padding-right: 10px; }
    .footer-wrapper { margin-top: 16px; width: 100%; position: relative; z-index: 1; }
    .remarks-container { border-top: 1px solid #000; width: 100%; padding-top: 8px; margin-top: 10px; display: flex; align-items: flex-start; }
    .remarks-label { border: 1px solid #000; padding: 2px 8px; font-weight: bold; font-size: 13px; background: #fff; margin-right: 12px; flex-shrink: 0; }
    .remarks-content { margin: 0; flex-grow: 1; }
    .remarks-text { color: #0f766e; font-size: 13px; font-weight: bold; margin: 8px 0; line-height: 1.4; display: block; }
    .disclaimer-nb { font-size: 9.5px; font-style: italic; font-weight: bold; text-align: center; margin: 8px 0 4px; color: #333; }
    .table-end-notice { text-align: center; font-size: 9.5px; font-weight: bold; font-style: italic; margin: 6px 0 8px; color: #000; page-break-inside: avoid; }
    .footer-bottom { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 4px; margin-top: 10px; position: relative; z-index: 1; }
    .gen-text { font-size: 9px; color: #333; text-align: center; flex: 1; }
    .footer-logo-left img, .footer-logo-right img { height: 26px; }

    @media print {
      @page { size: landscape; margin: 0.2in; }
      body { background: none; padding: 0; display: block; }
      .no-print { display: none !important; }
      .page { box-shadow: none; width: 100%; padding: 0.1in 0.2in; min-height: 95vh; }
      .transaction-table { page-break-inside: auto; }
      .transaction-table tr { page-break-inside: avoid; }
      .transaction-table thead { display: table-header-group; }
      .footer-wrapper { page-break-inside: avoid; }
      .watermark { position: fixed; left: 50%; transform: translate(-50%,-50%) rotate(-30deg); }
    }
  </style>
</head>
<body>

@if($mode === 'payment')
  <div class="action-bar no-print">
    <a href="{{ route('ols.landing', ['query' => $fileNumber]) }}" class="btn-light">&larr; Back to Search</a>
  </div>

  <div class="pay-columns">
  <div class="pay-wrap">
    <div class="pay-head">
      <h1>Submit Your Legal Search Request</h1>
      <p>Secure payment submits your request for approval. No account needed.</p>
    </div>
    <div class="pay-body">
      @php
        // Always a list, so single and multi-file requests render the same way.
        $payFiles  = $fileNumbers ?? [$fileNumber];
        $payUnit   = $unitAmount ?? $amount;
        $payCount  = count($payFiles);
      @endphp

      @if($payCount === 1)
        <div class="pay-row"><span class="lbl">File Number</span><span class="val">{{ $payFiles[0] }}</span></div>
      @else
        {{-- Every file is listed: the applicant is being charged per file, so they
             must be able to check the exact set before paying. --}}
        <div class="pay-row" style="align-items:flex-start;">
          <span class="lbl">File Numbers ({{ $payCount }})</span>
          <span class="val" style="text-align:right;">
            @foreach($payFiles as $payFile)
              <span style="display:block;">{{ $payFile }}</span>
            @endforeach
          </span>
        </div>
        <div class="pay-row">
          <span class="lbl">Per file</span>
          <span class="val">&#8358;{{ number_format($payUnit / 100) }} &times; {{ $payCount }}</span>
        </div>
      @endif

      <div class="pay-row"><span class="lbl">Total</span><span class="val pay-total">&#8358;{{ number_format($amount / 100) }}</span></div>

      @if($payCount > 1)
        <p class="pay-note" style="text-align:left;margin-top:8px;">
          Each file is searched and approved separately — you will receive
          {{ $payCount }} reports, one per file.
        </p>
      @endif

      <label for="paySearchPurpose" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Purpose of search <span style="color:#dc2626;">*</span></label>
      <select id="paySearchPurpose" required
              style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;background:#fff;">
        <option value="">— Select a purpose —</option>
        @foreach($purposes as $purpose)
          <option value="{{ $purpose->id }}">{{ $purpose->name }}</option>
        @endforeach
      </select>
      <p class="pay-note" style="margin-top:6px;text-align:left;">A search can only be carried out for one of the listed purposes.</p>

      {{-- Phone sits with the email it belongs beside, above it. It is submitted with
           the identification check, not with the payment. --}}
      <label for="payPhone" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Phone number <span style="color:#dc2626;">*</span></label>
      <input id="payPhone" type="tel" inputmode="tel" autocomplete="tel" placeholder="08031234567" maxlength="30"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <label for="payEmail" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Email address</label>
      <input id="payEmail" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <label for="payEmailConfirm" style="display:block;margin-top:12px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Confirm email address</label>
      <input id="payEmailConfirm" type="email" inputmode="email" autocomplete="email" placeholder="Re-enter your email" onpaste="return false;" ondrop="return false;"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <p class="pay-note" style="margin-top:6px;text-align:left;"><strong>Your report will be emailed to this address</strong> once approved, so please check it carefully. We also send your receipt here.</p>

      <button id="payBtn" class="pay-btn">Pay &#8358;{{ number_format($amount / 100) }} with Paystack</button>
      <div id="payError" class="pay-error"></div>
      <p class="pay-note">You will be charged once. Your request is then reviewed by the Director / Deputy Director, and the approved report is emailed to you as a PDF.</p>
    </div>
  </div>

  @include('online_legal_search.partials.identification-card')
  </div>

  <script src="https://js.paystack.co/v1/inline.js"></script>
  <script>
    (function () {
      const btn = document.getElementById('payBtn');
      const emailInput = document.getElementById('payEmail');
      const emailConfirmInput = document.getElementById('payEmailConfirm');
      const purposeInput = document.getElementById('paySearchPurpose');
      const errBox = document.getElementById('payError');
      const VERIFY_URL = @json(route('ols.payment.verify'));
      const RESULT_URL = @json(route('ols.result'));
      const CSRF = document.querySelector('meta[name="csrf-token"]').content;
      const fileNumber = @json($fileNumber);
      const searchParams = @json($searchParams);
      const PAY_LABEL = @json('Pay ₦' . number_format($amount / 100) . ' with Paystack');

      function showError(msg) { errBox.textContent = msg; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = PAY_LABEL; }

      // Send the verified buyer to their request-status page. The report itself
      // is emailed once a Director / Deputy Director approves the request.
      function openStatus(reference) {
        const qs = new URLSearchParams();
        Object.entries(searchParams || {}).forEach(([k, v]) => { if (v) qs.append(k, v); });
        qs.set('query', fileNumber);
        qs.set('ref', reference);
        window.location.href = RESULT_URL + '?' + qs.toString();
      }

      btn.addEventListener('click', function () {
        // ID name verification must have passed on the SERVER before the checkout
        // may open. window.OLS_ID_VERIFIED is set only by a verified response from
        // ols.verification.store; verifyPayment() re-checks the session-bound row
        // regardless, so tampering with this flag buys nothing.
        if (!window.OLS_ID_VERIFIED) {
          showError('Please complete the Identify your Customer (IYC) card — the check runs automatically once every field is filled and your ID photo is attached.');
          const idCard = document.getElementById('idCard');
          if (idCard) {
            idCard.classList.remove('is-collapsed');
            const idToggle = document.getElementById('idToggle');
            if (idToggle) { idToggle.setAttribute('aria-expanded', 'true'); }
            idCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          return;
        }

        // A search cannot proceed without one of the defined purposes.
        const purposeId = (purposeInput.value || '').trim();
        if (!purposeId) {
          showError('Please select the purpose of your search to continue.');
          purposeInput.focus();
          return;
        }

        const email = (emailInput.value || '').trim();
        const emailConfirm = (emailConfirmInput.value || '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          showError('Please enter a valid email address to continue.');
          emailInput.focus();
          return;
        }
        if (email.toLowerCase() !== emailConfirm.toLowerCase()) {
          showError('The email addresses do not match. Please re-enter to confirm.');
          emailConfirmInput.focus();
          return;
        }

        btn.disabled = true; btn.textContent = 'Opening secure checkout…'; errBox.style.display = 'none';
        const handler = PaystackPop.setup({
          key: @json($paystackPublicKey),
          email: email,
          amount: {{ (int) $amount }},
          currency: 'NGN',
          metadata: {
            custom_fields: [
              { display_name: 'File Number', variable_name: 'file_number', value: fileNumber },
              { display_name: 'Purpose of Search', variable_name: 'purpose', value: purposeInput.options[purposeInput.selectedIndex].text },
            ],
          },
          callback: function (response) {
            btn.textContent = 'Verifying payment…';
            fetch(VERIFY_URL, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
              body: JSON.stringify({ reference: response.reference, email: email, file_number: fileNumber, search_params: searchParams, purpose_id: purposeId }),
            })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
              if (ok && j.success) { openStatus(j.reference || response.reference); }
              else { showError((j && j.message) || 'Payment verification failed. Please contact support if you were charged.'); }
            })
            .catch(() => showError('A network error occurred while verifying your payment.'));
          },
          onClose: function () { btn.disabled = false; btn.textContent = PAY_LABEL; },
        });
        handler.openIframe();
      });
    })();
  </script>

  <script>
    /*
     * Applicant identification card.
     *
     * The checks here are a convenience only — StoreIdVerificationRequest re-applies
     * every one of them, and the verification status is computed on the server.
     * Nothing this script sends can decide the outcome.
     */
    (function () {
      const VERIFY_URL = @json(route('ols.verification.store'));
      const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
      const fileNumber = @json($fileNumber);
      const MAX_BYTES  = {{ (int) config('id_verification.uploads.max_kilobytes', 5120) }} * 1024;
      const ALLOWED    = ['image/jpeg', 'image/png', 'image/webp'];

      const el = (id) => document.getElementById(id);
      const typeSelect     = el('idType');
      const otherWrap      = el('idTypeOtherWrap');
      const customerSelect = el('idCustomerType');
      const barWrap        = el('idBarWrap');
      const frontLabel = el('idFrontLabel');
      const statusBox  = el('idStatus');
      const payBtn     = el('payBtn');
      const card       = el('idCard');
      const toggle     = el('idToggle');
      const headStatus = el('idHeadStatus');

      // Collapse/expand. Starts expanded and the state is not remembered between
      // loads: this is a required step, and reopening the page to a folded-away
      // form would be a good way to have applicants miss it.
      toggle.addEventListener('click', function () {
        const collapsed = card.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });

      window.OLS_ID_VERIFIED = false;

      const HEAD_LABELS = { ok: 'Verified', warn: 'Needs review', bad: 'Not verified' };

      function setStatus(kind, message) {
        statusBox.className = 'id-status ' + kind;
        statusBox.textContent = message;
        statusBox.style.display = 'block';

        // Mirrored into the header, which may be all the applicant can see.
        headStatus.textContent = HEAD_LABELS[kind] || '';
        headStatus.hidden = !headStatus.textContent;
      }

      // Individual or lawyer. Only a lawyer is asked for a Call-to-Bar number;
      // for an individual the field is hidden AND cleared, so a value typed before
      // switching type is never submitted.
      function applyCustomerTypeRules() {
        const opt = customerSelect.options[customerSelect.selectedIndex];
        const needsBar = opt && opt.dataset.requiresBar === '1';

        barWrap.style.display = needsBar ? 'block' : 'none';
        if (!needsBar) { el('idBarNumber').value = ''; }
      }

      // Only the label changes with the ID type - a passport's data page is not
      // a "front", so the chosen type names its own image.
      function applyTypeRules() {
        const opt = typeSelect.options[typeSelect.selectedIndex];

        otherWrap.style.display = typeSelect.value === 'other' ? 'block' : 'none';
        frontLabel.innerHTML = (opt && opt.dataset.frontLabel ? opt.dataset.frontLabel : 'Photo of your ID')
          + ' <span style="color:#dc2626;">*</span>';
      }

      function clearImage(inputId) {
        const input = el(inputId);
        const preview = el(inputId + 'Preview');
        const clearBtn = el(inputId + 'Clear');
        input.value = '';
        preview.innerHTML = '<span class="id-preview-empty">No image selected</span>';
        clearBtn.style.display = 'none';
        invalidate();
      }

      // Any edit after a pass invalidates it: the server would have to re-verify the
      // new details anyway, so the pay button must not stay unlocked.
      function invalidate() {
        if (window.OLS_ID_VERIFIED) {
          window.OLS_ID_VERIFIED = false;
          statusBox.style.display = 'none';
          headStatus.hidden = true;
        }
      }

      function wireImage(inputId) {
        const input = el(inputId);
        const preview = el(inputId + 'Preview');
        const clearBtn = el(inputId + 'Clear');

        input.addEventListener('change', function () {
          invalidate();
          const file = input.files && input.files[0];
          if (!file) { clearImage(inputId); return; }

          if (ALLOWED.indexOf(file.type) === -1) {
            setStatus('bad', 'Please choose a JPEG, PNG or WebP image.');
            clearImage(inputId);
            return;
          }
          if (file.size > MAX_BYTES) {
            setStatus('bad', 'That image is larger than 5MB. Please choose a smaller file.');
            clearImage(inputId);
            return;
          }

          const reader = new FileReader();
          reader.onload = function (e) {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Selected identification image';
            preview.appendChild(img);
            clearBtn.style.display = 'inline';
          };
          reader.readAsDataURL(file);

          // Picking the image is a deliberate act and usually the last one, so the
          // check runs without waiting out the typing debounce.
          scheduleVerification();
        });

        clearBtn.addEventListener('click', function () { clearImage(inputId); });
      }

      ['idFullName', 'idAddress', 'idTypeOther', 'idBarNumber'].forEach(function (id) {
        el(id).addEventListener('input', function () { invalidate(); scheduleVerification(); });
      });
      typeSelect.addEventListener('change', function () { applyTypeRules(); invalidate(); scheduleVerification(); });
      customerSelect.addEventListener('change', function () { applyCustomerTypeRules(); invalidate(); scheduleVerification(); });
      wireImage('idFront');
      applyTypeRules();
      applyCustomerTypeRules();

      // ---- Automatic verification -------------------------------------------
      // No button: the check fires by itself once the form is complete, and again
      // whenever an answer changes. Three guards keep that from being wasteful,
      // because each run uploads an image and costs the server an OCR pass:
      //   * a debounce, so typing a name does not fire on every keystroke;
      //   * a signature of the submitted answers, so nothing is re-sent unchanged;
      //   * an in-flight flag, so a slow check is never overlapped by another.
      // The route is rate limited regardless; these keep an honest applicant well
      // clear of that limit.
      let debounceTimer = null;
      let inFlight = false;
      let lastSignature = null;

      function currentAnswers() {
        const file = el('idFront').files && el('idFront').files[0];

        const customerOpt = customerSelect.options[customerSelect.selectedIndex];

        return {
          email:    (el('payEmail').value || '').trim(),
          phone:    (el('payPhone').value || '').trim(),
          fullName: (el('idFullName').value || '').trim(),
          address:  (el('idAddress').value || '').trim(),
          type:     typeSelect.value,
          typeOther: (el('idTypeOther').value || '').trim(),
          customerType: customerSelect.value,
          needsBar: !!(customerOpt && customerOpt.dataset.requiresBar === '1'),
          barNumber: (el('idBarNumber').value || '').trim(),
          file:     file || null,
        };
      }

      // Complete enough to be worth sending. Deliberately mirrors the server's
      // required fields — StoreIdVerificationRequest re-checks all of it.
      // What is still missing, in the order the applicant meets the fields. Named
      // rather than counted, because two of these live in the OTHER card: an
      // applicant who has filled the whole IYC card and is only missing their
      // phone number would otherwise sit in front of a form that does nothing and
      // says nothing.
      function missingAnswers(a) {
        const missing = [];

        if (!a.phone) { missing.push('your phone number'); }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.email)) { missing.push('a valid email address'); }
        if (!a.customerType) { missing.push('your customer type'); }
        if (a.needsBar && !a.barNumber) { missing.push('your Call-to-Bar number'); }
        if (!a.fullName) { missing.push('your full name'); }
        if (!a.address) { missing.push('your address'); }
        if (!a.type) { missing.push('your means of identification'); }
        if (a.type === 'other' && !a.typeOther) { missing.push('the identification type'); }
        if (!a.file) { missing.push('a photo of your ID'); }

        return missing;
      }

      // Has the applicant engaged with this card at all? Until they have, saying
      // nothing is right — the card is simply untouched.
      function identificationStarted(a) {
        return !!(a.fullName || a.address || a.type || a.file || a.barNumber);
      }

      function isComplete(a) {
        return missingAnswers(a).length === 0;
      }

      // Identifies the exact submission, image included, so re-sending an
      // unchanged form is skipped.
      function signatureOf(a) {
        return [
          a.email, a.phone, a.fullName, a.address, a.type, a.typeOther,
          a.customerType, a.barNumber,
          a.file ? a.file.name + ':' + a.file.size + ':' + a.file.lastModified : '',
        ].join('|');
      }

      function scheduleVerification() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runVerification, 900);
      }

      function runVerification() {
        if (inFlight) return;

        const answers = currentAnswers();

        const missing = missingAnswers(answers);

        if (missing.length > 0) {
          // Silent only while the card is untouched. Once they have started, tell
          // them exactly what is outstanding — including the two fields that live
          // in the payment card, which is otherwise an invisible reason for a form
          // that appears to do nothing.
          if (!identificationStarted(answers)) {
            statusBox.style.display = 'none';
            headStatus.hidden = true;
            return;
          }

          const list = missing.length === 1
            ? missing[0]
            : missing.slice(0, -1).join(', ') + ' and ' + missing[missing.length - 1];

          setStatus('warn', 'Still needed before we can check your identification: ' + list + '.');
          headStatus.textContent = 'Incomplete';
          headStatus.hidden = false;
          return;
        }

        const signature = signatureOf(answers);
        if (signature === lastSignature) return;

        const body = new FormData();
        body.append('file_number', fileNumber);
        body.append('email', answers.email);
        body.append('applicant_full_name', answers.fullName);
        body.append('applicant_phone', answers.phone);
        body.append('applicant_address', answers.address);
        body.append('customer_type', answers.customerType);
        if (answers.needsBar) { body.append('call_to_bar_number', answers.barNumber); }
        body.append('identification_type', answers.type);
        if (answers.type === 'other') { body.append('identification_type_other', answers.typeOther); }
        body.append('id_front', answers.file);

        inFlight = true;
        setStatus('warn', 'Checking your identification…');

        fetch(VERIFY_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: body })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
          .then(function (res) {
            inFlight = false;

            if (res.status === 422 && res.j && res.j.errors) {
              // A rejected submission is not remembered, so correcting the field
              // re-runs the check rather than being skipped as "unchanged".
              const first = Object.keys(res.j.errors)[0];
              setStatus('bad', (res.j.errors[first] && res.j.errors[first][0]) || 'Please check the details you entered.');
              return;
            }
            if (res.status === 429) {
              setStatus('warn', 'Too many checks in a row. Please wait a minute — this will retry when you change something.');
              return;
            }
            if (!res.ok || !res.j) { setStatus('bad', 'We could not complete the check. Please try again.'); return; }

            lastSignature = signature;
            window.OLS_ID_VERIFIED = res.j.status === 'verified';
            setStatus(res.j.status === 'verified' ? 'ok' : (res.j.status === 'review' ? 'warn' : 'bad'), res.j.message);

            if (window.OLS_ID_VERIFIED) { payBtn.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
          })
          .catch(function () {
            inFlight = false;
            setStatus('bad', 'A network error occurred while checking your identification. It will retry when you change an answer.');
          });
      }

      // Every input that forms part of the submission schedules a fresh check.
      ['payEmail', 'payPhone'].forEach(function (id) {
        el(id).addEventListener('input', function () { invalidate(); scheduleVerification(); });
      });
    })();
  </script>

@else
  {{-- Status mode: the report is delivered by email after approval, never rendered here. --}}
  @php
    $statusKey = $searchRequest->status ?? 'pending';
    $badge = [
      'pending'  => ['#fef3c7', '#92400e', 'Awaiting Approval'],
      'approved' => ['#dcfce7', '#166534', 'Approved'],
      'rejected' => ['#fee2e2', '#991b1b', 'Not Approved'],
    ][$statusKey] ?? ['#e2e8f0', '#334155', ucfirst($statusKey)];
  @endphp

  <div class="action-bar no-print">
    <a href="{{ route('ols.landing') }}" class="btn-light">&larr; New Search</a>
    <span style="color:#e2e8f0;font-size:12px;">Ref: {{ $payment->tracking_id ?? $payment->reference }}</span>
  </div>

  {{-- Same two-column shell as the payment step, so the request and the
       identification behind it stay side by side after payment too. --}}
  <div class="pay-columns">
  <div class="pay-wrap">
    @php
      // Always a collection, so a single-file payment renders exactly as before.
      $allRequests = collect($searchRequests ?? [$searchRequest])->filter();
      $requestCount = $allRequests->count();
    @endphp
    <div class="pay-head">
      @if($requestCount > 1)
        <h1>{{ $requestCount }} Legal Search Requests</h1>
        <p>Your payment was received and each file has been submitted for approval.</p>
      @else
        <h1>Request {{ $searchRequest->request_no }}</h1>
        <p>Your payment was received and your Legal Search request has been submitted.</p>
      @endif
    </div>
    <div class="pay-body">
      @if($requestCount > 1)
        {{-- Each file is approved on its own, so each gets its own row: one may be
             approved while another is still with the Director. --}}
        @foreach($allRequests as $req)
          @php
            $reqKey = $req->status ?? 'pending';
            $reqBadge = [
              'pending'  => ['#fef3c7', '#92400e', 'Awaiting Approval'],
              'approved' => ['#dcfce7', '#166534', 'Approved'],
              'rejected' => ['#fee2e2', '#991b1b', 'Not Approved'],
            ][$reqKey] ?? ['#e2e8f0', '#334155', ucfirst($reqKey)];
          @endphp
          <div class="pay-row" style="align-items:center;">
            <span class="lbl">
              <strong style="color:#1f2937;">{{ $req->file_number }}</strong><br>
              <span style="font-size:11px;">{{ $req->request_no }}</span>
            </span>
            <span class="val" style="background:{{ $reqBadge[0] }};color:{{ $reqBadge[1] }};padding:4px 10px;border-radius:999px;font-size:12px;">{{ $reqBadge[2] }}</span>
          </div>
        @endforeach
      @else
        <div class="pay-row">
          <span class="lbl">Status</span>
          <span class="val" style="background:{{ $badge[0] }};color:{{ $badge[1] }};padding:4px 10px;border-radius:999px;font-size:12px;">{{ $badge[2] }}</span>
        </div>
        <div class="pay-row"><span class="lbl">Request No.</span><span class="val">{{ $searchRequest->request_no }}</span></div>
        <div class="pay-row"><span class="lbl">File Number</span><span class="val">{{ $fileNumber }}</span></div>
      @endif
      <div class="pay-row"><span class="lbl">Purpose</span><span class="val">{{ $searchRequest->purpose ?: '—' }}</span></div>
      <div class="pay-row"><span class="lbl">Payment Ref</span><span class="val">{{ $payment->tracking_id ?? $payment->reference }}</span></div>
      <div class="pay-row"><span class="lbl">Report goes to</span><span class="val">{{ $payment->email }}</span></div>
      <div class="pay-row"><span class="lbl">Submitted</span><span class="val">{{ optional($searchRequest->submitted_at)->format('d M Y, g:i A') }}</span></div>

      @if($requestCount > 1)
        <div style="margin-top:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:14px;border-radius:10px;font-size:13px;">
          <strong>Your Online Legal Search (ONLS) request has been sent to the Director / Deputy Director
          for Confirmation, Verification &amp; Approval.</strong>
          Once your request has been Verified &amp; Approved, the full Legal Search report will be emailed
          to <strong>{{ $payment->email }}</strong> as a PDF attachment — one report per file, sent as each
          file is approved. You do not need to keep this page open.
        </div>
      @elseif($statusKey === 'approved')
        <div style="margin-top:18px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:14px;border-radius:10px;font-size:13px;">
          <strong>Approved{{ $searchRequest->reviewer_name ? ' by ' . $searchRequest->reviewer_name : '' }}.</strong>
          Your Legal Search report has been emailed to <strong>{{ $payment->email }}</strong> as a PDF attachment.
          If it has not arrived, please check your spam folder before contacting the Ministry.
        </div>
      @elseif($statusKey === 'rejected')
        <div style="margin-top:18px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px;border-radius:10px;font-size:13px;">
          <strong>This request was not approved.</strong>
          @if($searchRequest->rejection_reason)<br>Reason: {{ $searchRequest->rejection_reason }}@endif
          <br>Please contact the Ministry quoting request number {{ $searchRequest->request_no }}.
        </div>
      @else
        <div style="margin-top:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:14px;border-radius:10px;font-size:13px;">
          {{-- The address is the one the payment was made under, so the applicant
               can see exactly where the report will land before they close the page. --}}
          <strong>Your Online Legal Search (ONLS) request has been sent to the Director / Deputy Director
          for Confirmation, Verification &amp; Approval.</strong>
          Once your request has been Verified &amp; Approved, the full Legal Search report will be emailed
          to <strong>{{ $payment->email }}</strong> as a PDF attachment.
          You do not need to keep this page open.
        </div>
      @endif

      @if($requestCount > 1)
        <p class="pay-note">
          Keep request numbers
          <strong>{{ $allRequests->pluck('request_no')->filter()->implode(', ') }}</strong>
          for any correspondence about this search.
        </p>
      @else
        <p class="pay-note">Keep request number <strong>{{ $searchRequest->request_no }}</strong> for any correspondence about this search.</p>
      @endif
    </div>
  </div>

  {{-- Read-only: the identification was checked before payment and is part of the
       record under review, so it is shown back but never re-opened for editing.
       Absent only for payments made before the IYC step existed. --}}
  @if(!empty($verification))
    @include('online_legal_search.partials.identification-summary', ['verification' => $verification])
  @endif
  </div>
@endif

</body>
</html>
