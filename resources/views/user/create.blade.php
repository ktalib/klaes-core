<div class="modal-dialog w-full max-w-7xl shadow-none" role="document">
    <div class="mx-auto w-full">
        <div class="bg-white rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">
                    @if (\Auth::user()->type == 'super admin')
                        {{ __('Create Customer') }}
                    @else
                        {{ __('Create User') }}
                    @endif
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 absolute top-4 right-4" data-dismiss="modal" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{ Form::open(['url' => 'users', 'method' => 'post', 'enctype' => 'multipart/form-data']) }}

            @php
                $workStationOptions = collect($workStations ?? [])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->mapWithKeys(fn ($value) => [$value => $value])
                    ->toArray();

                $staffTypeOptions = !empty($staffTypeOptions ?? [])
                    ? $staffTypeOptions
                    : [
                        'MDC' => __('MDC - Mass Data Capture'),
                        'MLPP' => __('MLPP - Ministry of Land and Physical Planning'),
                    ];

                $paymentStructureOptions = is_array($paymentStructures ?? null)
                    ? $paymentStructures
                    : [];

                $shiftDropdown = collect($attendanceShifts ?? [])
                    ->mapWithKeys(fn ($details, $code) => [
                        $code => $details['label'] ?? strtoupper($code),
                    ])
                    ->toArray();
                $defaultShift = array_key_first($shiftDropdown);
            @endphp

            @php
                $autoDeactivateOldRaw = old('auto_deactivate');
                $autoDeactivateInitial = $autoDeactivateOldRaw !== null ? (bool) ((int) $autoDeactivateOldRaw) : false;
                $lastMdcAutoDeactivateInitial = $autoDeactivateOldRaw !== null ? (bool) ((int) $autoDeactivateOldRaw) : true;
            @endphp

            <div class="p-6 overflow-y-auto max-h-[80vh]" x-data="{
                selectedDept: '',
                selectedDeptName: '',
                showAll: false,
                userTypeId: '',
                userTypeName: '',
                userLevelName: '',
                selectedStaffTypeCategory: @json(old('staff_type_category')),
                selectedStaffTypeCategoryLabel: '',
                autoDeactivateValue: @json($autoDeactivateInitial),
                lastMdcAutoDeactivateValue: @json($lastMdcAutoDeactivateInitial),
                pcAccessValue: @json((bool) old('is_pc_access', 0)),
                lastMdcPcAccessValue: @json((bool) old('is_pc_access', 0)),
                leaveStartDate: @json(old('leave_start_date')),
                leaveEndDate: @json(old('leave_end_date')),

                get leaveDurationDays() {
                    if (!this.leaveStartDate || !this.leaveEndDate) {
                        return null;
                    }
                    const start = new Date(this.leaveStartDate);
                    const end = new Date(this.leaveEndDate);
                    if (isNaN(start) || isNaN(end) || end < start) {
                        return null;
                    }
                    return Math.round((end - start) / 86400000) + 1;
                },

                init() {
                    this.$nextTick(() => {
                        this.$watch('autoDeactivateValue', (value) => {
                            if (this.canEditAdvancedFields) {
                                this.lastMdcAutoDeactivateValue = value;
                            }
                        });

                        this.$watch('pcAccessValue', (value) => {
                            if (this.canEditAdvancedFields) {
                                this.lastMdcPcAccessValue = value;
                            }
                        });

                        if (this.$refs.staffTypeCategory) {
                            this.onStaffTypeCategoryChange({ target: this.$refs.staffTypeCategory });
                        }
                    });
                },

                onStaffTypeCategoryChange(event) {
                    this.selectedStaffTypeCategory = event.target?.value ?? '';
                    this.selectedStaffTypeCategoryLabel = event.target?.selectedOptions?.[0]?.text ?? '';
                    this.updateAutoDeactivateForStaffType();
                },

                get canEditAdvancedFields() {
                    const value = (this.selectedStaffTypeCategory || '').toString().toLowerCase();
                    const label = (this.selectedStaffTypeCategoryLabel || '').toString().toLowerCase();
                    if (value) {
                        return value === 'mdc' || value.startsWith('mdc ');
                    }
                    return label.includes('mdc - mass data capture');
                },

                get shouldAutoEnableAccess() {
                    const value = (this.selectedStaffTypeCategory || '').toString().toLowerCase();
                    const label = (this.selectedStaffTypeCategoryLabel || '').toString().toLowerCase();
                    if (value) {
                        return value === 'mlpp' || value.startsWith('mlpp ') || value === 'mdcm' || value.startsWith('mdcm ');
                    }
                    return label.includes('mlpp - ministry of land and physical planning') || label.includes('mdcm - mdc management');
                },

                updateAutoDeactivateForStaffType() {
                    if (this.shouldAutoEnableAccess) {
                        this.autoDeactivateValue = true;
                        this.pcAccessValue = true;
                        return;
                    }

                    if (this.canEditAdvancedFields) {
                        this.autoDeactivateValue = this.lastMdcAutoDeactivateValue ?? true;
                        this.pcAccessValue = this.lastMdcPcAccessValue ?? false;
                        return;
                    }
                },

                // A Supper Admin holds the whole system, so Step 1 (Department) and
                // Step 4 (Roles) stop applying — the grant is the user type itself.
                get isSupperAdmin() {
                    return this.userTypeName === 'Supper Admin';
                },

                // Auto-determine user level based on user type (Step 3)
                autoSetUserLevel(userTypeName) {
                    // Clear previous level
                    this.userLevelName = '';

                    // Auto-determine level based on user type
                    switch(userTypeName) {
                        case 'Supper Admin':
                            this.userLevelName = 'Highest';
                            break;
                        case 'Management':
                            this.userLevelName = 'Highest';
                            break;
                        case 'Operations':
                            this.userLevelName = 'High';
                            break;
                        case 'System':
                            this.userLevelName = 'Highest';
                            break;
                        case 'User':
                            this.userLevelName = 'Lowest';
                            break;
                        case 'ALL':
                            this.userLevelName = 'Lowest';
                            break;
                        default:
                            this.userLevelName = '';
                    }
                    
                    console.log('Auto-set level:', this.userLevelName, 'for user type:', userTypeName);
                },
                
                checkAll() {
                    document.querySelectorAll('#roles_grid > div').forEach(el => {
                        const isVisible = el.style.display !== 'none' && !el.hasAttribute('x-show') || 
                                         (el.hasAttribute('x-show') && el.offsetParent !== null);
                        if (isVisible) {
                            const checkbox = el.querySelector('input[type=checkbox]');
                            if (checkbox) checkbox.checked = true;
                        }
                    });
                },
                
                uncheckAll() {
                    document.querySelectorAll('#roles_grid > div').forEach(el => {
                        const isVisible = el.style.display !== 'none' && !el.hasAttribute('x-show') || 
                                         (el.hasAttribute('x-show') && el.offsetParent !== null);
                        if (isVisible) {
                            const checkbox = el.querySelector('input[type=checkbox]');
                            if (checkbox) checkbox.checked = false;
                        }
                    });
                },
                
                showAllRoles() {
                    this.showAll = true;
                    this.selectedDept = '';
                },
                
                // Step 4: Display Available Roles based on Department + User Type + Level
                shouldShowRole(roleUserType, roleLevel, roleName, roleDeptId) {
                    // If showing all roles, show everything
                    if (this.showAll) {
                        return true;
                    }
                    
                    // Department filtering (Step 1)
                    if (this.selectedDept) {
                        const roleDepId = String(roleDeptId);
                        const selectedDepId = String(this.selectedDept);
                        
                        // Hide roles that belong to other departments (unless they're universal)
                        if (roleDepId !== 'null' && roleDepId !== '' && roleDepId !== 'undefined' && roleDepId !== selectedDepId) {
                            return false;
                        }
                    }
                    
                    // User Type and Level filtering (Steps 2 & 3)
                    if (this.userTypeName && this.userLevelName) {
                        // Always show ALL user_type roles
                        if (roleUserType === 'ALL') {
                            return true;
                        }
                        
                        // Show roles that match the selected user type and level
                        if (roleUserType === this.userTypeName && roleLevel === this.userLevelName) {
                            return true;
                        }
                        
                        // Hierarchical access: higher levels can access lower level roles
                        if (this.userTypeName === 'Management') {
                            // Management can access Operations and User roles
                            if (roleUserType === 'Operations' || roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'Operations') {
                            // Operations can access User roles
                            if (roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'System') {
                            // System can access all role types
                            return true;
                        }
                        
                        // If we reach here and user type/level are selected, hide roles that don't match
                        return false;
                    }
                    
                    // If only user type is selected (no level yet)
                    if (this.userTypeName && !this.userLevelName) {
                        // Always show ALL user_type roles
                        if (roleUserType === 'ALL') {
                            return true;
                        }
                        
                        // Show roles that match the selected user type (any level)
                        if (roleUserType === this.userTypeName) {
                            return true;
                        }
                        
                        // Apply hierarchical access rules
                        if (this.userTypeName === 'Management') {
                            if (roleUserType === 'Operations' || roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'Operations') {
                            if (roleUserType === 'User') {
                                return true;
                            }
                        }
                        
                        if (this.userTypeName === 'System') {
                            return true;
                        }
                        
                        return false;
                    }
                    
                    // If no user type/level selected, show all roles (filtered by department only)
                    return true;
                }
            }">
                <div class="flex flex-wrap -mx-2">
                    @if (\Auth::user()->type != 'super admin')
                        <div class="w-full px-3">
                            {{-- Basic Information Section --}}
                            <div class="mb-6 space-y-5 rounded-lg border border-gray-200 bg-gray-50 p-6">
                                <h4 class="text-md font-medium text-gray-800">Basic Information</h4>

                                {{-- Passport photo leads the form. It is the first thing an
                                     administrator has in hand when creating an account, and the
                                     account is held by RequireProfilePhoto until one exists -- so
                                     burying it under the pay and leave fields invited a user being
                                     saved without the one thing that unlocks them. edit.blade.php
                                     has always shown it first; this brings create into line. --}}
                                <div class="flex flex-wrap -mx-2 mb-4">
                                    {{-- Passport Upload --}}
                                    <div class="w-full px-2 mb-4" x-data="{ 
                                        previewUrl: null,
                                        fileName: '',
                                        fileSize: '',
                                        showPreview: false,
                                        
                                        handleFileSelect(event) {
                                            const file = event.target.files[0];
                                            if (file) {
                                                // Validate file size (2MB = 2 * 1024 * 1024 bytes)
                                                if (file.size > 2 * 1024 * 1024) {
                                                    alert('File size must be less than 2MB');
                                                    event.target.value = '';
                                                    this.clearPreview();
                                                    return;
                                                }
                                                
                                                // Validate file type
                                                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                                                if (!allowedTypes.includes(file.type)) {
                                                    alert('Please select a valid image file (JPG, PNG, or GIF)');
                                                    event.target.value = '';
                                                    this.clearPreview();
                                                    return;
                                                }
                                                
                                                this.fileName = file.name;
                                                this.fileSize = this.formatFileSize(file.size);
                                                
                                                const reader = new FileReader();
                                                reader.onload = (e) => {
                                                    this.previewUrl = e.target.result;
                                                    this.showPreview = true;
                                                };
                                                reader.readAsDataURL(file);
                                            } else {
                                                this.clearPreview();
                                            }
                                        },
                                        
                                        clearPreview() {
                                            this.previewUrl = null;
                                            this.fileName = '';
                                            this.fileSize = '';
                                            this.showPreview = false;
                                        },
                                        
                                        formatFileSize(bytes) {
                                            if (bytes === 0) return '0 Bytes';
                                            const k = 1024;
                                            const sizes = ['Bytes', 'KB', 'MB'];
                                            const i = Math.floor(Math.log(bytes) / Math.log(k));
                                            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                                        },
                                        
                                        removeFile() {
                                            document.getElementById('passport_input').value = '';
                                            this.clearPreview();
                                        }
                                    }">
                                        <div>
                                            {{ Form::label('passport', __('Passport Photo'), ['class' => 'block text-sm font-medium text-gray-700 mb-1']) }}
                                            <div class="text-xs text-gray-500 mb-2">Upload a passport-sized photo (JPG, PNG, or GIF format, max 2MB)</div>
                                            
                                            {{-- Custom Upload Button --}}
                                            <div class="relative">
                                                <input type="file" 
                                                    name="profile" 
                                                    id="passport_input"
                                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                    accept="image/jpeg,image/png,image/gif"
                                                     
                                                    @change="handleFileSelect($event)"   />
                                                
                                                <div x-show="!showPreview" class="flex items-center justify-center w-full">
                                                    <label for="passport_input" class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-300 border-dashed rounded-lg cursor-pointer bg-indigo-50 hover:bg-indigo-100 transition-colors duration-200">
                                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                            <svg class="w-8 h-8 mb-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                            </svg>
                                                            <p class="mb-2 text-sm text-indigo-600 font-medium">
                                                                <span class="font-semibold">Click to upload</span> passport photo
                                                            </p>
                                                            <p class="text-xs text-indigo-500">JPG, PNG or GIF (max. 2MB)</p>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            {{-- Preview Section --}}
                                            <div x-show="showPreview" class="mt-4">
                                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                                    <div class="p-4">
                                                        <div class="flex items-center space-x-4">
                                                            {{-- Image Preview --}}
                                                            <div class="flex-shrink-0">
                                                                <div class="relative">
                                                                    <img :src="previewUrl" 
                                                                         alt="Passport Preview" 
                                                                         class="w-20 h-20 object-cover rounded-lg border-2 border-indigo-200 shadow-sm">
                                                                    <div class="absolute -top-2 -right-2">
                                                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-green-500 text-white rounded-full text-xs">
                                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                            </svg>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            {{-- File Info --}}
                                                            <div class="flex-1 min-w-0">
                                                                <div class="flex items-center space-x-2">
                                                                    <svg class="w-4 h-4 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    <span class="text-sm font-medium text-gray-900 truncate" x-text="fileName"></span>
                                                                </div>
                                                                <div class="mt-1 flex items-center space-x-4">
                                                                    <span class="text-xs text-gray-500" x-text="fileSize"></span>
                                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                        </svg>
                                                                        Ready to upload
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            {{-- Action Buttons --}}
                                                            <div class="flex-shrink-0 flex space-x-2">
                                                                {{-- Change Photo Button --}}
                                                                <label for="passport_input" class="inline-flex items-center px-3 py-1.5 border border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 cursor-pointer transition-colors duration-200">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                                                    </svg>
                                                                    Change
                                                                </label>
                                                                
                                                                {{-- Remove Button --}}
                                                                <button type="button" 
                                                                        @click="removeFile()"
                                                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                    </svg>
                                                                    Remove
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('username', __('Username'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-id-badge"></i></span>
                                            {{ Form::text('username', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter Username'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('password', __('Password'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
                                            {{ Form::password('password', [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter password'),
                                                'required' => 'required',
                                                'minlength' => '6'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('first_name', __('First Name'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-user"></i></span>
                                            {{ Form::text('first_name', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter First Name'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('last_name', __('Last Name'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-user-tag"></i></span>
                                            {{ Form::text('last_name', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter Last Name'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        {{ Form::label('phone', __('Phone Number'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-phone"></i></span>
                                            {{ Form::text('phone', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => '08012345678',
                                                'required' => 'required',
                                                'data-phone-ng' => true,
                                                'data-phone-ng-required' => true,
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('email', __('Email'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-envelope"></i></span>
                                            {{ Form::text('email', null, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter email')
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        {{ Form::label('staff_type_category', __('Staff Type Category'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-users"></i></span>
                                            {{ Form::select('staff_type_category', $staffTypeOptions, old('staff_type_category'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select staff type'),
                                                'required' => 'required',
                                                'x-ref' => 'staffTypeCategory',
                                                'x-on:change' => 'onStaffTypeCategoryChange($event)'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('work_station', __('Work Station'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-building"></i></span>
                                            {{ Form::select('work_station', $workStationOptions, old('work_station'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select Work Station'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Assign the main work station/module this user belongs to.') }}</p>
                                    </div>
                                    <div>
                                        {{ Form::label('payment_structure_id', __('Standard Payment Structure'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-coins"></i></span>
                                            {{ Form::select('payment_structure_id', $paymentStructureOptions, old('payment_structure_id'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select structure (optional)'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Pick a preset rate by role/work days; leave empty for manual override.') }}</p>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        {{ Form::label('work_days_per_week', __('No of Work Days'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-calendar-check"></i></span>
                                            {{ Form::select('work_days_per_week', [
                                                2 => __('2 days'),
                                                5 => __('5 days'),
                                                7 => __('7 days'),
                                            ], old('work_days_per_week'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select work days'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('man_hours_per_day', __('No of Man Hours'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-clock"></i></span>
                                            {{ Form::select('man_hours_per_day', [
                                                4 => __('4 hours'),
                                                8 => __('8 hours'),
                                            ], old('man_hours_per_day'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select man hours'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div>
                                        {{ Form::label('base_salary_override', __('Base Salary Override'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-money-bill-wave"></i></span>
                                            {{ Form::number('base_salary_override', old('base_salary_override'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Enter custom base salary (optional)'),
                                                'step' => '0.01',
                                                'min' => '0',
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Overrides preset amount when provided.') }}</p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2 mb-4">
                                    <div>
                                        {{ Form::label('shift_code', __('Assigned Shift'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-business-time"></i></span>
                                            {{ Form::select('shift_code', $shiftDropdown, old('shift_code', $defaultShift), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select shift'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Controls attendance window and lateness rules.') }}</p>
                                    </div>
                                    <div>
                                        {{ Form::label('auto_deactivate', __('Auto Deactivation'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::hidden('auto_deactivate', $autoDeactivateInitial ? 1 : 0, [
                                                'x-bind:value' => 'autoDeactivateValue ? 1 : 0'
                                            ]) }}
                                            <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                {{ Form::checkbox('auto_deactivate', 1, $autoDeactivateInitial, [
                                                    'class' => 'sr-only peer',
                                                    'disabled' => 'disabled',
                                                    'x-bind:disabled' => '!canEditAdvancedFields',
                                                    'x-model' => 'autoDeactivateValue'
                                                ]) }}
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <span class="text-sm font-medium text-gray-700 leading-5">{{ __('Suspend automatically after repeated absences') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Disable to exempt this account from automated deactivation.') }}</p>
                                    </div>
                                </div>

                                {{-- PC Access Toggle --}}
                                <div class="grid gap-4 md:grid-cols-2 mb-4">
                                    <div>
                                        {{ Form::label('is_pc_access', __('PC Access'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::hidden('is_pc_access', old('is_pc_access', 0), [
                                                'x-bind:value' => 'pcAccessValue ? 1 : 0'
                                            ]) }}
                                            <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                {{ Form::checkbox('is_pc_access', 1, old('is_pc_access', 0) == 1, [
                                                    'class' => 'sr-only peer',
                                                    'disabled' => 'disabled',
                                                    'x-bind:disabled' => '!canEditAdvancedFields',
                                                    'x-model' => 'pcAccessValue'
                                                ]) }}
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <span class="text-sm font-medium text-gray-700 leading-5">{{ __('User has PC/computer access') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Toggle OFF for staff requiring manual attendance.') }}</p>
                                    </div>
                                </div>

                                {{-- Holiday/Leave & Deputy Redirection (MLPP staff) --}}
                                <div class="mb-4 p-4 rounded-lg border border-amber-200 bg-amber-50">
                                    <h5 class="text-sm font-semibold text-amber-800 mb-1">
                                        <i class="fas fa-umbrella-beach mr-1"></i>{{ __('Holiday/Leave & Deputy Redirection') }}
                                    </h5>
                                    <p class="text-xs text-amber-700 mb-3">{{ __('Applicable to MLPP staff — record leave status and who should receive their file/task redirects while away.') }}</p>

                                    <div class="mb-4">
                                        {{ Form::label('is_on_leave', __('Currently On Leave/Holiday'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::checkbox('is_on_leave', 1, old('is_on_leave', false), [
                                                'class' => 'h-4 w-4 mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500'
                                            ]) }}
                                            <span class="text-sm text-gray-700 leading-5">{{ __('Mark this staff member as currently on leave/holiday') }}</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            {{ Form::label('leave_start_date', __('Leave Start Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_start_date', old('leave_start_date'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'x-model' => 'leaveStartDate'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('leave_end_date', __('Leave End Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_end_date', old('leave_end_date'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'x-model' => 'leaveEndDate'
                                            ]) }}
                                        </div>
                                    </div>
                                    <div class="mb-4 mt-1 text-xs font-medium text-amber-700" x-show="leaveDurationDays !== null" x-cloak>
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        <span x-text="leaveDurationDays"></span> <span x-text="leaveDurationDays === 1 ? 'day' : 'days'"></span> {{ __('of leave') }}
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            {{ Form::label('deputy_user_id', __('Deputy (Redirect To)'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::select('deputy_user_id', $deputyOptions ?? [], old('deputy_user_id'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm bg-white',
                                                'placeholder' => __('Select deputy')
                                            ]) }}
                                            <p class="mt-1 text-xs text-gray-500">{{ __('Colleague who receives this user\'s file/task redirects while on leave.') }}</p>
                                        </div>
                                        <div>
                                            {{ Form::label('leave_reason', __('Leave Reason'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::text('leave_reason', old('leave_reason'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'placeholder' => __('e.g. Annual Leave, Sick Leave, Study Leave')
                                            ]) }}
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 mt-4">
                                        <div>
                                            {{ Form::label('out_of_office_from', __('Out of Office Date From'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_from', old('out_of_office_from'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('out_of_office_to', __('Out of Office Date To'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_to', old('out_of_office_to'), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>

                            </div>
                            

                            {{-- Hierarchical Role Management Section --}}
                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="text-md font-medium text-blue-800 mb-3">Hierarchical Role Management</h4>
                                <div class="text-sm text-blue-700 mb-4">
                                    Follow the steps below to assign user roles. Each step filters the next to ensure data consistency.
                                </div>
                                
                                <div class="flex flex-wrap -mx-2 mb-4">
                                    {{-- Step 1: Department Selection --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('department_id', __('Step 1: Select Department'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2" x-show="!isSupperAdmin">Choose the department to filter available roles</div>
                                            <div class="text-xs text-amber-600 mb-2" x-show="isSupperAdmin" style="display: none;">Optional for a Supper Admin — the grant is not scoped to a department.</div>
                                            {{-- Required for every user type except Supper Admin, which is not department-scoped. --}}
                                            {{-- x-model, not just @change: Step 1 is the single source of truth for the
                                                 department, and the Module Permissions grid below filters on the same
                                                 value. Binding the element means a change made in either place moves
                                                 the other, and the posted department_id follows. --}}
                                            {{ Form::select('department_id', $departments, null, [
                                                'class' => 'w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500',
                                                'id' => 'department_id',
                                                'placeholder' => 'Select Department',
                                                'x-model' => 'selectedDept',
                                                'x-bind:required' => '!isSupperAdmin',
                                                '@change' => 'selectedDeptName = $event.target.selectedOptions[0].text; showAll = !$event.target.value;'
                                            ]) }}
                                        </div>
                                    </div>
                                    {{-- Step 2: User Type Selection --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('user_type', __('Step 2: Select User Type'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">User level will be automatically determined</div>
                                            <select name="user_type" id="user_type"
                                                class="w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                                @change="userTypeName = $event.target.value; autoSetUserLevel(userTypeName);"
                                                required>
                                                <option value="">Select User Type</option>
                                                @foreach($userTypes as $userType)
                                                    <option value="{{ $userType->name }}">{{ $userType->name }}</option>
                                                @endforeach
                                                {{-- Not a user_types row: the whole-system grant lives in assign_role, so it
                                                     is offered here directly rather than through the lookup table. --}}
                                                <option value="Supper Admin">Supper Admin (full system access)</option>
                                            </select>
                                            <div class="text-xs text-amber-700 mt-2" x-show="isSupperAdmin" style="display: none;">
                                                <i class="fas fa-shield-alt mr-1"></i>Steps 1 and 4 are skipped — this account is saved with <strong>Supper Admin</strong> as its only role.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Step 3: Auto-populated User Level --}}
                                <div class="flex flex-wrap -mx-2 mb-4">
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div>
                                            {{ Form::label('user_level', __('Step 3: User Level (Auto-determined)'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">Automatically set based on selected user type</div>
                                            <div x-show="!userTypeName">
                                                <input type="text" 
                                                    class="w-full p-2 border border-blue-300 rounded-md text-sm bg-gray-100"
                                                    value="Select User Type First"
                                                    readonly>
                                            </div>
                                            <div x-show="userTypeName">
                                                <input type="text" 
                                                    class="w-full p-2 border border-blue-300 rounded-md text-sm bg-green-50 text-green-800 font-medium"
                                                    x-bind:value="userLevelName || 'Determining...'"
                                                    readonly>
                                                <input type="hidden" name="user_level" x-bind:value="userLevelName">
                                            </div>
                                        </div>
                                    </div>
                                    {{-- User Type to Level Mapping Info --}}
                                    <div class="w-full md:w-1/2 px-2 mb-4">
                                        <div class="text-xs text-blue-700 bg-blue-100 p-3 rounded-md">
                                            <strong>Auto-Level Mapping:</strong><br>
                                            • Supper Admin → Highest<br>
                                            • Management → Highest<br>
                                            • Operations → High<br>
                                            • System → Highest<br>
                                            • User → Lowest<br>
                                            • ALL → Lowest
                                        </div>
                                    </div>
                                </div>

                                {{-- Officer Rank (Seniority) — used to prioritise file search requests --}}
                                <div class="flex flex-wrap -mx-2">
                                    <div class="w-full md:w-1/2 px-2 mb-2">
                                        <div>
                                            {{ Form::label('rank', __('Officer Rank (Seniority)'), ['class' => 'block text-sm font-medium text-blue-800 mb-1']) }}
                                            <div class="text-xs text-blue-600 mb-2">Designation used to prioritise this officer's file search requests — the most senior requester is honored first.</div>
                                            <select name="rank" id="rank" onchange="toggleRankOther(this)"
                                                class="w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Select Rank (optional)</option>
                                                @foreach($ranks as $rankOption)
                                                    <option value="{{ $rankOption }}" {{ old('rank') === $rankOption ? 'selected' : '' }}>{{ $rankOption }}</option>
                                                @endforeach
                                                <option value="__other__" {{ old('rank') === '__other__' ? 'selected' : '' }}>Other (specify)…</option>
                                            </select>
                                            <input type="text" name="rank_other" id="rank_other" value="{{ old('rank_other') }}"
                                                placeholder="Enter new rank title"
                                                class="w-full mt-2 p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 {{ old('rank') === '__other__' ? '' : 'hidden' }}">
                                            <div class="text-xs text-blue-600 mt-1">A new rank you add here is saved to the rank list for future use.</div>
                                            <script>
                                                function toggleRankOther(sel) {
                                                    var other = document.getElementById('rank_other');
                                                    if (!other) return;
                                                    if (sel.value === '__other__') {
                                                        other.classList.remove('hidden');
                                                    } else {
                                                        other.classList.add('hidden');
                                                        other.value = '';
                                                    }
                                                }
                                            </script>
                                        </div>
                                    </div>
                                    <div class="w-full md:w-1/2 px-2 mb-2">
                                        <div class="text-xs text-blue-700 bg-blue-100 p-3 rounded-md">
                                            <strong>Seniority (honored first):</strong><br>
                                            @foreach($ranks as $rankOption)
                                                • {{ $rankOption }}<br>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Module permissions.
                                 Replaces the old flat 174-checkbox "Step 4" grid and the
                                 system-wide "User Actions" block, neither of which could say
                                 what a user may DO in a given module. Shared with edit.blade.php
                                 so the two screens cannot drift apart again. --}}
                            <div class="mt-6" x-show="!isSupperAdmin">
                                @include('user.partials.permission-grid', [
                                    'userRoles' => $userRoles,
                                    'departments' => $departments,
                                    'selectedRoles' => [],
                                    'grants' => [],
                                    'canGrantDelete' => $canGrantDelete ?? false,
                                ])

                                @include('user.partials.dfr-permissions-modal', [
                                    'mode' => 'create',
                                    'dfrPerms' => [],
                                    'frScb' => false,
                                ])
                            </div>

                            {{-- Step 4 stands down for a Supper Admin: the grant is not made of modules. --}}
                            <div class="mt-6 p-4 rounded-lg border border-amber-300 bg-amber-50" x-show="isSupperAdmin" style="display: none;">
                                <h4 class="text-md font-medium text-amber-800 mb-1">
                                    <i class="fas fa-shield-alt mr-2"></i>{{ __('Module permissions are not required for a Supper Admin') }}
                                </h4>
                                <p class="text-sm text-amber-700">
                                    {{ __('This account is saved with') }} <strong>Supper Admin</strong>
                                    {{ __('in its role field, which grants every module and every action. Anything ticked above is discarded on save.') }}
                                </p>
                            </div>
                    @endif
                </div>
            </div>
            <div class="px-6 py-3 bg-gray-50 text-right">
                {{ Form::submit(__('Create'), ['class' => 'inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']) }}
                <button type="button" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
