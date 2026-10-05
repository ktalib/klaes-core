@extends('layouts.app')

@section('page-title', 'Bill Balance')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/bill_balance.css') }}">
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Bill Balance Records',
        'PageDescription' => 'Track, generate, and manage bill balance references.',
    ])
    @include('exports.consolidated_report_action', ['reportKey' => 'bill-balance'])

    <div class="max-w-7xl mx-auto p-6 space-y-6">
      @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
          <p class="font-semibold mb-1">Please fix the highlighted issues:</p>
          <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul> 
        </div>
      @endif

      {{-- Header --}}
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-2">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Bill Balance Records</h1>
          <p class="text-sm text-slate-500">Track, generate, and manage bill balance references.</p>
        </div>
        <button type="button" class="px-5 py-2.5 bg-white text-indigo-600 font-semibold rounded-lg border-2 border-indigo-600 hover:bg-indigo-50 transition shadow-sm text-sm open-bill-balance-modal flex items-center gap-2" data-mode="create">
          <i data-lucide="plus" class="h-4 w-4"></i>
          <span>Generate Bill</span>
        </button>
      </div>

      {{-- Stats Cards --}}
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        {{-- Generated Today Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <div class="flex items-start gap-4">
            <div class="p-3 bg-blue-50 rounded-xl border border-blue-100">
              <i data-lucide="calendar" class="h-6 w-6 text-blue-600"></i>
            </div>
            <div class="flex-1">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Generated Today</p>
              <p class="text-3xl font-bold text-slate-900">{{ number_format($stats['generated_today']) }}</p>
              <p class="text-xs text-slate-400 mt-1 uppercase">New Bills</p>
            </div>
          </div>
        </div>

        {{-- Paid Bills Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <div class="flex items-start gap-4">
            <div class="p-3 bg-green-50 rounded-xl border border-green-100">
              <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
            </div>
            <div class="flex-1">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Paid Bills</p>
              <p class="text-3xl font-bold text-slate-900">{{ number_format($stats['paid_count']) }}</p>
              <p class="text-xs text-slate-400 mt-1 uppercase">Fully Settled</p>
            </div>
          </div>
        </div>

        {{-- Total Bill Calculated Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <div class="flex items-start gap-3">
            <div class="p-2.5 bg-orange-50 rounded-xl border border-orange-100 shrink-0">
              <i data-lucide="calculator" class="h-5 w-5 text-orange-500"></i>
            </div>
            <div class="flex-1">
              <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Bill Calculated</p>
              <p class="text-xl font-bold text-slate-900 whitespace-nowrap">₦{{ number_format($stats['total_amount'], 2) }}</p>
            </div>
          </div>
        </div>

        {{-- Total Bill Collected Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <div class="flex items-start gap-3">
            <div class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-100 shrink-0">
              <i data-lucide="banknote" class="h-5 w-5 text-emerald-600"></i>
            </div>
            <div class="flex-1">
              <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Bill Collected</p>
              <p class="text-xl font-bold text-slate-900 whitespace-nowrap">₦0.00</p>
            </div>
          </div>
        </div>

        {{-- Total Records Card --}}
        <div class="bg-gradient-to-br from-indigo-600 to-blue-600 rounded-2xl shadow-sm p-5 text-white">
          <div class="flex items-start gap-4">
            <div class="p-3 bg-white/20 rounded-xl">
              <i data-lucide="database" class="h-6 w-6 text-white"></i>
            </div>
            <div class="flex-1">
              <p class="text-xs font-semibold text-indigo-100 uppercase tracking-wider mb-1">Total</p>
              <p class="text-3xl font-bold text-white">{{ number_format($records->total()) }}</p>
              <div class="flex items-center justify-between mt-1">
                <p class="text-xs text-indigo-200 uppercase">All Bill Records</p>
                <span class="px-2 py-0.5 bg-white/20 rounded text-xs font-medium">DBBL</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Filters --}}
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 hidden">
        <div class="flex items-center gap-2 mb-4">
          <i data-lucide="filter" class="h-4 w-4 text-slate-600"></i>
          <h3 class="font-semibold text-slate-900">Filters & Search</h3>
        </div>
        
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label class="form-label">Search</label>
            <div class="relative">
              <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" 
                class="form-input pl-10" placeholder="Reference, file number, name...">
              <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            </div>
          </div>
          
          <div>
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input">
          </div>
          
          <div>
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input">
          </div>
          
          <div>
            <label class="form-label">Payment Status</label>
            <select name="status" class="form-input">
              <option value="">All Status</option>
              <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
              <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
          </div>
          
          <div class="md:col-span-2 lg:col-span-4 flex gap-3">
            <button type="submit" class="btn-primary">
              <i data-lucide="search" class="h-4 w-4"></i>
              Apply Filters
            </button>
            <a href="{{ route('bill-balance.index') }}" class="btn-secondary">
              <i data-lucide="x" class="h-4 w-4"></i>
              Clear All
            </a>
          </div>
        </form>
      </div>

      {{-- Data Table --}}
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        {{-- Table Header Controls --}}
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <label class="text-sm font-medium text-slate-700">Show:</label>
              <form method="GET" class="inline-block">
                @foreach(request()->except('per_page') as $key => $value)
                  <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <select name="per_page" onchange="this.form.submit()" class="form-input-sm">
                  <option value="10" {{ ($filters['per_page'] ?? 25) == 10 ? 'selected' : '' }}>10</option>
                  <option value="25" {{ ($filters['per_page'] ?? 25) == 25 ? 'selected' : '' }}>25</option>
                  <option value="50" {{ ($filters['per_page'] ?? 25) == 50 ? 'selected' : '' }}>50</option>
                  <option value="100" {{ ($filters['per_page'] ?? 25) == 100 ? 'selected' : '' }}>100</option>
                </select>
              </form>
              <span class="text-sm text-slate-500">entries</span>
            </div>
          </div>
          
          <div class="flex flex-wrap items-center gap-3">
            {{-- Search. BillBalanceController::index() has always matched reference, file
                 number, applicant name, station, district and the billing receipt, but the
                 "Filters & Search" card above carries `hidden`, so there was no way to
                 reach it from the page. This is that search, in the table header where a
                 table search belongs.

                 The other filters ride along as hidden inputs, so searching keeps the page
                 size, the sort and any date/status filter instead of silently resetting
                 them. `page` is dropped on purpose: results change, so page 4 of the old
                 result set is meaningless. --}}
            <form method="GET" class="flex items-center gap-2">
              @foreach(request()->except(['search', 'page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
              @endforeach
              <div class="relative">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                  class="bb-search-input" placeholder="Reference, file number, name...">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
              </div>
              <button type="submit" class="btn-primary bb-search-btn" title="Search">
                <i data-lucide="search" class="h-4 w-4"></i>
              </button>
              @if (!empty($filters['search']))
                <a href="{{ route('bill-balance.index', request()->except(['search', 'page'])) }}"
                   class="btn-secondary bb-search-btn" title="Clear search">
                  <i data-lucide="x" class="h-4 w-4"></i>
                </a>
              @endif
            </form>

            <span class="text-sm text-slate-500">
              Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} results
            </span>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm bill-balance-table">
            <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wide">
              <tr>
                <th class="px-4 py-3 text-left whitespace-nowrap">S/N</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Bill Reference</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">File Number</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Applicant Name</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Applicant Address</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">House No</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Plot No</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Street Name</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">District</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">LGA</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Location / Station</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Rent Per Annum (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Registration Fees (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Survey Fees (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Preparation Fees (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Compensation Fees (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Development Charges (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Initial Total (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Amount Deposited (₦)</th>
                <th class="px-4 py-3 text-right whitespace-nowrap">Balance Due (₦)</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Balance Due To</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Date of Issue</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Date Created</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @forelse($records as $index => $record)
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3 text-left whitespace-nowrap tabular-nums">{{ ($records->firstItem() ?? 0) + $index }}</td>
                  @php
                    $registrationFee = (float) ($record->billing_site_plan_fee ?? 0);
                    $surveyFee = (float) ($record->billing_survey_fee ?? 0);
                    $preparationFee = (float) ($record->billing_processing_fee ?? 0);
                    $compensationFee = (float) ($record->billing_betterment_charges ?? 0);
                    $developmentFee = (float) ($record->billing_land_use_charge ?? 0);
                    $amountDeposited = (float) ($record->billing_penalty_fees ?? 0);
                    $totalAmount = (float) ($record->amount ?? 0) + $registrationFee + $surveyFee + $preparationFee + $compensationFee + $developmentFee;
                    $balanceDue = $totalAmount - $amountDeposited;
                    $balanceDueTo = $balanceDue > 0 ? 'GOVERNMENT' : ($balanceDue < 0 ? 'APPLICANT' : '—');
                  @endphp
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->reference ? \Illuminate\Support\Str::upper((string) $record->reference) : '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->file_number ? \Illuminate\Support\Str::upper((string) $record->file_number) : '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->applicant_name ? \Illuminate\Support\Str::upper((string) $record->applicant_name) : '—' }}</td>
                  <td class="px-4 py-3 text-left max-w-[260px] truncate" title="{{ $record->applicant_address ? \Illuminate\Support\Str::upper((string) $record->applicant_address) : '—' }}">{{ $record->applicant_address ? \Illuminate\Support\Str::upper((string) $record->applicant_address) : '—' }}</td>
                  {{-- Property location, broken into its parts. district falls back to the
                       legacy `district` column: rows written before the loc_* address
                       builder existed only ever filled that one. --}}
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->loc_house_number ? \Illuminate\Support\Str::upper((string) $record->loc_house_number) : '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->loc_plot_number ? \Illuminate\Support\Str::upper((string) $record->loc_plot_number) : '—' }}</td>
                  <td class="px-4 py-3 text-left max-w-[200px] truncate" title="{{ $record->loc_street_name ? \Illuminate\Support\Str::upper((string) $record->loc_street_name) : '—' }}">{{ $record->loc_street_name ? \Illuminate\Support\Str::upper((string) $record->loc_street_name) : '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ ($record->loc_district ?: $record->district) ? \Illuminate\Support\Str::upper((string) ($record->loc_district ?: $record->district)) : '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $record->loc_lga ? \Illuminate\Support\Str::upper((string) $record->loc_lga) : '—' }}</td>
                  <td class="px-4 py-3 text-left max-w-[260px] truncate" title="{{ $record->location_station ? \Illuminate\Support\Str::upper((string) $record->location_station) : '—' }}">{{ $record->location_station ? \Illuminate\Support\Str::upper((string) $record->location_station) : '—' }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format((float) ($record->amount ?? 0), 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($registrationFee, 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($surveyFee, 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($preparationFee, 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($compensationFee, 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($developmentFee, 2) }}</td>
                  <td class="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">{{ number_format($totalAmount, 2) }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ number_format($amountDeposited, 2) }}</td>
                  <td class="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">{{ number_format(abs($balanceDue), 2) }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ $balanceDueTo }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ optional($record->prepared_at)->format('d M Y') ?? '—' }}</td>
                  <td class="px-4 py-3 text-left whitespace-nowrap">{{ optional($record->created_at)->format('d M Y H:i') ?? '—' }}</td>
                  @php
                    $editMetadata = [
                      'reference' => $record->reference,
                      'file_number' => $record->file_number,
                      'applicant_name' => $record->applicant_name,
                      'applicant_address' => $record->applicant_address,
                      'location_station' => $record->location_station,
                      'district' => $record->district,
                      'amount' => $record->amount,
                      'prepared_at' => optional($record->prepared_at)->format('Y-m-d'),
                      'date_of_expiry' => optional($record->date_of_expiry)->format('Y-m-d'),
                      'rent_from_year' => $record->rent_from_year,
                      'rent_to_year' => $record->rent_to_year,
                      'app_house_number' => $record->app_house_number,
                      'app_plot_number' => $record->app_plot_number,
                      'app_street_name' => $record->app_street_name,
                      'app_district' => $record->app_district,
                      'app_lga' => $record->app_lga,
                      'app_state' => $record->app_state,
                      'loc_house_number' => $record->loc_house_number,
                      'loc_plot_number' => $record->loc_plot_number,
                      'loc_street_name' => $record->loc_street_name,
                      'loc_lga' => $record->loc_lga,
                      'loc_state' => $record->loc_state,
                    ];

                    $editBilling = [
                      'bill_balance_reciept' => $record->billing_receipt,
                      'bill_balance_date' => $record->billing_date
                        ? \Carbon\Carbon::parse($record->billing_date)->format('Y-m-d\TH:i')
                        : null,
                      'Site_Plan_Fee' => $registrationFee,
                      'survey_fee' => $surveyFee,
                      'Processing_Fee' => $preparationFee,
                      'Betterment_Charges' => $compensationFee,
                      'Land_Use_Charge' => $developmentFee,
                      'Penalty_Fees' =>   (float) ($record->billing_penalty_fees ?? 0),
                    ];
                  @endphp
                  <td class="px-4 py-3 text-left">
                    <div class="relative inline-block text-left">
                      <button type="button"
                        class="bill-action-toggle inline-flex items-center justify-center w-8 h-8 text-slate-500 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-700"
                        aria-haspopup="true"
                        aria-expanded="false"
                        title="Actions">
                        <i data-lucide="more-vertical" class="h-4 w-4"></i>
                      </button>
                      <div class="bill-action-menu hidden fixed z-[9999] w-40 rounded-lg border border-slate-200 bg-white shadow-lg">
                        <a href="{{ route('bill-balance.show', $record) }}" class="bill-action-item flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                          <i data-lucide="eye" class="h-3 w-3 text-blue-600"></i>
                          View
                        </a>
                        <button type="button"
                          class="bill-action-item w-full text-left flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 open-bill-balance-modal"
                          data-mode="edit"
                          data-update-url="{{ route('bill-balance.update', $record) }}"
                          data-record='@json($editMetadata)'
                          data-billing='@json($editBilling)'>
                          <i data-lucide="edit-2" class="h-3 w-3 text-indigo-600"></i>
                          Edit
                        </button>
                        <button type="button"
                          class="bill-action-item w-full text-left flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                          onclick="window.dispatchEvent(new CustomEvent('open-print-manager', { detail: { ref: @js($record->reference), type: 'Bill Balance', url: @js(route('bill-balance.print', $record)) } }))">
                          <i data-lucide="printer" class="h-3 w-3 text-emerald-600"></i>
                          Print
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="24" class="px-4 py-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                      <div class="p-3 bg-slate-100 rounded-full">
                        <i data-lucide="file-text" class="h-6 w-6 text-slate-400"></i>
                      </div>
                      <div>
                        <h3 class="text-sm font-medium text-slate-900">No bill balance records</h3>
                        <p class="text-sm text-slate-500">Get started by creating your first bill balance record.</p>
                      </div>
                      <button type="button" class="btn-primary open-bill-balance-modal" data-mode="create">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Create First Bill
                      </button>
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        
        {{-- Enhanced Pagination --}}
        @if($records->hasPages())
          <div class="p-4 border-t border-slate-100">
            <div class="flex items-center justify-between">
              <div class="text-sm text-slate-500">
                Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} results
              </div>
              
              <div class="flex items-center gap-2">
                {{-- Previous Page --}}
                @if($records->onFirstPage())
                  <span class="px-3 py-2 text-sm text-slate-400 bg-slate-100 rounded-lg cursor-not-allowed">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                  </span>
                @else
                  <a href="{{ $records->previousPageUrl() }}" 
                     class="px-3 py-2 text-sm text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                  </a>
                @endif
                
                {{-- Page Numbers --}}
                @foreach($records->getUrlRange(1, $records->lastPage()) as $page => $url)
                  @if($page == $records->currentPage())
                    <span class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg">{{ $page }}</span>
                  @else
                    <a href="{{ $url }}" 
                       class="px-3 py-2 text-sm text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">{{ $page }}</a>
                  @endif
                @endforeach
                
                {{-- Next Page --}}
                @if($records->hasMorePages())
                  <a href="{{ $records->nextPageUrl() }}" 
                     class="px-3 py-2 text-sm text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </a>
                @else
                  <span class="px-3 py-2 text-sm text-slate-400 bg-slate-100 rounded-lg cursor-not-allowed">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </span>
                @endif
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
    @include('bill_balance.partials.modal', ['billBalance' => $billBalance, 'billingValues' => $billingValues])
  </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
@endpush

@push('scripts')
  @if(session('status'))
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
          Swal.fire('Success', @json(session('status')), 'success');
        }
      });
    </script>
  @endif
  @if(session('error'))
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
          Swal.fire('Error', @json(session('error')), 'error');
        }
      });
    </script>
  @endif
  @if($errors->any())
    <script>
      window.billBalanceFormShouldOpen = true;
      window.billBalanceOldInput = @json(old());
    </script>
  @endif
  <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
  <script src="{{ asset('js/bill_balance.js') }}" defer></script>
  <script>
    // Initialize Lucide icons after page load
    document.addEventListener('DOMContentLoaded', () => {
      const closeAllActionMenus = () => {
        document.querySelectorAll('.bill-action-menu').forEach((menu) => menu.classList.add('hidden'));
      };

      document.querySelectorAll('.bill-action-toggle').forEach((toggleButton) => {
        toggleButton.addEventListener('click', (event) => {
          event.stopPropagation();
          const menu = toggleButton.parentElement.querySelector('.bill-action-menu');
          const wasHidden = menu.classList.contains('hidden');
          closeAllActionMenus();
          if (wasHidden) {
            // Position menu using fixed coordinates relative to the toggle button
            const rect = toggleButton.getBoundingClientRect();
            const menuHeight = 120; // approximate menu height
            const spaceBelow = window.innerHeight - rect.bottom;
            menu.style.left = (rect.right - 160) + 'px'; // 160 = w-40 (10rem)
            if (spaceBelow < menuHeight) {
              menu.style.top = (rect.top - menuHeight) + 'px';
            } else {
              menu.style.top = (rect.bottom + 4) + 'px';
            }
            menu.classList.remove('hidden');
          }
        });
      });

      document.querySelectorAll('.bill-action-item').forEach((actionItem) => {
        actionItem.addEventListener('click', () => {
          closeAllActionMenus();
        });
      });

      document.addEventListener('click', closeAllActionMenus);

      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        
        // Re-initialize icons when modal opens
        const modal = document.getElementById('bill-balance-modal');
        if (modal) {
          const observer = new MutationObserver(() => {
            if (!modal.classList.contains('hidden')) {
              lucide.createIcons();
            }
          });
          observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
        }
      }

      // ══════════ WIZARD LOGIC ══════════
      (function initWizard() {
        const TOTAL_STEPS = 4;
        let currentStep = 1;

        const panels    = document.querySelectorAll('.wizard-panel');
        const prevBtn   = document.getElementById('wizard-prev-btn');
        const nextBtn   = document.getElementById('wizard-next-btn');
        const submitBtn = document.getElementById('wizard-submit-btn');

        if (!panels.length || !prevBtn || !nextBtn || !submitBtn) return;

        function goToStep(step) {
          if (step < 1 || step > TOTAL_STEPS) return;
          currentStep = step;

          // Show / hide panels
          panels.forEach(p => {
            const s = parseInt(p.dataset.wizardStep, 10);
            p.classList.toggle('hidden', s !== currentStep);
          });

          // Update step circles & labels
          for (let i = 1; i <= TOTAL_STEPS; i++) {
            const circle = document.getElementById('wizard-circle-' + i);
            const label  = document.getElementById('wizard-label-' + i);
            if (!circle || !label) continue;

            if (i < currentStep) {
              // completed
              circle.className = 'w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 border-teal-500 bg-teal-100 text-teal-600';
              label.className  = 'text-xs font-medium mt-1 transition-colors duration-300 text-teal-600';
            } else if (i === currentStep) {
              // active
              circle.className = 'w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 border-teal-500 bg-teal-500 text-white';
              label.className  = 'text-xs font-medium mt-1 transition-colors duration-300 text-teal-600';
            } else {
              // upcoming
              circle.className = 'w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 border-slate-300 bg-white text-slate-400';
              label.className  = 'text-xs font-medium mt-1 transition-colors duration-300 text-slate-400';
            }
          }

          // Update connectors
          document.querySelectorAll('.wizard-connector').forEach(c => {
            const beforeStep = parseInt(c.dataset.beforeStep, 10);
            const inner = c.querySelector('div');
            if (inner) {
              inner.className = beforeStep <= currentStep
                ? 'h-full bg-teal-500 rounded transition-colors duration-300'
                : 'h-full bg-slate-200 rounded transition-colors duration-300';
            }
          });

          // Toggle nav buttons
          prevBtn.classList.toggle('hidden', currentStep === 1);
          nextBtn.classList.toggle('hidden', currentStep === TOTAL_STEPS);
          submitBtn.classList.toggle('hidden', currentStep !== TOTAL_STEPS);

          // Re-init icons for newly visible panel
          if (typeof lucide !== 'undefined') lucide.createIcons();

          // Sync required attributes after panel visibility changes
          syncRequiredAttributes();
        }

        // Disable required on hidden panels so browser validation doesn't block submit
        function syncRequiredAttributes() {
          panels.forEach(p => {
            const isHidden = p.classList.contains('hidden');
            p.querySelectorAll('[required], [data-was-required]').forEach(el => {
              if (isHidden) {
                if (el.hasAttribute('required')) {
                  el.removeAttribute('required');
                  el.setAttribute('data-was-required', '1');
                }
              } else {
                if (el.hasAttribute('data-was-required')) {
                  el.setAttribute('required', '');
                  el.removeAttribute('data-was-required');
                }
              }
            });
          });
        }

        nextBtn.addEventListener('click', () => goToStep(currentStep + 1));
        prevBtn.addEventListener('click', () => goToStep(currentStep - 1));

        // Allow clicking step indicators to jump
        document.querySelectorAll('.wizard-step-indicator').forEach(el => {
          el.addEventListener('click', () => {
            const target = parseInt(el.dataset.step, 10);
            if (target && target >= 1 && target <= TOTAL_STEPS) {
              goToStep(target);
            }
          });
        });

        // Reset wizard to step 1 when modal opens
        const modalEl = document.getElementById('bill-balance-modal');
        if (modalEl) {
          const wizObs = new MutationObserver(() => {
            if (!modalEl.classList.contains('hidden')) {
              goToStep(1);
            }
          });
          wizObs.observe(modalEl, { attributes: true, attributeFilter: ['class'] });
        }

        // Initialize to step 1 on page load
        goToStep(1);
      })();
    });

   
  </script>
@endpush
