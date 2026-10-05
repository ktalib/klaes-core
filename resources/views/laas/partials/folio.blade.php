{{--
    The applicant's folio. Two kinds of entry, shown side by side and never merged:
      Scanned      — a page in the file's EDMS folder, streamed as uploaded.
      System copy  — a document the system holds the data for (commissioning sheet,
                     LGA confirmation, tracking sheet, recommendation, RoFO, OSS
                     prints), drawn on demand and read-only. No scan needed.
    See App\Services\Laas\LaasFolioService.

    Every card carries a live preview of its document; clicking it opens the viewer
    on this page (no new tab, no download button). System copies arrive with
    printing stubbed out and staff toolbars hidden — LaasFolioService::forEmbedding().
--}}
@php
    $groupOrder = ['Commissioning', 'One Stop Shop', 'Grant', 'Scanned documents'];
    $rank = fn ($name) => ($i = array_search($name, $groupOrder, true)) === false ? 99 : $i;
    $groups = collect($folio)->groupBy('group')->sortBy(fn ($items, $name) => $rank($name));

    // One flat, ordered list for the viewer's previous / next.
    $viewerItems = [];
    foreach ($groups as $items) {
        foreach ($items as $doc) {
            if (!$doc['available']) {
                continue;
            }
            $isScan = $doc['kind'] === \App\Services\Laas\LaasFolioService::KIND_SCAN;
            $viewerItems[] = [
                'key'   => $doc['key'],
                'title' => $doc['title'],
                'badge' => $isScan ? 'Scanned' : 'System copy',
                'type'  => $doc['is_image'] ? 'image' : ($doc['is_pdf'] ? 'pdf' : 'page'),
                'url'   => route('laas.application.folio', [$application->reference_no, $doc['key']]),
            ];
        }
    }
    $viewerIndex = array_flip(array_column($viewerItems, 'key'));
@endphp

<style>
    /* A4 page drawn at full size, then scaled to the card's width by the script below. */
    .folio-thumb { position: relative; aspect-ratio: 210 / 297; overflow: hidden; background: #fff; }
    .folio-thumb iframe { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; border: 0;
                          transform-origin: 0 0; pointer-events: none; background: #fff; }
    .folio-thumb img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
    .folio-card:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
    .folio-viewer[hidden] { display: none; }
    .folio-viewer iframe, .folio-viewer img { -webkit-user-drag: none; }
</style>

<div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] px-6 py-4 dark:border-[var(--border)]">
        <h2 class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            <i data-lucide="files" class="h-4 w-4"></i> Documents
        </h2>
        @if($application->file_number)
            <span class="font-mono text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">{{ $application->file_number }}</span>
        @endif
    </div>

    @if(empty($folio))
        <p class="p-6 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            Documents on your file will appear here once your file number has been commissioned.
        </p>
    @else
        {{-- One continuous grid, ordered by group; the group rides on each card as
             a label. Separate per-group rows left lone cards stranded in wide gaps. --}}
        <div class="p-5 sm:p-6">
                    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        @foreach($groups->flatten(1) as $doc)
                            @php
                                $isScan = $doc['kind'] === \App\Services\Laas\LaasFolioService::KIND_SCAN;
                                $url = route('laas.application.folio', [$application->reference_no, $doc['key']]);
                                $date = $doc['date'] ? \Illuminate\Support\Carbon::parse($doc['date'])->format('j M Y') : null;
                            @endphp
                            <li>
                                @if($doc['available'])
                                    <button type="button" data-folio-open="{{ $viewerIndex[$doc['key']] }}"
                                            class="folio-card group block w-full overflow-hidden rounded-xl border border-[var(--border)] text-left transition hover:border-[var(--brand)] hover:shadow-md dark:border-[var(--border)]">
                                @else
                                    <div class="block w-full overflow-hidden rounded-xl border border-dashed border-[var(--border)] opacity-70 dark:border-[var(--border)]">
                                @endif

                                    <div class="folio-thumb border-b border-[var(--border)] dark:border-[var(--border)]">
                                        @if(!$doc['available'])
                                            <div class="flex h-full flex-col items-center justify-center gap-2 bg-[var(--surface-card)] p-3 text-center dark:bg-[var(--surface-card)]">
                                                <i data-lucide="file-clock" class="h-8 w-8 text-[var(--ink-faint)]"></i>
                                                <span class="text-xs text-[var(--ink-faint)]">
                                                    {{ $doc['note'] ?? ($isScan ? 'Page image not found — please contact the Lands office.' : 'Not available yet.') }}
                                                </span>
                                            </div>
                                        @elseif($doc['is_image'])
                                            <img src="{{ $url }}" alt="{{ $doc['title'] }}" loading="lazy" draggable="false">
                                        @elseif($doc['is_pdf'])
                                            <iframe data-folio-thumb src="{{ $url }}#toolbar=0&navpanes=0&view=FitH" loading="lazy" tabindex="-1" title="{{ $doc['title'] }}"></iframe>
                                        @else
                                            <iframe data-folio-thumb src="{{ $url }}" loading="lazy" tabindex="-1" title="{{ $doc['title'] }}" scrolling="no"></iframe>
                                        @endif

                                        @if($doc['available'])
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/30 group-hover:opacity-100">
                                                <span class="flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold text-gray-800 shadow">
                                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i> View
                                                </span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="px-3 py-2.5">
                                        <p class="truncate text-[10px] font-bold uppercase tracking-widest text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">{{ $doc['group'] }}</p>
                                        <p class="mt-0.5 truncate text-sm font-semibold text-[var(--ink)] dark:text-[var(--ink)]" title="{{ $doc['title'] }}">{{ $doc['title'] }}</p>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $isScan ? 'bg-[var(--brand-tint)] text-[var(--brand)]' : 'border border-[var(--border)] text-[var(--ink-soft)]' }}">
                                                {{ $isScan ? 'Scanned' : 'System copy' }}
                                            </span>
                                            @if($date)<span>{{ $date }}</span>@endif
                                        </p>
                                    </div>

                                @if($doc['available'])
                                    </button>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
        </div>
    @endif
