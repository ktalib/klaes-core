{{--
    One printable document assembled from several single-record print views.

    $head / $bodies come from App\Services\StitchedBatchPrint, which renders each
    record through its own print template — so this file holds no letter markup of
    its own and the batch can never drift from what a single print produces.

    Expects: $head, $bodies, $title, $subtitle, $logUrls (may be empty).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    {{-- The head of the record template: its styles, fonts and metadata. --}}
    {!! $head !!}

    <style>
        /* A zero-height marker placed BETWEEN records. Wrapping each letter in a box
           instead would add a block to the record's own page flow and can push its
           last lines onto an extra sheet. */
        .batch-print-break {
            height: 0;
            margin: 0;
            border: 0;
            break-before: page;
            page-break-before: always;
        }

        /* Zero-height anchor, one per document, so the preview can tell where one
           record's letters end and the next begins. */
        .bpb-doc-anchor { height: 0; margin: 0; border: 0; }

        /* ── Preview chrome ───────────────────────────────────────────────────
           Screen only, and additive only: nothing here changes a letter's box
           model, so what comes out of the printer is exactly what came out before
           this file grew a preview. Every rule is dropped by the print block at the
           bottom. What it buys is the two things a stitched batch has no other way
           to show — how many sheets are about to come out, and where you are in
           them.
           ------------------------------------------------------------------- */
        body {
            background: #e4e7ec;
        }
        /* Room for the rail, so it never sits over the letter. */
        body.bpb-has-rail { padding-right: 84px; }

        /* ── The sheet container ────────────────────────────────────────────
           Each page sits in a box of its own, A4 wide and centred, so the preview
           reads as paper instead of as text stretched across the monitor. These
           templates were written for a printer and set no width of their own, which
           is why the letters ran from one edge of the screen to the other.

           The container is a wrapper the script puts around each page, NOT the
           page's own element, and in print it is `display: contents` — the box
           disappears from layout entirely and its children lay out exactly as if it
           had never been there. That is what makes it safe to give the preview a
           width and a padding: none of it can reach the paper.

           The wrapper is only ever created where display: contents is supported.
           Everywhere else the script falls back to .bpb-paper, which is background
           and shadow only and cannot disturb a layout.
           ------------------------------------------------------------------- */
        .bpb-sheet {
            position: relative;
            width: min(210mm, calc(100% - 40px));
            margin: 0 auto 22px;
            padding: 12mm 13mm;
            box-sizing: border-box;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .16), 0 8px 24px rgba(15, 23, 42, .10);
            border-radius: 2px;
        }

        /* A template that already lays its content out on a fixed-size sheet (the
           RofO's .page-container, the conversion form's .a4-page) is its own paper:
           the container only centres it and gives it a shadow. A narrow window
           scrolls it sideways inside its own box rather than pushing the whole page
           out of shape. */
        .bpb-sheet--fixed {
            width: fit-content;
            max-width: 100%;
            padding: 0;
            overflow-x: auto;
        }

        /* Fallback where the wrapper cannot be used. */
        .bpb-paper {
            position: relative;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .16), 0 8px 24px rgba(15, 23, 42, .10);
        }

        .bpb-stage { padding: 22px 0 40px; }

        .batch-print-bar {
            position: sticky; top: 0; z-index: 999999;
            display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
            padding: 9px 18px;
            background: linear-gradient(90deg, #4c1d95 0%, #5b21b6 100%);
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 13px; line-height: 1.4;
            box-shadow: 0 2px 12px rgba(15, 23, 42, .28);
        }
        .bpb-title { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .bpb-title b { font-size: 13.5px; font-weight: 800; letter-spacing: .01em; }
        .bpb-title span { font-size: 11.5px; color: #ddd6fe; }

        .bpb-counts { display: flex; gap: 8px; flex-wrap: wrap; }
        .bpb-chip {
            display: inline-flex; align-items: baseline; gap: 5px;
            padding: 4px 11px; border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            font-size: 11.5px; font-weight: 600; color: #ede9fe; white-space: nowrap;
        }
        .bpb-chip b { font-size: 13px; font-weight: 800; color: #fff; }

        .bpb-nav { display: flex; align-items: center; gap: 6px; margin-left: auto; }
        .bpb-nav button {
            padding: 5px 10px; border: 0; border-radius: 6px;
            background: rgba(255, 255, 255, .16); color: #fff;
            font-weight: 800; font-size: 13px; line-height: 1; cursor: pointer;
        }
        .bpb-nav button:hover { background: rgba(255, 255, 255, .28); }
        .bpb-nav button:disabled { opacity: .35; cursor: default; }
        .bpb-position {
            min-width: 96px; text-align: center;
            font-size: 12px; font-weight: 700; color: #ede9fe;
            font-variant-numeric: tabular-nums;
        }
        .bpb-print {
            padding: 7px 16px; border: 0; border-radius: 6px;
            background: #fff; color: #4c1d95; font-weight: 800; font-size: 12px; cursor: pointer;
        }
        .bpb-print:hover { background: #f5f3ff; }

        .bpb-progress {
            position: absolute; left: 0; right: 0; bottom: 0; height: 2px;
            background: rgba(255, 255, 255, .18);
        }
        .bpb-progress i { display: block; height: 100%; width: 0; background: #c4b5fd; transition: width .12s linear; }

        /* ── Side rail ──────────────────────────────────────────────────────
           The count follows the scroll, pinned to the viewport rather than to a
           page, because the eye is down in the letters and not up at the bar.
           Sized so the page number reads at a glance and the ticks are big enough
           to aim at; a batch too long for one tick per page falls back to a fill.
           ------------------------------------------------------------------ */
        .bpb-rail {
            position: fixed; right: 16px; top: 50%; transform: translateY(-50%);
            z-index: 999998;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
            width: 52px; padding: 12px 0;
            border-radius: 26px;
            background: #4c1d95;
            box-shadow: 0 8px 26px rgba(15, 23, 42, .34);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #fff; user-select: none;
        }
        .bpb-rail-count { display: flex; flex-direction: column; align-items: center; line-height: 1; }
        .bpb-rail-count b { font-size: 19px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .bpb-rail-count i {
            font-style: normal; font-size: 10.5px; font-weight: 700;
            color: #c4b5fd; margin-top: 4px; font-variant-numeric: tabular-nums;
        }
        .bpb-rail hr { width: 22px; height: 1px; border: 0; margin: 0; background: rgba(255, 255, 255, .22); }
        .bpb-ticks {
            display: flex; flex-direction: column; gap: 4px; align-items: center;
            max-height: 44vh; overflow: hidden;
        }
        .bpb-ticks button {
            width: 8px; height: 8px; padding: 0; border: 0; border-radius: 999px;
            background: rgba(255, 255, 255, .32); cursor: pointer;
        }
        .bpb-ticks button:hover { background: rgba(255, 255, 255, .7); }
        .bpb-ticks button[aria-current="true"] { background: #fff; height: 18px; }
        .bpb-rail-fill {
            width: 6px; height: 38vh; border-radius: 999px;
            background: rgba(255, 255, 255, .22); overflow: hidden;
        }
        .bpb-rail-fill i { display: block; width: 100%; background: #c4b5fd; height: 0; }

        /* No room beside the letter on a narrow screen; the bar still counts, and
           the page number moves inside the sheet since there is no margin left to
           hang it in. */
        @media (max-width: 1180px) {
            .bpb-rail { display: none; }
            body.bpb-has-rail { padding-right: 0; }
            .bpb-page-no { left: auto; right: 6px; top: 4px; text-align: right; }
        }

        /* Page number, in the margin beside each page rather than over it — these
           documents run to the edge of their box and a badge on top of one covers
           the letterhead or the QR code. */
        .bpb-page-no {
            position: absolute; top: 0; left: -76px; width: 62px;
            text-align: right;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 10.5px; font-weight: 800; letter-spacing: .04em;
            color: #64748b; pointer-events: none;
        }
        .bpb-page-no b { display: block; font-size: 15px; color: #4c1d95; }

        /* The single-record templates ship their own print buttons. One per record
           is one too many here — the bar prints the whole batch. */
        .print-btn-container,
        .print-button { display: none !important; }

        @media print {
            .batch-print-bar,
            .bpb-rail,
            .bpb-page-no { display: none !important; }
            body { background: #fff; }
            body.bpb-has-rail { padding-right: 0; }
            .bpb-stage { padding: 0; }

            /* display: contents removes the wrapper from layout completely, so the
               page inside it prints exactly as it did before the preview existed —
               no width, no padding, no margin of the container survives. */
            .bpb-sheet,
            .bpb-sheet--fixed { display: contents !important; }

            .bpb-paper { background: none; box-shadow: none; position: static; }
        }
    </style>
</head>
<body>
    <div class="batch-print-bar no-print">
        <span class="bpb-title">
            <b>{{ $title }}</b>
            <span>{{ $subtitle }}</span>
        </span>

        {{-- Filled in by script: how many pages a document comes to is decided by
             its own template, not by this file. --}}
        <span class="bpb-counts">
            <span class="bpb-chip" id="bpbPagesChip" title="Pages this document will print">
                <b id="bpbPages">—</b> <span id="bpbPagesLabel">pages</span>
            </span>
            <span class="bpb-chip"><b>{{ count($bodies) }}</b> {{ \Illuminate\Support\Str::plural('document', count($bodies)) }}</span>
        </span>

        <span class="bpb-nav">
            <button type="button" id="bpbPrev" title="Previous page">&uarr;</button>
            <span class="bpb-position" id="bpbPosition">— of —</span>
            <button type="button" id="bpbNext" title="Next page">&darr;</button>
            <button type="button" class="bpb-print" onclick="window.print()">Print all</button>
        </span>

        <span class="bpb-progress"><i id="bpbProgress"></i></span>
    </div>

    {{-- Page counter, pinned beside the letters. Built by script so it reflects the
         pages that actually rendered, not a figure worked out here. --}}
    <div class="bpb-rail no-print" id="bpbRail" hidden>
        <span class="bpb-rail-count"><b id="bpbRailNow">1</b><i id="bpbRailTotal">/ 1</i></span>
        <hr>
        <span class="bpb-ticks" id="bpbTicks"></span>
        <span class="bpb-rail-fill" id="bpbRailFill" hidden><i></i></span>
    </div>

    {{-- $breakBetween: false when the record template already forces a page break
         between its own top-level pages. rofo_print does, via the general-sibling
         rule `.page-container ~ .page-container { page-break-before: always }` —
         once stitched, every page-container is a sibling, so record 2 already
         starts on a fresh sheet and a marker here would add a blank one. --}}
    <div class="bpb-stage">
        @foreach($bodies as $i => $body)
            @if(($breakBetween ?? true) && $i > 0)<div class="batch-print-break"></div>@endif
            <div class="bpb-doc-anchor" data-doc="{{ $i + 1 }}"></div>
            {!! $body !!}
        @endforeach
    </div>

    <script>
        // ── Page count and position ──────────────────────────────────────────
        // The count is STRUCTURAL: every element the templates declare as starting
        // a new sheet is one page. Two kinds qualify —
        //
        //   * a fixed-size sheet (.page-container on the RofO, .a4-page on the
        //     conversion recommendation): one element, one piece of paper;
        //   * anything whose own CSS forces a break before it — `.ack-page` carries
        //     page-break-before: always, each `.container` in print_layout carries
        //     it inline, and the stitcher's own marker between records does too.
        //
        // Reading it off computed style rather than a list of class names is what
        // keeps this honest for templates this file has never heard of: if a
        // template says it starts a page, it starts a page. A recommendation with
        // its letter and two acknowledgement copies counts as 3, which is what the
        // printer then reports.
        (function () {
            function all(selector, root) {
                return Array.prototype.slice.call((root || document).querySelectorAll(selector));
            }

            function forcesBreak(el) {
                var cs = window.getComputedStyle(el);
                var before = cs.breakBefore || cs.pageBreakBefore || '';
                return before === 'page' || before === 'always' || before === 'left' || before === 'right';
            }

            var fixed  = all('.page-container').concat(all('.a4-page'));
            var starts = [];

            if (fixed.length) {
                starts = fixed;
            } else {
                // Everything that declares a break, in document order, plus the top
                // of the document itself — the first page never has a break before
                // it and would otherwise go uncounted.
                var first = document.querySelector('.bpb-doc-anchor');
                if (first) { starts.push(first); }

                all('body *').forEach(function (el) {
                    // Every anchor but the first is redundant: the stitcher's own
                    // break marker sits immediately before it and is counted below,
                    // so counting both would double every record after the first.
                    if (el.classList.contains('bpb-doc-anchor')) { return; }
                    if (el.closest('.batch-print-bar') || el.closest('.bpb-rail')) { return; }
                    if (forcesBreak(el)) { starts.push(el); }
                });
            }

            if (!starts.length) { return; }

            // A page can be declared by something with no size of its own — the
            // stitcher's break marker, or a document anchor. There is nothing to
            // paint or number on a zero-height div and nothing to scroll to either,
            // so each of those resolves forward to the first real element on that
            // page. Counting still happens on the declaration; only the visuals
            // move.
            var visuals = starts.map(function (el) {
                var node = el;

                while (node && (node.classList.contains('bpb-doc-anchor')
                             || node.classList.contains('batch-print-break'))) {
                    node = node.nextElementSibling;
                }

                return node || el;
            });

            var total    = starts.length;
            var position = document.getElementById('bpbPosition');
            var progress = document.getElementById('bpbProgress');
            var prev     = document.getElementById('bpbPrev');
            var next     = document.getElementById('bpbNext');
            var rail     = document.getElementById('bpbRail');
            var railNow  = document.getElementById('bpbRailNow');
            var railTot  = document.getElementById('bpbRailTotal');
            var ticksBox = document.getElementById('bpbTicks');
            var railFill = document.getElementById('bpbRailFill');
            var current  = 0;
            var ticks    = [];

            // Past this many pages a tick is under 2px of travel — useless to aim
            // at, and a solid block to look at. The rail switches to a fill bar.
            var TICK_LIMIT = 40;

            document.getElementById('bpbPages').textContent = total;
            document.getElementById('bpbPagesLabel').textContent = total === 1 ? 'page' : 'pages';
            railTot.textContent = '/ ' + total;
            rail.hidden = false;
            document.body.classList.add('bpb-has-rail');

            // Paint each page start as a sheet and number it in the margin. Skipped
            // for a zero-height anchor, which has nowhere to show either.
            // The wrapper is only safe where `display: contents` is, because that
            // is what takes it back out of the layout at print time. Without it the
            // page keeps today's behaviour: a background and a shadow, no box.
            var canWrap = !!(window.CSS && window.CSS.supports
                             && window.CSS.supports('display', 'contents'));

            var isFixedSheet = fixed.length > 0;

            visuals.forEach(function (el, i) {
                if (!el || (el === starts[i] && (el.classList.contains('bpb-doc-anchor')
                                              || el.classList.contains('batch-print-break')))) {
                    return;
                }

                var host = el;

                if (canWrap && el.parentNode) {
                    var sheet = document.createElement('div');
                    sheet.className = 'bpb-sheet' + (isFixedSheet ? ' bpb-sheet--fixed' : '');
                    el.parentNode.insertBefore(sheet, el);
                    sheet.appendChild(el);
                    host = sheet;
                } else {
                    el.classList.add('bpb-paper');
                }

                var tag = document.createElement('div');
                tag.className = 'bpb-page-no no-print';
                tag.innerHTML = '<b>' + (i + 1) + '</b>of ' + total;
                host.appendChild(tag);

                // Scroll and highlight the container, not the page inside it, so the
                // jump lands on the top of the paper rather than the top of the text.
                visuals[i] = host;
            });

            if (total <= TICK_LIMIT) {
                starts.forEach(function (el, i) {
                    var tick = document.createElement('button');
                    tick.type = 'button';
                    tick.title = 'Page ' + (i + 1) + ' of ' + total;
                    tick.addEventListener('click', function () { goTo(i); });
                    ticksBox.appendChild(tick);
                    ticks.push(tick);
                });
            } else {
                ticksBox.hidden = true;
                railFill.hidden = false;
            }

            function render() {
                var pct = ((current + 1) / total) * 100;

                position.textContent = 'Page ' + (current + 1) + ' of ' + total;
                progress.style.width = pct + '%';
                prev.disabled = current === 0;
                next.disabled = current === total - 1;
                railNow.textContent = current + 1;

                if (ticks.length) {
                    ticks.forEach(function (tick, i) {
                        if (i === current) { tick.setAttribute('aria-current', 'true'); }
                        else { tick.removeAttribute('aria-current'); }
                    });
                } else {
                    railFill.firstElementChild.style.height = pct + '%';
                }
            }

            function goTo(i) {
                current = Math.max(0, Math.min(total - 1, i));
                (visuals[current] || starts[current]).scrollIntoView({ behavior: 'smooth', block: 'start' });
                render();
            }

            prev.addEventListener('click', function () { goTo(current - 1); });
            next.addEventListener('click', function () { goTo(current + 1); });

            // The arrow keys already scroll, so these are the page keys, which
            // otherwise jump by viewport rather than by page.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'PageDown') { e.preventDefault(); goTo(current + 1); }
                if (e.key === 'PageUp')   { e.preventDefault(); goTo(current - 1); }
            });

            // Scrolling updates the readout. The threshold picks whichever page
            // holds the middle of the viewport, so it changes over at the fold
            // rather than flickering between two pages at every edge.
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) { return; }
                        var i = visuals.indexOf(entry.target);
                        if (i >= 0 && i !== current) { current = i; render(); }
                    });
                }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });

                visuals.forEach(function (el) { if (el) { observer.observe(el); } });
            }

            // The observer changes over at the fold, which is right in the body of a
            // long stack but leaves the first and last page ambiguous — at the very
            // top or bottom of the document neither crosses the middle of the
            // screen. This pins those two ends.
            window.addEventListener('scroll', function () {
                if (window.scrollY <= 4 && current !== 0) { current = 0; render(); return; }

                var atEnd = window.innerHeight + window.scrollY >= document.body.scrollHeight - 4;
                if (atEnd && current !== total - 1) { current = total - 1; render(); }
            }, { passive: true });

            render();
        })();

        // The per-record scripts are stripped server-side, so printing and logging
        // happen once here for the whole batch.
        var LOG_URLS   = @json($logUrls ?? []);
        var CSRF_TOKEN = '{{ csrf_token() }}';
        var logged     = false;

        window.addEventListener('afterprint', function () {
            // The opener refreshes either way — a caller that logged the batch before
            // opening this page passes no URLs, and its list still needs to update.
            try { window.opener.location.reload(); } catch (e) {}

            if (logged || !LOG_URLS.length) return;
            logged = true;

            LOG_URLS.forEach(function (url) {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }).catch(function (e) { console.error('Failed to log print: ' + url, e); });
            });
        });
    </script>
</body>
</html>
