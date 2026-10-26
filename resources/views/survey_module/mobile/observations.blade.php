<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/SCF.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>Field Observations — Survey Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('survey_module.mobile._styles')
    <style>
        body { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }
        .job { display: block; text-decoration: none; color: inherit; padding: 14px 16px; margin-bottom: 10px; }
        .job strong { font-size: 15.5px; }
        .job .sub { font-size: 13px; opacity: .75; margin-top: 3px; }
        .job .count { float: right; font-size: 12.5px; background: var(--primary-50, #f1eefb); color: var(--primary, #29213D); border-radius: 999px; padding: 3px 10px; }
    </style>
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <div class="appbar-logo"><img src="{{ asset('assets/logo/SCF.png') }}" alt="SCF" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-compass'}))"></div>
        <div class="appbar-title"><h1>Field Observations</h1><p>Readings for an Instruction to Survey</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    <p style="font-size:14px;opacity:.8;margin:0 0 12px;">Pick the survey job you are in the field for. Readings work offline and sync later.</p>
    @forelse ($jobs as $j)
        @php $p = $j['prefill']; @endphp
        <a class="card job" href="{{ route('survey-module.mobile.observations.show', $j['key']) }}">
            <span class="count">{{ number_format($counts[$j['key']] ?? 0) }} reading{{ ($counts[$j['key']] ?? 0) === 1 ? '' : 's' }}</span>
            <strong>{{ $p['its_number'] }}</strong>
            <div class="sub">{{ $p['file_number'] }}{{ $p['grantee_name'] ? ' · ' . $p['grantee_name'] : '' }}</div>
            {{-- Plot No., District, LGA --}}
            <div class="sub">{{ implode(' · ', array_filter([$p['plot_no'] ? 'Plot ' . $p['plot_no'] : null, $p['district'], $p['lga']])) ?: 'No location recorded' }}</div>
            <div class="sub">{{ $p['surveyor_name'] ?: '' }} · {{ $j['source'] === 'cadastral' ? 'Cadastral I to S' : 'Survey I to S' }}</div>
        </a>
    @empty
        <div class="card card-pad" style="text-align:center;opacity:.8;">No open Instructions to Survey. They are issued from the desktop module (GKN &gt; Instruction to Survey).</div>
    @endforelse
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'readings'])
</body>
</html>
