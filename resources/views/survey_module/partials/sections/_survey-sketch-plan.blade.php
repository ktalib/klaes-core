{{-- Sketch Plan on Computation Field Observations (change list p.2): an attachment filed in the file's EDMS folder (Survey Registry). --}}
@php
    $rp = $anchor['routes'] ?? 'survey-module.gkn';
    $current = $sketches->firstWhere('is_superseded', 0);
    $older = $sketches->where('is_superseded', 1);
@endphp
<div style="background:#fff;border:1px solid var(--gray-200,#e5e7eb);border-radius:12px;padding:14px 16px;margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <strong><i class="fas fa-drafting-compass" style="color:var(--primary);"></i> Sketch Plan</strong>
            @if ($current)
                <div style="font-size:13px;margin-top:4px;">
                    @if ($current->url)<a href="{{ $current->url }}" target="_blank">{{ $current->original_name }}</a>@else {{ $current->original_name }} <span style="color:#b91c1c;">(scan missing)</span>@endif
                    <span style="color:var(--gray-500);">· {{ \Illuminate\Support\Carbon::parse($current->created_at)->format('d M Y H:i') }}{{ $current->uploaded_by_name ? ' · ' . $current->uploaded_by_name : '' }}</span>
                </div>
            @else
                <div class="helper-text" style="margin-top:4px;">No Sketch Plan yet.</div>
            @endif
        </div>
        @unless ($anchor['locked'])
            <form method="POST" action="{{ route($rp . '.sketch.store', $anchor['key']) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                @csrf
                <input type="file" name="sketch_plan" accept=".pdf,.jpg,.jpeg,.png" required style="font-size:13px;" />
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-upload"></i> {{ $current ? 'Replace' : 'Upload' }}</button>
            </form>
        @endunless
    </div>
    @error('sketch_plan')<div style="color:var(--danger,#dc2626);font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror
    <p class="helper-text" style="margin:6px 0 0;">PDF, JPG or PNG, up to 10 MB. Filed in {{ $anchor['prefill']['file_number'] ?: 'the file' }}'s EDMS folder (Survey Registry), so it shows in Page Typing.{{ $current ? ' A replacement keeps the earlier one in EDMS.' : '' }}</p>
    @if ($older->isNotEmpty())
        <details style="margin-top:6px;font-size:12px;color:var(--gray-600);">
            <summary>{{ $older->count() }} earlier version{{ $older->count() === 1 ? '' : 's' }}</summary>
            @foreach ($older as $d)
                <div>@if ($d->url)<a href="{{ $d->url }}" target="_blank">{{ $d->original_name }}</a>@else {{ $d->original_name }}@endif · {{ \Illuminate\Support\Carbon::parse($d->created_at)->format('d M Y H:i') }}</div>
            @endforeach
        </details>
    @endif
</div>
