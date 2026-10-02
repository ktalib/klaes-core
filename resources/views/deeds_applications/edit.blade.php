@extends('layouts.app')

@section('page-title', 'Edit Deeds Application')

@push('css')
<link rel="stylesheet" href="{{ asset('css/deeds_applications.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Edit Application',
        'PageDescription' => 'Update the selected deeds application.',
    ])

    <div class="max-w-3xl mx-auto p-6">
      <div class="card space-y-6">
        <form method="POST" action="{{ route('deeds-applications.update', $application) }}" class="space-y-6">
          @method('PUT')
          @include('deeds_applications.partials.form')
          <div class="flex justify-end gap-3">
            <a href="{{ route('deeds-applications.index') }}" class="btn-secondary">Back</a>
            <button type="submit" class="btn-primary">Update Application</button>
          </div>
        </form>
        <form method="POST" action="{{ route('deeds-applications.destroy', $application) }}" onsubmit="return confirm('Delete this application?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn-danger">Delete Application</button>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="{{ asset('js/deeds_applications.js') }}" defer></script>
@endpush
