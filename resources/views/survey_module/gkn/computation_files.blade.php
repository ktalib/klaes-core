@extends('survey_module.layouts.klaes')

@section('page-title', 'Computation Files — KLAES')

@php
  $PageTitle = 'Computation Files (Field Jackets)';
  $PageDescription = 'Computation files (field jackets) commissioned for survey jobs.';
@endphp

@section('survey-content')
{{-- CR 30102026 item 12: the table is left blank until the user confirms its source (2026-10-31).
     Government / Private tabs (item 15): Government = Survey, LPKN serials (item 13); Private = Cadastral, LPPL serials (item 14). --}}
@php $cfTab = request('tab') === 'private' ? 'private' : 'government'; @endphp
@include('survey_module.partials._gov_private_tabs', ['route' => 'survey-module.gkn.computation-files', 'tab' => $cfTab])
<div style="display:flex;gap:10px;align-items:flex-start;padding:12px 16px;margin-bottom:16px;border:1.5px solid #f59e0b;background:#fffbeb;border-radius:var(--radius-sm);color:#92400e;font-size:14px;">
    <i class="fas fa-triangle-exclamation" style="margin-top:2px;"></i>
    <div><strong>Source pending.</strong> The data source for this table has not been confirmed yet. Records will show here once it is connected.
        <div style="margin-top:4px;">{{ $cfTab === 'private' ? 'Private: computation files with an LPPL serial, commissioned by Cadastral.' : 'Government: computation files with an LPKN serial, commissioned by the Survey Department.' }}</div></div>
</div>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Computation File No.</th>
                    <th>Job No.</th>
                    <th>File No.</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Surveyor</th>
                    <th>I to S</th>
                    <th>Status</th>
                    <th>Commissioned</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
                        <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        No records. Source pending.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
