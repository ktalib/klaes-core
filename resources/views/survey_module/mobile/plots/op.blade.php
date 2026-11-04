@php
    use App\Models\Survey\SurveyOpShowing as Showing;
    use App\Http\Controllers\Survey\MobilePlotController as PC;
    $title = 'OP ' . ($op['serial'] ?: '—');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => $title])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ route('survey-module.mobile.plots.ops') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>{{ $title }}</h1><p>{{ $op['allottee'] ?: 'No allottee recorded' }}</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    @if (session('success'))<div class="scf-note ok">{{ session('success') }}</div>@endif
    <div class="scf-note ok" id="savedNote" hidden role="status"></div>

    <div class="card card-pad" style="margin-bottom:12px;">
        <strong>Occupancy Permit</strong>
        {{-- Plot No., District, LGA, Location --}}
        <dl class="kv">
            <dt>Allottee</dt><dd>{{ $op['allottee'] ?: '—' }}</dd>
            <dt>Plot No.</dt><dd>{{ $op['plot_no'] ?: '—' }}</dd>
            <dt>District</dt><dd>{{ $op['district'] ?: '—' }}</dd>
            <dt>LGA</dt><dd>{{ $op['lga'] ?: '—' }}</dd>
            <dt>Location</dt><dd>{{ $op['location'] ?: '—' }}</dd>
            @if ($op['layout'])<dt>Layout</dt><dd>{{ $op['layout'] }}</dd>@endif
            @if ($op['plot_size'])<dt>Plot size</dt><dd>{{ $op['plot_size'] }}</dd>@endif
            @if ($op['type'])<dt>OP type</dt><dd>{{ $op['type'] }}</dd>@endif
            @if ($op['file_number'])<dt>File no.</dt><dd>{{ $op['file_number'] }}</dd>@endif
            @if ($op['reg_no'])<dt>Reg. no.</dt><dd>{{ $op['reg_no'] }}</dd>@endif
            @if ($op['phone'])<dt>Phone</dt><dd><a href="tel:{{ $op['phone'] }}">{{ $op['phone'] }}</a></dd>@endif
        </dl>
        <a class="btn btn-ghost btn-sm" style="margin-top:10px;" href="{{ route('survey-module.mobile.plots.checks.create', ['op' => $op['key']]) }}"><i class="fas fa-map-location-dot"></i> Ground-check this plot</a>
    </div>

    <form class="card card-pad" id="showForm" method="POST" action="{{ route('survey-module.mobile.plots.showings.store', $op['key']) }}" novalidate style="margin-bottom:12px;">
        @csrf
        <strong id="nextLabel">Record the {{ Showing::ordinal($next) }} Showing</strong>
        <div class="field" style="margin-top:10px;"><label for="sv">Surveyor</label>
            <select class="input" id="sv" name="surveyor" required>
                <option value="">Choose…</option>
                @foreach ($surveyors as $s)
                    <option value="staff:{{ $s->id }}" @selected($me === $s->id)>{{ $s->full_name }}{{ $s->rank ? ' (' . $s->rank . ')' : '' }}</option>
                @endforeach
                <option value="{{ PC::SURVEYOR_OTHER }}">Other</option>
            </select></div>
        <div class="field" id="svOtherBox" hidden style="margin-top:8px;"><label for="svo">Surveyor's name</label>
            <input class="input" id="svo" name="surveyor_other" maxlength="255" disabled></div>
        <div class="grid2" style="margin-top:10px;">
            <div class="field"><label for="on">Date</label>
                <input class="input" id="on" type="date" name="shown_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
            <div class="field"><label for="at">Time</label>
                <input class="input" id="at" type="time" name="shown_at" value="{{ now()->format('H:i') }}" required></div>
        </div>
        <div class="field" style="margin-top:10px;"><label>Was the allottee there?</label>
            <div class="seg">
                <label><input type="radio" name="allottee_present" value="1" required> Yes</label>
                <label><input type="radio" name="allottee_present" value="0"> No</label>
            </div></div>
        {{-- Who asked for the showing (user, 2026-11-02): ticked takes the allottee's name and address. --}}
        <div class="field" style="margin-top:10px;">
            <input type="hidden" name="requester_is_allottee" value="0">
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="requester_is_allottee" value="1" id="rqSame" style="width:auto;"> Requester is the allottee</label></div>
        <div class="field" style="margin-top:8px;"><label for="rqn">Requester's name</label>
            <input class="input" id="rqn" name="requester_name" maxlength="255" required></div>
        {{-- Requester's address as an address builder (user, 2026-11-02), saved as the full address. Street and
             district suggest names from the office lists when there is signal; offline they are typed. --}}
        @php
            $rqLgas = \App\Http\Controllers\Survey\LookupController::options('lgas');
            $rqStates = \App\Http\Controllers\Survey\LookupController::options('states');
        @endphp
        <div class="field" style="margin-top:10px;"><label>Requester's address</label>
            <div class="sub" id="rqHint"></div></div>
        <div class="grid2" style="margin-top:6px;">
            <div class="field"><label for="rqh">House No.</label><input class="input" id="rqh" name="req_house" maxlength="100"></div>
            <div class="field"><label for="rqp">Plot No.</label><input class="input" id="rqp" name="req_plot" maxlength="100"></div>
        </div>
        <div class="field" style="margin-top:8px;"><label for="rqs">Street</label>
            <input class="input" id="rqs" name="req_street" maxlength="255" list="rqStreets" autocomplete="off" data-lookup="{{ route('survey-module.lookup.streets') }}">
            <datalist id="rqStreets"></datalist></div>
        <div class="field" style="margin-top:8px;"><label for="rqd">District</label>
            <input class="input" id="rqd" name="req_district" maxlength="255" list="rqDistricts" autocomplete="off" required data-lookup="{{ route('survey-module.lookup.districts') }}">
            <datalist id="rqDistricts"></datalist></div>
        <div class="grid2" style="margin-top:8px;">
            <div class="field"><label for="rql">LGA</label>
                <select class="input" id="rql" name="req_lga" required>
                    <option value="">Choose…</option>
                    @foreach ($rqLgas as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
                </select></div>
            <div class="field"><label for="rqst">State</label>
                <select class="input" id="rqst" name="req_state" required>
                    @foreach ($rqStates as $st)<option value="{{ $st }}" @selected($st === 'Kano')>{{ $st }}</option>@endforeach
                </select></div>
        </div>
        <div class="field" style="margin-top:8px;"><label for="rqFull">Full address</label>
            <input class="input" id="rqFull" readonly tabindex="-1" placeholder="Street, Plot, District, LGA, State" style="background:#f3f4f6;font-weight:600;"></div>
        <div class="field" id="reasonBox" style="margin-top:10px;" @if ($next === 1) hidden @endif><label for="rs">Reason for this showing</label>
            <select class="input" id="rs" name="reason" @disabled($next === 1)>
                <option value="">Choose…</option>
                @foreach (Showing::REASONS as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
            </select>
            <div class="sub">Required from the 2nd showing.</div></div>
        <div class="field" style="margin-top:10px;"><label for="nt">Notes</label>
            <textarea class="input" id="nt" name="notes" rows="2" maxlength="2000"></textarea></div>
        @include('survey_module.mobile.plots._gps')
        @include('survey_module.mobile.plots._photos')
        <div class="err-box" hidden></div>
        <button type="submit" class="btn btn-success" style="margin-top:12px;width:100%;"><i class="fas fa-check"></i> Save showing</button>
    </form>

    <div class="card card-pad" style="margin-bottom:12px;">
        <strong>Showing History</strong>
        <div id="history">
            @forelse ($showings as $s)
                <div class="item">
                    <div><strong>{{ $s->label }}</strong>{{ $s->reason ? ' · ' . $s->reason : '' }}
                        <div class="sub">{{ $s->shown_on?->format('d M Y') }} {{ $s->shown_at }} · {{ $s->surveyor_name }}</div>
                        @if ($s->requester_name)<div class="sub">Requested by {{ $s->requester_name }}{{ $s->requester_is_allottee ? ' (the allottee)' : '' }}</div>@endif
                        <div class="sub">Allottee {{ $s->allottee_present === null ? 'not recorded' : ($s->allottee_present ? 'present' : 'absent') }}{{ $s->latitude !== null ? ' · ' . number_format($s->latitude, 5) . ', ' . number_format($s->longitude, 5) : '' }}</div>
                        @if ($s->notes)<div class="sub">{{ $s->notes }}</div>@endif
                        @if ($s->photos->isNotEmpty())
                            <div class="thumbs">@foreach ($s->photos as $ph)<a href="{{ route('survey-module.mobile.plots.showings.photos.show', $ph) }}"><img src="{{ route('survey-module.mobile.plots.showings.photos.show', $ph) }}" alt="{{ $s->label }} photo" loading="lazy"></a>@endforeach</div>
                        @endif
                        {{-- Step 2: the field response to this showing (user, 2026-11-03). --}}
                        <a class="btn btn-ghost btn-sm" style="margin-top:6px;" href="{{ route('survey-module.mobile.plots.checks.create', ['op' => $op['key'], 'showing' => $s->id]) }}"><i class="fas fa-map-location-dot"></i> Ground-check this showing</a>
                    </div>
                </div>
            @empty
                <div class="sub" id="noHistory" style="padding:8px 0;">Not shown yet.</div>
            @endforelse
        </div>
    </div>

    @if ($checks->isNotEmpty())
        <div class="card card-pad">
            <strong>Ground checks</strong>
            @foreach ($checks as $c)
                <a class="item" href="{{ route('survey-module.mobile.plots.checks.show', $c) }}">
                    <div><strong>{{ $c->check_ref }}</strong><div class="sub">{{ $c->checked_on?->format('d M Y') }} · {{ $c->surveyor_name }}</div></div>
                    <div><span class="st {{ $c->result === 'Confirmed' ? 'ok' : 'bad' }}">{{ $c->result }}</span></div>
                </a>
            @endforeach
        </div>
    @endif
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'showings'])
@include('survey_module.mobile.plots._form_js')
<script>
(function () {
    var form = document.getElementById('showForm'), next = {{ $next }};
    var sv = form.surveyor, other = document.getElementById('svOtherBox');
    var toggleOther = function () { var on = sv.value === @json(PC::SURVEYOR_OTHER); other.hidden = !on; form.surveyor_other.disabled = !on; };
    sv.addEventListener('change', toggleOther); toggleOther();

    // Requester is the allottee: the OP's allottee name (locked) and address on record.
    var OP_ALLOTTEE = @json($op['allottee']), OP_ADDRESS = @json($op['address'] ?? null);
    var rqSame = document.getElementById('rqSame'), rqn = form.requester_name, rqHint = document.getElementById('rqHint'), rqFilled = {};
    function syncRequester() {
        if (rqFilled.n !== undefined && rqn.value === rqFilled.n) rqn.value = '';
        rqFilled = {}; rqn.readOnly = false; rqHint.textContent = '';
        if (!rqSame.checked) return;
        if (OP_ALLOTTEE) { rqn.value = rqFilled.n = OP_ALLOTTEE; rqn.readOnly = true; } else rqHint.textContent = 'No allottee name on record: type it. ';
        if (OP_ADDRESS) rqHint.textContent += "Allottee's address on record: " + OP_ADDRESS + '.';
    }
    rqSame.addEventListener('change', syncRequester);

    // The address parts and the full address, as the office composes it: Street, Plot (or House), District, LGA, State.
    var rqParts = ['req_street', 'req_plot', 'req_house', 'req_district', 'req_lga', 'req_state'];
    function rqFull() {
        var v = function (n) { return (form[n].value || '').trim(); };
        document.getElementById('rqFull').value = [v('req_street'), v('req_plot') || v('req_house'), v('req_district'), v('req_lga'), v('req_state')].filter(Boolean).join(', ');
    }
    rqParts.forEach(function (n) { form[n].addEventListener('input', rqFull); form[n].addEventListener('change', rqFull); });
    rqFull();
    // Street / district suggestions from the office lists while online; typing works offline.
    [['rqs', 'rqStreets'], ['rqd', 'rqDistricts']].forEach(function (pair) {
        var input = document.getElementById(pair[0]), list = document.getElementById(pair[1]), timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2 || !navigator.onLine) return;
            timer = setTimeout(function () {
                fetch(input.dataset.lookup + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (d) {
                        list.textContent = '';
                        (d.results || []).forEach(function (x) { if (x.id === 'Other') return; var o = document.createElement('option'); o.value = x.text; list.appendChild(o); });
                    }).catch(function () {});
            }, 250);
        });
    });

    pcSave(form, function () { return @json($title) + ' · showing'; }, function (f) {
        if (!f.surveyor.value) return 'Choose the surveyor who showed the plot.';
        if (f.surveyor.value === @json(PC::SURVEYOR_OTHER) && !f.surveyor_other.value.trim()) return "Type the surveyor's name.";
        if (!f.shown_on.value || !f.shown_at.value) return 'Give the date and time.';
        if (!f.querySelector('[name=allottee_present]:checked')) return 'Say whether the allottee was there.';
        if (!f.requester_name.value.trim()) return rqSame.checked ? 'This OP has no allottee name on record: untick "Requester is the allottee" and type the name.' : "Give the requester's name.";
        if (!f.req_district.value.trim()) return "Give the requester's district.";
        if (!f.req_lga.value) return "Choose the requester's LGA.";
        if (!f.req_state.value) return "Choose the requester's state.";
        if (next > 1 && !f.reason.value) return 'This OP has been shown before: say why (Change of Ownership or Verification).';
        return '';
    }, function (res) {
        var note = document.getElementById('savedNote');
        note.className = 'scf-note ' + (res.queued || res.photosWaiting ? 'wait' : 'ok');
        note.textContent = res.queued ? 'No signal: the showing is saved on this phone and will sync when you are back online.'
            : (res.res && res.res.label ? res.res.label : 'Showing') + ' recorded.' + (res.photosWaiting ? ' Some photos are still waiting to upload.' : '');
        note.hidden = false;
        var none = document.getElementById('noHistory'); if (none) none.remove();
        var row = document.createElement('div');
        row.className = 'item';
        row.innerHTML = '<div><strong></strong><div class="sub"></div></div>';
        row.querySelector('strong').textContent = res.queued ? 'Showing (waiting to sync)' : (res.res.label || 'Showing');
        row.querySelector('.sub').textContent = form.shown_on.value + ' ' + form.shown_at.value + ' · ' + (sv.value === @json(PC::SURVEYOR_OTHER) ? form.surveyor_other.value : sv.options[sv.selectedIndex].text);
        document.getElementById('history').prepend(row);
        // The next one needs a reason.
        next++;
        document.getElementById('nextLabel').textContent = 'Record another showing';
        document.getElementById('reasonBox').hidden = false; form.reason.disabled = false;
        form.notes.value = ''; form.reason.value = ''; form.latitude.value = '';
        rqSame.checked = false; rqFilled = {}; rqn.value = ''; syncRequester();
        ['req_street', 'req_plot', 'req_house', 'req_district', 'req_lga'].forEach(function (n) { form[n].value = ''; });
        form.req_state.value = 'Kano'; rqFull();
        form.querySelectorAll('[name=allottee_present]').forEach(function (r) { r.checked = false; r.closest('label').classList.remove('on'); });
        pcPhotos.clear();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
</body>
</html>
