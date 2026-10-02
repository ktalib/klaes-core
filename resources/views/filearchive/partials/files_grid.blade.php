<div id="files-container" class="card">
    <div class="p-6 border-b flex flex-row items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold">Archived Files</h2>
            <p class="text-sm text-muted-foreground">
        Pagetyped Digital Files ({{ $completedFiles->total() }} files)
            </p>    
        </div>
        @php
            $sc = $statusCounts ?? ['registry' => null, 'in_transit' => null, 'missing' => null];
            $af = $activeStatusFilter ?? null;
            $legendBase = request()->query();
            unset($legendBase['page']); // filtering resets to the first page

            $registryParams = $legendBase;
            if ($af === 'registry') { unset($registryParams['status_filter']); }
            else { $registryParams['status_filter'] = 'registry'; }

            $transitParams = $legendBase;
            if ($af === 'in_transit') { unset($transitParams['status_filter']); }
            else { $transitParams['status_filter'] = 'in_transit'; }
        @endphp
        <div class="flex items-center gap-4 text-sm text-muted-foreground">
            <a href="{{ route('filearchive.index', $registryParams) }}" class="status-legend no-underline flex items-center gap-2 px-2 py-1 rounded-md transition-colors hover:bg-gray-100" aria-pressed="{{ $af === 'registry' ? 'true' : 'false' }}" title="{{ $af === 'registry' ? 'Clear filter' : 'Show only files in the Registry' }}">
                <span class="inline-flex w-5 h-5 rounded-full bg-green-600"></span>
                <span>File in the Registry</span>
                @if(!is_null($sc['registry']))
                <span class="status-legend-count text-xs font-semibold text-gray-400">({{ $sc['registry'] }})</span>
                @endif
            </a>
            <a href="{{ route('filearchive.index', $transitParams) }}" class="status-legend no-underline flex items-center gap-2 px-2 py-1 rounded-md transition-colors hover:bg-gray-100" aria-pressed="{{ $af === 'in_transit' ? 'true' : 'false' }}" title="{{ $af === 'in_transit' ? 'Clear filter' : 'File checked out of the registry and currently in transit / with a holder' }}">
                <span class="inline-flex w-5 h-5 rounded-full bg-orange-500"></span>
                <span>File in Transit</span>
                @if(!is_null($sc['in_transit']))
                <span class="status-legend-count text-xs font-semibold text-gray-400">({{ $sc['in_transit'] }})</span>
                @endif
            </a>
            <span class="flex items-center gap-2 px-2 py-1" title="Files reported missing">
                <span class="inline-flex w-5 h-5 rounded-full bg-red-600"></span>
                <span>Missing Files</span>
                @if(!is_null($sc['missing'] ?? null))
                <span class="text-xs font-semibold text-gray-400">({{ $sc['missing'] }})</span>
                @endif
            </span>
        </div>
        <div class="flex gap-2">
            <div class="relative">
                <select id="sort-select" class="select select-sm pr-8">
                    <option value="year_asc" {{ request('sort', 'year_asc') == 'year_asc' ? 'selected' : '' }}>Year (Oldest First)</option>
                    <option value="year_desc" {{ request('sort') == 'year_desc' ? 'selected' : '' }}>Year (Newest First)</option>
                    <option value="file_number_asc" {{ request('sort') == 'file_number_asc' ? 'selected' : '' }}>File Number (A-Z)</option>
                    <option value="file_number_desc" {{ request('sort') == 'file_number_desc' ? 'selected' : '' }}>File Number (Z-A)</option>
                    <option value="title_asc" {{ request('sort') == 'title_asc' ? 'selected' : '' }}>Title (A-Z)</option>
                    <option value="title_desc" {{ request('sort') == 'title_desc' ? 'selected' : '' }}>Title (Z-A)</option>
                    <option value="updated_desc" {{ request('sort') == 'updated_desc' ? 'selected' : '' }}>Recently Updated</option>
                </select>
            </div>
            <button id="filter-button" class="btn btn-outline btn-sm gap-1">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Filter
            </button>
        </div>
    </div>
