{{--
    Online Legal Search — Identify your Customer (IYC) card.

    Sits beside the payment card on desktop and stacks below it on smaller
    screens. Submitting it runs ID NAME verification on the server: the name
    typed here is compared with the text OCR reads off the uploaded document.

    That check confirms the NAME MATCHES. It is not a check that the document is
    genuine, unaltered, or presented by its rightful holder — the wording on this
    card is deliberately limited to what is actually verified.

    Every rule below is re-applied server-side (StoreIdVerificationRequest); the
    JavaScript here only saves the applicant a round trip.
--}}
@php
    $idTypes = config('id_verification.types', []);
    $customerTypes = config('id_verification.customer_types', []);
@endphp

<div class="pay-wrap" id="idCard">
    {{-- The whole header is the toggle: a real <button> so it is reachable by
         keyboard and announced correctly, rather than a clickable <div>. --}}
    <button type="button" id="idToggle" class="pay-head id-head-toggle"
            aria-expanded="true" aria-controls="idCardBody">
        <span class="id-head-text">
            <h1>Identify your Customer (IYC)</h1>
            <p>We check that the name you enter matches the name on your ID.</p>
        </span>
        {{-- Carries the result while the card is collapsed, so folding it away
             never hides whether the check passed. --}}
        <span id="idHeadStatus" class="id-head-status" hidden></span>
        <span class="id-head-chevron" aria-hidden="true">&#9662;</span>
    </button>
    <div class="pay-body" id="idCardBody">

        <label for="idCustomerType" class="id-label">Customer type <span style="color:#dc2626;">*</span></label>
        <select id="idCustomerType" class="id-input">
            @foreach($customerTypes as $key => $type)
                <option value="{{ $key }}"
                        data-requires-bar="{{ !empty($type['requires_bar_number']) ? '1' : '0' }}">
                    {{ $type['label'] }}
                </option>
            @endforeach
        </select>

        {{-- Lawyers only. A lawyer still selects a means of identification and
             uploads the same ID as anyone else; the bar number is additional, not
             a substitute for it. --}}
        <div id="idBarWrap" class="id-specify" style="display:none;">
            <label for="idBarNumber" class="id-label" style="margin-top:0;">Call-to-Bar number <span style="color:#dc2626;">*</span></label>
            <input id="idBarNumber" type="text" class="id-input"
                   placeholder="e.g. SCN123456" maxlength="60" autocomplete="off" />
            <p class="pay-note" style="text-align:left;margin-top:6px;">
                Checked against your uploaded ID where it appears there. Most IDs do not
                print it, in which case the approving officer confirms it.
            </p>
        </div>

        <label for="idFullName" class="id-label">Full name <span style="color:#dc2626;">*</span></label>
        <input id="idFullName" type="text" class="id-input" autocomplete="name"
               placeholder="As written on your identification" maxlength="200" />

        <label for="idAddress" class="id-label">Residential / contact address <span style="color:#dc2626;">*</span></label>
        <textarea id="idAddress" class="id-input" rows="2" autocomplete="street-address"
                  placeholder="House number, street, town" maxlength="500"></textarea>

        <label for="idType" class="id-label">Means of identification <span style="color:#dc2626;">*</span></label>
        <select id="idType" class="id-input">
            <option value="">— Select your ID —</option>
            @foreach($idTypes as $key => $type)
                <option value="{{ $key }}"
                        data-front-label="{{ $type['front_label'] ?? 'Photo of your ID' }}">
                    {{ $type['label'] }}
                </option>
            @endforeach
        </select>

        {{-- Shown only for "Other government-issued ID", and required there: an
             unlabelled "other" tells the approving officer nothing. Indented and
             tinted so it reads as a follow-up to the select above rather than as
             a second, competing "identification type" question. --}}
        <div id="idTypeOtherWrap" class="id-specify" style="display:none;">
            <label for="idTypeOther" class="id-label" style="margin-top:0;">
                Please specify the identification type <span style="color:#dc2626;">*</span>
            </label>
            <input id="idTypeOther" type="text" class="id-input"
                   placeholder="e.g. NYSC Discharge Certificate, Government Staff ID" maxlength="120" />
        </div>

        {{-- One image only: the side carrying the name. Every accepted document
             prints it on the front, so a second upload added a step without
             adding anything to the comparison. --}}
        <div class="id-upload">
            <label for="idFront" class="id-label" id="idFrontLabel">Photo of your ID <span style="color:#dc2626;">*</span></label>
            <div class="id-preview id-preview-wide" id="idFrontPreview"><span class="id-preview-empty">No image selected</span></div>
            <input id="idFront" type="file" accept="image/jpeg,image/png,image/webp" class="id-file" />
            <button type="button" class="id-clear" id="idFrontClear" style="display:none;">Replace image</button>
        </div>

        <p class="pay-note" style="text-align:left;margin-top:10px;">
            JPEG, PNG or WebP, up to 5MB. Show the side with your name on it. Your document is
            stored privately and is only visible to the approving officer.
        </p>

        {{-- No button: the check runs on its own once every field above is filled
             and an image is chosen, and again whenever one of them changes. --}}
        <div id="idStatus" class="id-status" style="display:none;"></div>

        <p class="pay-note" style="text-align:left;">
            This step confirms the name you entered matches the name printed on your ID.
            It is not a check of the document's authenticity.
        </p>
    </div>
</div>
