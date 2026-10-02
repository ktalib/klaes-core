{{--
    Shelf/rack chip shown beside the file number on a card header.

    `shelf_location` may be recorded on the file or derived from the
    shelf_rack_ranges map by ShelfRackLocator, which flags the difference on
    `shelf_is_derived`. Derived values carry a known rack-level drift for part of
    the workbook set, so the chip is qualified in its tooltip — it is NOT marked
    visually, the two read identically on the card by choice.

    This slot shows a shelf/rack and nothing else. No shelf, no chip: a file with
    no rack is genuinely unshelved, and putting anything else here (a registry, a
    zone, a status) would read as a shelf location that does not exist.
--}}
@php
    $shelfLabel = trim((string) ($file->shelf_location ?? ''));
    $shelfIsDerived = !empty($file->shelf_is_derived);
@endphp
@if($shelfLabel !== '')
    <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-bold leading-none bg-white text-black whitespace-nowrap"
          title="Shelf/Rack {{ $shelfLabel }}{{ $shelfIsDerived ? ' — derived from the shelf map, not recorded on the file' : '' }}">
        {{ $shelfLabel }}
    </span>
@endif
