@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Main Content Container -->
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <!-- Header with back button -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">{{ $PageTitle }}</h2>
                <a href="{{ route('attribution.index') }}" 
                   class="flex items-center space-x-2 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Back to List</span>
                </a>
            </div>

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

            <!-- Edit Form -->
            <form method="POST" action="{{ route('attribution.update', $survey->ID) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- File Number -->
                    <div>
                        <label for="fileno" class="block text-sm font-medium text-gray-700 mb-2">File Number</label>
                        <input type="text" id="fileno" name="fileno" value="{{ old('fileno', $survey->fileno) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('fileno')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Plot Number -->
                    <div>
                        <label for="plot_no" class="block text-sm font-medium text-gray-700 mb-2">Plot Number</label>
                        <input type="text" id="plot_no" name="plot_no" value="{{ old('plot_no', $survey->plot_no) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('plot_no')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Block Number -->
                    <div>
                        <label for="block_no" class="block text-sm font-medium text-gray-700 mb-2">Block Number</label>
                        <input type="text" id="block_no" name="block_no" value="{{ old('block_no', $survey->block_no) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('block_no')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Approved Plan Number -->
                    <div>
                        <label for="approved_plan_no" class="block text-sm font-medium text-gray-700 mb-2">Approved Plan Number</label>
                        <input type="text" id="approved_plan_no" name="approved_plan_no" value="{{ old('approved_plan_no', $survey->approved_plan_no) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('approved_plan_no')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- TP Plan Number -->
                    <div>
                        <label for="tp_plan_no" class="block text-sm font-medium text-gray-700 mb-2">TP Plan Number</label>
                        <input type="text" id="tp_plan_no" name="tp_plan_no" value="{{ old('tp_plan_no', $survey->tp_plan_no) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('tp_plan_no')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Layout Name -->
                    <div>
                        <label for="layout_name" class="block text-sm font-medium text-gray-700 mb-2">Layout Name</label>
                        <input type="text" id="layout_name" name="layout_name" value="{{ old('layout_name', $survey->layout_name) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('layout_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- District Name -->
                    <div>
                        <label for="district_name" class="block text-sm font-medium text-gray-700 mb-2">District Name</label>
                        <input type="text" id="district_name" name="district_name" value="{{ old('district_name', $survey->district_name) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('district_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- LGA Name -->
                    <div>
                        <label for="lga_name" class="block text-sm font-medium text-gray-700 mb-2">LGA Name</label>
                        <input type="text" id="lga_name" name="lga_name" value="{{ old('lga_name', $survey->lga_name) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('lga_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Survey By -->
                    <div>
                        <label for="survey_by" class="block text-sm font-medium text-gray-700 mb-2">Survey By</label>
                        <input type="text" id="survey_by" name="survey_by" value="{{ old('survey_by', $survey->survey_by) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('survey_by')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Survey Date -->
                    <div>
                        <label for="survey_by_date" class="block text-sm font-medium text-gray-700 mb-2">Survey Date</label>
                        <input type="date" id="survey_by_date" name="survey_by_date" value="{{ old('survey_by_date', $survey->survey_by_date) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('survey_by_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Survey Plan Upload -->
                    <div class="md:col-span-2">
                        <label for="survey_plan_path" class="block text-sm font-medium text-gray-700 mb-2">Survey Plan</label>
                        <input type="file" id="survey_plan_path" name="survey_plan_path" 
                               accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        @if($survey->survey_plan_path)
                            <p class="text-sm text-gray-600 mt-1">
                                Current file: <a href="{{ asset('storage/' . $survey->survey_plan_path) }}" target="_blank" class="text-blue-600 hover:underline">View Current Plan</a>
                            </p>
                        @endif
                        @error('survey_plan_path')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="{{ route('attribution.index') }}" 
                       class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        Update Survey
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
    });
</script>
@endsection