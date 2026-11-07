@extends('surveyor_portal.layouts.guest')

@section('title', 'Register — KNSSGOF Portal')
@section('width', '520px')

@section('guest-banner')
    <img src="{{ asset('assets/logo/knssgof-register-banner.png') }}"
         alt="KNSSGOF — Kano State Surveyor General's Office Portal"
         width="1600" height="666" class="relative"
         style="width:100%;max-width:620px;height:auto;mix-blend-mode:multiply;">
@endsection

@section('guest-mobile-banner')
    <img src="{{ asset('assets/logo/knssgof-register-banner.png') }}"
         alt="KNSSGOF — Kano State Surveyor General's Office Portal"
         width="1600" height="666" class="mx-auto mb-5 lg:hidden"
         style="width:100%;max-width:460px;height:auto;mix-blend-mode:multiply;">
@endsection

@section('card')
    <h1 class="text-lg font-bold mb-1" id="ksHeading">Register as a surveyor</h1>
    <p class="ks-help mb-4">The Office of the Surveyor General confirms each registration. We will text you when you can sign in.</p>
    @php $co = old('company_choice'); @endphp
    <form method="POST" action="{{ route('surveyor-portal.register.submit') }}" class="space-y-4" enctype="multipart/form-data" id="ksRegisterForm">
        @csrf
        <div id="ksCompanyBox">
            <label class="ks-label" for="company_choice">Company <span class="ks-req">*</span></label>
            <select class="ks-input" id="company_choice" name="company_choice" onchange="ksCompany()">
                <option value="">Select company…</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $co === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            @error('company_choice')<p class="ks-error">{{ $message }}</p>@enderror
            <p class="ks-help mt-2">Only approved companies are listed. If your company is not listed, <a href="{{ route('surveyor-portal.company.register') }}" style="color:var(--ks-blue);font-weight:600;">submit it for approval</a>.</p>
        </div>
        <div id="ksPersonalName">
            <label class="ks-label" for="name"><span id="ksNameLabel">Surveyor's Name</span> <span class="ks-req">*</span></label>
            <input class="ks-input" id="name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name">
            @error('name')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        {{-- Passport photograph (user, 2026-11-05). Checked for one clear face in the browser, as at MLPP commissioning. --}}
        <div id="ksPersonalPassport">
            <span class="ks-label"><span id="ksPassportLabel">Passport Photograph</span> <span class="ks-req">*</span></span>
            <div class="flex items-start gap-4">
                <div id="ksPassportPreview" aria-hidden="true"
                     style="width:84px;height:108px;flex-shrink:0;border-radius:8px;border:1px dashed #B8BEC5;background:#F5F6F7 center/cover no-repeat;display:grid;place-items:center;color:#9AA1A9;">
                    <i data-lucide="user-round" class="w-8 h-8"></i>
                </div>
                <div class="min-w-0">
                    <label for="passport" class="ks-btn ks-btn-light" style="padding:8px 14px;cursor:pointer;"><i data-lucide="upload" class="w-4 h-4"></i> Choose photo</label>
                    <input class="sr-only" id="passport" type="file" name="passport" accept="image/jpeg,image/png" required>
                    <p class="ks-help" style="margin-top:6px;">A clear, recent photograph of <span id="ksPassportWhose">your face</span> alone, like a passport photo. JPG or PNG, up to 2 MB.</p>
                    <p id="ksPassportStatus" class="ks-help" style="margin-top:4px;" aria-live="polite">
                        @if ($errors->any() && ! $errors->has('passport'))Please choose your photo again.@endif
                    </p>
                    @error('passport')<p class="ks-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="phone"><span id="ksPhoneLabel">Phone</span> <span class="ks-req">*</span></label>
                <input class="ks-input" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" required autocomplete="tel" placeholder="08031234567">
                @error('phone')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="email"><span id="ksEmailLabel">Email</span> <span class="ks-req">*</span></label>
                <input class="ks-input" id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" required autocomplete="email">
                @error('email')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div id="ksPersonalSurcon">
            <label class="ks-label" for="surcon_number"><span id="ksSurconLabel">SURCON No.</span></label>
            <input class="ks-input" id="surcon_number" name="surcon_number" value="{{ old('surcon_number') }}" maxlength="50">
            @error('surcon_number')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        @include('surveyor_portal.partials._address_builder')
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="password">Password <span class="ks-req">*</span></label>
                <input class="ks-input" id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
                @error('password')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="password_confirmation">Confirm Password <span class="ks-req">*</span></label>
                <input class="ks-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        <button class="ks-btn ks-btn-primary w-full" id="ksSubmitLabel">Register</button>
    </form>
    <p class="ks-help text-center mt-4">Already registered? <a href="{{ route('surveyor-portal.login') }}" style="color:var(--ks-blue);font-weight:600;">Sign in</a></p>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    /* The address builder in the portal's own look: select2 boxes the same size as .ks-input. */
    .ks-address { border: 1px solid var(--ks-line); border-radius: 10px; padding: 14px; background: #FAFBFC; }
    .ks-address .ab-legend { font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ks-blue); margin-bottom: 10px; }
    .ks-address .select2-container--default .select2-selection--single { height: 42px; border: 1px solid #B8BEC5; border-radius: 8px; background: #fff; }
    .ks-address .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; padding-left: 12px; color: var(--ks-ink); font-size: 14px; }
    .ks-address .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6B7280; }
    .ks-address .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    .ks-address .select2-container--default.select2-container--focus .select2-selection--single,
    .ks-address .select2-container--default.select2-container--open .select2-selection--single { border-color: var(--ks-gold); }
    .select2-dropdown { font-size: 14px; }
