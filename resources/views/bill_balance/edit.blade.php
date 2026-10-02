@extends('layouts.app')

@section('page-title', 'Edit Bill Balance')

@push('css')
<link rel="stylesheet" href="{{ asset('css/bill_balance.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Edit Bill Balance',
        'PageDescription' => 'Update the selected bill balance details.',
    ])

    <div class="max-w-3xl mx-auto p-6">
      <div class="card space-y-6">
        <form method="POST" action="{{ route('bill-balance.update', $billBalance) }}" class="space-y-6">
          @method('PUT')
          @include('bill_balance.partials.form')
          <div class="flex justify-end gap-3">
            <a href="{{ route('bill-balance.index') }}" class="btn-secondary">Back</a>
            <button type="submit" class="btn-primary">Update</button>
          </div>
        </form>
        <form method="POST" action="{{ route('bill-balance.destroy', $billBalance) }}" onsubmit="return confirm('Delete this record?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn-danger">Delete Record</button>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="{{ asset('js/bill_balance.js') }}" defer></script>
@endpush
