{{--
    Official header for the public LAAS pages.

    Two bars, following the pattern civic services use to signal authenticity
    before anything else on the page: a thin government identification strip
    carrying the national arms, then the ministry's own seal and the service
    name. The seal sits on a white chip in both themes — it is a photograph of a
    physical seal with a pale background, so it cannot be dropped straight onto
    a dark surface.
--}}

<header class="relative z-40">

    {{-- Government identification strip --}}
    <div style="background: var(--brand-deep);">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 sm:px-6">
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}"
                     alt="" aria-hidden="true" class="h-5 w-auto">
                <p class="text-[11px] font-semibold tracking-wide" style="color: var(--on-deep-soft);">
                    An official portal of the <span class="text-white">Kano State Government</span>, Nigeria
                </p>
            </div>
            <p class="hidden items-center gap-1.5 text-[11px] font-medium sm:flex" style="color: var(--on-deep-soft);">
                <i data-lucide="lock" class="h-3 w-3" aria-hidden="true"></i>
                Secure connection
            </p>
        </div>
    </div>

    {{-- Masthead --}}
    <div class="border-b" style="background: var(--surface-card); border-color: var(--border);">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">

            <a href="{{ route('laas.landing') }}" class="flex items-center gap-3 sm:gap-4">
                <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-white ring-1 sm:h-14 sm:w-14"
                      style="--tw-ring-color: var(--border);">
                    <img src="{{ asset('assets/logo/ministry2.png') }}"
                         alt="Seal of the Kano State Ministry of Land and Physical Planning"
                         class="h-11 w-11 rounded-full object-contain sm:h-[3.1rem] sm:w-[3.1rem]">
                </span>

                <span class="hidden h-10 w-px sm:block" style="background: var(--border);"></span>

                <span>
                    <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal"
                         class="h-9 w-auto object-contain dark:hidden sm:h-11">
                    <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal"
                         class="hidden h-9 w-auto object-contain dark:block sm:h-11">
                </span>
            </a>

            {{-- Desktop navigation --}}
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary">
                @foreach([
                    ['#how-it-works',    'How it works'],
                    ['#before-you-begin','Before you begin'],
                    [route('laas.login'),'Track Status'],
                ] as [$href, $label])
                    <a href="{{ $href }}"
                       class="rounded-lg px-3 py-2 text-sm font-semibold transition hover:bg-[var(--brand-tint)]"
                       style="color: var(--ink-soft);">{{ $label }}</a>
                @endforeach

                <span class="mx-2 h-6 w-px" style="background: var(--border);"></span>

                <button type="button" onclick="laasToggleTheme()"
                        class="rounded-lg p-2 transition hover:bg-[var(--brand-tint)]"
                        style="color: var(--ink-soft);">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden" aria-hidden="true"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block" aria-hidden="true"></i>
                    <span class="sr-only" data-theme-label>Dark mode</span>
                </button>

                <a href="{{ route('laas.login') }}"
                   class="rounded-lg border-2 px-4 py-2 text-sm font-bold transition hover:bg-[var(--brand-tint)]"
                   style="color: var(--brand); border-color: var(--brand-line);">Sign In</a>

                <a href="{{ route('laas.register') }}" class="laas-btn px-4 py-2 text-sm">
                    Apply Now <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
            </nav>

            {{-- Mobile --}}
            <div class="flex items-center gap-1 lg:hidden">
                <button type="button" onclick="laasToggleTheme()" class="rounded-lg p-2" style="color: var(--ink-soft);">
                    <i data-lucide="sun" class="h-5 w-5 dark:hidden" aria-hidden="true"></i>
                    <i data-lucide="moon" class="hidden h-5 w-5 dark:block" aria-hidden="true"></i>
                    <span class="sr-only" data-theme-label>Dark mode</span>
                </button>
                <button type="button" id="laas-menu-btn" aria-expanded="false" aria-controls="laas-mobile-nav"
                        class="rounded-lg p-2" style="color: var(--ink);">
                    <i data-lucide="menu" class="h-6 w-6" aria-hidden="true"></i>
                    <span class="sr-only">Open menu</span>
                </button>
            </div>
        </div>

        {{-- Mobile navigation --}}
        <nav id="laas-mobile-nav" hidden aria-label="Primary"
             class="border-t px-4 py-4 lg:hidden" style="border-color: var(--border);">
            <ul class="space-y-1">
                @foreach([
                    ['#how-it-works',     'How it works',     'route'],
                    ['#before-you-begin', 'Before you begin', 'list-checks'],
                    [route('laas.login'), 'Track Status',     'search'],
                ] as [$href, $label, $icon])
                    <li>
                        <a href="{{ $href }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold"
                           style="color: var(--ink-soft);">
                            <i data-lucide="{{ $icon }}" class="h-4 w-4" aria-hidden="true"></i>{{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 grid gap-2">
                <a href="{{ route('laas.login') }}"
                   class="rounded-lg border-2 px-4 py-2.5 text-center text-sm font-bold"
                   style="color: var(--brand); border-color: var(--brand-line);">Sign In</a>
                <a href="{{ route('laas.register') }}" class="laas-btn px-4 py-2.5 text-sm">
                    Apply Now <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
            </div>
        </nav>
    </div>
</header>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('laas-menu-btn');
    const nav = document.getElementById('laas-mobile-nav');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        nav.hidden = open;
        btn.querySelector('.sr-only').textContent = open ? 'Open menu' : 'Close menu';
    });
})();
</script>
@endpush
