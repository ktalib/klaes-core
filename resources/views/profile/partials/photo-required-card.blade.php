{{-- Mandatory passport-photo card. Deliberately its own design and its own CSS
     (css/profile-photo-card.css) rather than the welcome popup's shell, so changes to
     either card never disturb the other. Rendered only while the account still needs a
     photo; RequireProfilePhoto enforces the same rule server-side. --}}
@php
    $photoCardPrimaryLogo = asset('assets/logo/logo.png');
    $photoCardSecondaryLogo = asset('assets/logo/Left_Logo.png');

    if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png')) {
            $photoCardPrimaryLogo = asset('storage/upload/logo/logo.png');
        }
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
            $photoCardSecondaryLogo = asset('storage/uploads/logo.jpeg');
        }
    }
@endphp

<link rel="stylesheet" href="{{ asset('css/profile-photo-card.css') }}">

<div id="profilePhotoCard" class="ppc-overlay"
    data-upload-url="{{ route('profile.picture.store') }}"
    data-max-kb="{{ \App\Services\ProfilePhotoService::MAX_KILOBYTES }}"
    data-auto-open="{{ session('open_profile_photo_card') ? 'true' : 'false' }}"
    role="dialog" aria-modal="true" aria-labelledby="ppcTitle" aria-hidden="true">

    <div class="ppc-card">
        <div class="ppc-masthead">
            <div class="ppc-logos">
                <img src="{{ $photoCardPrimaryLogo }}" alt="KLAES">
                <span class="ppc-logo-rule" aria-hidden="true"></span>
                <img src="{{ $photoCardSecondaryLogo }}" alt="Land Admin Enterprise System">
            </div>
            <p class="ppc-eyebrow">{{ __('Account setup') }}</p>
        </div>

        {{-- Landscape: picture on the left, verification detail on the right.
             Stacks back to one column on a narrow screen. --}}
        <div class="ppc-body">
            <div class="ppc-split">

                {{-- Left: the picture itself, square and large enough to judge. --}}
                <div class="ppc-pane-left">
                    <div class="ppc-frame" id="profilePhotoDropzone" role="button" tabindex="0"
                        aria-label="{{ __('Choose a passport photo') }}">
                        <img id="profilePhotoPreview" src="" alt="{{ __('Selected photo') }}" class="ppc-hidden">
                        {{-- Bounding box over the detected face, drawn by js/profile-photo-card.js. --}}
                        <canvas id="profilePhotoBox" class="ppc-box"></canvas>
                        <span id="profilePhotoPreviewPlaceholder" class="ppc-frame-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                <circle cx="12" cy="13" r="4" />
                            </svg>
                            <span class="ppc-frame-hint">{{ __('Click to choose a photo') }}</span>
                        </span>
                    </div>

                    <div class="ppc-field">
                        <label class="ppc-label" for="profilePhotoInput">{{ __('Passport photo') }}</label>
                        <input type="file" id="profilePhotoInput" name="profile" accept="image/jpeg,image/png,image/gif"
                            class="ppc-file">
                        <p class="ppc-hint">{{ __('JPG, PNG or GIF · maximum 2MB') }}</p>
                    </div>
                </div>

                {{-- Right: why it is being asked for, then what the detector found. --}}
                <div class="ppc-pane-right">
                    <h2 class="ppc-title" id="ppcTitle">
                        {{ auth()->user()->photo_face_rejected ? __('Real Photograph Required') : __('Profile Picture Required') }}
                    </h2>
                    <p class="ppc-lede">
                        {{ __('Hello') }}
                        <strong>{{ auth()->user()->first_name ?? auth()->user()->name }}</strong>,
                        @if (auth()->user()->photo_face_rejected)
                            {{ __('the picture on your account is not a photograph of your face, so it identifies you to nobody. A real passport photo is required before you can use KLAES.') }}
                        @else
                            {{ __('a passport photo is required before you can use KLAES. It identifies you on files, requests and approvals.') }}
                        @endif
                    </p>

                    {{-- What was actually wrong with the picture already on file, in the
                         detector's own words. Without it a rejection is just a refusal. --}}
                    @if (auth()->user()->photo_face_rejected)
                        <div class="ppc-verify">
                            <p class="ppc-verify-heading">{{ __('Picture currently on your account') }}</p>
                            <div class="ppc-current">
                                <img src="{{ auth()->user()->profile_url }}" alt="{{ __('Current picture') }}" class="ppc-current-thumb">
                                <p class="ppc-current-reason">
                                    {{ __('Rejected') }}@if (auth()->user()->photo_face_reason) — {{ auth()->user()->photo_face_reason }}@endif.
                                    {{ __('Please upload a clear photograph of your own face.') }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="ppc-verify">
                        <p class="ppc-verify-heading">{{ __('Face verification') }}</p>

                        {{-- Headline verdict; the rows below carry the measurements. --}}
                        <p id="profilePhotoFaceNote" class="ppc-face-note">
                            {{ __('Choose a photo to check it.') }}
                        </p>

                        <div id="profilePhotoRows" class="ppc-rows ppc-hidden"></div>

                        <p id="profilePhotoError" class="ppc-error ppc-hidden" role="alert"></p>
                    </div>

                    <button type="button" id="profilePhotoSubmit" class="ppc-submit">
                        {{ __('Upload Profile Picture') }}
                    </button>

                    <p class="ppc-footnote">
                        {{ auth()->user()->photo_face_rejected
                            ? __('The system stays locked until a photograph of your face is uploaded.')
                            : __('The system stays locked until a picture is uploaded.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/profile-photo-card.js') }}" defer></script>
