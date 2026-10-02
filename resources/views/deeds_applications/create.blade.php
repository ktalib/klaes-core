@extends('layouts.app')

@section('page-title', 'New Deeds Application')

@push('css')
<link rel="stylesheet" href="{{ asset('css/deeds_applications.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Create Application',
        'PageDescription' => 'Start a new deeds application record.',
    ])

    <div class="max-w-3xl mx-auto p-6">
      <div class="card">
        <form method="POST" action="{{ route('deeds-applications.store') }}" class="space-y-6">
          @include('deeds_applications.partials.form')
          <div class="flex justify-end gap-3">
            <a href="{{ route('deeds-applications.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save Application</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="{{ asset('js/deeds_applications.js') }}" defer></script>
@endpush
