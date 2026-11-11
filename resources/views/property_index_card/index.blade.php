@extends('layouts.app')
@section('page-title')
    {{ __('Property Index Card Assistant') }}
@endsection
@section('content')
@include('propertycard.css.style') 
    <!-- Main Content --> 
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6"> 
            @php
                $currentUser = auth()->user();
                $assignRoles = $currentUser && is_array($currentUser->assign_role)
                    ? $currentUser->assign_role
                    : array_filter(array_map('trim', explode(',', (string) ($currentUser->assign_role ?? ''))));
                $isSupperAdminRole = in_array('Supper Admin', $assignRoles, true);
                // Generate Property Index Card is a button here (items 37-38, 2026-10-20), for Registry, which generates the cards.
                $canGeneratePic = $currentUser && \App\Services\CadastralDept\PropertyIndexCards::installed()
                    && app(\App\Services\CadastralDept\DeskAccess::class)->canWorkDesk($currentUser, 'registry');
            @endphp
            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                {{-- <div class="flex flex-col space-y-2">
                    <h1 class="text-3xl font-bold tracking-tight">Property Index Card Assistant</h1>
                    <p class="text-gray-500">Capture and manage property index cards</p>
                </div> --}}
        
                <div class="flex items-center justify-end gap-2 mb-4">
                    @if($canGeneratePic)
                        <a href="{{ route('cadastral-dept.pic.index') }}" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            <i class="fas fa-id-card mr-2"></i>
                            Generate Property Index Card
                        </a>
                    @endif
                    @if($isSupperAdminRole)
                        <a href="{{ route('pra-pic-audit.index') }}" class="inline-flex items-center rounded border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                            <i class="fas fa-chart-bar mr-2"></i>
                            PRA/PIC Audit Dashboard
                        </a>
                    @endif
                </div>

                {{-- Two tabs (user, 2026-11-11): the captured PIC records (pic table), and the cards Cadastral
                     Registry generates (cadastral_property_index_cards), listed here read only. --}}
                @php
                    $picTab = (request('tab') === 'cadastral' || request()->filled('cards_q') || request()->filled('cards_page')) ? 'cadastral' : 'captured';
                @endphp
                <div class="flex gap-1 border-b border-gray-200" role="tablist">
                    <button type="button" role="tab" data-pic-tab="captured" aria-selected="{{ $picTab === 'captured' ? 'true' : 'false' }}"
                            class="pic-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px {{ $picTab === 'captured' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Captured Records
                    </button>
                    <button type="button" role="tab" data-pic-tab="cadastral" aria-selected="{{ $picTab === 'cadastral' ? 'true' : 'false' }}"
                            class="pic-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px {{ $picTab === 'cadastral' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Cadastral Generated Cards
                        @if ($cadastralCards)<span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ number_format($cadastralCards->total()) }}</span>@endif
                    </button>
                </div>

                <div id="pic-tab-cadastral" class="{{ $picTab === 'cadastral' ? '' : 'hidden' }}">
                    @include('property_index_card.partials.cadastral_cards')
                </div>

                <!-- Manual Property Details Content -->
                <div id="manual-assistant" class="{{ $picTab === 'captured' ? '' : 'hidden' }}">
                    @include('propertycard.partials.property_details', [
                        'featuredRecord' => $featuredRecord ?? null,
                        'dataRoute' => route('property_index_card.getData'),
                        'fallbackRoute' => route('propertycard.data.fallback'),
                        'cofoRoute' => route('propertycard.cofo'),
                        'showPropertyRecordButton' => false,
                        'showIndexCardButton' => true
                    ])
                </div>

                <!-- AI Property Details Content (placeholder kept for future use) -->
                <div id="ai-assistant" style="display: none;"></div>
           
            </div>
        
            <!-- Property Modal Dialogs -->
            @include('propertycard.partials.add_property_record')
            @include('propertycard.partials.edit_property_record')
            @include('propertycard.partials.view_property_record')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <!-- Include JavaScript after all DOM elements -->
    @include('propertycard.js.javascript')
    @include('propertycard.partials.property_form_sweetalert')
    <script>
        // Captured Records | Cadastral Generated Cards. The tab is kept in ?tab= so a reload stays on it.
        document.querySelectorAll('.pic-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-pic-tab');
                document.querySelectorAll('.pic-tab').forEach(function (b) {
                    var on = b === btn;
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                    b.classList.toggle('border-blue-600', on); b.classList.toggle('text-blue-700', on);
                    b.classList.toggle('border-transparent', !on); b.classList.toggle('text-gray-500', !on);
                });
                document.getElementById('manual-assistant').classList.toggle('hidden', tab !== 'captured');
                document.getElementById('pic-tab-cadastral').classList.toggle('hidden', tab !== 'cadastral');
                try { var u = new URL(window.location.href); u.searchParams.set('tab', tab); history.replaceState(null, '', u); } catch (e) {}
                if (tab === 'captured' && window.jQuery && jQuery.fn.dataTable) {
                    jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
                }
            });
        });
    </script>
@endsection
