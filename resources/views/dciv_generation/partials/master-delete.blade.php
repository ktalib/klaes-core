@if(auth()->user()?->assign_role === 'Supper Admin')
<button type="button" class="dciv-master-delete w-full px-4 py-2 text-left text-xs font-bold text-red-600 hover:bg-red-50 flex items-center gap-2"
    data-delete-url="{{ route('dciv-generation.master-delete', ['id' => $recordId]) }}"
    data-file-number="{{ $fileNumber }}">
    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Master Delete
</button>
@endif
