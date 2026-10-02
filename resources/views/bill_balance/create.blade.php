@extends('layouts.app')

@section('page-title', 'Create Bill Balance')

@push('css')
<link rel="stylesheet" href="{{ asset('css/bill_balance.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'New Bill Balance',
        'PageDescription' => 'Capture details for a new bill balance reference.',
    ])

    <div class="max-w-3xl mx-auto p-6">
      <div class="card">
        <form method="POST" action="{{ route('bill-balance.store') }}" class="space-y-6">
          @include('bill_balance.partials.form')
          <div class="flex justify-end gap-3">
            <a href="{{ route('bill-balance.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save Bill</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="{{ asset('js/bill_balance.js') }}" defer></script>
@endpush
