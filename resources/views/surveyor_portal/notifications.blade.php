@extends('surveyor_portal.layouts.portal')

@section('title', 'Notifications — MLPP-SG Portal')
@section('heading', 'Notifications')

@section('main')
    <div class="ks-card overflow-hidden">
        @php $me = auth('surveyor')->user(); @endphp
        @forelse ($notes as $n)
            {{-- A company also sees its surveyors' notices (2026-11-10), named, without the link into the surveyor's own pages. --}}
            @php $from = $n->surveyorFor($me); @endphp
            <div class="p-4" style="border-bottom:1px solid #ECEEF0;{{ $n->isReadBy($me) ? '' : 'background:#F3F6FB;' }}">
                @if ($from)<div class="ks-help" style="font-size:12px;font-weight:600;">Surveyor {{ $from }}</div>@endif
                <div class="font-semibold">@if ($n->link && ! $from)<a href="{{ $n->link }}" style="color:var(--ks-blue);">{{ $n->title }}</a>@else{{ $n->title }}@endif</div>
                <div class="text-sm">{{ $n->body }}</div>
                <div class="ks-help">{{ optional($n->created_at)->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <p class="p-6 ks-help text-center">No notifications yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notes->links() }}</div>
@endsection
