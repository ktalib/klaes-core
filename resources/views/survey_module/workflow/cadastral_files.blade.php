@extends('survey_module.layouts.klaes')

@section('page-title', 'Cadastral Files — KLAES')

@php
  $PageTitle = 'Cadastral Files';
  $PageDescription = 'Verification files in the Cadastral Department';
  $statusBadge = ['in_progress' => 'review', 'completed' => 'active', 'returned' => 'rejected'];
  $statusLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'returned' => 'Returned'];
@endphp

@section('survey-content')
@if (! $installed)
    <div class="table-wrapper" style="padding:28px;text-align:center;color:var(--gray-500);">
        The Cadastral Department workflow is not set up on this server.
    </div>
@else
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Verification Files</div>
            <div class="kpi-value">{{ number_format($stats['total']) }}</div>
            <div class="kpi-sub">Ever registered</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">In Progress</div>
            <div class="kpi-value">{{ number_format($stats['open']) }}</div>
            <div class="kpi-sub">Open in Cadastral</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">At Survey Examination</div>
            <div class="kpi-value">{{ number_format($stats['examination']) }}</div>
            <div class="kpi-sub">With the Examination Unit now</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Completed</div>
            <div class="kpi-value">{{ number_format($stats['completed']) }}</div>
            <div class="kpi-sub">Closed by Cadastral</div>
        </div>
    </div>

    <form method="GET" class="table-toolbar">
        <div class="left">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search file no., ref or title…"
                   style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
            <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Status</option>
                @foreach ($statusLabel as $k => $label)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="at" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">Any desk</option>
                <option value="examination" @selected(request('at') === 'examination')>At Survey Examination</option>
            </select>
            <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            @if (request()->hasAny(['q', 'status', 'at']))
                <a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.cadastral-files') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Ref</th>
                        <th>File No.</th>
                        <th>Title</th>
                        <th>Plot No.</th>
                        <th>District</th>
                        <th>LGA</th>
                        <th>Location</th>
                        <th>File Type</th>
                        <th>Now at</th>
                        <th>Received</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cases as $case)
                        @php
                            // Opening the case needs Cadastral access; script actions (Master Delete) stay on the Cadastral side.
                            $items = $canOpenCases
                                ? array_values(array_filter($case->rowActions(), fn ($i) => empty($i[4])))
                                : [];
                            $items[] = ['Queue for Examination', 'check-check', route('survey-module.workflow.examination', [
                                'linked_ref' => $case->file_number,
                                'exam_type'  => 'Cadastral File',
                            ])];
                        @endphp
                        <tr>
                            <td>{{ $cases->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $case->case_ref }}</strong></td>
                            <td>{{ $case->file_number }}</td>
                            <td>{{ $case->file_title ?: '—' }}</td>
                            <td>{{ $case->plot_no ?: '—' }}</td>
                            <td>{{ $case->district ?: '—' }}</td>
                            <td>{{ $case->lga ?: '—' }}</td>
                            <td>{{ $case->location ?: '—' }}</td>
                            <td>{{ $case->fileTypeLabel() }}</td>
                            <td>
                                {{ $case->isOpen() ? $case->deskShort() : '—' }}
                                @if ($case->isOpen())
                                    <div style="font-size:12px;color:var(--gray-600);">{{ $case->stepLabel() }}</div>
                                @endif
                            </td>
                            <td>{{ optional($case->received_at)->format('d M Y') ?: '—' }}</td>
                            <td>
                                <span class="status-badge {{ $statusBadge[$case->status] ?? 'pending' }}">
                                    <span class="dot"></span>{{ $statusLabel[$case->status] ?? $case->status }}
                                </span>
                            </td>
                            <td>@include('cadastral_dept.partials._row_actions', ['items' => $items])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
                                <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                @if (request()->hasAny(['q', 'status', 'at']))
                                    No verification files match that filter.
                                    <a href="{{ route('survey-module.workflow.cadastral-files') }}">Clear the filter</a>.
                                @else
                                    No verification files in the Cadastral Department yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>
                Showing {{ $cases->firstItem() ?? 0 }}–{{ $cases->lastItem() ?? 0 }}
                of {{ number_format($cases->total()) }} file(s)
            </span>
            <div class="pagination">{{ $cases->links() }}</div>
        </div>
    </div>
@endif
@endsection