</style>
@endpush

@push('scripts')
{{-- jQuery + select2 for the address builder, then the builder's shared script. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('survey_module.partials._address_builder_js')
<script>
    // The shared script keeps its list open for multi-selects; here each box takes one value, so a pick closes it.
    if (window.jQuery) {
        jQuery(document).on('select2:select', '.address-builder select', function () { jQuery(this).select2('close'); });
        // A message about the address goes as soon as the address changes.
        jQuery(document).on('change input', '.address-builder select, .address-builder input', function () { jQuery('#ksAddressError').hide().text(''); });
    }
</script>
{{-- The staff layout's face detector, with its settings (layouts/app.blade.php). Nothing leaves the browser. --}}
<script>
    window.FaceDetectionAssets = {
        api: @json(asset('js/face-detection/face-api.js')),
        detector: @json(asset('js/face-detection/profile-picture-detector.js')),
        models: @json(asset('models/face-detection')),
        thresholds: { minConfidence: 0.60, minFaceCoverage: 0.05, rejectIllustrations: true, maxFlatness: 0.62 }
    };
</script>
<script src="{{ asset('js/face-detection/loader.js') }}?v={{ filemtime(public_path('js/face-detection/loader.js')) }}"></script>
<script src="{{ asset('js/passport-face-check.js') }}?v={{ filemtime(public_path('js/passport-face-check.js')) }}"></script>
<script>
    (function () {
        var input = document.getElementById('passport'), box = document.getElementById('ksPassportPreview'),
            status = document.getElementById('ksPassportStatus'), form = document.getElementById('ksRegisterForm');
        var say = function (text, bad) { status.textContent = text; status.className = bad ? 'ks-error' : 'ks-help'; };
        var show = function (file) {
            if (!file) { box.style.backgroundImage = ''; box.firstElementChild && (box.firstElementChild.style.display = ''); return; }
            box.style.backgroundImage = 'url(' + URL.createObjectURL(file) + ')';
            box.style.borderStyle = 'solid';
            if (box.firstElementChild) box.firstElementChild.style.display = 'none';
        };
        var keep = function (file) {
            try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch (e) { /* older browser: keep the original */ }
        };
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            input.dataset.faceCheck = '';
            if (!file) { show(null); say(''); return; }
            if (!/^image\/(jpeg|png)$/.test(file.type)) { input.value = ''; show(null); say('Choose a JPG or PNG picture.', true); return; }
            if (!window.PassportFaceCheck) { show(file); say(file.size > 2097152 ? 'This picture is larger than 2 MB.' : 'Photo chosen.', file.size > 2097152); return; }
            input.dataset.faceCheck = 'pending';
            show(file); say('Checking the photo…');
            window.PassportFaceCheck.check(file).then(function (res) {
                input.dataset.faceCheck = '';
                if (!res.ok) { input.value = ''; show(null); say(res.message.replace(/the applicant's/g, ksPrivate() ? "the principal surveyor's" : 'your').replace(/of the applicant/g, ksPrivate() ? 'of the principal surveyor' : 'of yourself'), true); return; }
                if (res.cropped && res.file) { keep(res.file); show(res.file); }
                var kept = (input.files && input.files[0]) || file;
                if (kept.size > 2097152) { input.value = ''; show(null); say('This picture is larger than 2 MB. Choose a smaller one.', true); return; }
                say(res.cropped ? 'Photo checked and cropped to your face.' : (res.skipped ? 'Photo chosen.' : 'Photo checked.'));
            }).catch(function () { input.dataset.faceCheck = ''; say('Photo chosen.'); });
        });
        form.addEventListener('submit', function (e) {
            if (input.dataset.faceCheck === 'pending') { e.preventDefault(); say('Wait a moment: the photo is still being checked.', true); return; }
            // Checked here too: a server refusal comes back without the chosen photo, which a browser cannot refill.
            var field = function (id) { var el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; };
            var problem = '';
            if (!field('addr_lga')) problem = 'Enter the LGA in your address.';
            else if (!field('addr_state')) problem = 'Choose the state in your address.';
            var box = document.getElementById('ksAddressError');
            box.textContent = problem;
            box.style.display = problem ? '' : 'none';
            if (problem) { e.preventDefault(); box.closest('.address-builder').scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
        document.getElementById('addr_lga').addEventListener('input', function () {
            var box = document.getElementById('ksAddressError');
            box.textContent = '';
            box.style.display = 'none';
        });
    })();

    function ksPrivate() {
        return true;
    }

    function ksCompany() {
        var priv = ksPrivate();
        var sel = document.getElementById('company_choice');
        document.getElementById('ksCompanyBox').style.display = priv ? '' : 'none';
        sel.required = priv;
        document.getElementById('ksSubmitLabel').textContent = 'Register';
        document.querySelector('#ksRegisterForm .ab-legend').textContent = 'Address';
    }
    ksCompany();
</script>
@endpush
