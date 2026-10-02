@extends('layouts.app')

@section('page-title')
    {{$PageTitle}}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')
<div class="flex-1 overflow-auto bg-gray-100 min-h-screen py-8">
     <!-- Header -->
     @include('admin.header')
     <!-- Document Card -->
     <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-lg p-10 border border-gray-200">
          <div class="flex items-center justify-between mb-8">
                <div>
                     <h2 class="text-xl font-bold text-gray-700 uppercase tracking-wide">Physical Planning Department</h2>
                     <p class="text-sm text-gray-500">Kano State Government</p>
                </div>
                <img src="{{ asset('public/images/logo.png') }}" alt="Logo" class="h-12 w-auto">
          </div>
          <div class="mb-6">
                <div class="flex flex-col md:flex-row md:justify-between mb-2">
                     <div>
                          <span class="font-semibold text-gray-600">FROM:</span>
                          <span class="ml-2 text-gray-800">Director Physical Planning</span>
                     </div>
                     <div>
                          <span class="font-semibold text-gray-600">TO:</span>
                          <span class="ml-2 text-gray-800">Permanent Secretary</span>
                     </div>
                </div>
                <div class="mt-2">
                     <span class="font-semibold text-gray-600">RE:</span>
                     <span class="ml-2 text-gray-800">Application for Sectional Titling in respect of <span class="border-b border-dotted border-gray-400 px-4">…………………………………….</span> by <span class="border-b border-dotted border-gray-400 px-4">……………………………</span></span>
                </div>
          </div>
          <div class="mb-6 text-gray-700 leading-relaxed">
                <p class="mb-4">Above subject refers, please.</p>
                <p class="mb-4">
                     The physical site inspection conducted revealed that this application is accessible, conforms with existing land use, and has shared common boundaries.
                </p>
                <p class="mb-4">
                     The property is sub-divided into <span class="border-b border-dotted border-gray-400 px-4">……………………………</span> portions. All portions are accessible, conform with existing land use, and share the following facilities: <span class="border-b border-dotted border-gray-400 px-8">……………………………………………………………</span> with their respective recommended measurements: <span class="border-b border-dotted border-gray-400 px-8">……………...</span>
                </p>
                <p>
                     In view of the above facts and Section <span class="border-b border-dotted border-gray-400 px-4">………..</span> of Kano State Sectional and Systematic Land Titling Registration Law 2024 (1446AH), this application may be considered for recommendation. If you have no objection, please.
                </p>
          </div>
          <div class="flex justify-between mt-10">
                <div class="text-center">
                     <div class="h-8"></div>
                     <span class="block border-t border-gray-400 w-48 mx-auto"></span>
                     <span class="block mt-2 text-gray-700 font-semibold">Director Physical Planning</span>
                </div>
                <div class="text-center">
                     <div class="h-8"></div>
                     <span class="block border-t border-gray-400 w-48 mx-auto"></span>
                     <span class="block mt-2 text-gray-700 font-semibold">Permanent Secretary</span>
                </div>
          </div>
     </div>
     <!-- Footer -->
     @include('admin.footer')
</div>
@endsection

@push('styles')
<style>
     /* Custom styles for print or further UI polish */
     @media print {
          body {
                background: #fff !important;
          }
          .shadow-lg, .border {
                box-shadow: none !important;
                border: none !important;
          }
     }
</style>
@endpush
