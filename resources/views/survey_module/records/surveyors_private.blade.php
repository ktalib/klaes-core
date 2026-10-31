@extends('survey_module.layouts.klaes')

@section('page-title', 'Surveyors — KLAES')

@php
  $PageTitle = 'Surveyors';
  $PageDescription = 'Private (licensed) surveyors, from the Cadastral surveyor register';
  $fs = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
@endphp

@section('survey-content')
@include('survey_module.partials._gov_private_tabs', ['route' => 'survey-module.records.surveyors.index', 'tab' => 'private'])

{{-- Read-only here: licensed surveyors are kept in Cadastral's register (cadastral_surveyors without a staff link). --}}
<div class="comp-type-note" style="margin-bottom:16px;">
    <i class="fas fa-info-circle"></i>
    Private surveyors are kept in the Cadastral surveyor register.
    @if (\Illuminate\Support\Facades\Route::has('cadastral-module.surveyors.index'))
        <a href="{{ route('cadastral-module.surveyors.index') }}">Add or edit them there</a>.
    @endif
</div>

<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="private" />
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, SURCON no., firm, phone…" style="{{ $fs }}width:260px;" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->filled('q'))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.records.surveyors.index', ['tab' => 'private']) }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Name</th>
                    <th>SURCON No.</th>
                    <th>Firm</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Licence</th>
                    <th>Expires</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($surveyors as $s)
                    <tr>
                        <td>{{ $surveyors->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $s->full_name }}</strong></td>
                        <td>{{ $s->surcon_number ?: '—' }}</td>
                        <td>{{ $s->firm_name ?: '—' }}</td>
                        <td>{{ $s->phone ?: '—' }}</td>
                        <td>{{ $s->email ?: '—' }}</td>
                        <td>{{ $s->licence_status ?: '—' }}</td>
                        <td>{{ $s->licence_expires_on ? \Illuminate\Support\Carbon::parse($s->licence_expires_on)->format('d M Y') : '—' }}</td>
                        <td>{{ $s->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            {{ request()->filled('q') ? 'No private surveyors match this search.' : 'No private surveyors yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $surveyors->firstItem() ?? 0 }}–{{ $surveyors->lastItem() ?? 0 }} of {{ number_format($surveyors->total()) }} private surveyors</span>
        <div class="pagination">{{ $surveyors->links() }}</div>
    </div>
</div>
@endsection
