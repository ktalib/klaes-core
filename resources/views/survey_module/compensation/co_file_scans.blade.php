@extends('survey_module.layouts.klaes')

@section('page-title', 'Uploaded Files — KLAES')

@php
  $PageTitle = 'Uploaded Files · ' . $coFile->co_file_no;
  $PageDescription = 'The raw scanned pages of this CO file, as uploaded by Blind Scanning. Typed pages are in Doc-WARE.';
  $groups = $pages->groupBy(fn ($p) => $p->paper_size ?: 'Other');
@endphp

@section('survey-content')
{{-- CR 30102026 item 17 (user, 2026-10-31): the raw folder until page typing is done for these files. --}}
<div class="page-header">
    <div>
        <a href="{{ route('survey-module.compensation.co-files') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> CO Files</a>
        <span style="margin-left:10px;color:var(--gray-600);">{{ $coFile->display_title }}</span>
    </div>
    <a href="{{ route('filearchive.index', ['search' => $coFile->co_file_no, 'field' => 'fileNumber']) }}" class="btn btn-secondary" target="_blank" rel="noopener"><i class="fas fa-folder-open"></i> Open in Doc-WARE</a>
</div>

@if ($pages->isEmpty())
    <div style="text-align:center;padding:40px 16px;color:var(--gray-500);">
        <i class="fas fa-folder-open" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>
        No scanned pages have been uploaded for {{ $coFile->co_file_no }} yet.
    </div>
@else
    <p style="margin:0 0 12px;color:var(--gray-600);">{{ $pages->count() }} {{ \Illuminate\Support\Str::plural('page', $pages->count()) }}. Click a page to open it full size.</p>
    @foreach ($groups as $size => $list)
        <h3 style="margin:16px 0 8px;font-size:15px;">{{ $size }} <span style="font-weight:400;color:var(--gray-500);">· {{ $list->count() }}</span></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
            @foreach ($list as $p)
                @php
                    $url = '/storage/' . ltrim(strtr((string) $p->document_path, ['\\' => '/']), '/');
                @endphp
                <a href="{{ $url }}" target="_blank" rel="noopener" style="display:block;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);background:#fff;overflow:hidden;text-decoration:none;color:var(--gray-700);">
                    <div style="height:190px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;">
                        <img src="{{ $url }}" alt="{{ $p->original_filename }}" loading="lazy" style="max-width:100%;max-height:190px;object-fit:contain;" onerror="this.parentNode.textContent = 'Image not found';" />
                    </div>
                    <div style="padding:6px 8px;font-size:12px;">{{ $loop->iteration }}. {{ $p->original_filename }}</div>
                </a>
            @endforeach
        </div>
    @endforeach
@endif
@endsection
