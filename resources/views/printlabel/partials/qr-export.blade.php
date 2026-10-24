{{-- Export Generated QR Codes. Include with ['qrExportModule' => 'land'|'kangis'|'sltr'|'st']. --}}
@php
    $qrxModule = $qrExportModule ?? 'land';
    $qrxLabel = \App\Services\PrintLabel\QrCodeExportService::moduleConfig($qrxModule)['label'];
    $qrxId = 'qrExport_' . $qrxModule;
@endphp

<button
    type="button"
    id="{{ $qrxId }}_open"
    class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
>
    <i data-lucide="qr-code" class="h-4 w-4"></i>
    Export QR Codes
</button>

<div id="{{ $qrxId }}_modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4" role="dialog" aria-modal="true" aria-labelledby="{{ $qrxId }}_title">
    <form id="{{ $qrxId }}_form" method="GET" action="{{ route('printlabel-qr-export.download', ['module' => $qrxModule]) }}"
          class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-4">
            <h3 id="{{ $qrxId }}_title" class="text-lg font-semibold">Export Generated QR Codes ({{ $qrxLabel }})</h3>
            <button type="button" data-qrx-close class="text-gray-400 hover:text-gray-600" aria-label="Close">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="space-y-4 px-5 py-4 text-sm">
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-gray-700">From</span>
                    <input type="date" name="date_from" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2">
                </label>
                <label class="block">
                    <span class="text-gray-700">To</span>
                    <input type="date" name="date_to" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2">
                </label>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2">
                    <input type="radio" name="format" value="pdf" checked>
                    <span>PDF</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="format" value="csv">
                    <span>CSV</span>
                </label>
            </div>

            <div id="{{ $qrxId }}_count" class="rounded-md bg-gray-50 px-3 py-2 text-gray-700">Counting…</div>
            @if (session('error'))
                <div class="rounded-md bg-red-50 px-3 py-2 text-red-700">{{ session('error') }}</div>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t px-5 py-3">
            <button type="button" data-qrx-close class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
            <button type="submit" id="{{ $qrxId }}_submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <i data-lucide="download" class="h-4 w-4"></i>
                Export
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var id = @json($qrxId);
    var summaryUrl = @json(route('printlabel-qr-export.summary', ['module' => $qrxModule]));
    var reopen = @json((bool) session('error'));

    function init() {
        var openBtn = document.getElementById(id + '_open');
        var modal = document.getElementById(id + '_modal');
        var form = document.getElementById(id + '_form');
        var countBox = document.getElementById(id + '_count');
        var submitBtn = document.getElementById(id + '_submit');
        if (!openBtn || !modal || !form) return;

        var timer = null, seq = 0, pdfLimit = 0, matched = null;

        function format() {
            var checked = form.querySelector('input[name="format"]:checked');
            return checked ? checked.value : 'pdf';
        }

        function render() {
            if (matched === null) return;
            var tooMany = format() === 'pdf' && pdfLimit && matched > pdfLimit;
            var text = matched.toLocaleString() + ' file number' + (matched === 1 ? '' : 's') + ' in this range.';
            if (tooMany) text += ' The PDF holds at most ' + pdfLimit.toLocaleString() + ': narrow the range or choose CSV.';
            countBox.textContent = text;
            countBox.className = 'rounded-md px-3 py-2 ' + (tooMany || matched === 0 ? 'bg-yellow-50 text-yellow-800' : 'bg-gray-50 text-gray-700');
            submitBtn.disabled = tooMany || matched === 0;
            submitBtn.classList.toggle('opacity-50', submitBtn.disabled);
        }

        function refresh() {
            var mine = ++seq;
            var data = new URLSearchParams();
            ['date_from', 'date_to'].forEach(function (k) { data.set(k, form.elements[k].value); });
            countBox.textContent = 'Counting…';
            submitBtn.disabled = true;
            fetch(summaryUrl + '?' + data.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                .then(function (res) {
                    if (mine !== seq) return;
                    if (!res.ok || !res.body.success) {
                        countBox.textContent = res.body && res.body.errors ? Object.values(res.body.errors).flat().join(' ') : 'Could not count the file numbers.';
                        countBox.className = 'rounded-md bg-red-50 px-3 py-2 text-red-700';
                        matched = null;
                        return;
                    }
                    matched = res.body.count;
                    pdfLimit = res.body.pdf_limit;
                    render();
                })
                .catch(function () {
                    if (mine !== seq) return;
                    countBox.textContent = 'Could not count the file numbers.';
                    matched = null;
                });
        }

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            refresh();
        }

        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        openBtn.addEventListener('click', open);
        modal.querySelectorAll('[data-qrx-close]').forEach(function (b) { b.addEventListener('click', close); });
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

        form.addEventListener('change', function (e) {
            if (e.target.name === 'format') { render(); return; }
            clearTimeout(timer);
            timer = setTimeout(refresh, 300);
        });
        form.addEventListener('submit', function () {
            if (format() === 'pdf') {
                countBox.textContent = 'Preparing the PDF; the download starts by itself.';
                countBox.className = 'rounded-md bg-blue-50 px-3 py-2 text-blue-800';
            } else {
                setTimeout(close, 300);
            }
        });

        if (reopen) open();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
