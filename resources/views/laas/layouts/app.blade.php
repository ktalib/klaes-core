<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LAAS Portal')</title>
    <meta name="theme-color" content="#14201A">
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
         * Neutral-first. Surfaces, borders and body text are true greys with no
         * colour cast; green appears only where it carries meaning — a primary
         * action, a completed step, the brand mark. An earlier pass tinted every
         * neutral green and used gold decoratively, which made an ordinary form
         * look like a themed product rather than a government service.
         *
         * Colour is therefore rationed to: one green, one red for errors, and a
         * yellow that appears ONLY as a keyboard focus ring (transient, and the
         * accessibility convention).
         *
         * Every text/background pair meets WCAG AAA (7:1 body, 4.5:1 for text at
         * 24px or 18.66px bold):
         *
         *   --ink on --surface-card            18.3:1
         *   --ink-soft on --surface-card        7.8:1
         *   --brand on --surface-card           9.7:1
         *   #FFFFFF on --brand                  9.7:1
         *   --on-deep-soft on --brand-deep      9.3:1
         */
        :root {
            --brand:        #0B4F31;  /* deep forest — primary action only */
            --brand-strong: #083D26;
            --brand-deep:   #14201A;  /* dark ground for the hero and footer */
            --brand-tint:   #EDF1EE;  /* barely-there wash for chips and cards */
            --brand-line:   #C9D8CF;

            --surface:      #F5F6F7;  /* neutral grey page */
            --surface-card: #FFFFFF;
            --border:       #DBDEE2;

            --ink:          #14171A;  /* body copy */
            --ink-soft:     #4B5259;  /* secondary copy */
            --ink-faint:    #6C747C;  /* meta only — never body copy */

            --on-brand:     #FFFFFF;  /* ink on a brand fill */
            --on-deep:      #FFFFFF;
            --on-deep-soft: #B9C4BD;

            --danger:       #9F1239;
            --focus:        #E8B93E;  /* keyboard focus only — see :focus-visible */
        }

        .dark {
            /* Neutral charcoals, not green-black. */
            --brand:        #8FBFA5;  /* muted sage — legible without glowing */
            --brand-strong: #A9CFB9;
            --brand-deep:   #0D0F11;
            --brand-tint:   #1C2321;
            --brand-line:   #2F3A34;

            --surface:      #101214;
            --surface-card: #17191C;
            --border:       #2B2F34;

            --ink:          #E9EBED;
            --ink-soft:     #AAB1B8;
            --ink-faint:    #868E96;

            --on-brand:     #0E1611;
            --on-deep:      #FFFFFF;
            --on-deep-soft: #B9C4BD;

            --danger:       #F7A8B8;
            --focus:        #E8B93E;
        }

        body {
            background: var(--surface);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- Focus -----------------------------------------------------
         * A DUAL ring, because no single colour clears every surface a focus
         * indicator can land on: a bright amber that fails against the light
         * page (2.1:1) but sings against the dark hero, wrapped in a dark outer
         * ring that does the opposite. Between them the indicator always meets
         * the 3:1 required of non-text UI (WCAG 2.1 SC 1.4.11), whether focus
         * lands on a white card, a green button, or the hero.
         *
         * Amber is the only colour in the interface outside brand green and the
         * error red — and it is transient, so it never adds to the page palette.
         * :focus-visible only, so a mouse click leaves no ring behind.
         */
        :focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
            box-shadow: 0 0 0 6px rgba(17, 20, 23, .55);
            border-radius: 4px;
        }

        /* ---- Buttons ---------------------------------------------------- */
        .laas-btn,
        .laas-btn-invert,
        .laas-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-weight: 700;
            border-radius: .625rem;
            transition: background-color .15s, border-color .15s;
        }

        /* Primary on light backgrounds. */
        .laas-btn { background: var(--brand); color: #fff; border: 2px solid var(--brand); }
        .laas-btn:hover { background: var(--brand-strong); border-color: var(--brand-strong); }
        .dark .laas-btn { background: var(--brand); color: #04281A; border-color: var(--brand); }
        .dark .laas-btn:hover { background: var(--brand-strong); border-color: var(--brand-strong); }

        /* Primary on a dark ground: white fill, dark ink. Highest possible
           contrast without introducing a third colour. */
        .laas-btn-invert { background: #FFFFFF; color: #14201A; border: 2px solid #FFFFFF; }
        .laas-btn-invert:hover { background: #E8EAEC; border-color: #E8EAEC; }

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
                linear-gradient(rgba(255,255,255,.028) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.028) 1px, transparent 1px);
            background-size: 64px 64px;
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
            background: var(--focus);
            color: #14171A;
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
