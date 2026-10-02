@extends('layouts.app')

@section('page-title')
    {{ __('Deed of Purchase') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div class="max-w-5xl mx-auto space-y-4">

                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Deed of Purchase</h1>
                        <p class="text-gray-500 mt-1">
                            {{ $record->mlsFNo ?: ($record->kangisFileNo ?: ($record->NewKANGISFileno ?: $record->temp_fileno)) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('land-registration.capture.edit', $record->id) }}"
                            class="flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 transition-all">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                            Edit
                        </a>
                        <a href="{{ route('land-registration.capture.index') }}"
                            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-all">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Back to List
                        </a>
                    </div>
                </div>

                {{-- The particulars first: on a registry record they are what the
                     document is identified by. --}}
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Registration Particulars</h2>
                    @if($record->registration_number)
                        <div class="flex flex-wrap items-baseline gap-x-8 gap-y-3">
                            <div>
                                <div class="text-3xl font-bold text-gray-900">{{ $record->registration_number }}</div>
                                <div class="text-xs text-gray-500">serial / page / volume</div>
                            </div>
                            <div class="text-sm text-gray-600">
                                Registered
                                <span class="font-medium text-gray-900">
                                    {{ $record->reg_date ? \Carbon\Carbon::parse($record->reg_date)->format('d F Y') : '—' }}
                                </span>
                                in the {{ config('land_registration.authority.registry') }}
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            This capture has no registration number yet. It can be numbered from the
                            <a href="{{ route('land-registration.registration.index') }}" class="underline font-medium">register</a>.
                        </p>
                    @endif
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Parties</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">{{ config('land_registration.parties.first') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $record->party_1_name ?: '—' }}</dd>
                                <dd class="text-gray-600 text-xs">{{ $record->party_1_address }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ config('land_registration.parties.second') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $record->party_2_name ?: '—' }}</dd>
                                <dd class="text-gray-600 text-xs">{{ $record->party_2_address }}</dd>
                            </div>
                            @if(!empty($record->solicitor_name))
                                <div>
                                    <dt class="text-gray-500">Solicitor</dt>
                                    <dd class="font-medium text-gray-900">{{ $record->solicitor_name }}</dd>
                                    <dd class="text-gray-600 text-xs">{{ $record->solicitor_address }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h2 class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-4">Property</h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">Description</dt>
                                <dd class="font-medium text-gray-900">{{ $record->property_description ?: '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-gray-500">LGA</dt>
                                    <dd class="font-medium text-gray-900">{{ $record->lga ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">District</dt>
                                    <dd class="font-medium text-gray-900">{{ $record->district ?: '—' }}</dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-gray-500">Property ID</dt>
                                <dd class="font-medium text-gray-900">{{ $record->prop_id ?: '—' }}</dd>
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
                                @if(is_numeric($record->consideration_amount ?? null))
                                    &#8358;{{ number_format((float) $record->consideration_amount, 2) }}
                                @else
                                    {{ $record->consideration_amount ?: '—' }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Receipt No</dt>
                            <dd class="font-medium text-gray-900">{{ $record->receipt_no ?? '' ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection
