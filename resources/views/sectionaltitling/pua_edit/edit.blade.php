@extends('layouts.app')

@section('page-title')
    @if(isset($subApplication->is_sua_unit) && $subApplication->is_sua_unit == 1)
        {{ __('Edit Standalone Unit Application (SUA)') }}
    @else
        {{ __('Edit Unit Application') }}
    @endif
@endsection

@include('sectionaltitling.sub_app_css')
@include('sectionaltitling.partials.assets.css')

@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @php
            $PageTitle = (isset($subApplication->is_sua_unit) && $subApplication->is_sua_unit == 1) ? 'Edit Standalone Unit Application' : 'Edit Unit Application';
            $PageDescription = 'File Number: ' . ($subApplication->fileno ?? 'N/A');
        @endphp
        @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => $PageDescription])

        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="w-full max-w-7xl mx-auto">
                @if(session('success'))
                    <div
                        class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 shadow-sm rounded-lg flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 mr-3"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                    <!-- Top Identity Bar -->
                    @if(isset($subApplication->is_sua_unit) && $subApplication->is_sua_unit == 1)
                        <div class="bg-blue-600 text-white p-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold">SUA FileNo: {{ $subApplication->fileno ?? 'N/A' }}</h2>
                                <p class="text-blue-100 text-sm">Update Standalone Unit details</p>
                            </div>
                            <div
                                class="bg-blue-500 text-white px-4 py-1.5 rounded-full text-sm font-semibold border border-blue-400">
                                <i class="fas fa-building mr-2"></i>SUA MODE
                            </div>
                        </div>
                    @else
                        <div class="bg-emerald-600 text-white p-4">
                            <h2 class="text-xl font-bold">STFileNo: {{ $subApplication->fileno ?? 'N/A' }}</h2>
                            <p class="text-emerald-100 text-sm">Update Unit Application details</p>
                        </div>
                    @endif

                    <form action="{{ route('pua.update', $subApplication->id) }}" method="POST"
                        enctype="multipart/form-data" class="p-8">
                        @csrf
                        @method('PUT')

                        <!-- Main Application Reference -->
                        <div class="mb-10">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i data-lucide="link" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Main Application Reference
                            </h3>
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 border border-gray-100 p-5 rounded-xl">
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Application
                                        ID</label>
                                    <p class="text-sm font-bold text-blue-900">
                                        {{ !empty($motherApplication->applicationID) ? strtoupper($motherApplication->applicationID) : 'N/A' }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Property
                                        Location</label>
                                    <p class="text-sm text-gray-700 font-medium">{{ $propertyLocation }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Section -->
                        <div class="mb-10">
                            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                                <i data-lucide="user" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Applicant Details
                            </h3>

                            <div class="mb-8">
                                <label class="block text-sm font-bold text-gray-700 mb-3">Applicant Type</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label
                                        class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $subApplication->applicant_type === 'individual' ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                                        <input type="radio" name="applicant_type" value="individual"
                                            class="w-4 h-4 text-blue-600 border-gray-300" {{ $subApplication->applicant_type === 'individual' ? 'checked' : '' }}
                                            onclick="toggleApplicantFields('individual')">
                                        <span
                                            class="ml-3 font-bold text-gray-800 uppercase text-xs tracking-wide">Individual</span>
                                    </label>
                                    <label
                                        class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $subApplication->applicant_type === 'corporate' ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                                        <input type="radio" name="applicant_type" value="corporate"
                                            class="w-4 h-4 text-blue-600 border-gray-300" {{ $subApplication->applicant_type === 'corporate' ? 'checked' : '' }}
                                            onclick="toggleApplicantFields('corporate')">
                                        <span
                                            class="ml-3 font-bold text-gray-800 uppercase text-xs tracking-wide">Corporate</span>
                                    </label>
                                    <label
                                        class="flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 {{ $subApplication->applicant_type === 'multiple' ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                                        <input type="radio" name="applicant_type" value="multiple"
                                            class="w-4 h-4 text-blue-600 border-gray-300" {{ $subApplication->applicant_type === 'multiple' ? 'checked' : '' }}
                                            onclick="toggleApplicantFields('multiple')">
                                        <span class="ml-3 font-bold text-gray-800 uppercase text-xs tracking-wide">Multiple
                                            Owners</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Individual Section -->
                            <div id="individual-fields"
                                style="{{ $subApplication->applicant_type === 'individual' ? '' : 'display: none;' }}">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Title</label>
                                        <select name="applicant_title"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                            <option value="">Select</option>
                                            @foreach(['Mr.', 'Mrs.', 'Chief', 'Dr.', 'Prof.', 'Alhaji', 'Hajia', 'Barr.', 'Arc.', 'Engr.'] as $t)
                                                <option value="{{ $t }}" {{ $subApplication->applicant_title === $t ? 'selected' : '' }}>{{ $t }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">First
                                            Name</label>
                                        <input type="text" name="first_name" value="{{ $subApplication->first_name }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Middle
                                            Name</label>
                                        <input type="text" name="middle_name" value="{{ $subApplication->middle_name }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase">
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Surname</label>
                                        <input type="text" name="surname" value="{{ $subApplication->surname }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase">
                                    </div>
                                </div>
                            </div>

                            <!-- Corporate Section -->
                            <div id="corporate-fields"
                                style="{{ $subApplication->applicant_type === 'corporate' ? '' : 'display: none;' }}">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Corporate
                                            Name</label>
                                        <input type="text" name="corporate_name"
                                            value="{{ $subApplication->corporate_name }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">RC
                                            Number</label>
                                        <input type="text" name="rc_number" value="{{ $subApplication->rc_number }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Fields -->
                            <div id="contact-fields"
                                style="{{ $subApplication->applicant_type !== 'multiple' ? '' : 'display: none;' }}">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Email
                                            Address</label>
                                        <input type="email" name="email" value="{{ $subApplication->email }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Phone
                                            Number(s)</label>
                                        <input type="text" name="phone_number" value="{{ $subApplication->phone_number }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="block text-xs font-bold text-gray-500 uppercase mb-2">Residential/Office
                                            Address</label>
                                        <textarea name="address" rows="3"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">{{ $subApplication->address }}</textarea>
                                    </div>
                                </div>

                                <!-- Identification Documents -->
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6 p-6 bg-blue-50 rounded-2xl border border-blue-100">
                                    <div>
                                        <label class="block text-xs font-bold text-blue-800 uppercase mb-3">Means of
                                            Identification</label>
                                        <select name="identification_type"
                                            class="w-full py-2.5 px-4 bg-white border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                                            <option value="">Select ID Type</option>
                                            @foreach(['National ID', "Driver's License", "Voter's Card", 'International Passport'] as $idType)
                                                <option value="{{ strtolower($idType) }}" {{ strtolower($subApplication->identification_type) === strtolower($idType) ? 'selected' : '' }}>{{ $idType }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Upload Means of
                                            Identification</label>
                                        <input type="file" name="identification_image"
                                            class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        @if($subApplication->identification_image)
                                            <div class="mt-2 flex items-center p-2 bg-blue-50 rounded border border-blue-100">
                                                <i data-lucide="file-check" class="w-4 h-4 text-blue-600 mr-2"></i>
                                                <span class="text-xs text-blue-800">Current ID Document Uploaded</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-blue-800 uppercase mb-3 text-center">Passport
                                            Photograph</label>
                                        <div class="flex items-center justify-center space-x-6">
                                            @if($subApplication->passport)
                                                <div class="relative group">
                                                    <img src="{{ asset('storage/app/public/' . $subApplication->passport) }}"
                                                        class="w-24 h-24 object-cover rounded-xl border-4 border-white shadow-md group-hover:shadow-lg transition-all"
                                                        alt="Passport">
                                                    <div
                                                        class="absolute -top-2 -right-2 bg-blue-600 text-white p-1 rounded-full shadow-sm">
                                                        <i data-lucide="camera" class="w-3 h-3"></i>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="flex-1">
                                                <input type="file" name="passport"
                                                    class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                                                <p class="mt-2 text-[10px] text-gray-400">Leave empty to keep current photo.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Multiple Owners Section -->
                            <div id="multiple-owners-fields"
                                style="{{ $subApplication->applicant_type === 'multiple' ? '' : 'display: none;' }}">
                                <div id="multi-owners-container" class="space-y-6">
                                    @php
                                        $owners = json_decode($subApplication->multiple_owners_data, true) ?? [];
                                    @endphp
                                    @forelse($owners as $index => $owner)
                                        <div class="p-6 border-2 border-dashed border-gray-200 rounded-2xl bg-white relative owner-row hover:border-blue-300 transition-colors"
                                            id="owner-row-{{ $index }}">
                                            <button type="button" onclick="removeOwnerRow({{ $index }})"
                                                class="absolute -top-3 -right-3 w-8 h-8 bg-white text-red-500 border border-gray-100 rounded-full shadow-md hover:bg-red-50 hover:text-red-700 transition-all flex items-center justify-center">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Full
                                                        Name</label>
                                                    <input type="text" name="multiple_owners_names[]"
                                                        value="{{ $owner['name'] ?? '' }}"
                                                        class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Email
                                                        Address</label>
                                                    <input type="email" name="multiple_owners_email[]"
                                                        value="{{ $owner['email'] ?? '' }}"
                                                        class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Phone
                                                        Number</label>
                                                    <input type="text" name="multiple_owners_phone[]"
                                                        value="{{ $owner['phone'] ?? '' }}"
                                                        class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Contact
                                                        Address</label>
                                                    <input type="text" name="multiple_owners_address[]"
                                                        value="{{ $owner['address'] ?? '' }}"
                                                        class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div
                                            class="text-center py-10 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                                            <p class="text-gray-500 font-medium">No owners have been added yet.</p>
                                        </div>
                                    @endforelse
                                </div>
                                <button type="button" onclick="addOwnerRow()"
                                    class="mt-6 w-full flex items-center justify-center py-4 bg-gray-50 border-2 border-dashed border-gray-300 rounded-2xl text-gray-500 font-bold hover:bg-white hover:border-blue-400 hover:text-blue-600 transition-all">
                                    <i data-lucide="plus-circle" class="w-6 h-6 mr-3"></i> Add Another Owner
                                </button>
                            </div>
                        </div>

                        <hr class="my-10 border-gray-100">

                        <!-- Unit Info Section -->
                        <div class="mb-10">
                            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                                <i data-lucide="home" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Unit Information
                            </h3>

                            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 mb-8">
                                <h4 class="text-sm font-bold text-blue-900 mb-4 uppercase tracking-wider">Detailed Unit
                                    Selection</h4>
                                @include('sectionaltitling.types.commercial')
                                @include('sectionaltitling.types.residential')
                                @include('sectionaltitling.types.industrial')

                                <script>
                                    // Trigger land use display logic
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const landUse = "{{ $motherApplication->land_use ?? '' }}";
                                        console.log('Initialize unit types for land use:', landUse);
                                        // The partials themselves handle visibility based on URL parameters usually,
                                        // but for Edit Page we may need to override that logic or ensure the partials
                                        // check for a local variable.
                                    });
                                </script>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Block Number</label>
                                    <input type="text" name="block_number" value="{{ $subApplication->block_number }}"
                                        class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 outline-none"
                                        required>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-500 uppercase mb-2">Section/Floor</label>
                                    <input type="text" name="floor_number" value="{{ $subApplication->floor_number }}"
                                        class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 outline-none"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Unit Number</label>
                                    <input type="text" name="unit_number" value="{{ $subApplication->unit_number }}"
                                        class="w-full py-2.5 px-4 bg-blue-50 border border-blue-200 rounded-lg font-bold text-blue-900 outline-none"
                                        readonly>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Unit Size
                                        (sqm)</label>
                                    <input type="text" name="unit_size" value="{{ $subApplication->unit_size }}"
                                        class="w-full py-2.5 px-4 bg-blue-50 border border-blue-200 rounded-lg font-bold text-blue-900 outline-none"
                                        readonly>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Current Unit
                                        Type</label>
                                    <div
                                        class="py-2.5 px-4 bg-gray-100 border border-gray-200 rounded-lg text-gray-700 font-bold">
                                        {{ $subApplication->unit_type ?: 'Not specified' }}
                                    </div>
                                    <input type="hidden" name="unit_type" value="{{ $subApplication->unit_type }}">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Ownership
                                        Type</label>
                                    <select name="ownership"
                                        class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                        <option value="">Select Ownership</option>
                                        @foreach(['Sole Ownership', 'Joint Ownership', 'Leasehold', 'Corporate'] as $ownerType)
                                            <option value="{{ $ownerType }}" {{ $subApplication->ownership === $ownerType ? 'selected' : '' }}>{{ $ownerType }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        <!-- Location Details -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">District</label>
                                        <input type="text" name="unit_district" id="unit_district" value="{{ $subApplication->unit_district }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 outline-none uppercase" placeholder="Enter District">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">LGA</label>
                                        <select name="unit_lga" id="unit_lga"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                            <option value="{{ $subApplication->unit_lga }}">{{ $subApplication->unit_lga ?: 'Select LGA' }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">State</label>
                                        <input type="text" name="unit_state" value="{{ $subApplication->unit_state ?: 'KANO' }}"
                                            class="w-full py-2.5 px-4 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 outline-none" readonly>
                                    </div>
                                </div>

                                <script>
                                    // Fetch Kano State LGAs if needed, or just preserve the current one.
                                    // Standard KLAES pattern is to fetch from an API.
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const lgaSelect = document.getElementById("unit_lga");
                                        const currentLGA = "{{ $subApplication->unit_lga }}";

                                        fetch('https://nga-states-lga.onrender.com/?state=Kano')
                                            .then(res => res.json())
                                            .then(data => {
                                                if (Array.isArray(data)) {
                                                    data.forEach(lga => {
                                                        if (lga !== currentLGA) {
                                                            const option = document.createElement("option");
                                                            option.text = lga;
                                                            option.value = lga;
                                                            lgaSelect.add(option);
                                                        }
                                                    });
                                                }
                                            })
                                            .catch(err => console.warn('LGA Fetch failed, using existing value'));
                                    });
                                </script>
                            </div>

                            <hr class="my-10 border-gray-100">

                            <!-- Shared Areas & Extras -->
                            <div class="mb-12">
                                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                                    <i data-lucide="layers" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Shared Areas & Details
                                </h3>

                                <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200 mb-8">
                                    <div class="flex items-center justify-between mb-4">
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Select shared
                                            areas:</p>
                                        <div class="relative w-64">
                                            <input type="text" id="sharedAreasSearch"
                                                class="w-full px-3 py-1.5 pl-8 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none"
                                                placeholder="Search areas..." onkeyup="filterSharedAreas()">
                                            <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-2.5 top-2"></i>
                                        </div>
                                    </div>

                                    <div class="mb-4 flex gap-2">
                                        <button type="button"
                                            class="px-3 py-1 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 transition-colors"
                                            onclick="checkAllSharedAreas()">Check All</button>
                                        <button type="button"
                                            class="px-3 py-1 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 transition-colors"
                                            onclick="uncheckAllSharedAreas()">Uncheck All</button>
                                        <span id="selectedCount"
                                            class="ml-auto px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-bold">0
                                            selected</span>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="sharedAreasGrid">
                                        @php
                                            $sharedAreas = json_decode($subApplication->shared_areas, true) ?? [];
                                            $allAreas = [
                                                'hallways' => ['label' => 'Hallways', 'icon' => 'door-open', 'terms' => 'corridors passages'],
                                                'gardens' => ['label' => 'Gardens', 'icon' => 'flower', 'terms' => 'landscaping green space'],
                                                'parking_lots' => ['label' => 'Parking', 'icon' => 'car', 'terms' => 'carport garage'],
                                                'swimming_pool' => ['label' => 'Pool', 'icon' => 'droplets', 'terms' => 'aquatic center'],
                                                'gym' => ['label' => 'Gym', 'icon' => 'dumbbell', 'terms' => 'fitness center workout'],
                                                'rooftop' => ['label' => 'Rooftop', 'icon' => 'mountain', 'terms' => 'terrace deck patio'],
                                                'lobby' => ['label' => 'Lobby', 'icon' => 'sofa', 'terms' => 'reception entrance foyer'],
                                                'elevator' => ['label' => 'Elevator', 'icon' => 'arrow-up-down', 'terms' => 'lift'],
                                                'storage' => ['label' => 'Storage', 'icon' => 'package', 'terms' => 'lockers warehouse'],
                                                'conference_room' => ['label' => 'Meeting', 'icon' => 'users', 'terms' => 'boardroom'],
                                                'playground' => ['label' => 'Playground', 'icon' => 'sparkles', 'terms' => 'children kids'],
                                                'security_post' => ['label' => 'Security', 'icon' => 'shield', 'terms' => 'guardhouse gatehouse'],
                                                'generator_room' => ['label' => 'Generator', 'icon' => 'zap', 'terms' => 'power backup'],
                                                'laundry_room' => ['label' => 'Laundry', 'icon' => 'shirt', 'terms' => 'washing drying'],
                                                'community_hall' => ['label' => 'Community Hall', 'icon' => 'home', 'terms' => 'function events'],
                                                'shared_compounds' => ['label' => 'Compounds', 'icon' => 'layers', 'terms' => 'outdoor yard'],
                                                'bbq_area' => ['label' => 'BBQ Area', 'icon' => 'flame', 'terms' => 'barbecue grill'],
                                                'tennis_court' => ['label' => 'Tennis', 'icon' => 'circle', 'terms' => 'sports'],
                                                'basketball_court' => ['label' => 'Basketball', 'icon' => 'circle-dot', 'terms' => 'sports'],
                                                'jogging_track' => ['label' => 'Jogging', 'icon' => 'footprints', 'terms' => 'running track'],
                                                'sauna' => ['label' => 'Sauna/Spa', 'icon' => 'waves', 'terms' => 'wellness steam'],
                                                'library' => ['label' => 'Library', 'icon' => 'book-open', 'terms' => 'reading room study'],
                                                'cinema_room' => ['label' => 'Cinema', 'icon' => 'film', 'terms' => 'theater movie'],
                                                'game_room' => ['label' => 'Game Room', 'icon' => 'gamepad-2', 'terms' => 'billiards'],
                                                'mailroom' => ['label' => 'Mailroom', 'icon' => 'mail', 'terms' => 'delivery packages'],
                                                'bike_storage' => ['label' => 'Bike Storage', 'icon' => 'bike', 'terms' => 'bicycle parking'],
                                                'pet_area' => ['label' => 'Pet Area', 'icon' => 'paw-print', 'terms' => 'dog park'],
                                                'coworking_space' => ['label' => 'Workspace', 'icon' => 'briefcase', 'terms' => 'office'],
                                                'reception_area' => ['label' => 'Reception', 'icon' => 'user-round', 'terms' => 'waiting lounge'],
                                                'staircase' => ['label' => 'Staircase', 'icon' => 'move-up', 'terms' => 'stairs emergency'],
                                                'other' => ['label' => 'Other', 'icon' => 'plus-circle', 'terms' => 'custom specify']
                                            ];
                                        @endphp
                                        @foreach($allAreas as $key => $info)
                                            <div class="flex items-center shared-area-item"
                                                data-search-term="{{ $key }} {{ $info['terms'] }}">
                                                <label
                                                    class="flex items-center text-sm font-medium text-gray-700 cursor-pointer group w-full">
                                                    <div class="relative flex items-center justify-center">
                                                        <input type="checkbox" name="shared_areas[]" value="{{ $key }}"
                                                            id="area-{{ $key }}"
                                                            class="peer h-5 w-5 cursor-pointer appearance-none rounded border-2 border-gray-300 checked:bg-blue-600 checked:border-blue-600 transition-all"
                                                            {{ in_array($key, $sharedAreas) ? 'checked' : '' }}
                                                            onchange="updateSelectedCount()">
                                                        <i data-lucide="check"
                                                            class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none"></i>
                                                    </div>
                                                    <span
                                                        class="ml-3 group-hover:text-blue-600 transition-colors flex items-center">
                                                        <i data-lucide="{{ $info['icon'] }}"
                                                            class="w-4 h-4 mr-1.5 text-gray-400 group-hover:text-blue-500"></i>
                                                        {{ $info['label'] }}
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Other Areas
                                            (Specify)</label>
                                        <input type="text" name="other_areas_detail"
                                            value="{{ $subApplication->other_areas_detail }}"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg"
                                            placeholder="e.g. Playground, Storage units...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Internal Comments /
                                            Admin Notes</label>
                                        <textarea name="comments" rows="3"
                                            class="w-full py-2.5 px-4 bg-white border border-gray-200 rounded-lg"
                                            placeholder="Any additional information...">{{ $subApplication->comments }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Initial Bill Section -->
                            <div class="mb-12">
                                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                                    <i data-lucide="receipt" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Initial Bill Preview (Read-only)
                                </h3>
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                    <div class="p-5 flex items-center justify-between border-b border-gray-200 bg-white">
                                        <span class="text-sm font-bold text-gray-700">Fee Breakdown</span>
                                        <span
                                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold uppercase tracking-wider">Estimated
                                            Total</span>
                                    </div>
                                    <div class="p-6 space-y-4">
                                        <div
                                            class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100">
                                            <span class="text-sm text-gray-600">Processing Fee</span>
                                            <span class="text-sm font-bold text-gray-900 font-mono">₦20,000.00</span>
                                        </div>
                                        <div
                                            class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100">
                                            <span class="text-sm text-gray-600">Application Fee</span>
                                            <span class="text-sm font-bold text-gray-900 font-mono">₦10,000.00</span>
                                        </div>
                                        <div
                                            class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100">
                                            <span class="text-sm text-gray-600">Survey/Site Plan Fee
                                                ({{ $motherApplication->land_use ?? 'Residential' }})</span>
                                            <span class="text-sm font-bold text-blue-600 font-mono">
                                                @php
                                                    $landUse = strtolower($motherApplication->land_use ?? 'residential');
                                                    if (str_contains($landUse, 'commercial'))
                                                        echo '₦100,000.00';
                                                    elseif (str_contains($landUse, 'industrial'))
                                                        echo '₦100,000.00';
                                                    else
                                                        echo '₦50,000.00 - ₦70,000.00';
                                                @endphp
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-5 bg-blue-50 border-t border-gray-200 text-xs text-blue-700 italic">
                                        Note: These fees are generated automatically based on the land use and application type.
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-8 border-t border-gray-100 flex items-center justify-between">
                                <button type="button" onclick="window.history.back()"
                                    class="flex items-center px-6 py-3 text-gray-500 font-bold hover:text-gray-800 transition-colors">
                                    <i data-lucide="x-circle" class="w-5 h-5 mr-2"></i>
                                    Cancel Changes
                                </button>
                                <button type="submit"
                                    class="flex items-center px-10 py-4 bg-blue-600 text-white font-extrabold rounded-2xl shadow-xl shadow-blue-500/30 hover:bg-blue-700 hover:shadow-blue-500/50 transition-all active:scale-95">
                                    <i data-lucide="save" class="w-6 h-6 mr-3"></i>
                                    SAVE APPLICATION UPDATES
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            @include('admin.footer')
        </div>

        <script>
            function toggleApplicantFields(type) {
                document.getElementById('individual-fields').style.display = (type === 'individual') ? 'block' : 'none';
                document.getElementById('corporate-fields').style.display = (type === 'corporate') ? 'block' : 'none';
                document.getElementById('contact-fields').style.display = (type === 'multiple') ? 'none' : 'block';
                document.getElementById('multiple-owners-fields').style.display = (type === 'multiple') ? 'block' : 'none';

                // Dynamic styling for selected radio labels
                document.querySelectorAll('input[name="applicant_type"]').forEach(input => {
                    const container = input.closest('label');
                    if (input.value === type) {
                        container.classList.add('border-blue-500', 'bg-blue-50');
                        container.classList.remove('border-gray-100');
                    } else {
                        container.classList.remove('border-blue-500', 'bg-blue-50');
                        container.classList.add('border-gray-100');
                    }
                });
            }

            let ownerCount = {{ count($owners) }};
            function addOwnerRow() {
                const container = document.getElementById('multi-owners-container');
                const index = ownerCount++;
                const html = `
                        <div class="p-6 border-2 border-dashed border-gray-200 rounded-2xl bg-white relative owner-row hover:border-blue-300 transition-colors" id="owner-row-${index}">
                            <button type="button" onclick="removeOwnerRow(${index})" class="absolute -top-3 -right-3 w-8 h-8 bg-white text-red-500 border border-gray-100 rounded-full shadow-md hover:bg-red-50 hover:text-red-700 transition-all flex items-center justify-center">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Full Name</label>
                                    <input type="text" name="multiple_owners_names[]" class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Email Address</label>
                                    <input type="email" name="multiple_owners_email[]" class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Phone Number</label>
                                    <input type="text" name="multiple_owners_phone[]" class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Contact Address</label>
                                    <input type="text" name="multiple_owners_address[]" class="w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 text-sm">
                                </div>
                            </div>
                        </div>
                    `;
                const div = document.createElement('div');
                div.innerHTML = html;
                container.appendChild(div.firstElementChild);
                lucide.createIcons();
            }

            function removeOwnerRow(index) {
                document.getElementById(`owner-row-${index}`).remove();
            }

            // --- Shared Areas Logic ---
            function filterSharedAreas() {
                const searchTerm = document.getElementById('sharedAreasSearch').value.toLowerCase();
                const items = document.querySelectorAll('.shared-area-item');
                items.forEach(item => {
                    const text = item.getAttribute('data-search-term').toLowerCase();
                    item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                });
            }

            function updateSelectedCount() {
                const count = document.querySelectorAll('input[name="shared_areas[]"]:checked').length;
                const el = document.getElementById('selectedCount');
                if (el) el.textContent = count + ' selected';
            }

            function checkAllSharedAreas() {
                document.querySelectorAll('.shared-area-item:not([style*="display: none"]) input[name="shared_areas[]"]').forEach(cb => cb.checked = true);
                updateSelectedCount();
            }

            function uncheckAllSharedAreas() {
                document.querySelectorAll('input[name="shared_areas[]"]').forEach(cb => cb.checked = false);
                updateSelectedCount();
            }

            // --- Unit Type Logic ---
            function initUnitTypeVisibility() {
                const landUse = "{{ strtolower($motherApplication->land_use ?? 'residential') }}";
                console.log('Applying land use visibility for:', landUse);

                // Hide all specialized containers first
                const containers = [
                    '#commercial-type-container',
                    '#residential-type-container',
                    '#industrial-type-container'
                ];

                // Note: The partials likely use different IDs or classes. 
                // We need to ensure they are visible.
                // Based on sub_application.blade.php, they reflect URL parameters.
                // But here we can force them based on the record data.

                const comNode = document.querySelector('h2:contains("Types of Commercial")')?.closest('div.mb-6');
                const resNode = document.querySelector('h2:contains("Types of Residential")')?.closest('div.mb-6');
                const indNode = document.querySelector('h2:contains("Types of Industrial")')?.closest('div.mb-6');

                // Set up a helper to find by text if IDs aren't available
                function findSectionByTitle(title) {
                    return Array.from(document.querySelectorAll('h2')).find(h2 => h2.textContent.includes(title))?.closest('.mb-6');
                }

                const comSec = findSectionByTitle('Commercial');
                const resSec = findSectionByTitle('Residential');
                const indSec = findSectionByTitle('Industrial');

                if (comSec) comSec.style.display = (landUse.includes('commercial') || landUse.includes('mixed')) ? 'block' : 'none';
                if (resSec) resSec.style.display = landUse.includes('residential') ? 'block' : 'none';
                if (indSec) indSec.style.display = landUse.includes('industrial') ? 'block' : 'none';
            }

            document.addEventListener('DOMContentLoaded', function () {
                updateSelectedCount();
                initUnitTypeVisibility();
            });
        </script>
@endsection