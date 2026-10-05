@php
    /**
     * Survey Mobile — the officer's dashboard. First screen after sign-in.
     * Every figure is the signed-in officer's own cases (created_by), never the module's.
     */
    $user  = auth()->user();
    $first = \Illuminate\Support\Str::title(mb_strtolower(trim(explode(' ', trim((string) $user->name))[0] ?? ''))) ?: 'Officer';
    $hour  = (int) now()->format('G');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : ($s === 'Pending' ? 'Draft' : $s);
    $statusClass = fn ($s) => match ($s) {
        'Review' => 'st-review', 'Active' => 'st-active', 'Completed' => 'st-done', 'Rejected' => 'st-rejected', default => 'st-draft',
    };

    // Short naira for tiles: ₦1.2M, ₦850K. The full figure is in the tooltip.
    $naira = function (float $v): string {
        if ($v >= 1e9) return '₦' . rtrim(rtrim(number_format($v / 1e9, 1), '0'), '.') . 'B';
        if ($v >= 1e6) return '₦' . rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.') . 'M';
        if ($v >= 1e3) return '₦' . rtrim(rtrim(number_format($v / 1e3, 1), '0'), '.') . 'K';
        return '₦' . number_format($v);
    };

    // Filter tabs, in workflow order. Each one is a link, so the filter survives a refresh and the back button.
    $tabs = [
        ['key' => null,        'label' => 'All',            'n' => $stats['total']],
        ['key' => 'Pending',   'label' => 'Drafts',         'n' => $stats['draft']],
        ['key' => 'Review',    'label' => 'Pending Review', 'n' => $stats['review']],
        ['key' => 'Active',    'label' => 'Active',         'n' => $stats['active']],
        ['key' => 'Completed', 'label' => 'Completed',      'n' => $stats['completed']],
        ['key' => 'Rejected',  'label' => 'Rejected',       'n' => $stats['rejected']],
    ];

    // Status mix bar: one segment per status that has cases.
    $mix = array_values(array_filter([
        ['label' => 'Draft',          'n' => $stats['draft'],     'cls' => 'st-draft',    'icon' => 'fa-pen'],
        ['label' => 'Pending Review', 'n' => $stats['review'],    'cls' => 'st-review',   'icon' => 'fa-hourglass-half'],
        ['label' => 'Active',         'n' => $stats['active'],    'cls' => 'st-active',   'icon' => 'fa-bolt'],
        ['label' => 'Completed',      'n' => $stats['completed'], 'cls' => 'st-done',     'icon' => 'fa-circle-check'],
        ['label' => 'Rejected',       'n' => $stats['rejected'],  'cls' => 'st-rejected', 'icon' => 'fa-circle-xmark'],
    ], fn ($m) => $m['n'] > 0));

    $tabUrl = fn ($key) => route('survey-module.mobile.index', array_filter(['status' => $key, 'q' => $term ?: null]));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>Dashboard — Survey Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('survey_module.mobile._styles')
    <style>
        body { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }
        .appbar { padding-bottom: 20px; }

        .hero { max-width: var(--page-w); margin: 16px auto 0; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; justify-content: space-between; }
        .hero h2 { font-size: clamp(22px, 6vw, 30px); font-weight: 800; line-height: 1.15; }
        .hero p { opacity: .8; font-size: 14px; margin-top: 4px; }
        .hero .btn-cta { background: #fff; color: var(--primary-dark); box-shadow: 0 10px 24px -10px rgba(0,0,0,.45); }
        @media (max-width: 639px) { .hero .btn-cta { width: 100%; } }

        .tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        @media (min-width: 768px) { .tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; } }
        .tile { display: block; text-decoration: none; color: inherit; padding: 16px; border-radius: var(--radius); background: var(--card); border: 1px solid #efedf3; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px -14px rgba(30,27,46,.14); transition: transform .15s, box-shadow .15s; min-width: 0; }
        a.tile:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -14px rgba(30,27,46,.25); }
        a.tile.on { outline: 2px solid var(--primary); outline-offset: -2px; }
        .tile .top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .tile .lbl { font-size: 13px; color: var(--muted); font-weight: 600; }
        .tile .ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; flex: none; }
        .tile .val { font-size: clamp(26px, 7vw, 32px); font-weight: 800; color: var(--ink); margin-top: 8px; line-height: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tile .sub { font-size: 12px; color: var(--muted); margin-top: 6px; }

        .panel-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 26px 0 12px; }
        .panel-title h3 { font-size: 17px; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .panel-title h3 i { color: var(--primary); }

        /* Status mix: one stacked bar, 2px surface gap between segments, legend with counts below. */
        .mix-bar { display: flex; gap: 2px; height: 14px; border-radius: 7px; overflow: hidden; background: #f3f4f6; margin-top: 4px; }
        .mix-bar span { display: block; height: 100%; min-width: 6px; position: relative; cursor: default; }
        .mix-bar span:first-child { border-radius: 4px 0 0 4px; }
        .mix-bar span:last-child { border-radius: 0 4px 4px 0; }
        .mix-bar span:only-child { border-radius: 4px; }
        .mix-bar .st-draft { background: #9ca3af; } .mix-bar .st-review { background: #f59e0b; } .mix-bar .st-active { background: #3b82f6; }
        .mix-bar .st-done { background: #10b981; } .mix-bar .st-rejected { background: #ef4444; }
        .mix-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; font-size: 13px; color: var(--text); }
        .mix-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .mix-legend i.sw { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
        .mix-st-draft { background: #9ca3af; } .mix-st-review { background: #f59e0b; } .mix-st-active { background: #3b82f6; }
        .mix-st-done { background: #10b981; } .mix-st-rejected { background: #ef4444; }
        .mix-legend b { color: var(--ink); }
        .tip { position: fixed; z-index: 90; pointer-events: none; background: var(--ink); color: #fff; font-size: 13px; padding: 6px 10px; border-radius: 8px; opacity: 0; transition: opacity .12s; white-space: nowrap; }

        .impact { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: var(--line); border-radius: 16px; overflow: hidden; margin-top: 16px; }
        @media (min-width: 768px) { .impact { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .impact div { background: #fff; padding: 14px; min-width: 0; }
        .impact small { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .impact b { display: block; font-size: 20px; color: var(--ink); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .two-col { display: grid; gap: 16px; }
        @media (min-width: 1024px) { .two-col { grid-template-columns: minmax(0, 1fr) 340px; align-items: start; gap: 24px; } }

        .projects { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; scroll-snap-type: x mandatory; scrollbar-width: thin; }
        @media (min-width: 1024px) { .projects { flex-direction: column; overflow: visible; } .projects .proj { flex: none; } }
        .proj { flex: 0 0 240px; scroll-snap-align: start; display: block; text-decoration: none; color: inherit; border: 1px solid var(--line); border-radius: 16px; padding: 14px; background: #fff; }
        .proj:hover { border-color: #f9a8d4; background: var(--primary-50); }
        .proj .code { font-size: 12px; font-weight: 700; color: var(--muted); }
        .proj .name { font-weight: 700; color: var(--ink); margin: 2px 0 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .proj .foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; color: var(--muted); }
        .proj .go { color: var(--primary-dark); font-weight: 700; white-space: nowrap; }

        .tabs { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
        .tabs::-webkit-scrollbar { display: none; }
        .tabs a { flex: none; text-decoration: none; padding: 8px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; background: #fff; color: #374151; border: 1px solid var(--line); display: inline-flex; gap: 6px; align-items: center; }
        .tabs a .n { background: #f3f4f6; border-radius: 999px; padding: 0 7px; font-size: 12px; }
        .tabs a.on { background: var(--ink); color: #fff; border-color: var(--ink); }
        .tabs a.on .n { background: rgba(255,255,255,.2); }

        .search { position: relative; margin: 12px 0; }
        .search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .search .input { padding-left: 40px; }

        .case-list { display: grid; gap: 10px; }
        @media (min-width: 768px) { .case-list { grid-template-columns: 1fr 1fr; } }
        .case { display: block; text-decoration: none; color: inherit; background: #fff; border: 1px solid #efedf3; border-radius: 16px; padding: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.03); transition: border-color .15s; min-width: 0; }
        .case:hover { border-color: #f9a8d4; }
        .case.new { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(219,39,119,.12); }
        .case .top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .case .ref { font-weight: 800; color: var(--ink); }
        .case .proj-name { font-size: 14px; color: #374151; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .case .meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 13px; color: var(--muted); margin-top: 8px; }
        .case .meta span { display: inline-flex; align-items: center; gap: 5px; }

        .pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 14px; font-size: 14px; color: var(--muted); }

        .fab { position: fixed; right: 16px; bottom: calc(16px + env(safe-area-inset-bottom)); z-index: 45; width: 60px; height: 60px; border-radius: 20px; background: var(--primary); color: #fff; display: grid; place-items: center; font-size: 22px; box-shadow: 0 14px 28px -10px rgba(219,39,119,.8); text-decoration: none; }
        .fab:active { transform: scale(.96); }
        @media (min-width: 1024px) { .fab { display: none; } }
    </style>
</head>
<body>

<header class="appbar">
    <div class="appbar-row">
        <div class="appbar-logo">
            <img src="{{ asset('storage/upload/logo/Klase.png') }}" alt="KLAES"
                 onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-compass'}))">
        </div>
        <div class="appbar-title">
            <h1>Survey Mobile</h1>
            <p>Compensation field register</p>
        </div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>

    <div class="hero">
        <div>
            <h2>{{ $greet }}, {{ $first }}</h2>
            <p>{{ now()->format('l, j F Y') }} · {{ $stats['today'] }} registered today · {{ $stats['month'] }} this month</p>
        </div>
        <a href="{{ route('survey-module.mobile.register') }}" class="btn btn-cta"><i class="fas fa-plus"></i> Register new case</a>
    </div>
</header>

@include('survey_module.mobile._menu')

<div class="wrap">

    {{-- Result of the last save, carried over from the register. --}}
    @if (session('success'))
        <div class="flash ok" style="margin-bottom:16px;" role="status">
            <i class="fas fa-circle-check"></i>
            <div style="min-width:0;">
                <strong>Case saved</strong>
                @if (session('saved_ref')) <div class="ref">{{ session('saved_ref') }}</div> @endif
                <div style="font-size:14px;margin-top:4px;">{{ session('success') }}</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                    @if (session('saved_id'))
                        <a class="btn btn-sm btn-ghost" href="{{ route('survey-module.mobile.cases.show', session('saved_id')) }}"><i class="fas fa-eye"></i> View case</a>
                    @endif
                    <a class="btn btn-sm btn-primary" href="{{ route('survey-module.mobile.register') }}"><i class="fas fa-plus"></i> Register another</a>
                </div>
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="flash bad" style="margin-bottom:16px;" role="alert">
            <i class="fas fa-triangle-exclamation"></i>
            <div style="min-width:0;">
                @if (session('saved_ref')) <div class="ref">{{ session('saved_ref') }}</div> @endif
                <div style="font-size:14px;">{{ session('error') }}</div>
                @if (session('saved_id'))
                    <a class="btn btn-sm btn-ghost" style="margin-top:10px;" href="{{ route('survey-module.mobile.cases.show', session('saved_id')) }}"><i class="fas fa-eye"></i> View case</a>
                @endif
            </div>
        </div>
    @endif

    {{-- ============================== HEADLINE TILES ============================== --}}
    <div class="tiles">
        <a class="tile {{ $status === null ? 'on' : '' }}" href="{{ $tabUrl(null) }}">
            <div class="top"><span class="lbl">My cases</span><span class="ico" style="background:var(--primary-50);color:var(--primary);"><i class="fas fa-folder-open"></i></span></div>
            <div class="val">{{ number_format($stats['total']) }}</div>
            <div class="sub">All you have registered</div>
        </a>
        <a class="tile {{ $status === 'Pending' ? 'on' : '' }}" href="{{ $tabUrl('Pending') }}">
            <div class="top"><span class="lbl">Drafts</span><span class="ico st-draft"><i class="fas fa-pen"></i></span></div>
            <div class="val">{{ number_format($stats['draft']) }}</div>
            <div class="sub">Saved, not yet submitted</div>
        </a>
        <a class="tile {{ $status === 'Review' ? 'on' : '' }}" href="{{ $tabUrl('Review') }}">
            <div class="top"><span class="lbl">Pending Review</span><span class="ico st-review"><i class="fas fa-hourglass-half"></i></span></div>
            <div class="val">{{ number_format($stats['review']) }}</div>
            <div class="sub">Submitted, awaiting review</div>
        </a>
        <a class="tile {{ in_array($status, ['Active', 'Completed'], true) ? 'on' : '' }}" href="{{ $tabUrl('Completed') }}">
            <div class="top"><span class="lbl">Completed</span><span class="ico st-done"><i class="fas fa-circle-check"></i></span></div>
            <div class="val">{{ number_format($stats['completed']) }}</div>
            <div class="sub">{{ number_format($stats['active']) }} still in progress</div>
        </a>
    </div>

    <div class="two-col">
        <div style="min-width:0;">

            {{-- ============================== STATUS MIX + IMPACT ============================== --}}
            <div class="card card-pad" style="margin-top:16px;">
                <div class="panel-title" style="margin-top:0;">
                    <h3><i class="fas fa-chart-simple"></i> Where my cases are</h3>
                    <span class="hint" style="margin:0;">{{ number_format($stats['total']) }} total</span>
                </div>
                @if ($stats['total'])
                    <div class="mix-bar" role="img" aria-label="Status of my cases: @foreach ($mix as $m){{ $m['label'] }} {{ $m['n'] }}{{ $loop->last ? '' : ', ' }}@endforeach">
                        @foreach ($mix as $m)
                            <span class="{{ $m['cls'] }}" style="flex: {{ $m['n'] }} 1 0;"
                                  data-tip="{{ $m['label'] }}: {{ $m['n'] }} ({{ round($m['n'] / $stats['total'] * 100) }}%)"></span>
                        @endforeach
                    </div>
                    <div class="mix-legend">
                        @foreach ($mix as $m)
                            <span><i class="sw mix-{{ $m['cls'] }}"></i><i class="fas {{ $m['icon'] }}" style="color:var(--muted);font-size:11px;"></i> {{ $m['label'] }} <b>{{ $m['n'] }}</b></span>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state" style="margin-top:0;"><i class="fas fa-chart-simple"></i>Your figures appear here once you register a case.</div>
                @endif

                <div class="impact">
                    <div><small><i class="fas fa-users"></i> Farmers</small><b>{{ number_format($stats['farmers']) }}</b></div>
                    <div title="₦{{ number_format($stats['cash'], 2) }}"><small><i class="fas fa-money-bill-wave"></i> Cash value</small><b>{{ $naira($stats['cash']) }}</b></div>
                    <div><small><i class="fas fa-map-location-dot"></i> Plots</small><b>{{ number_format($stats['plots']) }}</b></div>
                    <div><small><i class="fas fa-ruler-combined"></i> Area</small><b>{{ rtrim(rtrim(number_format($stats['hectares'], 2), '0'), '.') ?: '0' }} Ha</b></div>
                </div>
            </div>

            {{-- ============================== MY CASES ============================== --}}
            <div class="panel-title">
                <h3><i class="fas fa-list-check"></i> My cases</h3>
            </div>

            <nav class="tabs" aria-label="Filter by status">
                @foreach ($tabs as $t)
                    <a href="{{ $tabUrl($t['key']) }}" class="{{ $status === $t['key'] ? 'on' : '' }}">{{ $t['label'] }} <span class="n">{{ $t['n'] }}</span></a>
                @endforeach
            </nav>

            <form class="search" method="GET" action="{{ route('survey-module.mobile.index') }}" role="search">
                @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
                <i class="fas fa-magnifying-glass"></i>
                <input class="input" type="search" name="q" value="{{ $term }}" placeholder="Search case no., project or district…" enterkeyhint="search">
            </form>

            @if ($cases->isEmpty())
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    @if ($term !== '' || $status)
                        No cases match. <a href="{{ route('survey-module.mobile.index') }}">Clear the filter</a>
                    @else
                        You have not registered a case yet.
                        <div style="margin-top:12px;"><a class="btn btn-primary btn-sm" href="{{ route('survey-module.mobile.register') }}"><i class="fas fa-plus"></i> Register your first case</a></div>
                    @endif
                </div>
            @else
                <div class="case-list">
                    @foreach ($cases as $c)
                        <a class="case {{ (int) session('saved_id') === $c->id ? 'new' : '' }}" href="{{ route('survey-module.mobile.cases.show', $c) }}">
                            <div class="top">
                                <span class="ref">{{ $c->case_ref }}</span>
                                <span class="pill {{ $statusClass($c->status) }}">{{ $statusLabel($c->status) }}</span>
                            </div>
                            <div class="proj-name">{{ $c->project?->name ?? '—' }}</div>
                            <div class="meta">
                                <span><i class="fas fa-calendar"></i> {{ optional($c->case_date)->format('d M Y') ?? '—' }}</span>
                                <span><i class="fas fa-users"></i> {{ $c->beneficiaries_count }}</span>
                                @if ($c->scheme_type === 'land')
                                    <span><i class="fas fa-map-location-dot"></i> {{ (int) $c->num_plots }} plots</span>
                                @else
                                    <span><i class="fas fa-money-bill-wave"></i> ₦{{ number_format((float) $c->trees_sum_line_total) }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                @if ($cases->hasPages())
                    <div class="pager">
                        @if ($cases->onFirstPage())
                            <span class="btn btn-sm btn-ghost" style="opacity:.5;"><i class="fas fa-arrow-left"></i></span>
                        @else
                            <a class="btn btn-sm btn-ghost" href="{{ $cases->previousPageUrl() }}" aria-label="Previous page"><i class="fas fa-arrow-left"></i></a>
                        @endif
                        <span>Page {{ $cases->currentPage() }} of {{ $cases->lastPage() }}</span>
                        @if ($cases->hasMorePages())
                            <a class="btn btn-sm btn-ghost" href="{{ $cases->nextPageUrl() }}" aria-label="Next page"><i class="fas fa-arrow-right"></i></a>
                        @else
                            <span class="btn btn-sm btn-ghost" style="opacity:.5;"><i class="fas fa-arrow-right"></i></span>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        {{-- ============================== ACTIVE PROJECTS ============================== --}}
        <aside style="min-width:0;">
            <div class="panel-title">
                <h3><i class="fas fa-folder-tree"></i> Active projects</h3>
            </div>
            @if ($projects->isEmpty())
                <div class="empty-state" style="margin-top:0;"><i class="fas fa-folder"></i>No active projects. Projects are created in the desktop module.</div>
            @else
                <div class="projects">
                    @foreach ($projects as $p)
                        <a class="proj" href="{{ route('survey-module.mobile.register', ['project' => $p->id]) }}">
                            <div class="code">{{ $p->project_code }}</div>
                            <div class="name">{{ $p->name }}</div>
                            <div class="foot">
                                <span class="badge {{ $p->isMonetary() ? 'monetary' : 'land' }}" style="font-size:11px;padding:3px 8px;">{{ $p->isMonetary() ? 'Monetary' : 'Land-for-Land' }}</span>
                                <span class="go">Register <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>

<a href="{{ route('survey-module.mobile.register') }}" class="fab" aria-label="Register new case"><i class="fas fa-plus"></i></a>
<div class="tip" id="tip" role="tooltip"></div>

<script>
(function () {
    // Hover/tap tooltip for the status bar segments.
    var tip = document.getElementById('tip');
    function show(el, x, y) { tip.textContent = el.getAttribute('data-tip'); tip.style.left = Math.min(x + 12, window.innerWidth - tip.offsetWidth - 8) + 'px'; tip.style.top = (y - 36) + 'px'; tip.style.opacity = 1; }
    document.querySelectorAll('.mix-bar [data-tip]').forEach(function (el) {
        el.addEventListener('mousemove', function (e) { show(el, e.clientX, e.clientY); });
        el.addEventListener('mouseleave', function () { tip.style.opacity = 0; });
        el.addEventListener('click', function (e) { show(el, e.clientX, e.clientY); setTimeout(function () { tip.style.opacity = 0; }, 1800); });
    });
})();
</script>
</body>
</html>
