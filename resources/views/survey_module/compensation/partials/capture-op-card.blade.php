{{--
    Capture OP on Survey (user, 2026-11-18): the same Capture Occupancy Permit (OP) card OSS Applications and the
    MLS File Number page use (instruments.partials.register_modal + instruments-capture.js), not a Survey card.
    Typing the OP serial lists the OPs on record to pick and backfill (window.opSerialPickLookup, as on the MLS
    page); a serial not on record is captured as a new OP.

    openSurveyOpCard({ onDone(serial) }): onDone runs after the capture's success dialog closes (Record Plot Showing).
    Without onDone the OP list reloads filtered to the serial.
--}}
@once
    @php
        // The lookups the shared modal reads, as BillBalanceController::addressLookups() gives them; passed to it
        // explicitly so a host page's own $districts / $lgas never reach it.
        $svOpDb = \Illuminate\Support\Facades\DB::connection('sqlsrv');
        $svOpLookups = [
            'states'      => $svOpDb->table('States')->orderBy('StateName')->get(),
            'lgas'        => $svOpDb->table('StatLGAs')->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
                ->where('States.StateName', 'Kano')->orderBy('LGAName')->select('StatLGAs.LGAName')->get(),
            'districts'   => $svOpDb->table('districts')->where('is_active', 1)->orderBy('name')->select('name')->get(),
            'streetNames' => \App\Models\StreetName::orderBy('name')->get(['id', 'name'])->toBase(),
            'record'      => null,
        ];
    @endphp
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    {{-- The Survey stylesheet's .flex { display:flex } outranks Tailwind's .hidden, so the shared modals
         (class="hidden ... flex") showed on page load (user, 2026-11-18). Hidden wins again. --}}
    <style>
        .flex.hidden, .survey-proto .flex.hidden { display: none; }
    </style>
    @include('instruments.partials.register_modal', $svOpLookups)
    @include('components.global-fileno-modal')
    <template id="template-occupancy-permit">@include('instruments.partials.types.occupancy-permit', ['record' => null])</template>
    <script>
        window.opSerialPickLookup = true;
        window.InstrumentCaptureConfig = Object.assign({}, window.InstrumentCaptureConfig || {}, {
            csrfToken: "{{ csrf_token() }}",
            urls: Object.assign({}, (window.InstrumentCaptureConfig || {}).urls || {}, {
                tpLookupSearch: "{{ route('instruments.tpLookups.search') }}",
                tpLookupStore: "{{ route('instruments.tpLookups.store') }}"
            })
        });
    </script>
    <script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
    <script src="{{ asset('js/instruments-capture.js') }}?v={{ @filemtime(public_path('js/instruments-capture.js')) }}"></script>
    <script>
    (function () {
        var LIST = @json(route('survey-module.compensation.op', ['tab' => 'existing']));
        var host = null;

        window.openSurveyOpCard = function (opts) {
            if (typeof window.openRegistrationDialog !== 'function') {
                Swal.fire({ icon: 'warning', title: 'Capture OP unavailable', text: 'The Capture OP card did not load. Refresh the page and try again.' });
                return;
            }
            host = opts || null;
            window.ossOpSubmitLabel = 'Capture Existing OP';
            window.openRegistrationDialog('occupancy-permit');
        };
        window.closeSurveyOpCard = function () {
            if (typeof window.closeRegistrationDialog === 'function') window.closeRegistrationDialog();
        };

        window.addEventListener('instrument-captured', function (e) {
            var d = e.detail || {};
            if (d.type && d.type !== 'occupancy-permit') return;
            var serial = d.opSerial || (d.data && (d.data.op_serial_number || (d.data.record && d.data.record.op_serial_number))) || '';
            if (host && host.onDone) { var done = host.onDone; host = null; done(String(serial)); return; }
            window.location = LIST + (serial ? '&q=' + encodeURIComponent(serial) : '');
        });
    })();
    </script>
@endonce
