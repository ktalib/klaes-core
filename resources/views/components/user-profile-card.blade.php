{{-- Shared "who created this" card. Opened by any element carrying
     data-user-card (with data-user-id and/or data-user-name) — see
     js/user-profile-card.js. Mounted once in the app layout so every table
     that shows a creator can use it without its own markup. --}}
<div id="userProfileCard" class="upc-overlay" data-endpoint="{{ route('users.profile-card') }}"
    {{-- Where a Super Admin's card reports its face-check verdict on somebody else's
         picture, so a stock avatar spotted here is flagged without waiting for that
         person to sign in. The server decides whether the report is accepted. --}}
    data-face-endpoint="{{ route('profile.picture.face-check') }}"
    data-csrf="{{ csrf_token() }}"
    role="dialog" aria-modal="true" aria-labelledby="upcName" aria-hidden="true">
    <div class="upc-card">
        <button type="button" class="upc-close" data-upc-close aria-label="{{ __('Close') }}">&times;</button>

        <div class="upc-head">
            <div class="upc-avatar-wrap">
                <div class="upc-avatar">
                    <img id="upcPhoto" src="" alt="" class="upc-hidden">
                    <span id="upcPhotoFallback" class="upc-initials"></span>
                </div>
                {{-- Verified tick when a passport photo is on file, an alert mark when it
                     is still missing. Filled in by js/user-profile-card.js. --}}
                <span id="upcBadge" class="upc-badge" role="img"></span>
            </div>
            <h3 class="upc-name" id="upcName">&nbsp;</h3>
            <p class="upc-username" id="upcUsername"></p>
            <p class="upc-photo-state" id="upcPhotoState"></p>
        </div>

        <dl class="upc-details">
            <div class="upc-row">
                <dt>{{ __('Full name') }}</dt>
                <dd id="upcFullName">—</dd>
            </div>
            <div class="upc-row">
                <dt>{{ __('Username') }}</dt>
                <dd id="upcUsernameValue">—</dd>
            </div>
            <div class="upc-row">
                <dt>{{ __('Phone') }}</dt>
                <dd id="upcPhone">—</dd>
            </div>
        </dl>

        {{-- Face-detection result for the photo being viewed. Runs in the browser on
             the image already displayed; nothing is uploaded or stored. --}}
        <p id="upcFaceCheck" class="upc-face-check upc-hidden"></p>

        <p id="upcState" class="upc-state upc-hidden"></p>
    </div>
</div>
