@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60 no-print">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen no-print">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('land-recommendations.index') }}" class="flex items-center gap-2 text-slate-600 hover:text-slate-900 transition font-medium">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Back to List
            </a>
            <div class="flex gap-3">
                @if($recommendation->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                    @if($recommendation->print_count < 2)
                        <a href="{{ route('land-recommendations.print', $recommendation->id) }}" target="_blank" class="px-6 py-2 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition shadow-lg flex items-center gap-2">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                            Print Recommendation ({{ 2 - $recommendation->print_count }} Left)
                        </a>
                    @else
                        <button type="button" disabled class="px-6 py-2 bg-slate-100 text-slate-400 font-bold rounded-lg cursor-not-allowed flex items-center gap-2 border border-slate-200">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                            Print Limit Reached
                        </button>
                    @endif
                @else
                    <button type="button" disabled class="px-6 py-2 bg-slate-100 text-slate-300 font-bold rounded-lg cursor-not-allowed flex items-center gap-2 border border-slate-200" title="Approve before printing">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print Recommendation (Locked)
                    </button>
                @endif

                @if($recommendation->status === \App\Models\LandRecommendation::STATUS_PENDING)
                    <a href="{{ route('land-recommendations.edit', $recommendation->id) }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
                        <i data-lucide="edit" class="h-4 w-4"></i>
                        Edit
                    </a>
                @else
                    <button type="button" disabled class="px-4 py-2 bg-slate-100 text-slate-300 font-bold rounded-lg cursor-not-allowed flex items-center gap-2 border border-slate-200" title="Cannot edit approved document">
                        <i data-lucide="edit" class="h-4 w-4"></i>
                        Edit (Locked)
                    </button>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-12 mb-12">
            @include('land_recommendations.templates.print_layout', ['recommendation' => $recommendation])
        </div>
    </div>
    @include('admin.footer')
</div>

<div class="print-only hidden">
    @include('land_recommendations.templates.print_layout', ['recommendation' => $recommendation])
</div>

<style>
    @media print {
        /* Hide layout elements */
        .no-print, 
        .sidebar, 
        [data-header-root], 
        #global-personal-alert,
        .personal-alert-toast { 
            display: none !important; 
        }

        /* Reset layout for printing */
        body, html { 
            background: white !important; 
            margin: 0 !important; 
            padding: 0 !important;
            height: auto !important;
            display: block !important;
            overflow: visible !important;
        }

        .print-only { 
            display: block !important;
            width: 100% !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }

        /* Ensure no extra padding/margins from parent containers */
        .flex-1, .py-12, .max-w-4xl {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            display: block !important;
        }
        
        .shadow-xl, .border { 
            box-shadow: none !important;
            border: none !important; 
        }
    }
</style>

@push('scripts')
<script>
    function approveRecommendation() {
        Swal.fire({
            title: 'Approve Recommendation?',
            text: "Once approved, this document can be printed up to 2 times.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Approve it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('{{ route("land-recommendations.approve", $recommendation->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error(response.statusText);
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Approved!', 'The recommendation has been approved.', 'success')
                .then(() => window.location.reload());
            }
        });
    }

    window.onafterprint = function() {
        fetch('{{ route("land-recommendations.log-print", $recommendation->id) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              console.log('Print logged:', data);
              if (data.success) {
                  window.location.reload();
              }
          })
          .catch(error => console.error('Error logging print:', error));
    };
</script>
@endpush
@endsection
