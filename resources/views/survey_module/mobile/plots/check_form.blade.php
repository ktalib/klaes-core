@php
    use App\Models\Survey\SurveyPlotCheck as Check;
    use App\Http\Controllers\Survey\MobilePlotController as PC;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => 'Ground check'])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ $op ? route('survey-module.mobile.plots.op', $op['key']) : route('survey-module.mobile.plots.index') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>Ground check</h1><p>Is the plot on the ground as recorded?</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    <div class="scf-note wait" id="queuedNote" hidden role="status"></div>

    <form class="card card-pad" id="checkForm" method="POST" action="{{ route('survey-module.mobile.plots.checks.store') }}" novalidate>
        @csrf
        <div class="field"><label>Which plot?</label>
            <div class="seg">
                <label><input type="radio" name="target_type" value="{{ Check::TARGET_OP }}" @checked($op || ! request('layout'))> An OP's plot</label>
                <label><input type="radio" name="target_type" value="{{ Check::TARGET_LAYOUT }}" @checked(! $op && request('layout'))> A layout plot</label>
            </div></div>

        {{-- An OP: found with the search, its plot details copied on save. --}}
        <div id="opBox" style="margin-top:10px;">
            <input type="hidden" name="op" value="{{ $op['key'] ?? '' }}">
            @if ($op)
                {{-- Plot No., District, LGA, Location --}}
                <dl class="kv">
                    <dt>OP</dt><dd>{{ $op['serial'] ?: '—' }}</dd>
                    <dt>Allottee</dt><dd>{{ $op['allottee'] ?: '—' }}</dd>
                    <dt>Plot No.</dt><dd>{{ $op['plot_no'] ?: '—' }}</dd>
                    <dt>District</dt><dd>{{ $op['district'] ?: '—' }}</dd>
                    <dt>LGA</dt><dd>{{ $op['lga'] ?: '—' }}</dd>
                    <dt>Location</dt><dd>{{ $op['location'] ?: '—' }}</dd>
                    @if ($op['layout'])<dt>Layout</dt><dd>{{ $op['layout'] }}</dd>@endif
                </dl>
                <a class="btn btn-ghost btn-sm" style="margin-top:8px;" href="{{ route('survey-module.mobile.plots.ops', ['for' => 'check']) }}"><i class="fas fa-rotate"></i> Another OP</a>
            @else
                <a class="btn btn-primary" style="width:100%;justify-content:center;" href="{{ route('survey-module.mobile.plots.ops', ['for' => 'check']) }}"><i class="fas fa-magnifying-glass"></i> Find the OP</a>
            @endif
        </div>

        {{-- A layout plot: the layout plan, and the plot as read on site. --}}
        <div id="layoutBox" style="margin-top:10px;" hidden>
            <div class="field"><label for="lp">Layout plan</label>
                <select class="input" id="lp" name="layout">
                    <option value="">Choose…</option>
                    @foreach ($layouts as $l)
                        <option value="{{ $l->id }}">{{ $l->lpkn_number }}{{ $l->layout_name ? ' · ' . $l->layout_name : '' }}</option>
                    @endforeach
                    <option value="{{ PC::OTHER }}">Other</option>
                </select></div>
            <div class="field" id="lpOtherBox" hidden style="margin-top:8px;"><label for="lpo">Layout number</label>
                <input class="input" id="lpo" name="layout_other" maxlength="100" autocapitalize="characters" disabled></div>
            <div class="field" style="margin-top:10px;"><label for="pn">Plot No.</label>
                <input class="input" id="pn" name="plot_no" maxlength="100" autocapitalize="characters"></div>
            <div class="grid2" style="margin-top:10px;">
                <div class="field"><label for="ds">District</label><input class="input" id="ds" name="district" maxlength="150"></div>
                <div class="field"><label for="lg">LGA</label><input class="input" id="lg" name="lga" maxlength="150"></div>
            </div>
        </div>

        <div class="field" style="margin-top:14px;"><label for="sv">Surveyor</label>
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
                <input class="input" id="on" type="date" name="checked_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
            <div class="field"><label for="at">Time</label>
                <input class="input" id="at" type="time" name="checked_at" value="{{ now()->format('H:i') }}"></div>
        </div>

        <div class="field" style="margin-top:10px;"><label for="bf">Beacon numbers found on the plot</label>
            <input class="input" id="bf" name="beacons_found" maxlength="1000" autocapitalize="characters" placeholder="e.g. KN4010, KN4011, KN4012">
            <div class="sub">Separate with commas. Each is checked against the Beacon Register when the check is saved.</div></div>

        <div class="field" style="margin-top:12px;"><label>Result</label>
            <div class="seg">
                <label><input type="radio" name="result" value="{{ Check::CONFIRMED }}" required> Confirmed</label>
                <label><input type="radio" name="result" value="{{ Check::DISCREPANCY }}"> Discrepancy</label>
            </div></div>
        <div id="discBox" hidden style="margin-top:10px;">
            <div class="field"><label for="dc">What is wrong?</label>
                <select class="input" id="dc" name="discrepancy" disabled>
                    <option value="">Choose…</option>
                    @foreach (Check::DISCREPANCIES as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                </select></div>
            <div class="field" id="dcOtherBox" hidden style="margin-top:8px;"><label for="dco">Describe it</label>
                <input class="input" id="dco" name="discrepancy_other" maxlength="255" disabled></div>
        </div>
        <div class="field" style="margin-top:10px;"><label for="nt">Notes</label>
            <textarea class="input" id="nt" name="notes" rows="2" maxlength="2000"></textarea></div>

        @include('survey_module.mobile.plots._gps')
        @include('survey_module.mobile.plots._photos')
        <div class="err-box" hidden></div>
        <button type="submit" class="btn btn-success" style="margin-top:12px;width:100%;"><i class="fas fa-check"></i> Save ground check</button>
    </form>
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'checks'])
@include('survey_module.mobile.plots._form_js')
<script>
(function () {
    var form = document.getElementById('checkForm'), OTHER = @json(PC::OTHER), DISC = @json(Check::DISCREPANCY), DISC_OTHER = @json(Check::OTHER);
    // Show a box and enable its fields only when it applies, so hidden values never post.
    var box = function (id, on) { var el = document.getElementById(id); el.hidden = !on; el.querySelectorAll('input,select').forEach(function (i) { if (i.type !== 'hidden') i.disabled = !on; }); };
    var target = function () { var r = form.querySelector('[name=target_type]:checked'); return r ? r.value : ''; };
    var result = function () { var r = form.querySelector('[name=result]:checked'); return r ? r.value : ''; };
    function sync() {
        box('opBox', target() === 'op');
        box('layoutBox', target() === 'layout');
        if (target() === 'layout') box('lpOtherBox', form.layout.value === OTHER);
        box('svOtherBox', form.surveyor.value === OTHER);
        box('discBox', result() === DISC);
        if (result() === DISC) box('dcOtherBox', form.discrepancy.value === DISC_OTHER);
    }
    form.addEventListener('change', sync); sync();

    pcSave(form, function (f) {
        return 'Ground check · ' + (target() === 'op' ? @json($op ? 'OP ' . ($op['serial'] ?: '') : 'OP') : (f.plot_no.value ? 'plot ' + f.plot_no.value.toUpperCase() : 'layout plot'));
    }, function (f) {
        if (target() === 'op' && !f.op.value) return 'Find the OP whose plot you are checking.';
        if (target() === 'layout') {
            if (!f.layout.value) return 'Choose the layout plan.';
            if (f.layout.value === OTHER && !f.layout_other.value.trim()) return 'Type the layout number.';
            if (!f.plot_no.value.trim()) return 'Give the plot number.';
        }
        if (!f.surveyor.value) return 'Choose the surveyor.';
        if (f.surveyor.value === OTHER && !f.surveyor_other.value.trim()) return "Type the surveyor's name.";
        if (!result()) return 'Say whether the plot is confirmed or there is a discrepancy.';
        if (result() === DISC && !f.discrepancy.value) return 'Say what the discrepancy is.';
        if (result() === DISC && f.discrepancy.value === DISC_OTHER && !f.discrepancy_other.value.trim()) return 'Describe the discrepancy.';
        return '';
    }, function (res) {
        if (res.queued) {
            var n = document.getElementById('queuedNote');
            n.textContent = 'No signal: the ground check is saved on this phone and will sync when you are back online.';
            n.hidden = false;
            form.reset(); pcPhotos.clear(); sync();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        location.href = res.res.redirect + (res.photosWaiting ? '?photos=waiting' : '');
    });
})();
</script>
</body>
</html>