</div>

@if(!empty($viewerItems))
    {{-- The viewer. Deliberately no download or open-in-tab control. --}}
    <div id="folioViewer" class="folio-viewer fixed inset-0 z-50 flex flex-col bg-black/80" hidden
         role="dialog" aria-modal="true" aria-labelledby="folioViewerTitle">
        <div class="flex items-center justify-between gap-3 bg-[var(--surface-card)] px-4 py-3 shadow dark:bg-[var(--surface-card)]">
            <div class="min-w-0">
                <p id="folioViewerTitle" class="truncate text-sm font-bold text-[var(--ink)] dark:text-[var(--ink)]"></p>
                <p class="mt-0.5 flex items-center gap-2 text-xs text-[var(--ink-faint)]">
                    <span id="folioViewerBadge" class="rounded border border-[var(--border)] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"></span>
                    <span id="folioViewerCount"></span>
                </p>
            </div>
            <div class="flex flex-shrink-0 items-center gap-1">
                <button type="button" data-folio-step="-1" class="rounded-lg p-2 text-[var(--ink-soft)] hover:bg-[var(--brand-tint)]" aria-label="Previous document">
                    <i data-lucide="chevron-left" class="h-5 w-5"></i>
                </button>
                <button type="button" data-folio-step="1" class="rounded-lg p-2 text-[var(--ink-soft)] hover:bg-[var(--brand-tint)]" aria-label="Next document">
                    <i data-lucide="chevron-right" class="h-5 w-5"></i>
                </button>
                <button type="button" data-folio-close class="ml-1 rounded-lg p-2 text-[var(--ink-soft)] hover:bg-[var(--brand-tint)]" aria-label="Close viewer">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>
        <div id="folioViewerBody" class="relative flex-1 overflow-auto p-3 sm:p-6">
            {{-- pointer-events:none so it can never sit between the reader and the
                 document's scrollbar, even if hiding it is late. --}}
            <div id="folioViewerLoading" class="pointer-events-none absolute inset-0 flex select-none items-center justify-center text-sm text-white/80">Loading…</div>
        </div>
    </div>

    <script>
    (function () {
        var items = @json($viewerItems);
        var viewer = document.getElementById('folioViewer');
        var body = document.getElementById('folioViewerBody');
        var loading = document.getElementById('folioViewerLoading');
        var current = -1;
        var lastFocus = null;

        // Fit each A4 thumbnail to its card's width.
        function fitThumbs() {
            document.querySelectorAll('iframe[data-folio-thumb]').forEach(function (frame) {
                var box = frame.parentElement;
                if (box) frame.style.transform = 'scale(' + (box.clientWidth / 794) + ')';
            });
        }
        fitThumbs();
        window.addEventListener('resize', fitThumbs);

        function show(index) {
            if (!items.length) return;
            current = (index + items.length) % items.length;
            var item = items[current];

            document.getElementById('folioViewerTitle').textContent = item.title;
            document.getElementById('folioViewerBadge').textContent = item.badge;
            document.getElementById('folioViewerCount').textContent = (current + 1) + ' of ' + items.length;

            Array.prototype.slice.call(body.children).forEach(function (child) {
                if (child !== loading) body.removeChild(child);
            });
            loading.hidden = false;
            loading.textContent = 'Loading…';

            var el;
            if (item.type === 'image') {
                el = document.createElement('img');
                el.src = item.url;
                el.alt = item.title;
                el.draggable = false;
                el.className = 'mx-auto max-h-full max-w-full rounded bg-white object-contain shadow-lg';
                el.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            } else {
                el = document.createElement('iframe');
                el.src = item.type === 'pdf' ? item.url + '#toolbar=0&navpanes=0' : item.url;
                el.title = item.title;
                el.className = 'mx-auto block h-full w-full max-w-4xl rounded bg-white shadow-lg';
                el.style.minHeight = '70vh';
            }
            var done = function () {
                clearInterval(poll);
                clearTimeout(fallback);
                // A document the reader has already stepped past must not hide the
                // indicator for the one now loading.
                if (el.isConnected) loading.hidden = true;
            };
            el.addEventListener('load', done);
            el.addEventListener('error', function () { done(); loading.hidden = false; loading.textContent = 'This document could not be loaded.'; });
            body.appendChild(el);

            // `load` waits for every image on the page, and these print pages pull
            // some from outside (coat of arms, barcode service) — one slow host and
            // it never comes. The document is readable once its own HTML has been
            // parsed, which a same-origin frame can report directly; the timeout
            // covers anything that cannot.
            var poll = setInterval(function () {
                try {
                    var doc = el.contentDocument;
                    if (doc && doc.body && doc.body.childElementCount && doc.readyState !== 'loading') done();
                } catch (e) { done(); }
            }, 150);
            var fallback = setTimeout(done, 4000);
        }

        function open(index) {
            lastFocus = document.activeElement;
            viewer.hidden = false;
            document.body.style.overflow = 'hidden';
            show(index);
            viewer.querySelector('[data-folio-close]').focus();
        }

        function close() {
            viewer.hidden = true;
            document.body.style.overflow = '';
            Array.prototype.slice.call(body.children).forEach(function (child) {
                if (child !== loading) body.removeChild(child);
            });
            if (lastFocus) lastFocus.focus();
        }

        document.querySelectorAll('[data-folio-open]').forEach(function (card) {
            card.addEventListener('click', function () { open(parseInt(card.getAttribute('data-folio-open'), 10)); });
        });
        viewer.querySelectorAll('[data-folio-step]').forEach(function (btn) {
            btn.addEventListener('click', function () { show(current + parseInt(btn.getAttribute('data-folio-step'), 10)); });
        });
        viewer.querySelector('[data-folio-close]').addEventListener('click', close);
        viewer.addEventListener('click', function (e) { if (e.target === viewer || e.target === body) close(); });
        document.addEventListener('keydown', function (e) {
            if (viewer.hidden) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowLeft') show(current - 1);
            else if (e.key === 'ArrowRight') show(current + 1);
        });
    })();
    </script>
@endif
