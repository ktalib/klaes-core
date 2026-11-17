{{-- Blank Land / ST / SLTR file cover with the file's details written on it.
     Shown on an archive card when the file has no cover page, or when its
     cover image fails to load (pass hidden => true for that case: the image's
     onerror reveals this as its next sibling).
     The template is a two-page spread; the front cover is its right half. --}}
@php
  $coverFileNo = strtoupper($file->file_number ?? '');
  if (str_starts_with($coverFileNo, 'SLTR-') || str_starts_with($coverFileNo, 'SL-')) {
      $coverKind = 'sltr';
  } elseif (str_starts_with($coverFileNo, 'ST-')) {
      $coverKind = 'st';
  } else {
      $coverKind = 'land';
  }
@endphp
{{-- ST has no cover of its own yet: COVERS/st.jpeg is a Land cover (user, 2026-11-16), so ST
     cards show a plain placeholder with the file's details until the right cover is supplied. --}}
@if($coverKind === 'st')
<div class="flex-1 flex flex-col items-center justify-center gap-2 w-full h-full bg-gray-50 px-3 text-center" @if(!empty($hidden)) style="display:none" @endif>
    <i data-lucide="folder" class="h-10 w-10 text-gray-300"></i>
    <span class="text-xs font-bold text-gray-800 break-all">{{ $file->file_number }}</span>
    <span class="text-xs text-gray-500 line-clamp-2">{{ $file->file_title ?: '—' }}</span>
</div>
@else
<div class="flex-1 relative overflow-hidden w-full h-full" @if(!empty($hidden)) style="display:none" @endif>
    <img src="{{ route('filearchive.cover-template', ['kind' => $coverKind]) }}"
         alt="File cover"
         class="absolute inset-0 w-full h-full"
         style="object-fit: cover; object-position: right center;"
         loading="lazy"
         onerror="this.style.display='none';" />
    <div class="absolute inset-0" style="pointer-events: none;">
        <span class="absolute truncate font-bold"
              style="top: 13.5%; left: 17%; right: 4%; font-size: clamp(5px, 1.8vw, 9px); line-height: 1; color: #1a1a1a;">
            {{ $file->file_number }}
        </span>
        <span class="absolute truncate font-semibold"
              style="top: 18.5%; left: 30%; right: 4%; font-size: clamp(4px, 1.6vw, 8px); line-height: 1; color: #1a1a1a;">
            {{ $file->file_title ?: '—' }}
        </span>
        <span class="absolute truncate font-semibold"
              style="top: 23%; left: 28%; right: 52%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
            {{ $file->plot_number ?: '—' }}
        </span>
        <span class="absolute truncate font-semibold"
              style="top: 23%; left: 56%; right: 4%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
            {{ $file->location ?: '—' }}
        </span>
        <span class="absolute truncate font-bold"
              style="top: 92.5%; left: 17%; right: 35%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
            {{ $file->file_number }}
        </span>
    </div>
</div>
@endif
