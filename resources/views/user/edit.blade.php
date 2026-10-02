<div class="modal-dialog w-full max-w-7xl shadow-none" role="document">
    <div class="mx-auto w-full">
        <div class="bg-white rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Edit User') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 absolute top-4 right-4" data-dismiss="modal" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{ Form::model($user, ['route' => ['users.update', $user->id], 'method' => 'PUT', 'enctype' => 'multipart/form-data']) }}

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

                $selectedShift = old('shift_code', $user->shift_code);
                $autoDeactivateOldRaw = old('auto_deactivate');
                if ($autoDeactivateOldRaw !== null) {
                    $autoDeactivateInitial = (bool) ((int) $autoDeactivateOldRaw);
                } else {
                    $autoDeactivateInitial = (bool) ($user->auto_deactivate ?? 0);
                }
                $lastMdcAutoDeactivateInitial = $autoDeactivateInitial;

                // Step 2's prefill. An account created before "Supper Admin" was a user type
                // carries the grant only in assign_role, so the dropdown reads that too —
                // otherwise re-saving such a user would silently demote them.
                // Matched case-sensitively and exactly: the 224 legacy accounts carrying the
                // lowercase type "super admin" must NOT be re-tagged here, or saving one
                // would hand it the strict assign_role grant it does not have today.
                $supperAdminRole = \App\Http\Controllers\UserController::SUPPER_ADMIN_ROLE;
                $isSupperAdminUser = in_array($supperAdminRole, [
                    trim((string) $user->assign_role),
                    trim((string) $user->user_type),
                    trim((string) $user->type),
                ], true);
                $selectedUserType = old('user_type', $isSupperAdminUser
                    ? $supperAdminRole
                    : ($user->type ?? $user->user_type));
                // A Supper Admin promoted from an account that never had a level would
                // otherwise post an empty user_level and fail validation.
                $selectedUserLevel = old('user_level', $user->user_level)
                    ?: ($isSupperAdminUser ? 'Highest' : '');
            @endphp

            <div class="p-6 overflow-y-auto max-h-[80vh]" x-data="{
                selectedDept: {{ json_encode(old('department_id', $user->department_id ?? '')) }},
                selectedDeptName: '',
                showAll: false,
                userTypeId: '',
                userTypeName: {{ json_encode($selectedUserType ?? '') }},
                userLevelName: {{ json_encode($selectedUserLevel ?? '') }},
                selectedStaffTypeCategory: {{ json_encode(old('staff_type_category', $user->staff_type_category)) }},
                selectedStaffTypeCategoryLabel: '',
                autoDeactivateValue: {{ json_encode($autoDeactivateInitial) }},
                lastMdcAutoDeactivateValue: {{ json_encode($lastMdcAutoDeactivateInitial) }},
                pcAccessValue: {{ json_encode((bool) old('is_pc_access', $user->is_pc_access ?? 0)) }},
                lastMdcPcAccessValue: {{ json_encode((bool) old('is_pc_access', $user->is_pc_access ?? 0)) }},
                leaveStartDate: {{ json_encode(old('leave_start_date', optional($user->leave_start_date)->format('Y-m-d'))) }},
                leaveEndDate: {{ json_encode(old('leave_end_date', optional($user->leave_end_date)->format('Y-m-d'))) }},

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
                        this.pcAccessValue = this.lastMdcPcAccessValue ?? (this.pcAccessValue ?? false);
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

                                {{-- Passport photo. Existing value may be a public-disk path, a bare
                                     filename under upload/profile, or the legacy "avatar.png" placeholder,
                                     so the URL is resolved by the model accessor. --}}
                                <div class="flex flex-col items-center gap-4 rounded-lg border border-dashed border-gray-300 bg-white p-4 sm:flex-row sm:items-start">
                                    <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-full border-2 border-gray-200 bg-gray-100">
                                        <img id="editProfilePreview"
                                            src="{{ $user->profile_url ?? '' }}"
                                            alt="{{ __('Profile photo') }}"
                                            class="h-full w-full object-cover {{ $user->profile_url ? '' : 'hidden' }}">
                                        <div id="editProfilePlaceholder"
                                            class="flex h-full w-full items-center justify-center text-gray-400 {{ $user->profile_url ? 'hidden' : '' }}">
                                            <i class="fas fa-user text-3xl"></i>
                                        </div>
                                    </div>
                                    <div class="w-full">
                                        {{ Form::label('profile', __('Passport Photo'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="mb-2 text-xs text-gray-500">{{ __('JPG, PNG or GIF, max 2MB. Leave empty to keep the current photo.') }}</div>
                                        <input type="file" name="profile" id="profile"
                                            accept="image/jpeg,image/png,image/gif"
                                            class="w-full rounded-md border border-gray-300 p-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-sm"
                                            onchange="(function(input){
                                                var img = document.getElementById('editProfilePreview');
                                                var ph = document.getElementById('editProfilePlaceholder');
                                                var file = input.files && input.files[0];
                                                if (!file) { return; }
                                                if (file.size > 2 * 1024 * 1024) {
                                                    alert('{{ __('The photo must be 2MB or smaller.') }}');
                                                    input.value = '';
                                                    return;
                                                }
                                                var reader = new FileReader();
                                                reader.onload = function (e) {
                                                    img.src = e.target.result;
                                                    img.classList.remove('hidden');
                                                    ph.classList.add('hidden');
                                                };
                                                reader.readAsDataURL(file);
                                                // Picking a new file is a replacement, not a removal.
                                                document.getElementById('removeProfileFlag').value = '0';
                                                var undo = document.getElementById('removeProfileUndo');
                                                var btn = document.getElementById('removeProfileBtn');
                                                if (undo) { undo.classList.add('hidden'); }
                                                if (btn) { btn.classList.remove('hidden'); }
                                            })(this)">
                                        @error('profile')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror

                                        {{-- Removal is a flag on this form rather than its own request: the card
                                             sits inside the edit form, and a nested form is invalid HTML. The
                                             list screen's action menu opens its own Profile Picture card, which
                                             posts to users.profile-photo.update instead. --}}
                                        <input type="hidden" name="remove_profile" id="removeProfileFlag" value="0">
                                        @if ($user->profile_url)
                                            <div class="mt-2">
                                                <button type="button" id="removeProfileBtn"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800"
                                                    onclick="markProfileForRemoval()">
                                                    <i class="fas fa-trash-alt"></i>
                                                    {{ __('Remove Profile Picture') }}
                                                </button>
                                                <div id="removeProfileUndo" class="hidden mt-1 flex items-center gap-2 text-xs text-amber-700">
                                                    <span>{{ __('Will be removed when you save.') }}</span>
                                                    <button type="button" class="font-medium underline hover:text-amber-900"
                                                        onclick="undoProfileRemoval()">
                                                        {{ __('Undo') }}
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Defined here too: this form is also reachable from pages that
                                                 do not carry the users-list stylesheet. --}}
                                            <style>.swal-topmost { z-index: 100000; }</style>
                                            <script>
                                            /**
                                             * The photo card lives inside the edit form, so removal is a flag on
                                             * that form rather than its own request — a nested form would be
                                             * invalid HTML. Nothing is deleted until Save Changes is submitted.
                                             */
                                            function undoProfileRemoval() {
                                                document.getElementById('removeProfileFlag').value = '0';
                                                document.getElementById('editProfilePreview').classList.remove('hidden');
                                                document.getElementById('editProfilePlaceholder').classList.add('hidden');
                                                document.getElementById('removeProfileUndo').classList.add('hidden');
                                                document.getElementById('removeProfileBtn').classList.remove('hidden');
                                            }

                                            function markProfileForRemoval() {
                                                var apply = function () {
                                                    document.getElementById('removeProfileFlag').value = '1';
                                                    document.getElementById('profile').value = '';
                                                    document.getElementById('editProfilePreview').classList.add('hidden');
                                                    document.getElementById('editProfilePlaceholder').classList.remove('hidden');
                                                    document.getElementById('removeProfileBtn').classList.add('hidden');
                                                    document.getElementById('removeProfileUndo').classList.remove('hidden');
                                                };

                                                var message = @json(__('The photo will be cleared when you save. :name will be asked to upload a new one before they can use the system again.', ['name' => $user->name]));

                                                if (typeof Swal === 'undefined') {
                                                    if (window.confirm(message)) { apply(); }
                                                    return;
                                                }

                                                Swal.fire({
                                                    title: @json(__('Remove profile picture?')),
                                                    text: message,
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonText: @json(__('Yes, remove it')),
                                                    cancelButtonText: @json(__('Cancel')),
                                                    confirmButtonColor: '#d97706',
                                                    cancelButtonColor: '#6b7280',
                                                    reverseButtons: true,
                                                    focusCancel: true,
                                                    // This form is shown inside the z-50 modal container.
                                                    customClass: { container: 'swal-topmost' }
                                                }).then(function (result) {
                                                    if (result.isConfirmed) { apply(); }
                                                });
                                            }
                                            </script>
                                        @endif
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
                                        {{ Form::label('password', __('New Password'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
                                            {{ Form::password('password', [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm',
                                                'placeholder' => __('Leave blank to keep current password')
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank to keep current password') }}</p>
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
                                        {{ Form::label('phone_number', __('Phone Number'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-phone"></i></span>
                                            {{ Form::text('phone_number', null, [
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
                                                'placeholder' => __('Enter email'),
                                                'required' => 'required'
                                            ]) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        {{ Form::label('staff_type_category', __('Staff Type Category'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-users"></i></span>
                                            {{ Form::select('staff_type_category', $staffTypeOptions, old('staff_type_category', $user->staff_type_category), [
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
                                            {{ Form::select('work_station', $workStationOptions, old('work_station', $user->work_station), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select Work Station'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Used for Activity Monitoring filters and reports.') }}</p>
                                    </div>
                                    <div>
                                        {{ Form::label('payment_structure_id', __('Standard Payment Structure'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-coins"></i></span>
                                            {{ Form::select('payment_structure_id', $paymentStructureOptions, old('payment_structure_id', $user->workstation_payment_structure_id), [
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
                                            ], old('work_days_per_week', $user->work_days_per_week), [
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
                                            ], old('man_hours_per_day', $user->man_hours_per_day), [
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
                                            {{ Form::number('base_salary_override', old('base_salary_override', $user->base_salary_override), [
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

                                <div class="grid gap-4 md:grid-cols-2 mb-6">
                                    <div>
                                        {{ Form::label('shift_code', __('Assigned Shift'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-business-time"></i></span>
                                            {{ Form::select('shift_code', $shiftDropdown, $selectedShift, [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 pl-10 text-sm bg-white',
                                                'placeholder' => __('Select shift'),
                                                'disabled' => 'disabled',
                                                'x-bind:disabled' => '!canEditAdvancedFields'
                                            ]) }}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Determines attendance cut-offs and late thresholds.') }}</p>
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
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Disable to keep this user active regardless of absence limits.') }}</p>
                                    </div>
                                </div>

                                {{-- PC Access Toggle --}}
                                <div class="grid gap-4 md:grid-cols-2 mb-6">
                                    <div>
                                        {{ Form::label('is_pc_access', __('PC Access'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                        <div class="flex items-start space-x-3">
                                            {{ Form::hidden('is_pc_access', old('is_pc_access', $user->is_pc_access ?? 0), [
                                                'x-bind:value' => 'pcAccessValue ? 1 : 0'
                                            ]) }}
                                            <label class="relative inline-flex items-center cursor-pointer mt-1">
                                                {{ Form::checkbox('is_pc_access', 1, old('is_pc_access', $user->is_pc_access) == 1, [
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
                                            {{ Form::checkbox('is_on_leave', 1, old('is_on_leave', $user->is_on_leave ?? false), [
                                                'class' => 'h-4 w-4 mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500'
                                            ]) }}
                                            <span class="text-sm text-gray-700 leading-5">{{ __('Mark this staff member as currently on leave/holiday') }}</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            {{ Form::label('leave_start_date', __('Leave Start Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_start_date', old('leave_start_date', optional($user->leave_start_date)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'x-model' => 'leaveStartDate'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('leave_end_date', __('Leave End Date'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('leave_end_date', old('leave_end_date', optional($user->leave_end_date)->format('Y-m-d')), [
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
                                            {{ Form::select('deputy_user_id', $deputyOptions ?? [], old('deputy_user_id', $user->deputy_user_id ?? null), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm bg-white',
                                                'placeholder' => __('Select deputy')
                                            ]) }}
                                            <p class="mt-1 text-xs text-gray-500">{{ __('Colleague who receives this user\'s file/task redirects while on leave.') }}</p>
                                        </div>
                                        <div>
                                            {{ Form::label('leave_reason', __('Leave Reason'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::text('leave_reason', old('leave_reason', $user->leave_reason ?? null), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm',
                                                'placeholder' => __('e.g. Annual Leave, Sick Leave, Study Leave')
                                            ]) }}
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 mt-4">
                                        <div>
                                            {{ Form::label('out_of_office_from', __('Out of Office Date From'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_from', old('out_of_office_from', optional($user->out_of_office_from)->format('Y-m-d')), [
                                                'class' => 'w-full rounded-md border border-gray-300 p-2 text-sm'
                                            ]) }}
                                        </div>
                                        <div>
                                            {{ Form::label('out_of_office_to', __('Out of Office Date To'), ['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}
                                            {{ Form::date('out_of_office_to', old('out_of_office_to', optional($user->out_of_office_to)->format('Y-m-d')), [
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
                                                x-init="$el.value = userTypeName"
                                                @change="userTypeName = $event.target.value; autoSetUserLevel(userTypeName);"
                                                required>
                                                <option value="">Select User Type</option>
                                                @php
                                                    $userTypes = ['Management', 'Operations', 'System', 'User', 'ALL'];
                                                @endphp
                                                @foreach($userTypes as $userType)
                                                    <option value="{{ $userType }}" {{ $selectedUserType == $userType ? 'selected' : '' }}>{{ $userType }}</option>
                                                @endforeach
                                                {{-- The whole-system grant lives in assign_role, so it is offered here directly. --}}
                                                <option value="{{ $supperAdminRole }}" {{ $selectedUserType == $supperAdminRole ? 'selected' : '' }}>{{ $supperAdminRole }} (full system access)</option>
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
                                            @php $currentRank = old('rank', $user->rank ?? ''); @endphp
                                            <select name="rank" id="rank" onchange="toggleRankOther(this)"
                                                class="w-full p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Select Rank (optional)</option>
                                                @foreach($ranks as $rankOption)
                                                    <option value="{{ $rankOption }}" {{ $currentRank === $rankOption ? 'selected' : '' }}>{{ $rankOption }}</option>
                                                @endforeach
                                                {{-- Preserve a previously-saved rank that isn't in the lookup options --}}
                                                @if($currentRank && $currentRank !== '__other__' && !in_array($currentRank, $ranks, true))
                                                    <option value="{{ $currentRank }}" selected>{{ $currentRank }} (current)</option>
                                                @endif
                                                <option value="__other__" {{ $currentRank === '__other__' ? 'selected' : '' }}>Other (specify)…</option>
                                            </select>
                                            <input type="text" name="rank_other" id="rank_other" value="{{ old('rank_other') }}"
                                                placeholder="Enter new rank title"
                                                class="w-full mt-2 p-2 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 {{ $currentRank === '__other__' ? '' : 'hidden' }}">
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

                            @php
                                $userActions = !empty($user->user_actions) ? explode(',', $user->user_actions) : [];
                            @endphp
                            
                            {{-- Module permissions.
                                 Replaces the old flat 174-checkbox "Step 4" grid and the
                                 system-wide "User Actions" block, neither of which could say
                                 what a user may DO in a given module. Shared with create.blade.php
                                 so the two screens cannot drift apart again. --}}
                            @php
                                $dfrPerms = !empty($user->dfr_permissions)
                                    ? array_map('trim', explode(',', $user->dfr_permissions))
                                    : [];
                            @endphp

                            <div class="mt-6" x-show="!isSupperAdmin">
                                @include('user.partials.permission-grid', [
                                    'userRoles' => $userRoles,
                                    'departments' => $departments,
                                    'selectedRoles' => $userAssignedRoles ?? [],
                                    'grants' => $moduleGrants ?? [],
                                    'canGrantDelete' => $canGrantDelete ?? false,
                                ])

                                @include('user.partials.dfr-permissions-modal', [
                                    'mode' => 'edit',
                                    'dfrPerms' => $dfrPerms,
                                    'frScb' => ($user->fr_permissions ?? '') === 'SCB',
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
                {{ Form::submit(__('Save Changes'), ['class' => 'inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']) }}
                <button type="button" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
