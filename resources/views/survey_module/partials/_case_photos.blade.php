{{-- Geo-tagged field photos of a case (SCF app, 2026-10-25). Kept with the case, not EDMS. --}}
@php $photos = $case->photos; @endphp
@if ($photos->isEmpty())
    <p style="font-size:13px;opacity:.7;margin:0;">No field photos yet. They are taken in the SCF field app.</p>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;">
        @foreach ($photos as $ph)
            @php $src = route('survey-module.mobile.cases.photos.show', [$case, $ph]); @endphp
            <figure style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;">
                <a href="{{ $src }}" target="_blank"><img src="{{ $src }}" alt="{{ $ph->caption ?: 'Field photo' }}" loading="lazy" style="width:100%;height:120px;object-fit:cover;display:block;"></a>
                <figcaption style="padding:6px 8px;font-size:12px;line-height:1.4;">
                    @if ($ph->caption)<div><strong>{{ $ph->caption }}</strong></div>@endif
                    <div style="opacity:.75;">{{ optional($ph->taken_at)->format('d M Y H:i') ?: optional($ph->created_at)->format('d M Y H:i') }}</div>
                    @if ($ph->hasLocation())
                        <a href="{{ $ph->mapUrl() }}" target="_blank" rel="noopener" style="font-size:12px;">{{ number_format($ph->latitude, 5) }}, {{ number_format($ph->longitude, 5) }}{{ $ph->accuracy_m ? ' (±' . round($ph->accuracy_m) . ' m)' : '' }}</a>
                    @else
                        <div style="opacity:.6;">No location</div>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
