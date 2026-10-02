<div id="bill-balance-modal" class="modal-overlay hidden">
  <div class="modal-card" style="max-width: 56rem;">
    {{-- Fixed header,   --}}
    <div class="flex items-center justify-between px-8 pt-6 pb-4 border-b border-slate-200 shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center">
          <i data-lucide="file-text" class="w-5 h-5 text-teal-600"></i>
        </div>
        <h2 id="bill-balance-modal-title" class="text-xl font-bold text-slate-900">Generate Bill Balance</h2>
      </div>
      <span class="modal-close rounded-lg p-2 cursor-pointer" data-close-modal>&times;</span>
    </div>

    <form id="bill-balance-form" method="POST" action="{{ route('bill-balance.store') }}" data-store-url="{{ route('bill-balance.store') }}" class="flex flex-col min-h-0 flex-1" novalidate>
      <input type="hidden" name="_method" value="POST">

      {{-- Scrollable body --}}
      <div class="overflow-y-auto flex-1 px-8 py-6">
        @include('bill_balance.partials.form', ['billBalance' => $billBalance, 'billingValues' => $billingValues])
      </div>

      {{-- Fixed footer --}}
      <div class="flex items-center justify-between px-8 py-4 border-t border-slate-200 bg-white shrink-0">
        <button type="button" id="wizard-prev-btn" class="btn-secondary hidden">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
          <span>Previous</span>
        </button>
        <div class="flex-1"></div>
        <div class="flex gap-3">
          <button type="button" class="btn-secondary" data-close-modal>
            <i data-lucide="x" class="w-4 h-4"></i>
            <span>Cancel</span>
          </button>
          <button type="button" id="wizard-next-btn" class="btn-primary">
            <span>Next</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
          <button type="submit" id="wizard-submit-btn" class="btn-primary hidden">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span>Generate Bill Balance</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

@include('components.global-fileno-modal')
