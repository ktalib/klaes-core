@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4" x-data="backfillRunner({{ (int) $remaining }})" x-init="start()">
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold text-slate-800">File Indexing &mdash; Coordinate Backfill</h1>
            <span class="text-sm px-3 py-1 rounded-full"
                  :class="running ? 'bg-emerald-100 text-emerald-700' : (finished ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700')"
                  x-text="running ? 'Running…' : (finished ? 'Finished' : (error ? 'Stopped (error)' : 'Stopped'))"></span>
        </div>

        <p class="text-sm text-slate-500 mb-6">
            Geocodes <code>file_indexings</code> rows missing latitude/longitude via OpenStreetMap, in batches of 10,
            automatically continuing until nothing is left. Rows that already have coordinates are never touched.
        </p>

        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            OpenStreetMap allows about one lookup per second and a single row can need up to three, so expect
            roughly <strong>4 seconds per row</strong>. Each request here starts with an empty address cache;
            the CLI keeps one warm for the whole run and is much faster for a large backfill:
            <code>php artisan fileindexing:backfill-coordinates --limit=5000</code>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-slate-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-slate-800" x-text="remaining.toLocaleString()"></div>
                <div class="text-xs text-slate-500 mt-1">Remaining</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-emerald-600" x-text="okTotal().toLocaleString()"></div>
                <div class="text-xs text-slate-500 mt-1">Geocoded (OK)</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-slate-400" x-text="totalProcessed.toLocaleString()"></div>
                <div class="text-xs text-slate-500 mt-1">Processed this session</div>
            </div>
        </div>

        <label class="mb-4 flex items-start gap-2 text-xs text-slate-600">
            <input type="checkbox" x-model="skipLgaOnly" :disabled="running"
                   class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-indigo-600">
            <span>
                Skip LGA-only matches. Without this, a row that resolves no further than its LGA is written
                with that LGA's town centre &mdash; the same point for every file in the LGA.
            </span>
        </label>

        <div class="flex gap-3 mb-6">
            <button type="button" @click="start()" :disabled="running || finished"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                Start
            </button>
            <button type="button" @click="stop()" :disabled="!running"
                    class="px-4 py-2 rounded-md bg-slate-200 text-slate-700 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                Stop
            </button>
            <button type="button" @click="resetCursor()" :disabled="running"
                    class="px-4 py-2 rounded-md border border-slate-300 bg-white text-slate-600 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Forget where the last run stopped and sweep from the first row again">
                Restart from beginning
            </button>
        </div>

        {{-- The cursor survives in localStorage between visits, so a run left over from
             the old Google-based backfill would silently resume past everything before
             it. Show where it stands and let it be cleared. --}}
        <p class="-mt-3 mb-6 text-xs text-slate-400">
            <template x-if="afterId !== null">
                <span>Resuming after row id <strong x-text="afterId"></strong>. Rows before it are not revisited.</span>
            </template>
            <template x-if="afterId === null">
                <span>Starting from the first row.</span>
            </template>
        </p>

        <template x-if="error">
            <div class="mb-6 p-3 rounded-md bg-red-50 text-red-700 text-sm" x-text="error"></div>
        </template>

        <div>
            <h2 class="text-sm font-semibold text-slate-700 mb-2">Batch log</h2>
            <div class="border border-slate-200 rounded-md max-h-80 overflow-y-auto divide-y divide-slate-100" x-ref="log">
                <template x-for="(entry, i) in log" :key="i">
                    <div class="px-3 py-2 text-xs font-mono text-slate-600" x-text="entry"></div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
<script>
function backfillRunner(initialRemaining) {
    const cursorKey = 'fileindexing_backfill_after_id';
    const storedCursor = parseInt(localStorage.getItem(cursorKey), 10);

    return {
        remaining: initialRemaining,
        totals: {},
        totalProcessed: 0,
        running: false,
        finished: initialRemaining <= 0,
        error: null,
        log: [],
        stopRequested: false,
        skipLgaOnly: false,
        afterId: Number.isFinite(storedCursor) ? storedCursor : null,

        // Outcomes are labelled by precision — "OK (street)", "OK (district)",
        // "OK (lga)" — so the card sums every OK tier rather than one fixed key.
        okTotal() {
            return Object.entries(this.totals)
                .filter(([status]) => status.startsWith('OK'))
                .reduce((sum, [, n]) => sum + n, 0);
        },

        start() {
            if (this.running || this.finished) return;
            this.running = true;
            this.stopRequested = false;
            this.error = null;
            this.loop();
        },

        stop() {
            this.stopRequested = true;
        },

        resetCursor() {
            if (this.running) return;
            this.afterId = null;
            localStorage.removeItem(cursorKey);
            this.finished = this.remaining <= 0;
            this.appendLog('Cursor cleared — the next run sweeps from the first row.');
        },

        appendLog(text) {
            this.log.push(new Date().toLocaleTimeString() + '  ' + text);
            this.$nextTick(() => {
                if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight;
            });
        },

        async loop() {
            while (!this.stopRequested) {
                try {
                    const res = await fetch('{{ route('fileindexing.backfill-coordinates.run') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            limit: 10,
                            after_id: this.afterId,
                            skip_lga_only: this.skipLgaOnly,
                        }),
                    });

                    if (!res.ok) {
                        const body = await res.json().catch(() => ({}));
                        this.error = body.error || ('Request failed (HTTP ' + res.status + ')');
                        this.running = false;
                        return;
                    }

                    const data = await res.json();

                    this.remaining = data.remaining;
                    this.totalProcessed += data.processed;
                    if (data.last_id !== null && data.last_id !== undefined) {
                        this.afterId = data.last_id;
                        localStorage.setItem(cursorKey, String(data.last_id));
                    }
                    for (const [status, n] of Object.entries(data.counts || {})) {
                        this.totals[status] = (this.totals[status] || 0) + n;
                    }

                    const summary = Object.entries(data.counts || {}).map(([s, n]) => s + '=' + n).join(', ') || 'no rows';
                    this.appendLog('Processed ' + data.processed + ' (' + summary + '), written ' + data.written + ', remaining ' + data.remaining);

                    if (data.processed === 0 || data.remaining <= 0) {
                        this.finished = true;
                        this.running = false;
                        return;
                    }
                } catch (e) {
                    this.error = 'Network error: ' + e.message;
                    this.running = false;
                    return;
                }
            }
            this.running = false;
        },
    };
}
</script>
@endsection
