{{-- data-pages-* for an Attach / Detach button: the page count the custody modal asks for (PageCounts). $pc = PageCounts::state(). --}}
@if (! empty($pc['direction']))
data-pages-dir="{{ $pc['direction'] }}" data-pages="{{ $pc['recorded']->pages ?? ($pc['direction'] === 'out' && $pc['dms_pages'] ? $pc['dms_pages'] : '') }}" data-pages-recorded="{{ $pc['recorded'] ? 1 : 0 }}" data-pages-dms="{{ $pc['dms_pages'] }}" data-pages-out="{{ $pc['direction'] === 'back' ? ($pc['out']->pages ?? '') : '' }}"
@endif
