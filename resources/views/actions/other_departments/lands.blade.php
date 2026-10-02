<div id="final-tab" class="tab-content {{ request()->has('lands') ? 'active' : '' }}">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-4 border-b">
                    <h3 class="text-sm font-medium">Lands</h3>
                    <p class="text-xs text-gray-500"></p>
                </div>
                <input type="hidden" id="application_id" value="{{ $application->id }}">
                <input type="hidden" name="fileno" value="{{ $application->fileno }}">
                <div class="p-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="file-name" class="text-xs font-medium block">
                                FileNo
                            </label>
                            <input
                                id="file-fileno"
                                type="text"
                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                                value="{{ $application->fileno }}"
                                disabled
                            >
                        </div>
                        <div class="space-y-2">
                            <label for="file-name" class="text-xs font-medium block">
                                File Name
                            </label>
                            <input
                                id="file-name"
                                type="text"
                               value="@if ($application->applicant_type == 'individual')
                          {{ $application->applicant_title }}{{ $application->first_name }}{{ $application->surname }}
                        @elseif($application->applicant_type == 'corporate')
                          {{ $application->rc_number }} {{ $application->corporate_name }}
                        @elseif($application->applicant_type == 'multiple')
                          @php
                            $names = @json_decode($application->multiple_owners_names, true);
                            if (is_array($names) && count($names) > 0) {
                              echo implode(', ', $names);
                            } else {
                              echo $application->multiple_owners_names;
                            }
                          @endphp
                        @endif"
                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled>
                        </div>
                    </div>

                    {{-- 
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="land-type" class="text-xs font-medium block">
                                Land Type
                            </label>
                            <input
                                id="land-type"
                                type="text"
                                placeholder="Enter Land Type"
                                class="w-full p-2 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                        <div class="space-y-2">
                            <label for="land-size" class="text-xs font-medium block">
                                Land Size
                            </label>
                            <input
                                id="land-size"
                                type="text"
                                placeholder="Enter Land Size"
                                class="w-full p-2 border border-gray-300 rounded-md text-sm"
                            >
                        </div>
                    </div>
                    --}}

                    <hr class="my-4">

                    <div class="flex justify-between items-center">
                        <div class="flex gap-2">
                            <a
                                href="{{ route('sectionaltitling.primary') }}"
                                class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50"
                            >
                                <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                Back
                            </a>
                            <button
                                class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-sky-900 hover:bg-gray-50">
                                <i data-lucide="folder-git-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                EDMS
                            </button>
                            <button
                                class="flex items-center px-3 py-1 text-xs bg-green-700 text-white rounded-md hover:bg-gray-800"
                            >
                                <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                                Submit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>