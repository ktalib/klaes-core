{{-- Three-dots trigger on a Doc-WARE file card. The menu itself is the one shared
     panel in card_actions_menu.blade.php: the card is overflow-hidden, which would
     clip a menu nested inside it. --}}
<button type="button"
        class="doc-card-actions-btn inline-flex items-center justify-center h-7 w-7 rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 shrink-0"
        aria-label="Actions" aria-haspopup="menu" title="Actions"
        data-id="{{ $file->id }}"
        data-file-number="{{ e($file->file_number) }}">
    <i data-lucide="more-vertical" class="h-4 w-4"></i>
</button>
