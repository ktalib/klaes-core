{{-- One row of the applications table, for DeedsApplicationController@data.
     Each cell's content follows a cell marker; the controller splits on it and
     hands DataTables one string per column. The <td> classes live on the column
     definitions in index.blade.php, so the column order here must match them. --}}
@if($isSupperAdmin)
<!--cell-->
  @if(!($application->is_st_assignment ?? false))
    <input type="checkbox" class="master-row-checkbox rounded text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer"
           data-id="{{ $application->id }}" data-file-no="{{ $application->file_number }}" onchange="updateBulkDeleteButtonState()">
  @else
    <span class="text-slate-300 font-bold">—</span>
  @endif
@endif
<!--cell-->
{{ $sn }}
<!--cell-->
<button type="button" class="view-properties-btn flex items-center gap-2 hover:text-blue-600 transition outline-none"
        data-main-file="{{ $application->file_number }}"
        data-main-desc="{{ $application->property_description }}"
        data-main-applicant="{{ $application->applicant_name }}"
        data-additional="{{ json_encode($application->additional_properties ?? []) }}">
    <span>{{ $application->file_number }}</span>
    @php
        $additionalCount = is_array($application->additional_properties) ? count($application->additional_properties) : 0;
    @endphp
    @if($additionalCount > 0)
        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 text-[10px] font-bold border border-amber-200"
              title="{{ collect($application->additional_properties)->pluck('file_number')->filter()->implode(', ') }}">
            +{{ $additionalCount }}
        </span>
    @endif
</button>
<!--cell-->
{{-- Painted by public/js/deeds-pipeline.js, one batched lookup per page drawn. --}}
<span data-pipeline-file="{{ $application->file_number }}"></span>
<!--cell-->
@php
$badgeClass = match($application->consent_type) {
'Assignment' => 'bg-blue-50 text-blue-700 border-blue-100',
'Gift' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
'Mortgage' => 'bg-amber-50 text-amber-700 border-amber-100',
'ST Assignment' => 'bg-purple-50 text-purple-700 border-purple-100',
'Sublease' => 'bg-pink-50 text-pink-700 border-pink-100',
'Devolution' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
default => 'bg-slate-50 text-slate-700 border-slate-100'
};
@endphp
<span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border whitespace-nowrap inline-block {{ $badgeClass }}">
  {{ $application->consent_type }}
</span>
<!--cell-->
@php
    $titles = collect([$application->applicant_name]);
    if (is_array($application->additional_properties)) {
        foreach ($application->additional_properties as $prop) {
            if (!empty($prop['applicant_name'])) $titles->push($prop['applicant_name']);
            elseif (!empty($prop['applicant'])) $titles->push($prop['applicant']);
        }
    }
    $titles = $titles->filter()->unique()->values();

    $party1Text = 'N/A';
    if ($titles->count() === 1) {
        $party1Text = $titles->first();
    } elseif ($titles->count() === 2) {
        $party1Text = $titles->first() . ' & ' . $titles->last();
    } elseif ($titles->count() > 2) {
        $last = $titles->pop();
        $party1Text = $titles->implode(', ') . ', & ' . $last;
    }
@endphp
{{ $party1Text }}
<!--cell-->
{{ $application->party_name }}
<!--cell-->
@php
$extraApplicants = collect($application->additional_applicants ?? [])->pluck('name')->filter()->values();
$extraParties = collect($application->additional_parties ?? [])->pluck('name')->filter()->values();
$otherPartyNames = $extraApplicants->merge($extraParties)->values();
@endphp
{{ $otherPartyNames->get(0, 'N/A') }}
<!--cell-->
<div class="flex items-center gap-2">
  <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
     <i data-lucide="user-check" class="h-3.5 w-3.5"></i>
  </div>
  <span class="text-slate-600 text-sm font-medium uppercase">{{ $application->created_by ?:
    ($application->user ? $application->user->first_name . ' ' . $application->user->last_name :
    'System') }}</span>
</div>
<!--cell-->
{{ ($application->application_submitted_date ?? $application->application_date) ?
\Carbon\Carbon::parse($application->application_submitted_date ??
$application->application_date)->format('M d, Y') : 'N/A' }}
<!--cell-->
{{ $application->created_at->format('h:i A') }}
<!--cell-->
{{ $application->created_at->format('M d, Y') }}
<!--cell-->
<div class="flex items-center gap-2">
  <span class="w-2 h-2 rounded-full {{ $application->print_count > 0 ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
  <span class="font-bold text-slate-700">{{ $application->print_count }}</span>
</div>
<!--cell-->
<div class="flex items-center justify-center">
  <button type="button"
    class="deeds-action-trigger p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition duration-200"
    data-app="{{ json_encode($application->withoutRelations()) }}"
    data-is-st="{{ (isset($application->is_st_assignment) && $application->is_st_assignment) ? 'true' : 'false' }}">
    <i data-lucide="more-horizontal" class="h-5 w-5"></i>
  </button>
</div>
