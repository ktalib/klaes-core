@extends('alaes_portal.layouts.app')

@section('title', 'ALAES Portal â€” Abia State Land Allocation Application System')

@section('body')

@include('alaes_portal.partials.gov-header')

<main id="ALAES-main">

    {{-- ================= HERO ================= --}}
    <section class="relative overflow-hidden" style="background: var(--brand-deep);">

        {{-- Survey-grid texture + a soft emerald bloom behind the artwork --}}
        <div class="ALAES-grid-texture absolute inset-0" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-40 top-1/2 h-[560px] w-[560px] -translate-y-1/2 rounded-full opacity-40 blur-3xl"
             style="background: radial-gradient(circle, rgba(255,255,255,.07) 0%, transparent 70%);" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0"
             style="background: linear-gradient(100deg, var(--brand-deep) 32%, rgba(6,48,30,.72) 58%, rgba(6,48,30,.35) 100%);"
             aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:gap-8 lg:py-24">

            <div>
                {{-- State badge --}}
                <p class="mb-6 inline-flex items-center gap-2.5 rounded-full border py-2 pl-2 pr-4"
                   style="border-color: rgba(255,255,255,.22); background: rgba(255,255,255,.06);">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white">
                        <img src="{{ asset('alaes-assets/logos/abia.jpeg') }}" alt="" aria-hidden="true"
                             class="h-5 w-5 rounded-full object-contain">
                    </span>
                    <span class="ALAES-eyebrow" style="color: var(--on-deep-soft);">
                        Official Portal Â· MINISTRY OF LANDS, SURVEY AND URBAN PLANNING
                    </span>
                </p>

                <h1 class="text-[2.6rem] font-black leading-[1.06] tracking-tight text-white sm:text-6xl">
                    Apply for Land<br class="hidden sm:block"> Allocation Online
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-8" style="color: var(--on-deep-soft);">
                    Submit your application from anywhere in Abia State and follow it all the way to your
                    Right of Occupancy. You are notified by SMS the moment it is received, when the Director
                    approves it, and at every step after that.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('alaes_portal.register') }}" class="ALAES-btn-invert px-7 py-4 text-base">
                        <i data-lucide="file-plus-2" class="h-5 w-5" aria-hidden="true"></i>
                        Start an Application
                    </a>
                    <a href="{{ route('alaes_portal.login') }}" class="ALAES-btn-ghost px-7 py-4 text-base">
                        <i data-lucide="search" class="h-5 w-5" aria-hidden="true"></i>
                        Track Application
                    </a>
                </div>

                {{-- Trust row --}}
                <ul class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 border-t pt-7"
                    style="border-color: rgba(255,255,255,.16);">
                    @foreach([
                        ['shield-check', 'Government-issued file number'],
                        ['message-square-check', 'SMS updates at every stage'],
                        ['clock', 'Track progress in real time'],
                    ] as [$icon, $label])
                        <li class="flex items-center gap-2.5 text-sm font-semibold" style="color: var(--on-deep-soft);">
                            <i data-lucide="{{ $icon }}" class="h-4 w-4 flex-shrink-0" style="color: var(--on-deep-soft);" aria-hidden="true"></i>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="hidden lg:block">
                @include('alaes_portal.partials.hero-visual')
            </div>
        </div>
    </section>

    {{-- ================= HOW IT MOVES ================= --}}
    <section id="how-it-works" class="scroll-mt-8 py-20" style="background: var(--surface);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">

            <div class="mx-auto mb-14 max-w-2xl text-center">
                <p class="ALAES-eyebrow mb-3" style="color: var(--brand);">The process</p>
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl" style="color: var(--ink);">
                    How your application moves
                </h2>
                <p class="mt-4 text-base leading-7" style="color: var(--ink-soft);">
                    Six steps from submission to a signed Right of Occupancy. You are told when each one completes â€”
                    you never have to call the office to ask.
                </p>
            </div>

            @php
                $steps = [
                    ['send',            'You submit',           'Fill the form and submit it online. You are told at once that processing has started.'],
                    ['user-check',      'Director approves',    'The Director or an assigned officer reviews your application and approves it.'],
                    ['hash',            'File Number assigned', 'The Ministry generates your official file number and sends it to your phone.'],
                    ['ruler',           'Survey &amp; Cadastral',   'A survey request is raised, goes to Cadastral, and returns completed.'],
                    ['clipboard-check', 'Recommendation',       'The Land Office prepares and approves the recommendation on your file.'],
                    ['stamp',           'RoFO signed',          'The Director of Lands signs your Right of Occupancy, ready for collection.'],
                ];
            @endphp

            {{-- Progress rail: the connector sits behind the numbered badges, and
                 stops short of the last card so it does not run off the row. --}}
            <ol class="relative grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="pointer-events-none absolute left-0 right-0 top-[2.35rem] hidden h-0.5 lg:block"
                     style="background: var(--brand-line);" aria-hidden="true"></div>

                @foreach($steps as $i => [$icon, $title, $desc])
                    <li class="ALAES-card relative flex flex-col p-6 transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="relative flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-base font-black text-white ring-4"
                                  style="background: var(--brand); --tw-ring-color: var(--surface-card);">
                                {{ $i + 1 }}
                            </span>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl"
                                  style="background: var(--brand-tint); color: var(--brand);">
                                <i data-lucide="{{ $icon }}" class="h-5 w-5" aria-hidden="true"></i>
                            </span>
                        </div>

                        <h3 class="text-base font-extrabold" style="color: var(--ink);">{!! $title !!}</h3>
                        <p class="mt-2 text-sm leading-6" style="color: var(--ink-soft);">{!! $desc !!}</p>

                        @if($i === count($steps) - 1)
                            <p class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold"
                               style="color: var(--brand);">
                                <i data-lucide="party-popper" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                Ready for collection
                            </p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ================= BEFORE YOU BEGIN ================= --}}
    <section id="before-you-begin" class="scroll-mt-8 border-y py-20"
             style="background: var(--brand-tint); border-color: var(--border);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">

            <div class="mx-auto mb-14 max-w-2xl text-center">
                <p class="ALAES-eyebrow mb-3" style="color: var(--brand);">Prepare</p>
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl" style="color: var(--ink);">
                    Before you begin
                </h2>
                <p class="mt-4 text-base leading-7" style="color: var(--ink-soft);">
                    Have these three things to hand. The whole form takes about ten minutes.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                @foreach([
                    ['smartphone', 'A working phone number',
                     'Every update is sent there by SMS, so use a number you can receive messages on.',
                     'Nigerian mobile number'],
                    ['id-card', 'Means of identification',
                     'A National ID (NIN), driver&rsquo;s licence, voter&rsquo;s card or international passport.',
                     'PDF or photo, up to 5&nbsp;MB'],
                    ['map-pin', 'Details of the land',
                     'The Local Government Area, district and location of the plot you are applying for.',
                     'Plot number if you have one'],
                ] as [$icon, $title, $desc, $note])
                    <div class="ALAES-card flex flex-col p-7">
                        <span class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl"
                              style="background: var(--brand); color: #fff;">
                            <i data-lucide="{{ $icon }}" class="h-6 w-6" aria-hidden="true"></i>
                        </span>
                        <h3 class="text-lg font-extrabold" style="color: var(--ink);">{{ $title }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-6" style="color: var(--ink-soft);">{!! $desc !!}</p>
                        <p class="mt-5 inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold"
                           style="background: var(--brand-tint); color: var(--brand);">
                            <i data-lucide="check" class="h-3.5 w-3.5" aria-hidden="true"></i>{!! $note !!}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= CLOSING CTA ================= --}}
    <section class="py-20" style="background: var(--surface);">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <div class="relative overflow-hidden rounded-3xl px-8 py-14 text-center sm:px-14"
                 style="background: var(--brand-deep);">
                <div class="ALAES-grid-texture absolute inset-0" aria-hidden="true"></div>

                <div class="relative">
                    <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                        Ready to apply?
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-base leading-7" style="color: var(--on-deep-soft);">
                        Create your account and start your land allocation application. It takes about ten minutes,
                        and you can save and come back at any time.
                    </p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('alaes_portal.register') }}" class="ALAES-btn-invert px-7 py-4 text-base">
                            <i data-lucide="file-plus-2" class="h-5 w-5" aria-hidden="true"></i>
                            Start an Application
                        </a>
                        <a href="{{ route('alaes_portal.login') }}" class="ALAES-btn-ghost px-7 py-4 text-base">
                            I already have an account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

