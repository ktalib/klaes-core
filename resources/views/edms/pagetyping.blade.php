@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing') }}
@endsection
  
 

@section('content')
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
@endphp

@include('edms.css.pagetyping_css')

<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="main-container">
        @include('edms.pagetyping.partials.breadcrumb', compact('fileIndexing'))

        @php
            $allPages = [];
            $groupAccumulator = [];

            foreach ($fileIndexing->scannings->values() as $docIndex => $scanning) {
                $documentPath = $scanning->document_path;
                $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
                $isPdf = $extension === 'pdf';
                $fallbackUrl = asset('storage/' . ltrim($documentPath, '/'));
                $publicDisk = Storage::disk('public');
                $resolvedUrl = $publicDisk->exists($documentPath) ? $publicDisk->url($documentPath) : $fallbackUrl;
                $displayName = $scanning->original_filename ?: 'Document ' . ($docIndex + 1);
                $displayOrder = $scanning->display_order ?: ($docIndex + 1);
                $documentType = $scanning->document_type ? trim($scanning->document_type) : null;

                if ($documentType) {
                    $groupKey = 'type-' . Str::slug($documentType, '_');
                    $groupLabel = $documentType;
                    $groupType = 'document_type';
                } else {
                    $baseKey = $isPdf ? 'pdf' : ($extension ?: 'other');
                    $groupKey = 'format-' . $baseKey;
                    $groupLabel = strtoupper($extension ?: 'Misc') . ' Files';
                    $groupType = 'format';
                }

                $groupAliases = array_unique(array_filter([
                    $groupKey,
                    $groupType === 'document_type' ? 'group-document-type' : 'group-format',
                    $isPdf ? 'format-pdf' : 'format-image',
                    $extension ? 'ext-' . $extension : null,
                ]));

                $allPages[] = [
                    'type' => $isPdf ? 'pdf' : 'image',
                    'document_index' => $docIndex,
                    'page_number' => 1,
                    'file_path' => $documentPath,
                    'display_name' => $displayName,
                    'page_index' => $docIndex,
                    'scanning_id' => $scanning->id,
                    'url' => $resolvedUrl,
                    'file_extension' => $extension,
                    'display_order' => $displayOrder,
                    'created_at' => optional($scanning->created_at)->toDateTimeString(),
                    'metadata' => [
                        'document_type' => $documentType,
                        'paper_size' => $scanning->paper_size,
                    ],
                    'group_key' => $groupKey,
                    'group_label' => $groupLabel,
                    'group_type' => $groupType,
                    'group_aliases' => $groupAliases,
                ];

                if (! isset($groupAccumulator[$groupKey])) {
                    $groupAccumulator[$groupKey] = [
                        'key' => $groupKey,
                        'label' => $groupLabel,
                        'type' => $groupType,
                        'count' => 0,
                    ];
                }
                $groupAccumulator[$groupKey]['count']++;
            }

            usort($allPages, function ($a, $b) {
                return [$a['display_order'], $a['scanning_id']] <=> [$b['display_order'], $b['scanning_id']];
            });

            foreach ($allPages as $index => &$page) {
                $page['page_index'] = $index;
                $page['page_number'] = $index + 1;
            }
            unset($page);

            $folderGroups = array_values($groupAccumulator);
            $priorityLabels = [
                'scan upload document' => 0,
            ];

            usort($folderGroups, function ($a, $b) use ($priorityLabels) {
                $aLabel = strtolower($a['label']);
                $bLabel = strtolower($b['label']);

                $aPriority = $priorityLabels[$aLabel] ?? PHP_INT_MAX;
                $bPriority = $priorityLabels[$bLabel] ?? PHP_INT_MAX;

                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }

                return strcmp($aLabel, $bLabel);
            });

            $smartHighlights = collect($folderGroups)
                ->sortByDesc('count')
                ->take(3)
                ->values()
                ->all();

            $totalPages = count($allPages);
        @endphp
        
        @if($totalPages > 0)
            @include('edms.pagetyping.partials.advanced-controls')
            @include('edms.pagetyping.partials.workspace', [
                'totalPages' => $totalPages,
                'smartHighlights' => $smartHighlights,
                'folderGroups' => $folderGroups,
                'allPages' => $allPages,
                'fileIndexing' => $fileIndexing,
            ])
            @include('edms.pagetyping.partials.action-buttons', ['fileIndexing' => $fileIndexing])
        @else
            @include('edms.pagetyping.partials.no-documents', ['fileIndexing' => $fileIndexing])
        @endif

        @include('edms.pagetyping.partials.help')
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<script>
    window.pageTypingConfig = {
        pageData: @json($allPages ?? []),
        fileIndexingId: {{ $fileIndexing->id }},
        totalPages: {{ $totalPages }},
        routes: {
            saveSingle: '{{ route('pagetyping.save-single') }}',
            typingData: '{{ route('pagetyping.api.typing-data') }}',
            replacePage: '{{ route('pagetyping.api.replace-page') }}',
        },
        pdf: {
            workerUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'
        }
    };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" integrity="sha512-1QrHy+RUrK3mDaN44EWJ5PJpj6EaveQyGVF8aRTsz3wL1osppcrPuPuheurdePPNyAaqmJesHYA/Ecc8kalZ2g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('js/edms-pagetyping.js') }}"></script>
@endsection