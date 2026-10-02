@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex justify-between mb-4">
        <h1 class="text-2xl font-bold">GIS Data Details</h1>
        <x-print-button>Print GIS Report</x-print-button>
    </div>
    
    <!-- Your regular content here -->
    <div class="bg-white rounded shadow p-6">
        <!-- Regular view content -->
    </div>
    
    <!-- Hidden by default but shown when printing -->
    <div class="hidden">
        <x-gis-print-template :gisData="$gisData" />
    </div>
</div>
@endsection
