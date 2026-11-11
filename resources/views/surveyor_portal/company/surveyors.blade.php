@extends('surveyor_portal.layouts.portal')

@section('title', 'Our Surveyors — MLPP-SG Portal')
@section('heading', 'Our Surveyors')

{{-- The surveyors registered on the portal under this company (user, 2026-11-10). Read only. --}}
@push('styles')
    <style>
        .ks-co-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .ks-co-search { display:flex; gap:8px; }
        .ks-co-search .ks-input { width:260px; max-width:60vw; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    </style>
@endpush

@section('main')
    <div class="ks-co-top">
        <p class="ks-help" style="margin:0;">Surveyors register on the portal under your company; the Surveyor General approves each one.</p>
        <form method="GET" class="ks-co-search" role="search">
            <label class="sr-only" for="coSearch">Search surveyors</label>
            <input class="ks-input" id="coSearch" name="q" value="{{ $search }}" placeholder="Name, Surveyor ID, SURCON…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>

    <div class="ks-card overflow-x-auto">
        <table class="ks-table" style="min-width:900px;">
            <thead><tr><th>S/N</th><th>Surveyor</th><th>Surveyor ID</th><th>SURCON No.</th><th>Phone</th><th>Email</th><th>Registered</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($rows as $a)
                    @php $label = ['pending' => ['Waiting for the SG', 'submitted'], 'active' => ['Active', 'accepted'], 'rejected' => ['Not approved', 'rejected'], 'suspended' => ['Suspended', 'rejected']][$a->status] ?? [ucfirst((string) $a->status), '']; @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="font-semibold">{{ $a->name }}</td>
                        <td class="ks-num">{{ $a->surveyor_id_no ?: '—' }}</td>
                        <td class="ks-num">{{ $a->surcon_number ?: '—' }}</td>
                        <td class="ks-num">{{ $a->phone ?: '—' }}</td>
                        <td>{{ $a->email ?: '—' }}</td>
                        <td class="ks-num">{{ optional($a->created_at)->format('d/m/Y') }}</td>
                        <td><span class="ks-badge {{ $label[1] }}">{{ $label[0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ks-help" style="text-align:center;padding:28px;">
                        @if ($search !== '') No surveyor matches “{{ $search }}”. @else No surveyor has registered under your company yet. @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
