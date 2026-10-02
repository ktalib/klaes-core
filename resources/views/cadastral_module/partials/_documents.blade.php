{{--
    Documents filed for a cadastral record (App\Services\Cadastral\CadastralDocuments).

    Each is a scan in the file's EDMS folder; the link opens it through the
    existing Scan Upload download route. A superseded document is still listed,
    struck through: documents are never deleted.

    Expects: $docs (collection from CadastralDocuments::for()).
--}}
@if ($docs->isEmpty())
    —
@else
    <div style="display:flex;flex-direction:column;gap:3px;">
        @foreach ($docs as $doc)
            <div style="font-size:12px;{{ $doc->is_superseded ? 'opacity:.55;text-decoration:line-through;' : '' }}"
                 title="{{ $doc->is_superseded ? 'Superseded' : 'Scan #' . $doc->scanning_id . ' in the file\'s EDMS folder' }}">
                <i class="fas {{ $doc->mime === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                @if ($doc->url)
                    <a href="{{ $doc->url }}" target="_blank" rel="noopener">{{ Str::limit($doc->original_name, 34) }}</a>
                @else
                    {{ Str::limit($doc->original_name, 34) }} <em>(scan missing)</em>
                @endif
                <span style="color:var(--gray-500);">· {{ \Illuminate\Support\Carbon::parse($doc->created_at)->format('d M Y') }}</span>
            </div>
        @endforeach
    </div>
@endif
