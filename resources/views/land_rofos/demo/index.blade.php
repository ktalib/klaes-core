<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Land RofO Layout Demo</title>
    <style>
        :root { --green: #006b3f; --red: #b91c1c; --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; color: var(--ink); background: #f3f4f6; }
        header { background: var(--green); color: #fff; padding: 14px 20px; }
        header h1 { margin: 0; font-size: 18px; }
        header p { margin: 4px 0 0; font-size: 13px; opacity: .9; }
        main { padding: 16px 20px 40px; max-width: 1280px; margin: 0 auto; }
        form { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 14px 16px; display: grid; gap: 10px; }
        .presets { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 13px; }
        .presets a { padding: 5px 12px; border-radius: 999px; border: 1px solid var(--green); color: var(--green); text-decoration: none; font-weight: 600; }
        .presets a.on { background: var(--green); color: #fff; }
        .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
        label { font-size: 12px; font-weight: 600; color: var(--muted); display: grid; gap: 4px; }
        input[type=text], textarea { font: inherit; font-size: 13px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; color: var(--ink); }
        textarea { resize: vertical; min-height: 54px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 13px; }
        button { font: inherit; font-weight: 600; background: var(--green); color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; cursor: pointer; }
        .compare { max-width: 640px; margin: 18px auto 0; }
        .col h2 { margin: 0 0 4px; font-size: 15px; }
        .col .note { font-size: 12px; color: var(--muted); margin: 0 0 8px; min-height: 32px; }
        .verdict { font-size: 13px; font-weight: 700; padding: 6px 10px; border-radius: 6px; margin-bottom: 8px; background: #f3f4f6; }
        .verdict.bad { background: #fee2e2; color: var(--red); }
        .verdict.good { background: #dcfce7; color: #166534; }
        .sheet { position: relative; width: 100%; aspect-ratio: 210 / 297; overflow: hidden; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.18); }
        .sheet iframe { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; border: 0; transform-origin: 0 0; }
        .open { display: inline-block; margin-top: 8px; font-size: 13px; color: var(--green); font-weight: 600; }
        @media (max-width: 680px) { main { padding: 12px 16px 32px; } }
    </style>
</head>
<body>
<header>
    <h1>Land RofO Layout Demo</h1>
    <p>Sample data only. The fixed Land RofO letter on the security paper scan. The live RofO print is not changed by this page.</p>
</header>
<main>
    <form method="get" action="{{ route('demo.land-rofo-layout') }}">
        <div class="presets">
            <strong>Sample:</strong>
            @foreach(['short' => 'Short address', 'long' => 'Long address', 'extreme' => 'Very long address'] as $key => $label)
                <a href="{{ route('demo.land-rofo-layout', ['preset' => $key]) }}" class="{{ $values['preset'] === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <input type="hidden" name="preset" value="{{ $values['preset'] }}">
        <div class="fields">
            <label>Applicant name <textarea name="name">{{ $values['name'] }}</textarea></label>
            <label>Applicant address <textarea name="address">{{ $values['address'] }}</textarea></label>
            <label>Location <textarea name="location">{{ $values['location'] }}</textarea></label>
        </div>
        <div class="fields">
            <label>R of O No. <input type="text" name="file_no" value="{{ $values['file_no'] }}"></label>
            <label>Plot No. <input type="text" name="plot" value="{{ $values['plot'] }}"></label>
            <label>Layout plan No. <input type="text" name="plan" value="{{ $values['plan'] }}"></label>
            <label>Purpose <input type="text" name="purpose" value="{{ $values['purpose'] }}"></label>
        </div>
        <div class="actions">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--ink)">
                <input type="hidden" name="paper" value="0">
                <input type="checkbox" name="paper" value="1" {{ $values['paper'] ? 'checked' : '' }}> Show the security paper scan
            </label>
            <button type="submit">Update letter</button>
        </div>
    </form>

    @php
        $query = collect($values)->except('preset')->map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : $v)->all();
    @endphp
    <div class="compare">
        @foreach(['fixed' => ['Fixed layout', 'Frame sized like the SLTR letter, with a margin all round, pinned clear of the barcode; long text is fitted inside it.']] as $layout => [$title, $note])
            @php $src = route('demo.land-rofo-layout.letter', $query + ['layout' => $layout]); @endphp
            <section class="col">
                <h2>{{ $title }}</h2>
                <p class="note">{{ $note }}</p>
                <div class="verdict" data-verdict="{{ $layout }}">Measuring…</div>
                <div class="sheet"><iframe src="{{ $src }}" title="{{ $title }}" data-layout="{{ $layout }}"></iframe></div>
                <a class="open" href="{{ $src }}" target="_blank" rel="noopener">Open full size to print ↗</a>
            </section>
        @endforeach
    </div>
</main>
<script>
    (function () {
        var PX_PER_MM = 96 / 25.4;
        var BARCODE_TOP_MM = 277.5; // where the stock's barcode band starts

        function scale() {
            document.querySelectorAll('.sheet').forEach(function (sheet) {
                sheet.querySelector('iframe').style.transform = 'scale(' + (sheet.clientWidth / 794) + ')';
            });
        }

        function measure(frame) {
            var verdict = document.querySelector('[data-verdict="' + frame.dataset.layout + '"]');
            try {
                var doc = frame.contentDocument;
                var page = doc.querySelector('.page-container');
                var border = doc.querySelector('.ornate-border');
                var bottom = (border.getBoundingClientRect().bottom - page.getBoundingClientRect().top) / PX_PER_MM;
                var gap = BARCODE_TOP_MM - bottom;
                var inner = border.querySelector('.inner-content');
                var zoom = inner && inner.getAttribute('data-fit-zoom');
                var text = 'Frame ends at ' + bottom.toFixed(1) + 'mm. ';
                text += gap >= 0
                    ? gap.toFixed(1) + 'mm clear of the barcode.'
                    : 'Runs ' + (-gap).toFixed(1) + 'mm INTO the barcode.';
                if (zoom && zoom !== '1') { text += ' Text fitted to ' + Math.round(zoom * 100) + '%.'; }
                verdict.textContent = text;
                verdict.className = 'verdict ' + (gap >= 0 ? 'good' : 'bad');
            } catch (e) {
                verdict.textContent = 'Could not measure this letter.';
            }
        }

        document.querySelectorAll('.sheet iframe').forEach(function (frame) {
            frame.addEventListener('load', function () {
                // Let the fixed letter finish fitting itself before measuring.
                setTimeout(function () { measure(frame); }, 300);
            });
        });
        window.addEventListener('resize', scale);
        scale();
    })();
</script>
</body>
</html>
