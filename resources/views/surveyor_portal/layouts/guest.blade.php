@extends('surveyor_portal.layouts.app')

{{-- Sign in, register and password pages. The wordmark is the banner, the seal the logo (user, 2026-11-05). --}}
@push('styles')
<style>
    .ks-guest { display:grid; grid-template-columns:minmax(0,1fr); min-height:calc(100vh - 68px); }
    @media (min-width:1024px) { .ks-guest { grid-template-columns:minmax(0,1fr) minmax(0,1fr); } }
    /* clip, not hidden: hidden would make the banner a scroll box and stop the inner panel sticking. */
    .ks-guest .ks-guest-banner { overflow:clip; }
    .ks-guest-banner-inner { position:sticky; top:68px; align-self:flex-start; display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; min-height:calc(100vh - 68px); padding:48px 40px 56px; }
    .ks-guest-points { display:grid; gap:10px; width:100%; max-width:440px; margin-top:26px; }
    .ks-guest-points li { display:flex; align-items:center; gap:12px; padding:11px 14px; border:1px solid #E6EAF0; border-radius:12px; background:rgba(255,255,255,.9); color:var(--ks-ink); font-size:14px; font-weight:600; box-shadow:0 4px 14px rgba(11,58,126,.05); }
    .ks-guest-point-icon { display:grid; flex:0 0 36px; width:36px; height:36px; place-items:center; border-radius:10px; }
    .ks-guest-main { display:flex; align-items:flex-start; justify-content:center; padding:56px 16px 48px; background:var(--ks-page); }
    @media (min-width:1024px) { .ks-guest-main { align-items:center; } }
    .ks-auth-card { position:relative; margin-top:34px; padding:30px 30px 26px; border:1px solid #D5DAE0; border-radius:16px; background:#fff; box-shadow:0 18px 40px -22px rgba(11,58,126,.4); }
    .ks-auth-card::before { position:absolute; top:-1px; left:24px; right:24px; height:4px; border-radius:0 0 4px 4px; background:linear-gradient(90deg,var(--ks-blue) 0 33.3%,var(--ks-green) 33.3% 66.6%,var(--ks-gold) 66.6% 100%); content:""; }
    .ks-auth-seal { display:block; width:68px; height:68px; margin:-66px auto 12px; border-radius:50%; background:#fff; box-shadow:0 0 0 5px #fff, 0 6px 16px rgba(0,0,0,.18); }
    .ks-auth-card h1 { color:#102A4C; font-size:22px; font-weight:800; letter-spacing:-.01em; line-height:1.25; text-align:center; }
    .ks-auth-card h1 + .ks-help { text-align:center; }
    .ks-auth-card .ks-input { min-height:44px; transition:border-color .15s ease, box-shadow .15s ease; }
    .ks-auth-card .ks-input:hover { border-color:#8E97A1; }
    .ks-auth-card .ks-input:focus { border-color:var(--ks-blue); outline:0; box-shadow:0 0 0 3px rgba(201,154,30,.35); }
    .ks-auth-card .ks-btn-primary { min-height:46px; font-size:15px; box-shadow:0 6px 16px -8px rgba(11,58,126,.7); }
    .ks-auth-card .ks-btn:focus-visible { outline:2px solid var(--ks-gold); outline-offset:2px; }
    .ks-auth-alt { margin-top:18px; padding-top:16px; border-top:1px solid #ECEEF0; text-align:center; }
    /* Leading icon inside an input. */
    .ks-field { position:relative; }
    .ks-field > svg { position:absolute; top:50%; left:13px; width:18px; height:18px; transform:translateY(-50%); color:#7A838C; pointer-events:none; }
    .ks-field > .ks-input { padding-left:40px; }
    /* Show / hide password: the eye button sits inside the field on the right. */
    .ks-pw { position:relative; }
    .ks-pw > .ks-input { padding-right:46px; }
    .ks-pw-toggle { position:absolute; top:50%; right:6px; display:grid; place-items:center; width:34px; height:34px; transform:translateY(-50%); border:0; border-radius:8px; background:transparent; color:#5F6873; cursor:pointer; }
    .ks-pw-toggle:hover { background:#F0F2F5; color:var(--ks-blue); }
    .ks-pw-toggle:focus-visible { outline:2px solid var(--ks-gold); outline-offset:1px; }
    @media (max-width:520px) { .ks-auth-card { padding:26px 18px 22px; } }
</style>
@endpush

@section('body')
@include('surveyor_portal.partials.public_nav')

<div class="ks-guest">
    <aside class="hidden lg:flex ks-guest-banner">
        <div class="ks-guest-banner-inner">
            {{-- The Ministry's address under the image; the "Under the Ministry" line is not shown here (user, 2026-11-10). --}}
            @hasSection('guest-banner')
                @yield('guest-banner')
                <p class="relative ks-help" style="margin:10px 0 0;text-align:center;font-size:14px;max-width:520px;">{{ $portalSettings['ministry_address'] }}</p>
            @else
                <img src="{{ asset($portalSettings['logo_wordmark']) }}" alt="{{ $portalSettings['brand_name'] }}, {{ $portalSettings['office_name'] }} Portal" width="900" height="300"
                     class="relative" style="width:100%;max-width:520px;height:auto;mix-blend-mode:multiply;">
                <p class="relative ks-help" style="margin:10px 0 0;text-align:center;font-size:14px;max-width:520px;">{{ $portalSettings['ministry_address'] }}</p>
                <ul class="relative ks-guest-points">
                    <li><span class="ks-guest-point-icon" style="background:#EAF1FB;color:var(--ks-blue);"><i data-lucide="user-plus" class="w-5 h-5"></i></span> Register once, approved by the SG's office</li>
                    <li><span class="ks-guest-point-icon" style="background:#EAF6EE;color:var(--ks-green);"><i data-lucide="map-pin" class="w-5 h-5"></i></span> Request public or private beacon numbers</li>
                    <li><span class="ks-guest-point-icon" style="background:#FBF4E2;color:#9A7414;"><i data-lucide="printer" class="w-5 h-5"></i></span> Accept them and print your issue slip</li>
                </ul>
            @endif
        </div>
    </aside>

    <div class="ks-guest-main">
        <div class="w-full" style="max-width:@yield('width', '440px');">
            @hasSection('guest-mobile-banner')
                @yield('guest-mobile-banner')
            @else
                <img src="{{ asset($portalSettings['logo_wordmark']) }}" alt="{{ $portalSettings['brand_name'] }}" width="900" height="300" class="mx-auto lg:hidden" style="width:100%;max-width:280px;height:auto;">
            @endif
            <p class="ks-help lg:hidden" style="margin:6px 0 20px;text-align:center;font-size:12px;">{{ $portalSettings['ministry_address'] }}</p>
            @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
            <div class="ks-auth-card">
                <img src="{{ asset($portalSettings['logo_seal']) }}" alt="{{ $portalSettings['brand_name'] }} seal" width="68" height="68" class="ks-auth-seal">
                @yield('card')
            </div>
            @include('partials.vmal_credit', ['dark' => false])
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // An eye button on every password field, to show or hide what was typed.
    (function () {
        var eye = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
        var eyeOff = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>';
        document.querySelectorAll('.ks-auth-card input[type="password"]').forEach(function (input) {
            var wrap = input.parentElement.classList.contains('ks-pw') ? input.parentElement : null;
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'ks-pw';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ks-pw-toggle';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');
            btn.innerHTML = eye;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? eyeOff : eye;
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                input.focus();
            });
            wrap.appendChild(btn);
        });
        // Never submit a password field left in "show" mode as text: put it back first.
        document.querySelectorAll('.ks-auth-card form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('.ks-pw input').forEach(function (i) { i.type = 'password'; });
            });
        });
    })();
</script>
@endpush
