@extends('layouts.app')
@section('page-title')
    {{ __('Primary Application Form') }}
@endsection

{{-- Include CSS Assets --}}
@include('sectionaltitling.partials.assets.css')
@include('primaryform.assets.css.styles')

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

@section('content')
<div class="flex-1 overflow-auto">
    {{-- Header --}}
    @include('admin.header')
    
    {{-- Dashboard Content --}}
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold leading-tight text-gray-800">
                Primary Application Form
            </h2>
            <a href="{{ route('sectionaltitling.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Applications
            </a>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
            {{-- Loading Overlay --}}
            <div class="loading-overlay">
                <div class="loader"></div>
            </div>
            
            {{-- Main Form Card --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <form id="primaryApplicationForm" method="POST" action="{{ route('primaryform.store') }}" enctype="multipart/form-data">
                    @csrf
                    
                    {{-- Hidden Fields --}}
                    <input type="hidden" name="land_use" value="{{ request()->query('landuse', 'COMMERCIAL') }}">
                    <input type="hidden" name="np_fileno" value="{{ $npFileNo ?? '' }}">
                    <input type="hidden" name="serial_no" value="{{ $serialNo ?? '' }}">
                    <input type="hidden" name="current_year" value="{{ $currentYear ?? date('Y') }}">
                    
                    {{-- Step 1: Basic Information --}}
                    @include('primaryform.partials.steps.step1-basic')
                    
                    {{-- Step 2: Shared Areas --}}
                    @include('primaryform.partials.steps.step2-sharedareas')
                    
                    {{-- Step 3: Documents --}}
                    @include('primaryform.partials.steps.step3-documents')
                    
                    {{-- Step 4: Buyers List --}}
                    @include('primaryform.partials.steps.step4-buyers')
                    
                    {{-- Step 5: Summary --}}
                    @include('primaryform.partials.steps.step5-summary')
                </form>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    @include('admin.footer')
</div>

{{-- Print Template (Hidden) --}}
@include('primaryform.partials.print')

{{-- JavaScript Assets --}}
@include('primaryform.assets.js.scripts')

@endsection