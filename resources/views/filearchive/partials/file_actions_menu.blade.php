<details class="relative ml-auto file-actions-menu"
         onclick="event.stopPropagation(); document.querySelectorAll('.file-actions-menu[open]').forEach(menu => { if (menu !== this) menu.removeAttribute('open'); });">
    <summary class="badge text-xs font-medium bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 cursor-pointer list-none">
        <i data-lucide="ellipsis-vertical" class="h-3 w-3 mr-1"></i>
        Actions
    </summary>
    <div class="absolute right-0 bottom-full z-[60] mb-1 w-56 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg">
        <button type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                title="Move {{ e($file->file_number) }} to another registry"
                onclick="event.stopPropagation(); this.closest('details').removeAttribute('open'); EdmsRegistryTransfer.open({{ (int) $file->id }}, @js($file->file_number), () => window.location.reload());">
            <i data-lucide="folder-symlink" class="h-4 w-4 text-blue-600"></i>
            Move to NR
        </button>
    </div>
</details>
