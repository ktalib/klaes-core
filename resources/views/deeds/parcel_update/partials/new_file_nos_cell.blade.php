{{-- New FileNo(s) cell for the SPU / APU listings. $newFiles: the file numbers commissioned
     for this row (App\Services\ParcelUpdateNewFiles); a dash until commissioning.
     $newFilesFrom (optional): the application's own number, for the modal title.
     The first 3 show in the cell; the full list opens in one shared modal (a subdivision
     can produce well over 100 files), with S/N, File Title and a search box. --}}
@php
    $newFiles = array_values($newFiles ?? []);
    $shown = array_slice($newFiles, 0, 3);
    $more = count($newFiles) - count($shown);
    // [file no, title] pairs for the modal; titles come from the indexed record.
    $withTitles = array_map(fn ($f) => [$f, \App\Services\ParcelUpdateNewFiles::titleOf($f)], $newFiles);
@endphp
<td class="px-4 py-3 text-xs align-top">
    @if(!$newFiles)
        <span class="text-slate-300">&mdash;</span>
    @else
        <div class="flex flex-col gap-0.5 font-mono font-bold text-emerald-700 whitespace-nowrap">
            @foreach($shown as $fileNo)
                <span title="{{ \App\Services\ParcelUpdateNewFiles::titleOf($fileNo) }}">{{ $fileNo }}</span>
            @endforeach
        </div>
        {{-- Always offered, so the titles can be read even for one or two files. --}}
        <button type="button"
                class="pu-newfiles-open mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 whitespace-nowrap"
                data-files='@json($withTitles)'
                data-from="{{ $newFilesFrom ?? '' }}">
                {{ $more > 0 ? '+' . $more . ' more · view all ' . count($newFiles) : 'view with title' . (count($newFiles) === 1 ? '' : 's') }}
            </button>
    @endif
</td>

@include('deeds.parcel_update.partials.file_list_modal')