<div class="p-6">
        @if($completedFiles->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="files-grid">
                @foreach($completedFiles as $file)
                @php
                    $isCheckedOut = $file->fileTracking 
                        && ($file->fileTracking->status === 'checked_out' || $file->fileTracking->assignment_status === 'in-transit');
                    $tracking = $file->fileTracking;
                    $movementHistory = [];
                    $currentLocation = null;
                    $currentStatus = 'registry';
                    if ($tracking) {
                        $mh = $tracking->movement_history ?? [];
                        if (is_array($mh)) {
                            $movementHistory = $mh;
                        } elseif (is_string($mh)) {
                            $movementHistory = json_decode($mh, true) ?: [];
                        }
                        $latest = null;
                        $latestTs = 0;
                        foreach ($movementHistory as $mv) {
                            $ts = 0;
                            $d = $mv['log_in_date'] ?? ($mv['log_out_date'] ?? null);
                            $t = $mv['log_in_time'] ?? ($mv['log_out_time'] ?? null);
                            if ($d) {
                                $ts = strtotime($d . ' ' . ($t ?: '00:00:00')) ?: 0;
                            }
                            if ($ts > $latestTs) {
                                $latestTs = $ts;
                                $latest = $mv;
                            }
                        }
                        if ($latest) {
                            $currentLocation = $latest['current_location'] ?? ($latest['location'] ?? ($latest['office'] ?? null));
                            $hasLogout = !empty($latest['log_out_date']) || !empty($latest['log_out_time']);
                            $hasLogin = !empty($latest['log_in_date']) || !empty($latest['log_in_time']);
                            if ($hasLogin && !$hasLogout) {
                                $currentStatus = 'in_transit';
                            } else {
                                $currentStatus = 'registry';
                            }
                        }
                        if (!empty($tracking->current_location)) {
                            $currentLocation = $tracking->current_location;
                        }
                    }
                    $isInDocWare = !empty($currentLocation);
                    $statusLabel = $currentStatus === 'in_transit' ? 'File in Transit' : 'File in the Registry';
                    if ($isInDocWare) {
                        $statusLabel = $currentLocation;
                    }
                @endphp
                <div class="border rounded-lg overflow-hidden hover:shadow-md transition-shadow cursor-pointer file-card {{ $isCheckedOut ? 'opacity-50 grayscale' : '' }}"
                    data-id="{{ $file->id }}"
                    data-pages-url="{{ route('filearchive.document-pages', ['id' => $file->id, 'url' => request('url')]) }}"
                    data-file-number="{{ e($file->file_number) }}"
                    data-file-title="{{ e($file->file_title) }}"
                    data-status="{{ e($currentStatus) }}"
                    data-location="{{ e($currentLocation) }}"
                    data-shelf-location="{{ e($file->shelf_location ?? '') }}">
                    <div class="aspect-[3/4] bg-gray-100 relative">
                            <!-- Document cover with actual cover page preview -->
                            <div class="absolute inset-0 flex flex-col bg-white">
                                <!-- Cover Type Header - Prominent Display -->
                                @php
                                    $storagePreviewUrl = $file->storage_preview_url ?? null;
                                    $desiredCoverCode = 'FC-FC-OFC-0';
                                    $coverPage = $file->pagetypings->first(function($page) use ($desiredCoverCode) {
                                        return strtoupper($page->page_code ?? '') === $desiredCoverCode;
                                    });

                                    if (!$coverPage) {
                                        $coverPage = $file->firstPageTyping;
                                    }

                                    // Use the archive media route for database-backed cards.
                                    // It resolves the EDMS path server-side, avoiding a broken
                                    // static /storage mapping on the web server.
                                    $cardPreviewUrl = $storagePreviewUrl ?: ($coverPage
                                        ? route('filearchive.cover-image', ['pageTyping' => $coverPage->id])
                                        : null);

                                    $coverType = $coverPage ? $coverPage->coverType : null;
                                    $coverTypeName = $coverType ? $coverType->Name : 'Unknown Cover';
                                    
                                    $coverPageCode = '---';
                                    if ($coverPage) {
                                        if (method_exists($coverPage, 'getFormattedPageCode')) {
                                            $coverPageCode = $coverPage->getFormattedPageCode() ?: null;
                                        }
                                        if (!$coverPageCode) {
                                            $coverPageCode = $coverPage->page_code ?? null;
                                        }
                                        if (!$coverPageCode && property_exists($coverPage, 'page_code')) {
                                            $coverPageCode = $coverPage->page_code;
                                        }
                                    }
                                    $coverPageCode = $coverPageCode ?: '---';

                                    $coverPagePath = null;
                                    if ($coverPage) {
                                        $rawPath = $coverPage->scanning && $coverPage->scanning->document_path
                                            ? $coverPage->scanning->document_path
                                            : $coverPage->file_path;

                                        if ($rawPath) {
                                            $rawPath = str_replace('\\', '/', $rawPath);
                                            $trimmedPath = ltrim($rawPath, '/');

                                            $prefixesToStrip = [
                                                'storage/app/public/',
                                                'app/public/',
                                                'public/',
                                                'storage/'
                                            ];

                                            $normalizedPath = $trimmedPath;
                                            foreach ($prefixesToStrip as $prefix) {
                                                if (stripos($normalizedPath, $prefix) === 0) {
                                                    $normalizedPath = substr($normalizedPath, strlen($prefix));
                                                    break;
                                                }
                                            }
                                            $normalizedPath = ltrim($normalizedPath, '/');

                                            $extension = strtolower(pathinfo($normalizedPath ?: $trimmedPath, PATHINFO_EXTENSION));
                                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

                                            $isImagePage = in_array($extension, $imageExtensions);
                                            if (!$isImagePage && method_exists($coverPage, 'isImagePage')) {
                                                $isImagePage = $coverPage->isImagePage();
                                            }

                                            if ($isImagePage) {
                                                // Resolve via the configured 'public' disk (mirrors the working
                                                // document-pages preview). On production the public disk root may
                                                // point to a different mount than storage_path('app/public'), so a
                                                // raw file_exists(storage_path(...)) check fails and the cover never
                                                // renders even though the file is reachable through the disk URL.
                                                if ($normalizedPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($normalizedPath)) {
                                                    $coverPagePath = \Illuminate\Support\Facades\Storage::disk('public')->url($normalizedPath);
                                                } elseif ($trimmedPath && file_exists(public_path($trimmedPath))) {
                                                    $coverPagePath = asset($trimmedPath);
                                                }
                                            }
                                        }
                                    }

                                    /* Cover template approach (commented out for now)
                                    $fileNoUpper = strtoupper($file->file_number ?? '');
                                    if (strpos($fileNoUpper, 'SLTR-') === 0 || strpos($fileNoUpper, 'SL-') === 0) {
                                        $coverTemplateFile = 'EDMS/COVERS/sltr.jpeg';
                                    } elseif (strpos($fileNoUpper, 'ST-') === 0) {
                                        $coverTemplateFile = 'EDMS/COVERS/st.jpeg';
                                    } else {
                                        $coverTemplateFile = 'EDMS/COVERS/land.jpeg';
                                    }
                                    $coverTemplateUrl = asset('storage/' . $coverTemplateFile);
                                    */
                                @endphp
                                <div 
                                    class="h-8 flex items-center justify-between px-3 file-card-status-header transition-colors duration-200 {{ $currentStatus === 'in_transit' ? 'bg-orange-500' : ($isInDocWare ? 'bg-amber-600' : 'bg-green-600') }}"
                                    data-file-number="{{ $file->file_number }}"
                                    data-status-label="{{ e($statusLabel) }}"
                                >
                                    <span class="text-xs font-medium text-white whitespace-nowrap">
                                        {{ $file->file_number }}
                                    </span>
                                    <div class="flex-1 flex items-center justify-center min-w-0 px-1.5">
                                        @include('filearchive.partials.shelf_badge', ['file' => $file])
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white whitespace-nowrap">
                                               {{ (int) (($file->storage_page_count ?? 0) > 0 ? $file->storage_page_count : ($file->pagetypings_count ?? 0)) }} pages
                                        </span>
                                    </div>
                                    <div class="status-tooltip">
                                        <div class="font-semibold mb-1">{{ $file->file_number }}</div>
                                        <div class="text-gray-200">{{ $statusLabel }}</div>
                                        @if(!empty($file->shelf_location))
                                        <div class="text-gray-300 mt-1">🗂️ Shelf/Rack: {{ e($file->shelf_location) }}</div>
                                        @endif
                                        @if($isInDocWare && $currentLocation)
                                        <div class="text-gray-300 mt-1">📍 {{ $currentLocation }}</div>
                                        @elseif($currentStatus === 'in_transit')
                                        <div class="text-gray-300 mt-1">⏳ File is in transit</div>
                                        @else
                                        <div class="text-gray-300 mt-1">✅ File is in the registry</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1 flex flex-col overflow-hidden">
                                    @if($cardPreviewUrl)
                                        <!-- Actual cover page image -->
                                        <div class="flex-1 p-2">
                                            <img src="{{ $cardPreviewUrl }}" 
                                                 alt="Cover page for {{ $file->file_number }}"
                                                 class="w-full h-full object-contain bg-gray-50 rounded-sm"
                                                 loading="lazy"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <!-- Fallback if image fails -->
                                            <div class="hidden flex-1 flex-col p-3 overflow-hidden justify-center">
                                                <div class="w-full h-2 bg-gray-200 rounded mb-1"></div>
                                                <div class="w-3/4 h-2 bg-gray-200 rounded mb-2"></div>
                                                <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                                <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                                <div class="w-5/6 h-1.5 bg-gray-100 rounded mb-2"></div>
                                                <div class="w-full flex justify-center my-1">
                                                    <div class="w-12 h-8 bg-gray-200 rounded"></div>
                                                </div>
                                                <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                                <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                                <div class="w-4/5 h-1.5 bg-gray-100 rounded"></div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Document-style content preview when no cover image -->
                                        <div class="flex-1 flex flex-col p-3 overflow-hidden">
                                            <div class="w-full h-2 bg-gray-200 rounded mb-1"></div>
                                            <div class="w-3/4 h-2 bg-gray-200 rounded mb-2"></div>
                                            <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                            <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                            <div class="w-5/6 h-1.5 bg-gray-100 rounded mb-2"></div>
                                            <div class="w-full flex justify-center my-1">
                                                <div class="w-12 h-8 bg-gray-200 rounded"></div>
                                            </div>
                                            <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                            <div class="w-full h-1.5 bg-gray-100 rounded mb-1"></div>
                                            <div class="w-4/5 h-1.5 bg-gray-100 rounded"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>

                        <div class="p-3">
                            <h3 class="font-semibold text-sm line-clamp-1 mb-2 text-gray-900" title="{{ $file->file_title }}">
                                {{ $file->file_title }}
                            </h3>
                            <div class="mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 whitespace-nowrap">
                                    {{ $file->file_number }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                @php
                                    // Calculate estimated file size based on page count
                                       $pageCount = (int) (($file->storage_page_count ?? 0) > 0 ? $file->storage_page_count : ($file->pagetypings_count ?? 0)) > 0 ? (int) (($file->storage_page_count ?? 0) > 0 ? $file->storage_page_count : ($file->pagetypings_count ?? 0)) : 1;
                                    $estimatedSizeKB = $pageCount * 120; // Assume ~120KB per page
                                    if ($estimatedSizeKB >= 1024) {
                                        $fileSize = round($estimatedSizeKB / 1024, 1) . ' MB';
                                    } else {
                                        $fileSize = $estimatedSizeKB . ' KB';
                                    }
                                @endphp
                                <span class="text-xs font-medium text-orange-600">{{ $fileSize }}</span>
                                <span class="badge text-xs bg-emerald-100 text-emerald-700 font-medium">
                                    Archived
                                </span>
                            </div>
                        </div>
                        <div class="p-2 pt-0 flex flex-wrap gap-1">
                            <!-- Cover Type Badge -->
                            @if($coverPage)
                                <span class="badge text-xs font-medium {{ $coverType ? (stripos($coverType->Name, 'front') !== false ? 'bg-indigo-100 text-indigo-700' : 'bg-teal-100 text-teal-700') : 'bg-gray-100 text-gray-700' }}">
                                    {{ $coverType->Name ?? $coverPageCode }}
                                </span>
                            @endif
                            @if($file->land_use_type)
                                <span class="badge text-xs font-medium bg-purple-100 text-purple-700">{{ $file->land_use_type }}</span>
                            @endif
                            @php
                                $districtName = \App\Support\GeoName::district($file->district);
                            @endphp
                            @if($districtName)
                                <span class="badge text-xs font-medium bg-amber-100 text-amber-700">{{ $districtName }}</span>
                            @endif

                        </div>

                        {{-- File Movement History (Doc-WARE) --}}
                        @if(!empty($movementHistory) && is_array($movementHistory))
                        @php
                            $sortedHistory = $movementHistory;
                            usort($sortedHistory, function($a, $b) {
                                $tsA = strtotime(($a['log_in_date'] ?? ($a['log_out_date'] ?? '1970-01-01')) . ' ' . (($a['log_in_time'] ?? ($a['log_out_time'] ?? '00:00:00'))));
                                $tsB = strtotime(($b['log_in_date'] ?? ($b['log_out_date'] ?? '1970-01-01')) . ' ' . (($b['log_in_time'] ?? ($b['log_out_time'] ?? '00:00:00'))));
                                return $tsB <=> $tsA;
                            });
                            $displayHistory = array_slice($sortedHistory, 0, 5);
                        @endphp
                        <div class="file-movement-info" data-fno="{{ e($file->file_number) }}">
                            <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1">
                                <i data-lucide="route" class="h-3 w-3"></i> Movement History
                            </div>
                            @foreach($displayHistory as $mv)
                                @php
                                    $mvLoc = $mv['current_location'] ?? ($mv['location'] ?? ($mv['office'] ?? 'Unknown'));
                                    $mvDate = $mv['log_in_date'] ?? ($mv['log_out_date'] ?? '');
                                    $mvTime = $mv['log_in_time'] ?? ($mv['log_out_time'] ?? '');
                                    $mvAction = !empty($mv['log_in_date']) || !empty($mv['log_in_time']) ? 'Log In' : 'Log Out';
                                @endphp
                                <div class="mv-row">
                                    <span class="mv-date">{{ e($mvDate) }} {{ e($mvTime) }}</span>
                                    <span class="mv-location">{{ e($mvLoc) }}</span>
                                </div>
                            @endforeach
                            @if(count($movementHistory) > 5)
                                <div class="text-[10px] text-gray-400 text-center mt-1 italic">+{{ count($movementHistory) - 5 }} more</div>
                            @endif
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($completedFiles->hasPages())
                <div class="flex justify-center border-t pt-6 mt-6">
                    {{ $completedFiles->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <i data-lucide="archive" class="h-16 w-16 mx-auto text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Archived Files Found</h3>
                <p class="text-gray-600 mb-6">
                    @if(request()->filled('search'))
                        No files match your search criteria. Try adjusting your search terms.
                    @else
                        Complete page typing for files to see them in the archive.
                    @endif
                </p>
                @if(request()->filled('search'))
                    <a href="{{ route('filearchive.index', array_filter(['url' => request('url')])) }}" class="btn btn-outline">
                        <i data-lucide="x" class="h-4 w-4 mr-2"></i>
                        Clear Search
                    </a>
                @else
                    <a href="{{ route('pagetyping.index') }}" class="btn btn-primary">
                        <i data-lucide="type" class="h-4 w-4 mr-2"></i>
                        Go to Page Typing
                    </a>
                @endif
            </div>
        @endif
    </div>
 
 
</div>
