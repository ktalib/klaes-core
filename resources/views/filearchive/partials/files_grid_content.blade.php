@if($files->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="files-grid">
        @foreach($files as $file)
        <div class="border rounded-lg overflow-hidden hover:shadow-md transition-shadow cursor-pointer file-card"
            data-id="{{ $file->id }}"
            data-pages-url="{{ route('filearchive.document-pages', ['id' => $file->id, 'url' => request('url')]) }}"
            data-file-number="{{ e($file->file_number) }}"
            data-file-title="{{ e($file->file_title) }}"
            data-shelf-location="{{ e($file->shelf_location ?? '') }}">
            <div class="aspect-[3/4] bg-gray-100 relative">
                    <!-- Document cover with actual cover page preview -->
                    <div class="absolute inset-0 flex flex-col bg-white">
                        <!-- Cover Type Header - Prominent Display -->
                        @php
                            $desiredCoverCode = 'FC-FC-OFC-0';
                            $coverPage = $file->pagetypings->first(function($page) use ($desiredCoverCode) {
                                return strtoupper($page->page_code ?? '') === $desiredCoverCode;
                            });

                            if (!$coverPage) {
                                $coverPage = $file->firstPageTyping;
                            }

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

                            // Determine cover template based on file number prefix
                            $fileNoUpper = strtoupper($file->file_number ?? '');
                            if (strpos($fileNoUpper, 'SLTR-') === 0 || strpos($fileNoUpper, 'SL-') === 0) {
                                $coverTemplateFile = 'EDMS/COVERS/sltr.jpeg';
                            } elseif (strpos($fileNoUpper, 'ST-') === 0) {
                                $coverTemplateFile = 'EDMS/COVERS/st.jpeg';
                            } else {
                                $coverTemplateFile = 'EDMS/COVERS/land.jpeg';
                            }
                            $coverTemplateUrl = asset('storage/' . $coverTemplateFile);
                            $storagePreviewUrl = $file->storage_preview_url ?? null;
                            $cardPreviewUrl = $storagePreviewUrl ?: ($coverPage
                                ? route('filearchive.cover-image', ['pageTyping' => $coverPage->id])
                                : null);
                        @endphp
                        <div 
                            class="h-8 bg-green-600 flex items-center justify-between px-3 file-card-status-header transition-colors duration-200" 
                            data-file-number="{{ $file->file_number }}"
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
                        </div>
                        @if($cardPreviewUrl)
                            <div class="flex-1 p-2 overflow-hidden">
                                <img src="{{ $cardPreviewUrl }}"
                                     alt="Cover page for {{ $file->file_number }}"
                                     class="w-full h-full object-contain bg-gray-50 rounded-sm"
                                     loading="lazy"
                                     onerror="this.style.display='none';" />
                            </div>
                        @else
                            <div class="flex-1 relative overflow-hidden">
                                <!-- Cover template (front cover = right half of spread) -->
                                <img src="{{ $coverTemplateUrl }}" 
                                     alt="Cover" 
                                     class="absolute inset-0 w-full h-full"
                                     style="object-fit: cover; object-position: right center;"
                                     onerror="this.style.display='none';" />
                                <!-- Data values overlay (positioned on right-half form area) -->
                                <div class="absolute inset-0" style="pointer-events: none;">
                                    <!-- FILE NO -->
                                    <span class="absolute truncate font-bold"
                                          style="top: 13.5%; left: 17%; right: 4%; font-size: clamp(5px, 1.8vw, 9px); line-height: 1; color: #1a1a1a;">
                                        {{ $file->file_number }}
                                    </span>
                                    <!-- NAME OF HOLDER -->
                                    <span class="absolute truncate font-semibold"
                                          style="top: 18.5%; left: 30%; right: 4%; font-size: clamp(4px, 1.6vw, 8px); line-height: 1; color: #1a1a1a;">
                                        {{ $file->file_title ?: '—' }}
                                    </span>
                                    <!-- PLOT NUMBER -->
                                    <span class="absolute truncate font-semibold"
                                          style="top: 23%; left: 28%; right: 52%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
                                        {{ $file->plot_number ?: '—' }}
                                    </span>
                                    <!-- LOCATION -->
                                    <span class="absolute truncate font-semibold"
                                          style="top: 23%; left: 56%; right: 4%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
                                        {{ $file->location ?: '—' }}
                                    </span>
                                    <!-- Bottom FILE NO -->
                                    <span class="absolute truncate font-bold"
                                          style="top: 92.5%; left: 17%; right: 35%; font-size: clamp(4px, 1.4vw, 7px); line-height: 1; color: #1a1a1a;">
                                        {{ $file->file_number }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                </div>

                <div class="p-3">
                    <h3 class="font-medium text-sm line-clamp-1 mb-2" title="{{ $file->file_title }}">
                        {{ $file->file_title }}
                    </h3>
                    <div class="mb-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 whitespace-nowrap">
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
                        <span class="text-xs text-muted-foreground">{{ $fileSize }}</span>
                        <span class="badge badge-secondary text-xs">
                            Archived
                        </span>
                    </div>
                </div>
                <div class="p-2 pt-0 flex flex-wrap gap-1">
                    <!-- Cover Type Badge -->
                    @if($coverPage)
                        <span class="badge text-xs {{ $coverType ? (stripos($coverType->Name, 'front') !== false ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') : 'bg-gray-100 text-gray-700' }}">
                            {{ $coverPageCode }}
                        </span>
                    @endif
                    @if($file->land_use_type)
                        <span class="badge badge-secondary text-xs">{{ $file->land_use_type }}</span>
                    @endif
                    @php
                        $districtName = \App\Support\GeoName::district($file->district);
                    @endphp
                    @if($districtName)
                        <span class="badge badge-secondary text-xs">{{ $districtName }}</span>
                    @endif

                </div>
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    @if($files->hasPages())
        <div class="flex justify-center border-t pt-6 mt-6">
            {{ $files->links() }}
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
