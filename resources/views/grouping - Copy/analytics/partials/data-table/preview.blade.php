<section class="table-preview" aria-labelledby="table-preview-heading">
    <header class="table-header">
        <div>
            <h2 id="table-preview-heading">Recent Records</h2>
            @if(!empty($appliedFilters))
                <p>
                    Showing <span data-counter="preview-count">{{ number_format($previewCount ?? count($tablePreview)) }}</span>
                    of {{ number_format($previewTotal ?? 0) }} files.
                </p>
                <p class="active-filters" data-active-filters>
                    @if(isset($previewTotal))
                        <span>Total matches: {{ number_format($previewTotal) }}</span>
                    @endif
                    @foreach($appliedFilters as $key => $value)
                        @continue(in_array($key, ['per_page', 'page']))
                        @php
                            $labelMap = [
                                'batch' => 'Batch',
                                'landuse' => 'Land use',
                                'year' => 'Year',
                                'fileno' => 'File number',
                            ];
                            $label = $labelMap[$key] ?? ucfirst($key);
                        @endphp
                        <span>{{ $label }}: {{ $value }}</span>
                    @endforeach
                </p>
            @else
                <p>Showing the latest <span data-counter="preview-count">{{ number_format($previewCount ?? count($tablePreview)) }}</span> files from the grouping dataset. Use the dedicated search to explore more.</p>
                <p class="active-filters hidden" data-active-filters></p>
            @endif
        </div>
        <div class="table-actions">
            @if(($hasActiveFilters ?? false) === true)
                <div class="quick-filter-control">
                    <label for="grouping-preview-quick-filter" class="sr-only">Quick filter</label>
                    <input
                        type="search"
                        id="grouping-preview-quick-filter"
                        class="form-input"
                        placeholder="Quick filter…"
                        data-table-quick-filter
                        autocomplete="off"
                    >
                </div>
            @endif
            <button type="button" class="btn-secondary" x-on:click="openSearchModal()">
                <i class="fas fa-search mr-2"></i>Advanced search
            </button>
        </div>
    </header>

    <div class="table-container" x-ref="tableContainer">
    <table id="grouping-preview-table" class="grouping-preview-table whitespace-nowrap">
            <thead>
                <tr>
                    <th scope="col">Awaiting File No</th>
                    <th scope="col">MLS File No</th>
                    <th scope="col">Mapping</th>
                    <th scope="col">Group No</th>
                    <th scope="col">Batch</th>
                    <th scope="col">SYS Batch</th>
                    <th scope="col">MDC Batch</th>
                    <th scope="col">Registry</th>
                    <th scope="col">Shelf Rack</th>
                    <th scope="col">Indexed By</th>
                    <th scope="col">Land Use</th>
                    <th scope="col">Year</th>
                    <th scope="col">Created</th>
                </tr>
            </thead>
            <tbody data-table="preview">
                @forelse ($tablePreview as $row)
                    <tr data-row>
                        <td data-heading="Awaiting File No" class="whitespace-nowrap">{{ $row->awaiting_fileno ?? '—' }}</td>
                        <td data-heading="MLS File No" class="whitespace-nowrap">{{ $row->mls_fileno ?? '—' }}</td>
                        <td data-heading="Mapping" class="whitespace-nowrap">
                            <span class="pill {{ ($row->mapping ?? 0) === 1 ? 'pill-success' : 'pill-warning' }}">
                                {{ ($row->mapping ?? 0) === 1 ? 'Matched' : 'Pending' }}
                            </span>
                        </td>
                        <td data-heading="Group No" class="whitespace-nowrap">{{ $row->group_number ?? '—' }}</td>
                        <td data-heading="Batch" class="whitespace-nowrap">{{ $row->batch_no ?? '—' }}</td>
                        <td data-heading="SYS Batch" class="whitespace-nowrap">{{ $row->sys_batch_no ?? '—' }}</td>
                        <td data-heading="MDC Batch" class="whitespace-nowrap">{{ $row->mdc_batch_no ?? '—' }}</td>
                        <td data-heading="Registry" class="whitespace-nowrap">{{ $row->registry ?? '—' }}</td>
                        <td data-heading="Shelf Rack" class="whitespace-nowrap">{{ $row->shelf_rack ?? '—' }}</td>
                        <td data-heading="Indexed By" class="whitespace-nowrap">{{ $row->indexed_by ?? '—' }}</td>
                        <td data-heading="Land Use" class="whitespace-nowrap">{{ $row->landuse ?? '—' }}</td>
                        <td data-heading="Year" class="whitespace-nowrap">{{ $row->year ?? '—' }}</td>
                        <td data-heading="Created" class="whitespace-nowrap">{{ optional($row->created_at ? \Carbon\Carbon::parse($row->created_at) : null)?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr data-empty-row>
                        <td colspan="13" class="empty">No records available for preview.</td>
                    </tr>
                @endforelse
                @if(($previewCount ?? 0) > 0)
                    <tr data-empty-row class="hidden">
                        <td colspan="13" class="empty">No matching records for this quick filter.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if(isset($previewPaginator) && $previewPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <nav class="table-pagination" aria-label="Grouping preview pagination">
            {{ $previewPaginator->onEachSide(1)->links() }}
        </nav>
    @endif

    <footer class="table-footer">
        <p>
            @if(!empty($appliedFilters))
                Page {{ number_format($previewCurrentPage ?? 1) }} of {{ isset($previewPaginator) ? number_format($previewPaginator->lastPage()) : '1' }}.
            @endif
            Need full access? Use the search or export functions for the complete dataset.
        </p>
    </footer>
</section>

<div id="grouping-search-modal" class="search-modal hidden" role="dialog" aria-modal="true" aria-labelledby="grouping-search-title">
    <div class="search-modal__backdrop" data-action="close-search"></div>
    <div class="search-modal__panel" role="document">
        <header class="search-modal__header">
            <div>
                <h3 id="grouping-search-title">Advanced search</h3>
                <p>Select filters to pull specific records from the grouping dataset.</p>
            </div>
            <button type="button" class="search-modal__close" data-action="close-search" aria-label="Close search">
                <i class="fas fa-times"></i>
            </button>
        </header>

        @php
            $selectedPerPage = (int) ($filters['per_page'] ?? 50);
        @endphp
        <form id="grouping-search-form" class="search-modal__form" method="GET" action="{{ route('grouping-analytics.dashboard') }}">
            <div class="form-grid">
                <div class="form-field">
                    <label for="search-fileno" class="form-label">File number</label>
                    <input type="text" id="search-fileno" name="fileno" class="form-input" placeholder="Search awaiting or MLS" value="{{ $filters['fileno'] ?? '' }}">
                </div>
                <div class="form-field">
                    <label for="search-batch" class="form-label">Batch number</label>
                    <input type="text" id="search-batch" name="batch" class="form-input" placeholder="e.g. BATCH001" value="{{ $filters['batch'] ?? '' }}">
                </div>
                <div class="form-field">
                    <label for="search-landuse" class="form-label">Land use</label>
                    <select id="search-landuse" name="landuse" class="form-select">
                        <option value="">All land uses</option>
                        @foreach($filterOptions['landuses'] as $landuse)
                            <option value="{{ $landuse }}" @selected(strtoupper($filters['landuse'] ?? '') === $landuse)>{{ $landuse }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="search-year" class="form-label">Year</label>
                    <select id="search-year" name="year" class="form-select">
                        <option value="">All years</option>
                        @foreach($filterOptions['years'] as $year)
                            <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="search-per-page" class="form-label">Records per page</label>
                    <select id="search-per-page" name="per_page" class="form-select">
                        @foreach([50, 100, 250] as $option)
                            <option value="{{ $option }}" @selected($selectedPerPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="search-modal__actions">
                <a href="{{ route('grouping-analytics.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-undo mr-2"></i>Reset
                </a>
                <div class="action-spacer"></div>
                <button type="button" class="btn-secondary" data-action="close-search">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter mr-2"></i>Apply filters
                </button>
            </div>
        </form>
        <footer class="search-modal__footer" data-search-results-info>
            @if(!empty($appliedFilters))
                <strong>{{ number_format($previewTotal ?? 0) }}</strong> total record{{ ($previewTotal ?? 0) === 1 ? '' : 's' }}. Showing {{ number_format($previewCount ?? 0) }} per page.
            @else
                Choose any filter combination and submit to reload the dashboard with matching records.
            @endif
        </footer>
    </div>
</div>
