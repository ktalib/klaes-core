<!-- SuA Tab Applicant Information -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center gap-3 mb-6">
        <div class="p-2 bg-green-100 rounded-lg">
            <i data-lucide="user" class="h-5 w-5 text-green-600"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Applicant Information</h3>
    </div>

    <!-- Applicant Type Selection -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-4">
            Applicant Type <span class="text-red-500">*</span>
        </label>
        <div class="flex flex-wrap gap-4">
            <!-- Individual -->
            <div class="relative flex-1 min-w-[200px]">
                <input type="radio" id="sua_individual" name="sua_applicant_type" value="Individual" 
                       class="sr-only peer" required checked>
                <label for="sua_individual" 
                       class="flex items-center gap-3 px-5 py-3 bg-white border-2 border-blue-500 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all duration-200">
                    <div class="p-2 bg-blue-100 rounded-lg peer-checked:bg-blue-200">
                        <i data-lucide="user" class="h-5 w-5 text-blue-600"></i>
                    </div>
                    <div class="flex-1">
                        <span class="block text-sm font-medium text-gray-900">Individual</span>
                    </div>
                </label>
            </div>

            <!-- Corporate -->
            <div class="relative flex-1 min-w-[200px]">
                <input type="radio" id="sua_corporate" name="sua_applicant_type" value="Corporate" 
                       class="sr-only peer">
                <label for="sua_corporate" 
                       class="flex items-center gap-3 px-5 py-3 bg-white border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-green-500 peer-checked:bg-green-50 transition-all duration-200">
                    <div class="p-2 bg-green-100 rounded-lg peer-checked:bg-green-200">
                        <i data-lucide="building" class="h-5 w-5 text-green-600"></i>
                    </div>
                    <div class="flex-1">
                        <span class="block text-sm font-medium text-gray-900">Corporate</span>
                    </div>
                </label>
            </div>

            <!-- Multiple -->
            <div class="relative flex-1 min-w-[200px]">
                <input type="radio" id="sua_multiple" name="sua_applicant_type" value="Multiple" 
                       class="sr-only peer">
                <label for="sua_multiple" 
                       class="flex items-center gap-3 px-5 py-3 bg-white border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-purple-500 peer-checked:bg-purple-50 transition-all duration-200">
                    <div class="p-2 bg-purple-100 rounded-lg peer-checked:bg-purple-200">
                        <i data-lucide="users" class="h-5 w-5 text-purple-600"></i>
                    </div>
                    <div class="flex-1">
                        <span class="block text-sm font-medium text-gray-900">Multiple</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- Individual Fields (Default Visible) -->
    <div id="sua_individual_fields" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="sua_title" class="block text-sm font-medium text-gray-700 mb-2">
                    Title
                </label>
                <select id="sua_title" name="sua_title" 
                        class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select Title</option>
                    @if(isset($titles) && count($titles) > 0)
                        @foreach($titles as $title)
                            <option value="{{ $title->display_name }}">{{ $title->display_name }}</option>
                        @endforeach
                    @else
                        <option value="Mr">Mr</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Miss">Miss</option>
                        <option value="Ms">Ms</option>
                        <option value="Dr">Dr</option>
                        <option value="Prof">Prof</option>
                        <option value="Eng">Eng</option>
                        <option value="Arch">Arch</option>
                    @endif
                </select>
            </div>
            <div>
                <label for="sua_first_name" class="block text-sm font-medium text-gray-700 mb-2">
                    First Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="sua_first_name" name="sua_first_name" 
                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="sua_middle_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Middle Name
                </label>
                <input type="text" id="sua_middle_name" name="sua_middle_name" 
                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="sua_last_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Surname <span class="text-red-500">*</span>
                </label>
                <input type="text" id="sua_last_name" name="sua_last_name" 
                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    <!-- Corporate Fields (Hidden by default) -->
    <div id="sua_corporate_fields" class="space-y-4" style="display: none;">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="sua_corporate_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Company Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="sua_corporate_name" name="sua_corporate_name" 
                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="sua_rc_number" class="block text-sm font-medium text-gray-700 mb-2">
                    RC Number
                </label>
                <input type="text" id="sua_rc_number" name="sua_rc_number" 
                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    <!-- Multiple Applicants Fields (Hidden by default) -->
    <div id="sua_multiple_fields" class="space-y-4" style="display: none;">
        <div class="bg-gradient-to-br from-purple-50 to-blue-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i data-lucide="users" class="h-5 w-5 text-purple-600"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-gray-900">Multiple Owners</h4>
                    <p class="text-sm text-gray-600">Add multiple property owners for this application</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                <div class="flex items-center gap-2 mb-3">
                    <i data-lucide="crown" class="h-4 w-4 text-yellow-600"></i>
                    <span class="text-sm font-semibold text-gray-800">Primary Owner (Owner 1)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="sua_owner_title" class="block text-sm font-medium text-gray-700 mb-2">
                            Title
                        </label>
                        <select id="sua_owner_title" name="sua_primary_title" 
                                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Title</option>
                            @if(isset($titles) && count($titles) > 0)
                                @foreach($titles as $title)
                                    <option value="{{ $title->display_name }}">{{ $title->display_name }}</option>
                                @endforeach
                            @else
                                <option value="Mr">Mr</option>
                                <option value="Mrs">Mrs</option>
                                <option value="Miss">Miss</option>
                                <option value="Ms">Ms</option>
                                <option value="Dr">Dr</option>
                                <option value="Prof">Prof</option>
                                <option value="Eng">Eng</option>
                                <option value="Arch">Arch</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label for="sua_owner_first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="sua_owner_first_name" name="sua_primary_first_name" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Primary owner first name">
                    </div>
                    <div>
                        <label for="sua_owner_middle_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Middle Name
                        </label>
                        <input type="text" id="sua_owner_middle_name" name="sua_primary_middle_name" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Primary owner middle name">
                    </div>
                    <div>
                        <label for="sua_owner_last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Surname <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="sua_owner_last_name" name="sua_primary_last_name" 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Primary owner last name">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle SuA applicant type changes
            const suaRadios = document.querySelectorAll('input[name="sua_applicant_type"]');
            
            suaRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Hide all sections
                    document.getElementById('sua_individual_fields').style.display = 'none';
                    document.getElementById('sua_corporate_fields').style.display = 'none';
                    document.getElementById('sua_multiple_fields').style.display = 'none';
                    
                    // Show selected section
                    if (this.value === 'Individual') {
                        document.getElementById('sua_individual_fields').style.display = 'block';
                    } else if (this.value === 'Corporate') {
                        document.getElementById('sua_corporate_fields').style.display = 'block';
                    } else if (this.value === 'Multiple') {
                        document.getElementById('sua_multiple_fields').style.display = 'block';
                    }
                });
            });
        });
    </script>
</div>

    {{--
        Applicant contact, for the ST commissioning SMS.
        st_file_numbers recorded a name, a title and an RC number and no way to
        reach the person any of it describes, so this field and its column were
        added together. data-sms-phone attaches the shared-number check: a
        number already on file against many other files is almost certainly an
        agent's, and the officer is asked to confirm it before the SMS uses it.
    --}}
    <div class="mt-4">
        <label for="sua_applicant_phone" class="block text-sm font-medium text-gray-700 mb-2">
            Phone No of Applicant
        </label>
        <input type="text" id="sua_applicant_phone" name="applicant_phone"
               data-sms-phone
               class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="08031234567">
    </div>

