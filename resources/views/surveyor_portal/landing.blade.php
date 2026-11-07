@extends('surveyor_portal.layouts.app')

@section('title', 'KNSSGOF Portal · Kano State Surveyor General\'s Office')

@push('styles')
<style>
    .ks-hero { position: relative; overflow: hidden; background: #fff; border-bottom: 1px solid var(--ks-line); }
    /* A surveyor's grid, faint, fading out at the edges. */
    .ks-hero::before { content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: linear-gradient(rgba(11,58,126,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(11,58,126,.06) 1px, transparent 1px);
        background-size: 44px 44px; -webkit-mask-image: radial-gradient(ellipse at center, #000 35%, transparent 78%); mask-image: radial-gradient(ellipse at center, #000 35%, transparent 78%); }
    .ks-traverse { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
    .ks-eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: var(--ks-blue); background: #EEF3FA; border: 1px solid #D6E0EE; }
    .ks-stripe { height: 6px; background: linear-gradient(90deg, var(--ks-blue) 0 33.3%, var(--ks-green) 33.3% 66.6%, var(--ks-gold) 66.6% 100%); }
    .ks-step { position: relative; padding: 22px 20px 20px; }
    .ks-step-no { position: absolute; top: -14px; left: 20px; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 13px; color: #fff; background: var(--ks-blue); box-shadow: 0 0 0 4px var(--ks-page); }
    .ks-icon { width: 44px; height: 44px; border-radius: 10px; display: grid; place-items: center; }
    .ks-section-title { font-size: clamp(22px, 3vw, 28px); font-weight: 800; letter-spacing: -.01em; }
    .ks-check li { display: flex; gap: 10px; align-items: flex-start; padding: 6px 0; font-size: 15px; }
    .ks-check li i { color: var(--ks-green); flex-shrink: 0; margin-top: 2px; }

    /* Slides: stacked in one grid cell, so the hero is as tall as its tallest slide and never jumps. */
    .ks-slide-stack { display: grid; position: relative; }
    .ks-slide { grid-area: 1 / 1; position: relative; opacity: 0; visibility: hidden; transition: opacity .7s ease, visibility .7s ease; }
    .ks-slide.is-on { opacity: 1; visibility: visible; z-index: 1; }
    .ks-slide-photo, .ks-slide-seal { display: flex; align-items: center; overflow: hidden; color: #fff; }
    .ks-slide-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .ks-slide-shade { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(6,18,38,.9) 0%, rgba(6,18,38,.66) 42%, rgba(6,18,38,.1) 74%); }
    .ks-slide-copy { position: relative; max-width: 580px; padding: 48px 16px 76px; margin-left: max(16px, calc((100% - 1120px) / 2 + 16px)); }
    .ks-slide-title { font-size: clamp(26px, 4vw, 42px); font-weight: 800; line-height: 1.15; letter-spacing: -.01em; margin-top: 14px; }
    .ks-slide-text { font-size: clamp(15px, 1.8vw, 18px); line-height: 1.6; opacity: .9; margin-top: 12px; }
    .ks-slide-seal { background: radial-gradient(ellipse at 72% 50%, #1D1C18 0%, #0B0B0B 62%); padding: 40px 0 76px; }
    .ks-seal-img { width: 100%; max-width: 440px; justify-self: center; -webkit-mask-image: radial-gradient(circle at 50% 46%, #000 44%, transparent 58%); mask-image: radial-gradient(circle at 50% 46%, #000 44%, transparent 58%); }
    .ks-slide-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 5; width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center;
        background: rgba(255,255,255,.92); color: var(--ks-blue); border: 1px solid var(--ks-line); box-shadow: 0 4px 14px rgba(0,0,0,.15); cursor: pointer; }
    .ks-slide-nav:hover { background: #fff; }
    .ks-slide-dots { position: absolute; left: 50%; bottom: 20px; transform: translateX(-50%); z-index: 5; display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 999px; background: rgba(15,23,32,.4); backdrop-filter: blur(4px); }
    .ks-dot { width: 9px; height: 9px; border-radius: 999px; border: 0; padding: 0; background: rgba(255,255,255,.6); cursor: pointer; transition: width .3s, background .3s; }
    .ks-dot.on { width: 24px; background: #fff; }
    .ks-slide-pause { width: 22px; height: 22px; border: 0; border-radius: 50%; display: grid; place-items: center; background: transparent; color: #fff; cursor: pointer; margin-left: 2px; }
    .ks-slide-nav:focus-visible, .ks-dot:focus-visible, .ks-slide-pause:focus-visible { outline: 2px solid var(--ks-gold); outline-offset: 2px; }
    @media (max-width: 767px) {
        .ks-slide-photo { align-items: flex-end; }
        .ks-slide-shade { background: linear-gradient(0deg, rgba(6,18,38,.94) 0%, rgba(6,18,38,.72) 55%, rgba(6,18,38,.15) 100%); }
        .ks-slide-copy { padding: 140px 16px 76px; }
        .ks-seal-img { max-width: 240px; grid-row: 1; }
        .ks-slide-nav { display: none; }
    }
    @media (prefers-reduced-motion: reduce) { .ks-slide, .ks-dot { transition: none; } }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var root = document.getElementById('ksSlides');
        if (!root) return;
        var slides = Array.prototype.slice.call(root.querySelectorAll('.ks-slide'));
        var dots = Array.prototype.slice.call(root.querySelectorAll('.ks-dot'));
        var pauseBtn = root.querySelector('.ks-slide-pause');
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var at = 0, paused = reduce, holding = false;

        function go(n) {
            at = (n + slides.length) % slides.length;
            slides.forEach(function (s, k) {
                var on = k === at;
                s.classList.toggle('is-on', on);
                s.setAttribute('aria-hidden', on ? 'false' : 'true');
                s.inert = !on;                       // hidden slides' links stay out of the tab order
            });
            dots.forEach(function (d, k) { d.classList.toggle('on', k === at); if (k === at) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
        }
        function drawPause() {
            pauseBtn.setAttribute('aria-label', paused ? 'Play the slides' : 'Pause the slides');
            pauseBtn.innerHTML = '<i data-lucide="' + (paused ? 'play' : 'pause') + '" class="w-3.5 h-3.5"></i>';
            if (window.lucide) lucide.createIcons();
        }

        root.querySelectorAll('.ks-slide-nav').forEach(function (b) { b.addEventListener('click', function () { go(at + Number(b.dataset.step)); }); });
        dots.forEach(function (d) { d.addEventListener('click', function () { go(Number(d.dataset.go)); }); });
        pauseBtn.addEventListener('click', function () { paused = !paused; drawPause(); });
        root.addEventListener('mouseenter', function () { holding = true; });
        root.addEventListener('mouseleave', function () { holding = false; });
        root.addEventListener('focusin', function () { holding = true; });
        root.addEventListener('focusout', function (e) { if (!root.contains(e.relatedTarget)) holding = false; });
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { go(at - 1); e.preventDefault(); }
            if (e.key === 'ArrowRight') { go(at + 1); e.preventDefault(); }
        });
        setInterval(function () { if (!paused && !holding && !document.hidden) go(at + 1); }, 7000);
        go(0);
        if (paused) drawPause();
    })();
</script>
@endpush

@section('body')
@include('surveyor_portal.partials.public_nav')

{{-- The hero is a slideshow (user, 2026-11-05: "add images slides"): the wordmark banner first, then the field
     photo and the seal. Every 7 s; pauses on hover or focus, and never moves by itself under reduced motion. --}}
<section class="ks-hero" id="ksSlides" aria-roledescription="carousel" aria-label="About the KNSSGOF portal">
    <div class="ks-slide-stack">
        <div class="ks-slide is-on" role="group" aria-roledescription="slide" aria-label="1 of 3">
        {{-- A traverse between beacons, drawn behind the banner. Decoration only. --}}
        <svg class="ks-traverse" viewBox="0 0 1200 560" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <g fill="none" stroke-width="1.5" stroke-dasharray="6 6">
                <polyline points="70,470 230,380 190,230 330,120" stroke="rgba(21,128,61,.35)"/>
                <polyline points="1130,90 990,170 1050,320 900,430 980,520" stroke="rgba(11,58,126,.30)"/>
            </g>
            <g stroke-width="2">
                @foreach ([[70,470],[230,380],[190,230],[330,120]] as [$x, $y])
                    <rect x="{{ $x - 6 }}" y="{{ $y - 6 }}" width="12" height="12" fill="#fff" stroke="rgba(21,128,61,.6)"/>
                    <path d="M{{ $x - 3 }} {{ $y }}h6M{{ $x }} {{ $y - 3 }}v6" stroke="rgba(21,128,61,.6)"/>
                @endforeach
                @foreach ([[1130,90],[990,170],[1050,320],[900,430],[980,520]] as [$x, $y])
                    <rect x="{{ $x - 6 }}" y="{{ $y - 6 }}" width="12" height="12" fill="#fff" stroke="rgba(11,58,126,.55)"/>
                    <path d="M{{ $x - 3 }} {{ $y }}h6M{{ $x }} {{ $y - 3 }}v6" stroke="rgba(11,58,126,.55)"/>
                @endforeach
            </g>
            <g font-family="ui-monospace, Consolas, monospace" font-size="12" fill="rgba(11,58,126,.45)">
                <text x="246" y="374">KDJ 0001</text><text x="346" y="116">KDJ 0002</text>
                <text x="1005" y="164">A0001B</text><text x="1066" y="316">A0002B</text>
            </g>
        </svg>

        <div class="relative mx-auto px-4 text-center" style="max-width:920px;padding-top:56px;padding-bottom:64px;">
            <span class="ks-eyebrow"><i data-lucide="map-pin" class="w-4 h-4"></i> Kano State Surveyor General's Office</span>

            <h1 class="sr-only">KNSSGOF, the Kano State Surveyor General's Office Portal</h1>
            <img src="{{ asset('assets/logo/knssgof-wordmark.png') }}" alt="KNSSGOF, Kano State Surveyor General's Office Portal"
                 width="900" height="300" class="mx-auto" style="width:100%;max-width:720px;height:auto;margin-top:22px;mix-blend-mode:multiply;">

            <p class="mx-auto" style="max-width:620px;margin-top:8px;font-size:clamp(16px,2.2vw,19px);line-height:1.6;color:var(--ks-soft);">
                Beacon numbers for licensed surveyors, online. Register once, request beacon numbers from the Surveyor General,
                accept what is issued and print your Beacon Issue Slip, without a trip to the office.
            </p>

            <div class="flex flex-wrap justify-center gap-3" style="margin-top:28px;">
                @if ($signedIn)
                    <a class="ks-btn ks-btn-primary" style="padding:13px 24px;font-size:15px;" href="{{ route('surveyor-portal.dashboard') }}"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Go to my dashboard</a>
                    <a class="ks-btn ks-btn-light" style="padding:13px 24px;font-size:15px;" href="{{ route('surveyor-portal.requests.create') }}"><i data-lucide="plus-circle" class="w-4 h-4"></i> Request beacons</a>
                @else
                    <a class="ks-btn ks-btn-primary" style="padding:13px 24px;font-size:15px;" href="{{ route('surveyor-portal.register') }}" data-register-dialog-trigger><i data-lucide="user-plus" class="w-4 h-4"></i> Register as a surveyor</a>
                    <a class="ks-btn ks-btn-light" style="padding:13px 24px;font-size:15px;" href="{{ route('surveyor-portal.login') }}"><i data-lucide="log-in" class="w-4 h-4"></i> Sign in</a>
                @endif
            </div>

            <ul class="flex flex-wrap justify-center gap-x-6 gap-y-2 ks-help" style="margin-top:26px;font-size:14px;">
                <li class="flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4" style="color:var(--ks-green);"></i> Accounts approved by the SG's office</li>
                <li class="flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4" style="color:var(--ks-green);"></i> A notice at every step</li>
                <li class="flex items-center gap-2"><i data-lucide="printer" class="w-4 h-4" style="color:var(--ks-green);"></i> Printable Beacon Issue Slip</li>
            </ul>
        </div>
        </div>

        <div class="ks-slide ks-slide-photo" role="group" aria-roledescription="slide" aria-label="2 of 3" aria-hidden="true">
            <img src="{{ asset('assets/portal/kssgof-field-survey.jpg') }}" alt="" width="1024" height="572" loading="lazy" class="ks-slide-bg" style="object-position:72% center;">
            <div class="ks-slide-shade"></div>
            <div class="ks-slide-copy">
                <span class="ks-eyebrow" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.3);color:#fff;"><i data-lucide="compass" class="w-4 h-4"></i> In the field</span>
                <h2 class="ks-slide-title">Request beacon numbers from the field.</h2>
                <p class="ks-slide-text">From your phone or computer: say how many beacons, the type of survey and where it is. The SG issues the next available numbers.</p>
                <div class="flex flex-wrap gap-3" style="margin-top:24px;">
                    @if ($signedIn)
                        <a class="ks-btn" style="background:var(--ks-gold);color:#1B1405;padding:12px 22px;" href="{{ route('surveyor-portal.requests.create') }}"><i data-lucide="plus-circle" class="w-4 h-4"></i> Request beacons</a>
                    @else
                        <a class="ks-btn" style="background:var(--ks-gold);color:#1B1405;padding:12px 22px;" href="{{ route('surveyor-portal.register') }}" data-register-dialog-trigger><i data-lucide="user-plus" class="w-4 h-4"></i> Register as a surveyor</a>
                        <a class="ks-btn" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.6);padding:12px 22px;" href="{{ route('surveyor-portal.login') }}">Sign in</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="ks-slide ks-slide-seal" role="group" aria-roledescription="slide" aria-label="3 of 3" aria-hidden="true">
            <div class="mx-auto px-4 grid md:grid-cols-2 items-center gap-6 w-full" style="max-width:1120px;">
                <div class="ks-slide-copy" style="padding:0;max-width:none;">
                    <span class="ks-eyebrow" style="background:rgba(201,154,30,.14);border-color:rgba(201,154,30,.45);color:#E7C66A;"><i data-lucide="shield-check" class="w-4 h-4"></i> Official portal</span>
                    <h2 class="ks-slide-title">The Surveyor General's Office, online.</h2>
                    <p class="ks-slide-text">Registrations are approved by the SG's office, and every beacon number is issued from the state's own register.</p>
                    <div class="flex flex-wrap gap-3" style="margin-top:24px;">
                        <a class="ks-btn" style="background:var(--ks-gold);color:#1B1405;padding:12px 22px;" href="{{ $signedIn ? route('surveyor-portal.dashboard') : route('surveyor-portal.register') }}" @unless ($signedIn) data-register-dialog-trigger @endunless>{{ $signedIn ? 'Go to my dashboard' : 'Get started' }}</a>
                        <a class="ks-btn" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.45);padding:12px 22px;" href="#how">How it works</a>
                    </div>
                </div>
                <img src="{{ asset('assets/logo/knssgof-logo.png') }}" alt="KNSSGOF seal" width="1200" height="900" loading="lazy" class="ks-seal-img">
            </div>
        </div>
    </div>

    <button type="button" class="ks-slide-nav" data-step="-1" aria-label="Previous slide" style="left:12px;"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
    <button type="button" class="ks-slide-nav" data-step="1" aria-label="Next slide" style="right:12px;"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
    <div class="ks-slide-dots">
        @foreach ([1, 2, 3] as $n)
            <button type="button" class="ks-dot {{ $n === 1 ? 'on' : '' }}" data-go="{{ $n - 1 }}" aria-label="Show slide {{ $n }}" @if ($n === 1) aria-current="true" @endif></button>
        @endforeach
        <button type="button" class="ks-slide-pause" aria-label="Pause the slides"><i data-lucide="pause" class="w-3.5 h-3.5"></i></button>
    </div>
    <div class="ks-stripe"></div>
</section>

<main>
    {{-- In the field (user, 2026-11-05: "add this image [to] the portal"). --}}
    <section class="mx-auto px-4" style="max-width:1120px;padding-top:56px;">
        <div class="ks-card overflow-hidden grid lg:grid-cols-5" style="box-shadow:0 18px 40px -28px rgba(11,58,126,.45);">
            <div class="lg:col-span-3 relative" style="min-height:240px;">
                <img src="{{ asset('assets/portal/kssgof-field-survey.jpg') }}" width="1024" height="572" loading="lazy"
                     alt="A surveyor in a hard hat and safety vest reads a GNSS controller beside a concrete beacon, with a tripod behind him"
                     class="w-full h-full" style="object-fit:cover;object-position:60% center;display:block;aspect-ratio:1024/572;">
            </div>
            <div class="lg:col-span-2 p-6 sm:p-8 flex flex-col justify-center">
                <span class="ks-eyebrow self-start"><i data-lucide="compass" class="w-4 h-4"></i> In the field</span>
                <h2 class="ks-section-title" style="margin-top:14px;">Every beacon you plant, on record.</h2>
                <p class="ks-help" style="font-size:15px;line-height:1.65;margin-top:10px;">
                    Each number the SG issues is tied to your name, your company and the survey it was asked for,
                    so the beacons you set out are on the state's register from the day they are issued.
                </p>
                <ul class="ks-check" style="margin-top:12px;">
                    <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Numbers issued in order, never twice</li>
                    <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Your requests and slips in one place</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="mx-auto px-4" style="max-width:1120px;padding-top:56px;padding-bottom:16px;">
        <div class="text-center" style="margin-bottom:36px;">
            <h2 class="ks-section-title">How it works</h2>
            <p class="ks-help" style="font-size:15px;margin-top:6px;">Four steps from registration to a printed slip.</p>
        </div>
        <ol class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4" style="list-style:none;padding:0;">
            @foreach ([
                ['user-plus', '#EEF3FA', 'var(--ks-blue)', 'Register', 'Confirm your company is registered, then give your name, phone, email and SURCON number. You get a Registration ID.'],
                ['shield-check', '#ECF7EF', 'var(--ks-green)', 'Get approved', 'The Surveyor General\'s office checks your details and activates your account with a Surveyor ID. You can sign in from then on.'],
                ['map-pin', '#FBF5E6', '#9A7414', 'Request beacons', 'Say how many beacons you need and where the survey is: plot, district and LGA.'],
                ['printer', '#EEF3FA', 'var(--ks-blue)', 'Accept and print', 'The SG issues the next available numbers. Accept them in the portal and print your Beacon Issue Slip.'],
            ] as $i => [$icon, $bg, $fg, $title, $text])
                <li class="ks-card ks-step">
                    <span class="ks-step-no">{{ $i + 1 }}</span>
                    <div class="ks-icon" style="background:{{ $bg }};color:{{ $fg }};"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></div>
                    <h3 class="font-bold" style="font-size:17px;margin-top:14px;">{{ $title }}</h3>
                    <p class="ks-help" style="font-size:14px;line-height:1.6;margin-top:6px;">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Who can register, and what to have ready --}}
    <section class="mx-auto px-4 grid gap-6 lg:grid-cols-2" style="max-width:1120px;padding-top:40px;padding-bottom:56px;">
        <div class="ks-card p-6">
            <div class="ks-icon" style="background:#ECF7EF;color:var(--ks-green);"><i data-lucide="briefcase" class="w-5 h-5"></i></div>
            <h3 class="font-bold" style="font-size:18px;margin-top:14px;">Private surveyors</h3>
            <p class="ks-help" style="font-size:14px;line-height:1.6;margin-top:6px;">For surveys by a registered company. Numbers carry your company's letters and continue its own series.</p>
            <p style="margin-top:12px;font-family:ui-monospace,Consolas,monospace;font-size:13px;color:var(--ks-green);">e.g. A0001B</p>
        </div>
        <div class="ks-card p-6" style="background:linear-gradient(180deg,#fff, #FBFCFE);">
            <div class="ks-icon" style="background:#FBF5E6;color:#9A7414;"><i data-lucide="clipboard-list" class="w-5 h-5"></i></div>
            <h3 class="font-bold" style="font-size:18px;margin-top:14px;">Have these ready</h3>
            <ul class="ks-check" style="margin-top:8px;">
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Your SURCON registration number</li>
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> A mobile number you can be reached on</li>
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> An email address</li>
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Your company's name, if private</li>
            </ul>
        </div>
    </section>

    {{-- Closing call to action --}}
    @unless ($signedIn)
        <section class="px-4" style="padding-bottom:64px;">
            <div class="mx-auto flex flex-col md:flex-row items-center justify-between gap-5 p-8"
                 style="max-width:1120px;border-radius:16px;background:linear-gradient(120deg,var(--ks-blue-dark),var(--ks-blue) 60%,#0E4A9E);color:#fff;">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('assets/logo/knssgof-logo.png') }}" alt="KNSSGOF seal" width="64" height="64" style="width:64px;height:64px;border-radius:50%;flex-shrink:0;box-shadow:0 0 0 3px rgba(201,154,30,.55);">
                    <div>
                        <h2 class="font-extrabold" style="font-size:22px;">Ready to request beacons?</h2>
                        <p style="opacity:.85;font-size:15px;margin-top:4px;">Registration takes a few minutes. Approval comes from the SG's office.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 shrink-0">
                    <a class="ks-btn" style="background:var(--ks-gold);color:#1B1405;padding:12px 22px;" href="{{ route('surveyor-portal.register') }}" data-register-dialog-trigger>Register now</a>
                    <a class="ks-btn" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.55);padding:12px 22px;" href="{{ route('surveyor-portal.login') }}">Sign in</a>
                </div>
            </div>
        </section>
    @endunless
</main>

<footer style="background:#0F1720;color:#C9D1DA;">
    <div class="mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-4" style="max-width:1120px;">
        <div class="flex items-center gap-3">
            <img src="{{ asset('assets/logo/knssgof-logo.png') }}" alt="KNSSGOF seal" width="40" height="40" style="width:40px;height:40px;border-radius:50%;">
            <div style="font-size:14px;">
                <div class="font-semibold" style="color:#fff;">Kano State Surveyor General's Office</div>
                <div style="font-size:12px;opacity:.75;">KNSSGOF Portal · part of KLAES</div>
            </div>
        </div>
        <div class="flex items-center gap-5" style="font-size:14px;">
            <a href="{{ route('surveyor-portal.login') }}" style="color:#C9D1DA;">Sign in</a>
            <a href="{{ route('surveyor-portal.register') }}" style="color:#C9D1DA;" data-register-dialog-trigger>Register</a>
            <span style="opacity:.6;">© {{ date('Y') }}</span>
        </div>
    </div>
</footer>
@endsection
