<div class="card mb-6">
    <div class="p-6 border-b">
        <h2 class="text-lg font-semibold">Search Archives</h2>
        <p class="text-sm text-muted-foreground">Find archived files by file number, title, or page content</p>
    </div>
    <div class="p-6">
        <form id="search-form" action="{{ route('filearchive.index', array_filter(['url' => request('url')])) }}" method="GET">
            @if(request('url'))
                <input type="hidden" name="url" value="{{ request('url') }}">
            @endif
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Search Term</label>
                    <div class="relative mt-1">
                        <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                        <input id="search" name="search" value="{{ request('search') }}" placeholder="Enter search term..." class="input pl-8">
                    </div>
                </div>
                <div class="flex flex-col md:flex-row gap-2">
                    <div>
                        <label for="registry" class="block text-sm font-medium mb-1">Registry</label>
                        <select id="registry" name="registry" class="select w-full md:w-[180px]">
                            <option value="all">All Registries</option>
                            @foreach($registryOptions ?? [] as $value => $label)
                                <option value="{{ $value }}" {{ (string) request('registry') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-medium mb-1">Year</label>
                        <select id="year" name="year" class="select w-full md:w-[140px]">
                            <option value="all">All Years</option>
                            @foreach($yearOptions ?? [] as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="searchField" class="block text-sm font-medium mb-1">Search In</label>
                        <select id="searchField" name="field" class="select w-full md:w-[180px]">
                            <option value="all" {{ request('field') == 'all' ? 'selected' : '' }}>All Fields</option>
                            <option value="page" {{ request('field') == 'page' ? 'selected' : '' }}>Page Type</option>
                            <option value="type" {{ request('field') == 'type' ? 'selected' : '' }}>Land Use Type</option>
                            <option value="fileName" {{ request('field') == 'fileName' ? 'selected' : '' }}>File Name</option>
                            <option value="fileNumber" {{ request('field') == 'fileNumber' ? 'selected' : '' }}>File Number</option>
                        </select>
                    </div>
                    <div>
                        <label for="category" class="block text-sm font-medium mb-1">Category</label>
                        <select id="category" name="category" class="select w-full md:w-[180px]">
                            <option value="all" {{ request('category') == 'all' ? 'selected' : '' }}>All Categories</option>
                            <option value="land" {{ request('category') == 'land' ? 'selected' : '' }}>Land Records</option>
                            <option value="legal" {{ request('category') == 'legal' ? 'selected' : '' }}>Legal Documents</option>
                            <option value="admin" {{ request('category') == 'admin' ? 'selected' : '' }}>Administrative</option>
                        </select>
                    </div>
                    <div>
                        <label for="cover_type" class="block text-sm font-medium mb-1">Cover Type</label>
                        <select id="cover_type" name="cover_type" class="select w-full md:w-[180px]">
                            <option value="all" {{ request('cover_type') == 'all' ? 'selected' : '' }}>All Cover Types</option>
                            <option value="front" {{ request('cover_type') == 'front' ? 'selected' : '' }}>Front Cover (FC)</option>
                            <option value="back" {{ request('cover_type') == 'back' ? 'selected' : '' }}>Back Cover (BC)</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" id="search-button" class="btn btn-primary">Search</button>
                        <a href="{{ route('filearchive.index', array_filter(['url' => request('url')])) }}" id="reset-search" class="btn btn-outline">Clear</a>
                    </div>
                </div>
            </div>
        </form>

        <div id="search-type-indicator" class="mt-2 flex items-center {{ request()->filled('search') ? '' : 'hidden' }}">
            <span id="search-badge" class="badge badge-outline text-xs">
                @if(request()->filled('search'))
                    Searching: "{{ request('search') }}" 
                    @if(request('field') !== 'all')
                        in {{ ucfirst(request('field')) }}
                    @endif
                @endif
            </span>
            @if(request()->filled('search'))
                <span id="search-description" class="text-xs text-muted-foreground ml-2">
                    {{ $completedFiles->total() }} results found
                </span>
            @endif
        </div>
        
        <!-- Advanced Filters (Initially Hidden) -->
        <div id="advanced-filters" class="mt-4 pt-4 border-t hidden">
            <h3 class="text-sm font-medium mb-2">Advanced Filters</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Date Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input py-1 text-sm" placeholder="From">
                        <span class="text-xs text-muted-foreground">to</span>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="input py-1 text-sm" placeholder="To">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Page Type</label>
                    <select name="page_type" class="select text-sm">
                        <option value="">Any Type</option>
                        @if(isset($popularPageTypes))
                            @foreach($popularPageTypes as $pageType)
                                <option value="{{ $pageType->page_type }}" {{ request('page_type') == $pageType->page_type ? 'selected' : '' }}>
                                    {{ $pageType->page_type }} ({{ $pageType->count }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Land Use</label>
                    <select name="land_use" class="select text-sm">
                        <option value="">Any Use</option>
                        <option value="Residential" {{ request('land_use') == 'Residential' ? 'selected' : '' }}>Residential</option>
                        <option value="Commercial" {{ request('land_use') == 'Commercial' ? 'selected' : '' }}>Commercial</option>
                        <option value="Industrial" {{ request('land_use') == 'Industrial' ? 'selected' : '' }}>Industrial</option>
                        <option value="Mixed" {{ request('land_use') == 'Mixed' ? 'selected' : '' }}>Mixed Use</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-sm font-medium mb-1">Quick Filters</label>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="land_use" data-value="Residential">Residential</span>
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="land_use" data-value="Commercial">Commercial</span>
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="page_type" data-value="Certificate">Certificate</span>
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="page_type" data-value="Deed">Deed</span>
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="page_type" data-value="Application Form">Application</span>
                    <span class="badge badge-outline px-2 py-1 cursor-pointer filter-tag" data-filter="page_type" data-value="Map">Map</span>
                </div>
            </div>
        </div>
        
        <div class="mt-3 text-center">
            <button id="toggle-advanced-filters" class="btn btn-ghost btn-sm">
                <span id="advanced-filters-text">Show Advanced Filters</span>
                <i data-lucide="chevron-down" class="h-4 w-4 ml-1"></i>
            </button>
        </div>
    </div>
</div>