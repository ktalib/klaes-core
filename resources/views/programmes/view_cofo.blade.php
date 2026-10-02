@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Certificate of Occupancy') }}
@endsection

@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .cofo-preview-wrapper {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .cofo-preview-frame {
            display: block;
            width: 100%;
            min-height: 720px;
            border: none;
            background: white;
        }

        @media (max-width: 1024px) {
            .cofo-preview-frame {
                min-height: 640px;
            }
        }

        @media (max-width: 640px) {
            .cofo-preview-frame {
                min-height: 520px;
            }
        }
    </style>
@endsection

@section('content')
<div id="mainContent" class="flex-1 overflow-auto">
    @include($headerPartial ?? 'admin.header')

    <div class="p-6">
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @php
            $landUseDisplay = !empty($cofo->land_use) ? strtoupper($cofo->land_use) : 'N/A';
            $startDateDisplay = !empty($cofo->start_date)
                ? \Carbon\Carbon::parse($cofo->start_date)->format('F d, Y')
                : 'N/A';
            $issuedDateInstance = null;
            if (!empty($cofo->issued_date)) {
                $issuedDateInstance = \Carbon\Carbon::parse($cofo->issued_date);
            } elseif (!empty($cofo->created_at)) {
                $issuedDateInstance = \Carbon\Carbon::parse($cofo->created_at);
            }
            $issuedDateDisplay = $issuedDateInstance ? $issuedDateInstance->format('F d, Y') : 'N/A';

            $unitDescriptor = null;
            if (!empty($cofo->unit_description)) {
                $unitDescriptor = trim($cofo->unit_description);
            } else {
                $unitSegments = [];
                if (!empty($cofo->block_no)) {
                    $unitSegments[] = 'BLOCK ' . \Illuminate\Support\Str::upper(trim((string) $cofo->block_no));
                }
                if (!empty($cofo->floor_no)) {
                    $unitSegments[] = 'FLOOR ' . \Illuminate\Support\Str::upper(trim((string) $cofo->floor_no));
                }
                if (!empty($cofo->flat_no)) {
                    $unitSegments[] = 'UNIT ' . \Illuminate\Support\Str::upper(trim((string) $cofo->flat_no));
                }
                if (!empty($cofo->plot_no)) {
                    $unitSegments[] = 'PLOT ' . \Illuminate\Support\Str::upper(trim((string) $cofo->plot_no));
                }
                $unitDescriptor = !empty($unitSegments) ? implode(', ', $unitSegments) : null;
            }
            if (!$unitDescriptor && !empty($cofo->certificate_number)) {
                $unitDescriptor = 'Certificate No: ' . trim($cofo->certificate_number);
            }
            $unitDescriptor = $unitDescriptor ?: 'N/A';

            if ($unitDescriptor !== 'N/A' && function_exists('normalizeLocationText')) {
                $unitDescriptor = normalizeLocationText($unitDescriptor) ?: $unitDescriptor;
            }

            $totalTermDisplay = $cofo->total_term ?? null;
            if (!empty($totalTermDisplay) || $totalTermDisplay === 0 || $totalTermDisplay === '0') {
                $termString = trim((string) $totalTermDisplay);
                $totalTermDisplay = stripos($termString, 'year') !== false ? $termString : $termString . ' years';
            } else {
                $totalTermDisplay = 'N/A';
            }
        @endphp

        <div class="bg-white rounded-md shadow-sm p-6 space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Certificate Preview</h2>
                    <p class="text-sm text-gray-500">All updates reflect immediately in the embedded print layout.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="printPreviewButton" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Print
                    </button>
                    <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 border border-blue-600 text-blue-600 text-sm font-medium rounded-md hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        Open in New Tab
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                <div class="lg:col-span-3 space-y-3">
                    <div class="cofo-preview-wrapper">
                        <iframe
                            src="{{ $printUrl }}"
                            title="Certificate of Occupancy Preview"
                            loading="lazy"
                            class="cofo-preview-frame"
                            referrerpolicy="no-referrer"
                        ></iframe>
                    </div>
                    <p class="text-xs text-gray-500">If the preview does not load, use the &ldquo;Open in New Tab&rdquo; button above.</p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-md p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Certificate details</h3>
                    <dl class="space-y-3 text-sm text-gray-700">
                        <div>
                            <dt class="font-medium text-gray-500">File Number</dt>
                            <dd>{{ $cofo->file_no ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Certificate Reference</dt>
                            <dd>{{ $cofo->certificate_number ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Land Use</dt>
                            <dd>{{ $landUseDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Unit / Location</dt>
                            <dd>{{ $unitDescriptor }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Holder</dt>
                            <dd>{{ $cofo->holder_name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Address</dt>
                            <dd>{{ $cofo->holder_address ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Term</dt>
                            <dd>{{ $totalTermDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Start Date</dt>
                            <dd>{{ $startDateDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Issued Date</dt>
                            <dd>{{ $issuedDateDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Signed By</dt>
                            <dd>{{ $cofo->signed_by ?? '' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Signatory Title</dt>
                            <dd>{{ $cofo->signed_title ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @include($footerPartial ?? 'admin.footer')
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const printPreviewButton = document.getElementById('printPreviewButton');
        const printUrl = @json($printUrl);

        if (printPreviewButton) {
            printPreviewButton.addEventListener('click', function() {
                const printWindow = window.open(printUrl, '_blank');

                if (!printWindow) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Popup Blocked',
                            text: 'Please allow popups for this site to print the certificate.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Please allow popups for this site to print the certificate.');
                    }
                    return;
                }

                const pollTimer = setInterval(function() {
                    if (printWindow.document && printWindow.document.readyState === 'complete') {
                        clearInterval(pollTimer);
                        printWindow.focus();
                        printWindow.print();
                    }
                }, 300);

                setTimeout(function() {
                    clearInterval(pollTimer);
                }, 10000);
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'generate') {
            Swal.fire({
                title: 'Certificate Generated',
                text: 'The Certificate of Occupancy has been successfully generated.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        }
    });
</script>
@endsection
