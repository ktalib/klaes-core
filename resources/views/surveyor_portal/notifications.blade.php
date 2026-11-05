@extends('surveyor_portal.layouts.portal')

@section('title', 'Notifications — KSSGOF Portal')
@section('heading', 'Notifications')

@section('main')
    <div class="ks-card overflow-hidden">
        @forelse ($notes as $n)
            <div class="p-4" style="border-bottom:1px solid #ECEEF0;{{ $n->read_at ? '' : 'background:#F3F6FB;' }}">
                <div class="font-semibold">@if ($n->link)<a href="{{ $n->link }}" style="color:var(--ks-blue);">{{ $n->title }}</a>@else{{ $n->title }}@endif</div>
                <div class="text-sm">{{ $n->body }}</div>
                <div class="ks-help">{{ optional($n->created_at)->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <p class="p-6 ks-help text-center">No notifications yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notes->links() }}</div>
@endsection