{{-- ================= FOOTER ================= --}}
<footer style="background: var(--brand-deep);">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">

            <div>
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white">
                        <img src="{{ asset('alaes-assets/logos/abia.jpeg') }}"
                             alt="Seal of the MINISTRY OF LANDS, SURVEY AND URBAN PLANNING"
                             class="h-11 w-11 rounded-full object-contain">
                    </span>
                    <div>
                        <p class="text-sm font-extrabold text-white">MINISTRY OF LANDS, SURVEY AND URBAN PLANNING</p>
                        <p class="text-xs" style="color: var(--on-deep-soft);">Abia State, Nigeria</p>
                    </div>
                </div>
                <p class="max-w-sm text-sm leading-6" style="color: var(--on-deep-soft);">
                    The Land Allocation Application System (ALAES) is the Ministry&rsquo;s official channel for land
                    allocation applications, operated as part of ALAES.
                </p>
            </div>

            <div>
                <p class="ALAES-eyebrow mb-4 text-white">Portal</p>
                <ul class="space-y-2.5">
                    @foreach([
                        [route('laas.register'), 'Start an application'],
                        [route('laas.login'),    'Sign in'],
                        ['#how-it-works',        'How it works'],
                        ['#before-you-begin',    'Before you begin'],
                    ] as [$href, $label])
                        <li>
                            <a href="{{ $href }}" class="text-sm font-medium hover:text-white hover:underline"
                               style="color: var(--on-deep-soft);">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="ALAES-eyebrow mb-4 text-white">Beware of fraud</p>
                <p class="text-sm leading-6" style="color: var(--on-deep-soft);">
                    The Ministry never asks for payment through private accounts or agents. Every genuine update
                    reaches you on this portal and by SMS from the Ministry.
                </p>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t pt-7 sm:flex-row"
             style="border-color: rgba(255,255,255,.16);">
            <p class="text-xs" style="color: var(--on-deep-soft);">
                &copy; {{ date('Y') }} MINISTRY OF LANDS, SURVEY AND URBAN PLANNING. All rights reserved.
            </p>
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('alaes-assets/logos/abia_gis.jpeg') }}" alt="" aria-hidden="true" class="h-6 w-auto">
                <p class="text-xs font-semibold" style="color: var(--on-deep-soft);">Federal Republic of Nigeria</p>
            </div>
        </div>
    </div>
</footer>
@endsection







