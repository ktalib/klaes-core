@extends('layouts.app')

@section('page-title', 'Application Details')

@push('css')
<link rel="stylesheet" href="{{ asset('css/deeds_applications.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Deeds Application Details',
        'PageDescription' => 'Structured summary of the selected record.',
    ])

    <div class="max-w-3xl mx-auto p-6 space-y-6">
      <div class="card space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="label">Application Number</p>
            <p class="value">{{ $application->application_number }}</p>
          </div>
          <div>
            <p class="label">File Number</p>
            <p class="value">{{ $application->file_number ?? '—' }}</p>
          </div>
          <div>
            <p class="label">Subject</p>
            <p class="value">{{ $application->subject ?? '—' }}</p>
          </div>
          <div>
            <p class="label">Status</p>
            <p class="value">{{ DeedsApplication::STATUS_OPTIONS[$application->status] ?? $application->status }}</p>
          </div>
          <div>
            <p class="label">Submitted By</p>
            <p class="value">{{ $application->submitted_by ?? '—' }}</p>
          </div>
          <div>
            <p class="label">Submitted At</p>
            <p class="value">{{ optional($application->submitted_at)->format('d M Y h:i A') ?? '—' }}</p>
          </div>
        </div>
        <div>
          <p class="label">Notes</p>
          <p class="value">{{ $application->notes ?? '—' }}</p>
        </div>
      </div>
      <div class="flex gap-3">
        <a href="{{ route('deeds-applications.index') }}" class="btn-secondary">Back</a>
        <a href="{{ route('deeds-applications.edit', $application) }}" class="btn-primary">Edit</a>
      </div>
    </div>
  </div>
@endsection
