@extends('layouts.app')
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{ __('Customer Details') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Customer') }}</a></li>
    <li class="breadcrumb-item" aria-current="page">{{ __('Show') }}</li>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex" aria-label="Tabs">
                    <a href="#profile-1" class="tab-button active px-6 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-500 whitespace-nowrap flex items-center" id="profile-tab-1" data-bs-toggle="tab" role="tab" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        {{ __('Transaction History') }}
                    </a>
                </nav>
            </div>
            <div class="tab-content">
                <div class="tab-pane show active" id="profile-1" role="tabpanel" aria-labelledby="profile-tab-1">
                    <div class="p-4">
                        <div class="flex flex-col md:flex-row -mx-4">
                            <div class="w-full md:w-1/3 px-4 mb-6 md:mb-0">
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                    <div class="p-4 border-b border-gray-200">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <img class="h-12 w-12 rounded-full object-cover"
                                                    src="{{ $user->profile_url ?? $profile . '/avatar.png' }}"
                                                    alt="User image" />
                                            </div>
                                            <div class="ml-4">
                                                <h3 class="text-lg font-medium text-gray-900">{{ $user->name }}</h3>
                                                <p class="text-sm text-gray-500">Active User</p>
                                            </div>
                                            <div class="ml-auto">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                    {{ $user->type }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-2">
                                        <ul class="divide-y divide-gray-200">
                                            <li class="py-3 px-2">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 text-gray-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3 flex-1">
                                                        <p class="text-sm font-medium text-gray-900">{{ __('Email') }}</p>
                                                    </div>
                                                    <div class="ml-2">
                                                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="py-3 px-2">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 text-gray-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3 flex-1">
                                                        <p class="text-sm font-medium text-gray-900">{{ __('Phone') }}</p>
                                                    </div>
                                                    <div class="ml-2">
                                                        <p class="text-sm text-gray-500">{{ $user->phone_number }}</p>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="py-3 px-2">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 text-gray-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3 flex-1">
                                                        <p class="text-sm font-medium text-gray-900">{{ __('Department') }}</p>
                                                    </div>
                                                    <div class="ml-2">
                                                        <p class="text-sm text-gray-500">{{ $user->department ? $user->department->name : 'No Department' }}</p>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full md:w-2/3 px-4">
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                    <div class="p-4 border-b border-gray-200">
                                        <h3 class="text-lg font-medium text-gray-900">{{ __('Transactions History') }}</h3>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('User') }}</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Date') }}</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Amount') }}</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Payment Type') }}</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Payment Status') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach ($transactions as $transaction)
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            {{ !empty($transaction->users) ? $transaction->users->name : '' }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ dateFormat($transaction->created_at) }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ $settings['CURRENCY_SYMBOL'] . $transaction->amount }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ $transaction->payment_type }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            @if ($transaction->payment_status == 'Pending')
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                    {{ $transaction->payment_status }}
                                                                </span>
                                                            @elseif($transaction->payment_status == 'Success')
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                    {{ $transaction->payment_status }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                    {{ $transaction->payment_status }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
