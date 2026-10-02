 
@section('page-title')
    {{ __('PLANNING RECOMMENDATION') }}
@endsection

<style>
    @media print {
        body * {
            visibility: visible;
        }
        .no-print, button, footer, nav, .header, .sidebar {
            display: none !important;
        }
        .print-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>

 
<div class="print-container">
    <div class="p-6">
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            @include('actions.planning_recomm')
        </div>

        <div class="mt-4 no-print">
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i data-lucide="printer" class="w-4 h-4 mr-2"></i> Print
            </button>
            <button onclick="window.history.back()" class="inline-flex items-center px-4 py-2 ml-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back
            </button>
        </div>
    </div>
</div>
 
