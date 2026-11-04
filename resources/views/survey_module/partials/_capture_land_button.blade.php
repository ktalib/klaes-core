{{-- Farm Assessment > Capture Land (user, 2026-11-03: "capture existing, capture new"; it's not "register").
     Items renamed Capture Farmer (New) and Update Farmers List (Old) the same day (user).
     New opens the land form (field assessment); Old opens Capture Compensation, for lists kept on paper.
     $captureProject (optional) carries ?project= through. --}}
@php $cpq = ! empty($captureProject) ? ['project' => $captureProject] : []; @endphp
<div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false" style="position:relative;">
    <button type="button" x-ref="btn" class="btn btn-primary" aria-haspopup="menu" :aria-expanded="open"
            @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 300); open = !open">
        <i class="fas fa-plus"></i> Capture Land <i class="fas fa-chevron-down" style="font-size:11px;margin-left:4px;"></i>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" role="menu"
         :style="'top:' + top + 'px; left:' + left + 'px'"
         class="fixed z-50 rounded-lg border border-gray-200 bg-white shadow-lg py-1" style="width:300px;">
        <a href="{{ route('survey-module.compensation.cases.register', $cpq) }}" role="menuitem" class="flex items-start gap-3 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50">
            <i data-lucide="file-plus" class="w-4 h-4 text-gray-500" style="margin-top:2px;"></i>
            <span><strong>Capture Farmer</strong><br><span class="text-xs text-gray-500">A land assessed now: farmers, farm, trees and compensation.</span></span>
        </a>
        <a href="{{ route('survey-module.compensation.capture-list', $cpq) }}" role="menuitem" class="flex items-start gap-3 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50">
            <i data-lucide="archive" class="w-4 h-4 text-gray-500" style="margin-top:2px;"></i>
            <span><strong>Update Farmers List</strong><br><span class="text-xs text-gray-500">An existing record kept on paper: key in its compensation list.</span></span>
        </a>
    </div>
</div>
