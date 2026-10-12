@extends('layouts.app')

@section('content')
@php $job = $jacket->job; $case = $jacket->case; @endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Field Jacket ' . $jacket->field_jacket_no, 'PageDescription' => $case->case_ref . ' · survey job ' . $job->job_number])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @canDo('Cad - Records', 'print')
        <a href="{{ route('cadastral-dept.field-jackets.print', $jacket->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Print jacket cover
        </a>
        @if ($job->its_number)
          <a href="{{ route('cadastral-dept.field-jackets.its.print', $jacket->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
            <i data-lucide="file-text" class="w-4 h-4"></i> Print I to S
          </a>
        @endif
      @endcanDo
      <span class="ml-auto">@include('cadastral_dept.field_jackets._status', ['cdStatus' => $job->status])</span>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">File</h3>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
          <dt class="text-gray-500">File No.</dt><dd class="col-span-2 font-mono">{{ $jacket->file_number }}</dd>
          <dt class="text-gray-500">File Title</dt><dd class="col-span-2">{{ $jacket->file_title ?: '—' }}</dd>
          <dt class="text-gray-500">Plot No.</dt><dd class="col-span-2">{{ $jacket->plot_no ?: '—' }}</dd>
          <dt class="text-gray-500">District</dt><dd class="col-span-2">{{ $jacket->district ?: '—' }}</dd>
          <dt class="text-gray-500">LGA</dt><dd class="col-span-2">{{ $jacket->lga ?: '—' }}</dd>
          <dt class="text-gray-500">Location</dt><dd class="col-span-2">{{ $jacket->location ?: '—' }}</dd>
        </dl>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Jacket, job and I to S</h3>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
          <dt class="text-gray-500">Field Jacket</dt><dd class="col-span-2 font-mono">{{ $jacket->field_jacket_no }}</dd>
          <dt class="text-gray-500">Survey Job</dt><dd class="col-span-2 font-mono">{{ $job->job_number }}</dd>
          <dt class="text-gray-500">Surveyor</dt><dd class="col-span-2">{{ $job->surveyor_name ?: '—' }}@if ($job->firm_name) <span class="text-gray-500">({{ $job->firm_name }})</span>@endif</dd>
          <dt class="text-gray-500">I to S</dt><dd class="col-span-2 font-mono">{{ $job->its_number ?: 'Not issued' }}@if ($job->its_issued_at) <span class="font-sans text-gray-500">· {{ $job->its_issued_at->format('d M Y') }}</span>@endif</dd>
          <dt class="text-gray-500">Scope</dt><dd class="col-span-2">{{ $job->job_scope ?: '—' }}</dd>
          <dt class="text-gray-500">Commissioned</dt><dd class="col-span-2">{{ $jacket->commissioned_at->format('d M Y H:i') }}, {{ optional($jacket->commissioner)->name }}</dd>
          @if ($jacket->remarks)<dt class="text-gray-500">Remarks</dt><dd class="col-span-2">{{ $jacket->remarks }}</dd>@endif
        </dl>
      </div>
    </div>

    <div>
      <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Surveyor assignments</h3>
      <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Surveyor</th><th class="px-3 py-2">Firm</th><th class="px-3 py-2">From</th><th class="px-3 py-2">To</th><th class="px-3 py-2">Reason</th><th class="px-3 py-2">By</th></tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($jacket->assignments as $as)
              <tr class="{{ $as->ended_at ? '' : 'bg-green-50' }}">
                <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                <td class="px-3 py-2">{{ $as->surveyor_name }}</td>
                <td class="px-3 py-2">{{ $as->firm_name ?: '—' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $as->assigned_at->format('d M Y H:i') }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $as->ended_at ? $as->ended_at->format('d M Y H:i') : 'Current' }}</td>
                <td class="px-3 py-2">{{ $as->reason ?: ($loop->first ? 'Commissioned' : '—') }}</td>
                <td class="px-3 py-2">{{ optional($as->assigner)->name }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if ($canReassign)
      @canDo('Cad - Records', 'edit')
        <form method="POST" action="{{ route('cadastral-dept.field-jackets.reassign.update', $jacket->id) }}"
              class="rounded-xl border border-gray-200 bg-white p-5 space-y-3"
              data-confirm-title="Reassign the surveyor?" data-confirm="{{ $jacket->field_jacket_no }} and job {{ $job->job_number }} go to the chosen surveyor.">
          @csrf
          <div>
            <div class="text-base font-bold text-gray-800">Reassign surveyor</div>
            <p class="text-xs text-gray-500">The surveyor is locked to the jacket. Reassigning keeps the history above@if ($job->its_number) and re-addresses I to S {{ $job->its_number }} to the new surveyor@endif.</p>
          </div>
          @include('cadastral_dept.field_jackets._surveyor_select', ['cdExcept' => $jacket->cadastral_surveyor_id])
          <textarea name="reason" rows="2" required maxlength="1000" placeholder="Reason for the reassignment (required)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
          <div class="flex justify-end"><button class="px-4 py-2 rounded-lg bg-yellow-600 text-white text-sm font-semibold">Reassign</button></div>
        </form>
      @endcanDo
    @endif
  </div>
  @include('admin.footer')
</div>
@endsection
