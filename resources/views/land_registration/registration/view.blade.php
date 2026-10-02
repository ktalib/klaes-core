@extends('layouts.app')
@section('page-title')
    {{ __('Registered Deed of Purchase') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div class="max-w-5xl mx-auto space-y-4">

                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Registered Deed of Purchase</h1>
                        <p class="text-gray-500 mt-1">{{ $registration->fileno }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('land-registration/print-rds/deed_reg_' . $registration->id) }}" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            RDS
                        </a>
                        <a href="{{ route('land-registration.cor.index', ['id' => 'deed_reg_' . $registration->id]) }}" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                            <i data-lucide="award" class="w-4 h-4"></i>
                            CoR
                        </a>
                        <a href="{{ route('land-registration.registration.index') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Register
                        </a>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Registration Particulars</h2>
                    <div class="flex flex-wrap items-center gap-x-10 gap-y-4">
                        <div>
                            <div class="text-3xl font-bold text-gray-900">{{ $registration->registration_number }}</div>
                            <div class="text-xs text-gray-500">serial / page / volume</div>
                        </div>
                        <div class="grid grid-cols-3 gap-6 text-sm">
                            <div>
                                <div class="text-gray-500 text-xs">No</div>
                                <div class="font-bold">{{ $registration->serial_no }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs">Page</div>
                                <div class="font-bold">{{ $registration->page_no }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs">Volume</div>
                                <div class="font-bold">{{ $registration->volume_no }}</div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            {{ $registration->deeds_date ? \Carbon\Carbon::parse($registration->deeds_date)->format('d F Y') : '—' }}
                            @if($registration->deeds_time)
                                <span class="text-gray-400">at {{ $registration->deeds_time }}</span>
                            @endif
                            <div class="text-xs text-gray-500">{{ config('land_registration.authority.registry') }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Parties</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">{{ config('land_registration.parties.first') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $registration->grantor ?: '—' }}</dd>
                                <dd class="text-gray-600 text-xs">{{ $registration->party_1_address }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ config('land_registration.parties.second') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $registration->grantee ?: '—' }}</dd>
                                <dd class="text-gray-600 text-xs">{{ $registration->party_2_address }}</dd>
                            </div>
                            @if(!empty($registration->solicitor_name))
                                <div>
                                    <dt class="text-gray-500">Solicitor</dt>
                                    <dd class="font-medium text-gray-900">{{ $registration->solicitor_name }}</dd>
                                    <dd class="text-gray-600 text-xs">{{ $registration->solicitor_address }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Property</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">Description</dt>
                                <dd class="font-medium text-gray-900">{{ $registration->property_description ?: '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-gray-500">LGA</dt>
                                    <dd class="font-medium text-gray-900">{{ $registration->lga ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">District</dt>
                                    <dd class="font-medium text-gray-900">{{ $registration->district ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Plot No</dt>
                                    <dd class="font-medium text-gray-900">{{ $registration->plot_number ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Size</dt>
                                    <dd class="font-medium text-gray-900">{{ $registration->size ?: '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Payment</h2>
                    <dl class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <dt class="text-gray-500">Amount</dt>
                            <dd class="font-medium text-gray-900">
                                {{-- Formatted only when it is actually a number:
                                     legacy rows can hold free text like "N1.5M". --}}
                                @if(is_numeric($registration->consideration_amount ?? null))
                                    &#8358;{{ number_format((float) $registration->consideration_amount, 2) }}
                                @else
                                    {{ $registration->consideration_amount ?: '—' }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Receipt No</dt>
                            <dd class="font-medium text-gray-900">{{ $registration->receipt_no ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection
