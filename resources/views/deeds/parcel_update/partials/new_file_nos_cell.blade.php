{{-- New FileNo(s) cell for the SPU / APU listings. $newFiles: the file numbers commissioned
     for this row (App\Services\ParcelUpdateNewFiles); a dash until commissioning.
     $newFilesFrom (optional): the application's own number.
     $newFilesPlots (optional): plot numbers in commissioning order (a subdivision's
     schedule), for a new file whose own record carries none.
     Only the first file number shows in the row. When there are more, an arrow is put in
     front of the row (new_file_rows_script) and opening it lists the others as full rows
     under this one, built on demand so a 1,500-plot subdivision does not load 1,500 hidden
     rows with the page. --}}
@php
    $newFiles = array_values($newFiles ?? []);
    $firstNewFile = $newFiles[0] ?? null;
    $moreFiles = [];
    if (count($newFiles) > 1) {
        $newFilesPlots = array_values($newFilesPlots ?? []);
        $newFileDetails = \App\Services\ParcelUpdateNewFiles::details($newFiles);
        foreach ($newFiles as $i => $f) {
            if ($i === 0) continue;
            $d = $newFileDetails[mb_strtoupper(trim($f))];
            $date = null;
            try { $date = $d['date'] ? \Carbon\Carbon::parse($d['date'])->format('M d, Y') : null; } catch (\Throwable $e) { $date = null; }
            // The file's own location as commissioned (district, LGA), as the row above shows it;
            // composed from district + LGA when it has none. Never the plot: that has its own column.
            $location = $d['location'] ?: implode(', ', array_filter([$d['district'], $d['lga']]));
            $moreFiles[] = [
                'f' => $f,
                't' => $d['title'] ?: \App\Services\ParcelUpdateNewFiles::titleOf($f),
                'p' => $d['plot'] ?: (string) ($newFilesPlots[$i] ?? ''),
                'l' => $location,
                'd' => $date,
                'c' => $d['by'],
            ];
        }
    }
@endphp
<td class="pu-newfiles-cell px-4 py-3 text-xs align-top"
    @if($moreFiles) data-more-files='@json($moreFiles)' data-total="{{ count($newFiles) }}" data-from="{{ $newFilesFrom ?? '' }}" @endif>
    @if(!$firstNewFile)
        <span class="text-slate-300">&mdash;</span>
    @else
        <span class="font-mono font-bold text-emerald-700 whitespace-nowrap" title="{{ \App\Services\ParcelUpdateNewFiles::titleOf($firstNewFile) }}">{{ $firstNewFile }}</span>
    @endif
</td>

@include('deeds.parcel_update.partials.new_file_rows_script')
