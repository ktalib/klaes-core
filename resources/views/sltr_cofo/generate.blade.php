@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Generate SLTR Certificate') }}
@endsection

{{--
 | SLTR CofO — Front Page form.
 |
 | The certificate is the Deeds instrument capture. What Deeds registered (holder, plot,
 | location, land use, registration particulars) is shown read-only; the form fills only the
 | fields capture leaves empty — holder address, term, commencement date — and stamps the
 | issue date (cofo_date) the first time it is saved.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', [
        'PageTitle' => 'SLTR Certificate of Occupancy',
        'PageDescription' => 'Front Page',
    ])
    @include('instrument_workflow.partials.styles')
    @include('sltr_cofo.partials.styles')
    @include('sltr_cofo.partials.theme')

    @php $generated = $row->stages_done['front_page']; @endphp

    <div class="iw-page cw-page sltr-theme space-y-5" style="max-width:1100px">
        <div class="sltr-band"></div>

        @include('instrument_workflow.partials.flash')

        @if ($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
                <i data-lucide="alert-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="iw-crumbs" style="margin-bottom:0">
            <span>SLTR</span><span>/</span><span>Certificate</span><span>/</span>
            <a href="{{ route('sltr-cofo.front-page') }}">CofO Workflow</a><span>/</span>
            <span class="text-gray-900">{{ $generated ? __('Edit front page') : __('Generate front page') }}</span>
        </div>

        <form method="POST" action="{{ route('sltr-cofo.save') }}" class="iw-card">
            @csrf
            <input type="hidden" name="file_no" value="{{ $values['file_no'] }}">

            <div class="iw-card-head">
                <div>
                    <div class="iw-card-title">
                        <span class="iw-icon-tile blue"><i data-lucide="file-text" class="h-5 w-5"></i></span>
                        <span class="iw-mono">{{ $values['file_no'] }}</span>
                    </div>
                    <div class="iw-card-sub">
                        {{ __('Registered as') }}
                        <strong class="iw-mono">{{ $values['registration_number'] ?: '—' }}</strong>
                        · {{ __('Volume') }} {{ $values['volume_no'] ?: '—' }}
                        · {{ __('Page') }} {{ $values['page_no'] ?: '—' }}
                        @if ($values['reg_date'])
                            · {{ \Carbon\Carbon::parse($values['reg_date'])->format('d M Y') }}
                        @endif
                    </div>
                </div>
                @include('sltr_cofo.partials.status_pill', ['status' => $row->fsm_status])
            </div>

            <div class="iw-card-body space-y-5">
                {{-- What Deeds registered. Read-only: corrections go through instrument capture. --}}
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('From Deeds instrument capture') }}</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                        <div class="cw-rail-row"><span class="text-gray-500">{{ __('Holder') }}</span><strong>{{ $values['holder_name'] ?: '—' }}</strong></div>
                        <div class="cw-rail-row"><span class="text-gray-500">{{ __('Land use') }}</span><strong>{{ $values['land_use'] ?: '—' }}</strong></div>
                        <div class="cw-rail-row"><span class="text-gray-500">{{ __('Plot No.') }}</span><strong>{{ $values['plot_no'] ?: '—' }}</strong></div>
                        <div class="cw-rail-row"><span class="text-gray-500">{{ __('District / LGA') }}</span><strong>{{ collect([$values['property_district'], $values['property_lga']])->filter()->implode(', ') ?: '—' }}</strong></div>
                    </div>
                    <p class="cw-sub" style="margin-top:6px">{{ __('To correct any of these, amend the instrument capture in Deeds.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Holder address') }} *</label>
                        <textarea name="holder_address" rows="2" class="iw-textarea" required>{{ old('holder_address', $values['holder_address']) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Commencement date') }} *</label>
                        <input type="date" name="start_date" class="iw-input" required value="{{ old('start_date', $values['start_date']) }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Term (years)') }} *</label>
                        <input type="number" name="total_term" min="1" max="99" class="iw-input" required value="{{ old('total_term', $values['total_term']) }}">
                    </div>
                </div>

                <div class="iw-alert info">
                    @if ($values['cofo_date'])
                        {{ __('Issued') }} {{ \Carbon\Carbon::parse($values['cofo_date'])->format('d M Y') }}
                        — {{ __('saving again keeps this date.') }}
                    @else
                        {{ __('The issue date is set to today when the front page is generated, and kept after that.') }}
                    @endif
                </div>
            </div>

            <div class="iw-card-body" style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid #f3f4f6">
                <a href="{{ route('sltr-cofo.front-page') }}" class="iw-btn iw-btn-light">{{ __('Cancel') }}</a>
                <button type="submit" class="iw-btn iw-btn-primary">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    {{ $generated ? __('Save front page') : __('Generate front page') }}
                </button>
            </div>
        </form>
    </div>

    @include($footerPartial ?? 'admin.footer')
</div>
@endsection
