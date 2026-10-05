@include('cadastral_module.partials._flash')


<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>This is rule-based matching, not AI.</strong>
        It compares the file number as written and in its normalised spelling against the duplicate
        register, and looks for other files sharing a plot number in the same district.
        It cannot compare <em>boundaries</em> — KLAES stores no parcel geometry, so two allocations
        can only be compared by what they are labelled. Treat every hit as something for a human to confirm.
    </div>
</div>

{{-- Checking a file goes through the global file-number selector, like every
     other file number in the module; the page then reads ?file_number=, so a
     number that is not indexed can still be checked against the register. --}}
<div class="table-toolbar" style="align-items:flex-end;">
    <div class="left" style="flex:1;max-width:720px;">
        @include('cadastral_module.partials._file_picker', [
            'navigate' => route('cadastral-module.registry.duplicates'),
            'label'    => 'File to check',
            'number'   => $fileNumber,
        ])
    </div>
    @if ($fileNumber)
        <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.registry.duplicates') }}">Clear</a>
    @endif
</div>

@if ($summary)
    <div class="dash-card" style="margin-bottom:18px;">
        <div class="card-header"><strong>{{ $fileNumber }}</strong></div>

        <div class="calc-grid" style="padding:12px 0;">
            <div class="calc-card">
                <div class="kpi-label">Reads as</div>
                <div class="kpi-value" style="font-size:18px;">
                    {{ $summary['format']['normalised'] ?: '—' }}
                </div>
                <div class="helper-text">
                    {{ ucfirst($summary['format']['class'] ?? 'unknown') }}
                    @if ($summary['format']['land_use']) · {{ $summary['format']['land_use'] }} @endif
                </div>
            </div>

            <div class="calc-card">
                <div class="kpi-label">In the index</div>
                <div class="kpi-value" style="font-size:18px;">
                    {{ $summary['indexed'] ? 'Yes' : 'No' }}
                </div>
                @if ($summary['indexed'])
                    <div class="helper-text">
                        {{ $summary['indexed']->file_title ?: '(untitled)' }}
                        @if ($summary['indexed']->plot_number) · Plot {{ $summary['indexed']->plot_number }} @endif
                    </div>
                @endif
            </div>

            <div class="calc-card">
                <div class="kpi-label">Correspondence file</div>
                <div class="kpi-value" style="font-size:18px;">
                    {{ $summary['correspondence']['exists'] ? 'Commissioned' : 'Not yet' }}
                </div>
                @if ($summary['correspondence']['corresponding_fileno'])
                    <div class="helper-text">{{ $summary['correspondence']['corresponding_fileno'] }}</div>
                @elseif (Route::has('mls-file-no-matching.index'))
                    <div class="helper-text">
                        <a href="{{ route('mls-file-no-matching.index') }}">Commission it</a>
                    </div>
                @endif
            </div>

            <div class="calc-card">
                <div class="kpi-label">Shelf</div>
                <div class="kpi-value" style="font-size:18px;">{{ $summary['shelf_location'] ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="table-wrapper" style="margin-bottom:18px;">
        <div class="table-toolbar">
            <div class="left"><strong>Duplicate register</strong></div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>File Number</th><th>Title</th><th>Registry</th><th>Plot</th><th>Location</th><th>Category</th><th>Comment</th></tr>
                </thead>
                <tbody>
                    @forelse ($summary['duplicates'] as $dup)
                        <tr>
                            <td><strong>{{ $dup->file_number }}</strong></td>
                            <td>{{ $dup->file_title ?: '—' }}</td>
                            <td>{{ $dup->registry ?: '—' }}</td>
                            <td>{{ $dup->plot_number ?: '—' }}</td>
                            <td>{{ $dup->location ?: '—' }}</td>
                            <td>{{ $dup->category ?: '—' }}</td>
                            <td>{{ Str::limit($dup->comment, 60) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:22px;color:var(--gray-500);">
                                Nothing in the duplicate register for this number.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-wrapper">
        <div class="table-toolbar">
            <div class="left"><strong>Other files on the same plot</strong></div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>File Number</th><th>Title</th><th>Plot</th><th>District</th><th>LGA</th><th>Registry</th></tr>
                </thead>
                <tbody>
                    @forelse ($summary['doubles'] as $row)
                        <tr>
                            <td><strong>{{ $row->file_number }}</strong></td>
                            <td>{{ $row->file_title ?: '—' }}</td>
                            <td>{{ $row->plot_number ?: '—' }}</td>
                            <td>{{ $row->district ?: '—' }}</td>
                            <td>{{ $row->lga ?: '—' }}</td>
                            <td>{{ $row->registry ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:22px;color:var(--gray-500);">
                                No other file shares this plot number in the same district.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="table-wrapper">
        <div class="table-toolbar">
            <div class="left"><strong>Receipts already flagged</strong></div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Receipt</th><th>File Number</th><th>Title</th><th>Why</th><th>Received</th>@if ($holdsEnabled)<th>Hold</th>@endif<th></th></tr>
                </thead>
                <tbody>
                    @forelse ($flagged as $receipt)
                        <tr>
                            <td><strong>{{ $receipt->receipt_ref }}</strong></td>
                            <td>{{ $receipt->file_number }}</td>
                            <td>{{ Str::limit($receipt->file_title, 40) ?: '—' }}</td>
                            <td>{{ $receipt->duplicate_note ?: '—' }}</td>
                            <td>{{ optional($receipt->received_at)->format('d M Y') ?: '—' }}</td>
                            @if ($holdsEnabled)
                                <td>
                                    @if ($receipt->isOnHold())
                                        <span class="status-badge rejected" title="{{ $receipt->hold_reason }}"><span class="dot"></span>On Hold</span>
                                    @elseif ($receipt->hold_status === \App\Models\Cadastral\CadastralFileReceipt::HOLD_CLEARED)
                                        <span class="status-badge completed" title="{{ $receipt->hold_clear_note }}"><span class="dot"></span>Cleared</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="action-icons">
                                    <a class="btn btn-outline btn-xs"
                                       href="{{ route('cadastral-module.registry.duplicates', ['file_number' => $receipt->file_number]) }}">
                                        Check
                                    </a>
                                    @if ($holdsEnabled)
                                        @include('cadastral_module.partials._hold_actions', ['receipt' => $receipt])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $holdsEnabled ? 7 : 6 }}" style="text-align:center;padding:28px;color:var(--gray-500);">
                                <i class="fas fa-circle-check" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                Nothing is flagged. Select a file number above to check one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Showing {{ $flagged->firstItem() ?? 0 }}–{{ $flagged->lastItem() ?? 0 }} of {{ number_format($flagged->total()) }}</span>
            <div class="pagination">{{ $flagged->links() }}</div>
        </div>
    </div>
@endif
