{{-- Cadastral success message as a slim banner, like the notification strip (user, 2026-10-29):
     the success pop-up was too much. Included by layouts.app on cadastral-dept.* pages, which
     then skip the global success pop-up. Closes itself after about 6 seconds; hovering keeps it. --}}
@if (session('success'))
  <div x-data="{ show: true, hover: false, t: null,
                 start() { clearTimeout(this.t); this.t = setTimeout(() => { if (!this.hover) this.show = false }, 6000) } }"
       x-init="start()" x-show="show" x-cloak
       x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
       @mouseenter="hover = true" @mouseleave="hover = false; start()"
       role="status" aria-live="polite"
       class="fixed top-3 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-3xl px-4 print:hidden">
    <div class="flex items-center gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 shadow-md">
      <i data-lucide="bell" class="w-4 h-4 text-blue-600 flex-shrink-0"></i>
      <div class="flex-1 min-w-0 text-sm text-gray-800">
        <span class="font-semibold">{{ session('success') }}</span>
        <span class="text-gray-500"> · Cadastral · just now</span>
      </div>
      <button type="button" @click="show = false" aria-label="Close" class="flex-shrink-0 text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
    </div>
  </div>
@endif
