{{--
    ST family strip (CR-11112026-2 item 15, user 2026-11-12): Land File > Primary ST > Unit(s) ST, the file being
    viewed marked. A member that is indexed opens its own folio; the Land File's pages open read-only from an ST
    folio. An SUA has an imaginary mother: it stands alone, its mother number a plain label.
    Takes $stFamily (StFamily::of) and $file. Read only.
--}}
@if (!empty($stFamily))
@php
    $sfHere = mb_strtoupper(trim((string) $file->file_number));
    $sfChip = function (?array $m, string $role) use ($sfHere) {
        if (!$m) return '';
        $here = mb_strtoupper($m['number']) === $sfHere;
        $cls = $here ? 'bg-blue-700 text-white border-blue-700' : ($m['indexing_id'] ? 'bg-white text-blue-800 border-blue-300 hover:bg-blue-50' : 'bg-gray-50 text-gray-400 border-gray-200');
        $inner = '<span class="block font-bold tracking-wider ' . ($here ? 'text-blue-100' : 'text-gray-400') . '" style="font-size:10px;">' . e($role) . '</span>'
            . '<span class="block text-sm font-semibold">' . e($m['number']) . '</span>'
            . (!$m['indexing_id'] ? '<span class="block" style="font-size:10px;">not indexed</span>' : '');
        $box = 'inline-block rounded-md border px-3 py-1.5 ' . $cls;

        return !$here && $m['indexing_id']
            ? '<a href="' . e(route('vfs.show', $m['indexing_id'])) . '" class="' . $box . '" title="Open ' . e($m['number']) . '">' . $inner . '</a>'
            : '<span class="' . $box . '"' . ($here ? ' aria-current="page"' : '') . '>' . $inner . '</span>';
    };
    $sfLand = $stFamily['land'] ?? null;
    $sfShowLand = in_array($stFamily['kind'], ['primary', 'pua'], true) && !empty($sfLand['indexing_id']);
@endphp
<div class="bg-yellow-50 border-b border-yellow-200 px-6 py-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1 text-xs font-bold tracking-wider text-yellow-900 mr-2">
            <i data-lucide="git-fork" class="h-4 w-4"></i> ST FAMILY
        </span>

        @if ($stFamily['kind'] === 'sua')
            {!! $sfChip(['number' => $stFamily['number'], 'indexing_id' => $file->id], 'SUA UNIT') !!}
            <span class="text-xs text-yellow-900">
                Mother number <strong>{{ $stFamily['sua_mother'] ?: '—' }}</strong>: no Primary file and no Land File behind it, so this unit stands alone.
            </span>
        @else
            {!! $sfChip($sfLand, 'LAND FILE') !!}
            <i data-lucide="chevron-right" class="h-4 w-4 text-yellow-700"></i>
            {!! $sfChip($stFamily['primary'], 'PRIMARY ST') !!}
            @if (!empty($stFamily['units']))
                <i data-lucide="chevron-right" class="h-4 w-4 text-yellow-700"></i>
                @foreach ($stFamily['units'] as $u)
                    {!! $sfChip($u, 'UNIT ST') !!}
                @endforeach
            @else
                <span class="text-xs text-yellow-900 ml-1">No unit files yet.</span>
            @endif
        @endif

        @if ($sfShowLand)
            <span class="flex-1"></span>
            <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold border border-yellow-400 text-yellow-900 bg-white rounded-md py-2 px-3 hover:bg-yellow-100"
                    data-vfs-open-land data-url="{{ route('filearchive.document-pages', $sfLand['indexing_id']) }}" data-file="{{ $sfLand['number'] }}">
                <i data-lucide="book-open" class="h-4 w-4"></i> View Land File pages ({{ $sfLand['number'] }})
            </button>
        @endif
    </div>
</div>
@endif
