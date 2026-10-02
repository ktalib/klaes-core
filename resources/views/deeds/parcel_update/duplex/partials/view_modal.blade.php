{{-- Read-only view of a duplex: its stages in execution order and the chain of
     holding numbers each one produced. --}}
<div id="dx-view-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col border border-slate-100">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-black text-slate-800" id="dx-view-title">Duplex</h3>
                <p class="text-xs text-slate-500" id="dx-view-sub"></p>
            </div>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-4" id="dx-view-body"></div>
    </div>
</div>
