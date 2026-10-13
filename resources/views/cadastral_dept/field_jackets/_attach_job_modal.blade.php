{{--
    Attach Survey Job (user, 2026-10-12): jobs are registered first on Survey Jobs, with no file
    or surveyor; here, at Assign to Surveyor (I to S), a job and a surveyor are attached to the
    file. That commissions the field jacket on it and ticks the task. The job keeps its own
    survey location.

    $waitingJobs  FieldJackets::waitingJobs()
    $surveyors    FieldJackets::surveyors()
    Open it with any element carrying:
      data-attach-job  data-url  data-ref  data-file  data-plot  data-district  data-lga
--}}
<div id="cd-aj-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" role="dialog" aria-modal="true">
  <form id="cd-aj-form" method="POST" action="" class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto"
        data-confirm-title="Attach the job and surveyor?" data-confirm="The job and the surveyor are attached to this file, and the field jacket is commissioned on the job.">
    @csrf
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="text-xs font-bold uppercase text-gray-500"><span id="cd-aj-ref"></span> · <span id="cd-aj-file" class="font-mono"></span></div>
        <div class="text-lg font-bold text-gray-800">Attach Survey Job and Surveyor</div>
        <p class="text-xs text-gray-500 mt-1">Pick the survey job and the surveyor for this file. The field jacket is commissioned on the job; then issue the I to S.</p>
      </div>
      <button type="button" data-aj-close class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Close">&times;</button>
    </div>
    <dl class="grid grid-cols-3 gap-2 text-sm rounded-lg bg-gray-50 p-3">
      <div><dt class="text-xs text-gray-500">Plot No.</dt><dd id="cd-aj-plot">—</dd></div>
      <div><dt class="text-xs text-gray-500">District</dt><dd id="cd-aj-district">—</dd></div>
      <div><dt class="text-xs text-gray-500">LGA</dt><dd id="cd-aj-lga">—</dd></div>
    </dl>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Survey job <span class="text-red-600">*</span></label>
      @if ($waitingJobs->isEmpty())
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-900">
          No survey job is waiting for a file.
          <a href="{{ route('cadastral-module.survey-jobs.index') }}" class="underline font-semibold">Register a survey job</a> first, then come back.
        </div>
      @else
        <select name="cadastral_survey_job_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">Choose the job</option>
          @foreach ($waitingJobs as $cdJob)
            @php
              $cdJobText = implode(' · ', array_filter([
                  $cdJob->job_number,
                  \Illuminate\Support\Str::limit((string) $cdJob->property_location, 40),
                  \Illuminate\Support\Str::limit((string) $cdJob->job_scope, 40),
              ]));
            @endphp
            <option value="{{ $cdJob->id }}">{{ $cdJobText }}</option>
          @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Only jobs not yet attached to a file are listed, with their survey location.</p>
      @endif
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Surveyor <span class="text-red-600">*</span></label>
      @include('cadastral_dept.field_jackets._surveyor_select')
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Remarks (optional)</label>
      <input type="text" name="remarks" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div class="flex justify-end gap-2">
      <button type="button" data-aj-close class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
      <button @disabled($waitingJobs->isEmpty() || $surveyors->isEmpty()) class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold disabled:opacity-50">Attach</button>
    </div>
  </form>
</div>
<script>
  (function () {
    var modal = document.getElementById('cd-aj-modal');
    var $ = function (id) { return document.getElementById(id); };
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-attach-job]');
      if (el) {
        e.preventDefault();
        $('cd-aj-form').setAttribute('action', el.getAttribute('data-url'));
        $('cd-aj-ref').textContent = el.getAttribute('data-ref') || '';
        $('cd-aj-file').textContent = el.getAttribute('data-file') || '';
        $('cd-aj-plot').textContent = el.getAttribute('data-plot') || '—';
        $('cd-aj-district').textContent = el.getAttribute('data-district') || '—';
        $('cd-aj-lga').textContent = el.getAttribute('data-lga') || '—';
        modal.querySelectorAll('select').forEach(function (sel) { sel.value = ''; });
        modal.classList.remove('hidden');
        return;
      }
      if (e.target.closest('[data-aj-close]') || e.target === modal) modal.classList.add('hidden');
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.classList.add('hidden'); });
  })();
</script>
