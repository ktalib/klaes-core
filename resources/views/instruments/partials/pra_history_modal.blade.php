{{-- Shared PRA History Modal --}}
{{-- z-index 1100000 keeps it above the instrument capture dialog (z-index 1000020) --}}
<div id="pra-history-modal"
    class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-all duration-300 flex items-center justify-center p-4"
    style="z-index: 1100000;">
    <div
        class="relative w-full max-w-lg shadow-[0_20px_50px_rgba(0,0,0,0.3)] rounded-2xl bg-white overflow-hidden animate-scale-in border border-slate-200">
        <!-- Header -->
        <div
            class="px-6 py-5 bg-gradient-to-r from-indigo-600 to-blue-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-white/20 rounded-xl backdrop-blur-md">
                    <i data-lucide="history" class="h-5 w-5 text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">Property Record History</h3>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <p class="text-xs text-indigo-100 font-medium" id="pra-history-subtitle">Historical records for File: </p>
                    </div>
                </div>
            </div>
            <button type="button" id="pra-history-close-btn"
                class="group p-2 hover:bg-white/20 rounded-xl transition-all duration-200">
                <i data-lucide="x"
                    class="h-5 w-5 text-indigo-100 group-hover:text-white group-hover:rotate-90 transition-all duration-300"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 bg-slate-50/50">
            <div id="pra-history-body" class="grid grid-cols-1 gap-4 overflow-y-auto custom-scrollbar"
                style="max-height: 60vh;">
                <!-- Content injected via JS -->
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-white border-t border-slate-100 flex justify-end items-center">
            <button type="button" id="pra-history-bottom-close-btn"
                class="px-6 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 font-semibold text-sm hover:bg-slate-200 hover:text-slate-800 transition-all duration-200 hover:shadow-sm">
                Dismiss
            </button>
        </div>
    </div>
</div>
