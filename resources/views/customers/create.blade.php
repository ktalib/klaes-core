@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Create Customer') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-wide text-gray-500">Customers</p>
            <h1 class="text-2xl font-semibold text-gray-900">Create Customer</h1>
            <p class="mt-1 text-sm text-gray-600">Capture customer details and optionally create/link an entity.</p>
        </div>
        <a href="{{ route('customers.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            ← Back to list
        </a>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow">
        <form action="{{ route('customers.store') }}" method="POST" class="space-y-8">
            @csrf
            @php($customerModel = new \App\Models\Customer())
            @include('customers.partials.form', ['entities' => $entities, 'customer' => $customerModel])

            <div class="flex items-center justify-end space-x-3">
                <a href="{{ route('customers.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">Save Customer</button>
            </div>
        </form>
    </div>

    @include('admin.footer')
</div>
@endsection
