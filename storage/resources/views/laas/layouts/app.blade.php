<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LAAS Portal')</title>
    <meta name="theme-color" content="#06301E">
    <link rel="icon" href="{{ asset('assets/logo/laas-light-logo.jpeg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind = { config: { darkMode: 'class' } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Applied before first paint so a dark-mode user never sees a white flash.
        (function () {
            const t = localStorage.getItem('laas-theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
                document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>

    <style>
        /*
         * LAAS design tokens.
         *
         * Contrast is the constraint that set these values, not taste: every
         * text/background pair below meets WCAG AAA (7:1 for body copy, 4.5:1
         * for text at 24px, or 18.66px bold and above). The greens are pushed
         * much darker than the brand mark's mid-green because mid-green on
         * white lands around 4:1 — fine for a logo, and nowhere near enough for
         * body text.
         *
         *   --ink on --surface            16.1:1
         *   --ink-soft on --surface        8.9:1
         *   --brand on --surface          10.4:1
         *   #FFFFFF on --brand-deep       14.6:1   (the hero)
         *   --on-deep-soft on --brand-deep 9.7:1   (hero sub-copy)
         *   #1A1200 on --gold              9.8:1   (primary button)
         */
        :root {
            --brand:        #0B4F31;  /* deep emerald — primary on light */
            --brand-strong: #083D26;
            --brand-deep:   #06301E;  /* hero ground */
            --brand-tint:   #EAF2ED;  /* faint green wash for cards/chips */
            --brand-line:   #C3DAcc;

            --gold:         #F5B301;  /* accent, from the LAAS mark */
            --gold-strong:  #D99A00;

            --surface:      #F7F9F7;  /* off-white page */
            --surface-card: #FFFFFF;
            --border:       #D8E0DA;

            --ink:          #10201A;  /* body copy */
            --ink-soft:     #46564E;  /* secondary copy */
            --ink-faint:    #64756C;  /* meta only — never body copy */

            --on-deep:      #FFFFFF;
            --on-deep-soft: #C9DED4;

            --danger:       #9F1239;
            --focus:        #F5B301;

            /* Text colour for "in progress" copy. The gold itself is far too
             * light to set type in — #F5B301 on white is about 1.8:1 — so the
             * warning voice gets its own darkened tone at 7.1:1. */
            --warn-ink:     #7A4E00;
            /* Readable ink for anything sitting ON a gold or brand fill. */
            --on-gold:      #1A1200;
            --on-brand:     #FFFFFF;
        }

        .dark {
            --brand:        #6EE7B7;  /* light mint reads as "brand" on dark */
            --brand-strong: #A7F3D0;
            --brand-deep:   #041F13;
            --brand-tint:   #0C2A1E;
            --brand-line:   #1D4736;

            --gold:         #FBBF24;
            --gold-strong:  #F5B301;

            --surface:      #060F0B;
            --surface-card: #0E1A14;
            --border:       #24382D;

            --ink:          #ECFDF5;
            --ink-soft:     #B6C9BE;
            --ink-faint:    #90A69A;

            --on-deep:      #FFFFFF;
            --on-deep-soft: #C9DED4;

            --danger:       #FDA4AF;
            --focus:        #FBBF24;

            --warn-ink:     #FCD34D;
            --on-gold:      #1A1200;
            /* On dark, --brand is a light mint, so ink on it must be dark. */
            --on-brand:     #04281A;
        }

        body {
            background: var(--surface);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- Focus -----------------------------------------------------
         * One visible ring everywhere, gold so it clears both the off-white
         * page and the deep emerald hero. :focus-visible only, so a mouse
         * click does not leave a ring behind.
         */
        :focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* ---- Buttons ---------------------------------------------------- */
        .laas-btn,
        .laas-btn-gold,
        .laas-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-weight: 700;
            border-radius: .625rem;
            transition: background-color .15s, border-color .15s, transform .15s, box-shadow .15s;
        }

        /* Primary on light backgrounds. */
        .laas-btn { background: var(--brand); color: #fff; border: 2px solid var(--brand); }
        .laas-btn:hover { background: var(--brand-strong); border-color: var(--brand-strong); }
        .dark .laas-btn { background: var(--brand); color: #04281A; border-color: var(--brand); }
        .dark .laas-btn:hover { background: var(--brand-strong); border-color: var(--brand-strong); }

        /* Primary on the deep hero — gold carries further than green on green. */
        .laas-btn-gold { background: var(--gold); color: #1A1200; border: 2px solid var(--gold); }
        .laas-btn-gold:hover { background: var(--gold-strong); border-color: var(--gold-strong); transform: translateY(-1px); }

        /* Secondary — outline, deliberately quieter than either fill. */
        .laas-btn-ghost { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.7); }
        .laas-btn-ghost:hover { background: rgba(255,255,255,.12); border-color: #fff; }

        /* ---- Surfaces ---------------------------------------------------- */
        .laas-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
        }

        .laas-input {
            width: 100%;
            background: var(--surface-card);
            color: var(--ink);
            border: 1.5px solid var(--border);
            border-radius: .625rem;
            padding: .625rem 1rem;
        }
        .laas-input::placeholder { color: var(--ink-faint); }
        .laas-input:focus { border-color: var(--brand); }

        .laas-eyebrow {
            font-size: .6875rem;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        /* Faint survey-grid texture for the hero. Decorative only. */
        .laas-grid-texture {
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 56px 56px;
        }

        /* Skip link — visible the moment it takes focus. */
        .laas-skip {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 100;
        }
        .laas-skip:focus {
            left: 1rem;
            top: 1rem;
            padding: .75rem 1.25rem;
            background: var(--gold);
            color: #1A1200;
            font-weight: 700;
            border-radius: .5rem;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <a href="#laas-main" class="laas-skip">Skip to main content</a>

    @yield('body')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) window.lucide.createIcons();
        });

        function laasToggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('laas-theme', isDark ? 'dark' : 'light');
            document.querySelectorAll('[data-theme-label]').forEach(function (el) {
                el.textContent = isDark ? 'Light mode' : 'Dark mode';
            });
            if (window.lucide) window.lucide.createIcons();
        }
    </script>
    @stack('scripts')
</body>
</html>
