{{--
    A survey job's Survey Type and Beacons, and its requests to the Surveyor General (CR 03112026, with
    the Survey I to S). On the Job Book's job page and the Computation File page. $job = CadastralSurveyJob.
--}}
@php
    $cdTypes = \App\Services\Survey\SurveySettings::surveyTypes();
    $cdTypeNames = $cdTypes->pluck('name');
    $cdPick = old('survey_type', $job->survey_type === null ? '' : ($cdTypeNames->contains($job->survey_type) ? $job->survey_type : 'Other'));
    $cdOther = old('survey_type_other', $cdPick === 'Other' ? $job->survey_type : '');
    $cdBeacons = app(\App\Services\Survey\ItsBeacons::class)->forCadastralJob($job->id);
    $cdRequests = \App\Models\Survey\SurveyBeaconRequest::where('cadastral_survey_job_id', $job->id)->orderByDesc('id')->get();
    $cdLocked = in_array($job->status, ['Accepted', 'Cancelled'], true);
    $cdInput = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm';
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4" style="margin-top:16px;" id="job-survey-details">
    <div>
        <div class="text-base font-bold text-gray-800">Survey Type &amp; Beacons</div>
        <p class="text-xs text-gray-500">For the Instruction to Surveyor. Each beacon must be in the Beacon Register, not issued to another surveyor, and not on another job.</p>
    </div>
    <form method="POST" action="{{ route('cadastral-module.survey-jobs.survey-details', $job) }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Survey Type</label>
                <select name="survey_type" class="{{ $cdInput }}" @disabled($cdLocked)
                        onchange="var o=document.getElementById('cdTypeOther'),on=this.value==='Other';o.style.display=on?'':'none';o.disabled=!on;o.required=on;">
                    <option value="">Select survey type…</option>
                    @foreach ($cdTypes as $st)<option value="{{ $st->name }}" @selected($cdPick === $st->name)>{{ $st->name }}</option>@endforeach
                    <option value="Other" @selected($cdPick === 'Other')>Other (specify)</option>
                </select>
                <input type="text" name="survey_type_other" id="cdTypeOther" maxlength="100" value="{{ $cdOther }}" placeholder="Specify the survey type *" aria-label="Specify the survey type"
                       class="{{ $cdInput }} mt-2" style="{{ $cdPick === 'Other' ? '' : 'display:none;' }}"
                       @if ($cdPick === 'Other' && ! $cdLocked) required @else disabled @endif />
                @error('survey_type_other')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Beacon Numbers</label>
                <textarea name="beacon_numbers" rows="2" maxlength="8000" placeholder="A0001B to A0004B, A1404B" class="{{ $cdInput }}" @disabled($cdLocked)>{{ old('beacon_numbers', implode(', ', $cdBeacons)) }}</textarea>
                @error('beacon_numbers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        @unless ($cdLocked)
            <div class="flex justify-end"><button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Save</button></div>
        @endunless
    </form>

    {{-- Request for Beacons, to the SG (user, 2026-11-05). --}}
    <div class="border-t border-gray-200 pt-4 space-y-3">
        <div class="text-sm font-bold text-gray-800">Request Beacons from the Surveyor General</div>
        @if ($job->surveyor_name)
            @unless ($cdLocked)
                <form method="POST" action="{{ route('cadastral-module.survey-jobs.request-beacons', $job) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Number of Beacons <span class="text-red-600">*</span></label>
                        <input type="number" name="quantity" min="1" max="{{ \App\Services\Survey\BeaconRequests::MAX_QUANTITY }}" value="{{ old('quantity', 4) }}" required class="{{ $cdInput }}" />
                        @error('quantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Remarks</label>
                        <input type="text" name="remarks" maxlength="2000" value="{{ old('remarks') }}" class="{{ $cdInput }}" />
                    </div>
                    <button class="px-4 py-2 rounded-lg bg-yellow-600 text-white text-sm font-semibold">Send to SG</button>
                </form>
            @endunless
        @else
            <p class="text-xs text-gray-500">Assign a surveyor to the job first.</p>
        @endif

        @if ($cdRequests->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="px-2 py-1">S/N</th><th class="px-2 py-1">Request No.</th><th class="px-2 py-1">Date</th><th class="px-2 py-1">Beacons</th><th class="px-2 py-1">Status</th><th class="px-2 py-1">Issued Numbers</th><th class="px-2 py-1"></th></tr></thead>
                    <tbody>
                        @foreach ($cdRequests as $q)
                            <tr class="border-t border-gray-100">
                                <td class="px-2 py-1">{{ $loop->iteration }}</td>
                                <td class="px-2 py-1 font-mono">{{ $q->request_no }}</td>
                                <td class="px-2 py-1">{{ optional($q->created_at)->format('d/m/Y') }}</td>
                                <td class="px-2 py-1">{{ $q->quantity }}</td>
                                <td class="px-2 py-1">{{ $q->status === 'Rejected' ? 'Not issued' : $q->status }}@if ($q->review_note)<div class="text-xs text-gray-500">{{ $q->review_note }}</div>@endif</td>
                                <td class="px-2 py-1">{{ $q->issued_numbers ?: '—' }}</td>
                                <td class="px-2 py-1 text-right">
                                    @if ($q->status === 'Issued')
                                        <form method="POST" action="{{ route('cadastral-module.survey-jobs.accept-beacons', [$job, $q]) }}">
                                            @csrf
                                            <button class="px-3 py-1 rounded-lg bg-green-700 text-white text-xs font-semibold">Accept</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
