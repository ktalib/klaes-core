@extends('layouts.app')
@section('page-title')
    {{ __('SURVEY MODULE') }}
@endsection

 
@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            <!-- Survey Type Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px" id="surveyTabs" role="tablist">
                        <li class="mr-2" role="presentation">
                            <a href="{{ route('survey_records.index') }}" 
                               class="tab-btn {{ !request()->is('survey_records/st-survey') && !request()->is('survey/sltr-survey') ? 'active text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }} py-3 px-6 font-medium text-base rounded-t-lg border-b-2">
                                Regular Survey
                            </a>
                        </li>
                        <li class="mr-2" role="presentation">
                            <a href="{{ route('survey.st_survey') }}" 
                               class="tab-btn {{ request()->is('survey_records/st-survey') ? 'active text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }} py-3 px-6 font-medium text-base rounded-t-lg border-b-2">
                                ST Survey
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="{{ route('survey.sltr') }}" 
                               class="tab-btn {{ request()->is('survey_records/sltr-survey') ? 'active text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }} py-3 px-6 font-medium text-base rounded-t-lg border-b-2">
                                SLTR Survey
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Tab Content -->
            <div id="surveyTabContent">
                <!-- Regular Survey Content - Only show if we're on the main survey page -->
                @if(!request()->is('survey_records/st-survey') && !request()->is('survey_records/sltr-survey'))
                <div id="regular-content" class="tab-content active">
                    <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold">Regular Survey</h2>
                            {{-- <div class="flex items-center space-x-4">
                                <button class="flex items-center space-x-2 px-4 py-2 bg-gray-900 text-white rounded-md">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                                    <span>New Regular Survey</span>
                                </button>
                            </div> --}}
                        </div>
                        <div class="p-12 text-center text-gray-500">
                            <i data-lucide="file-text" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                            <h3 class="text-lg font-medium mb-2">No Regular Surveys Yet</h3>
                            <p>Start by creating a new survey using the button above</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>
   
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.classList.remove('text-blue-600');
                    btn.classList.remove('border-blue-600');
                    btn.classList.add('border-transparent');
                });
                
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                });
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                button.classList.add('text-blue-600');
                button.classList.add('border-blue-600');
                button.classList.remove('border-transparent');
                
                const tabId = button.getAttribute('data-tab');
                const activeContent = document.getElementById(tabId);
                activeContent.classList.remove('hidden');
                activeContent.classList.add('active');
            });
        });
    });
</script>

<style>
    .tab-btn.active {
        font-weight: 600;
    }
    
    /* Animation for tab transitions */
    .tab-content {
        transition: all 0.3s ease-in-out;
    }
</style>
    
@endsection
