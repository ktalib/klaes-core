@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Edit Instrument Registration') }}
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="container mx-auto py-6 space-y-6 px-4">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h1 class="text-2xl font-bold">Edit Instrument Registration</h1>
            <div>
                <a href="{{ route('instrument_registration.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Instrument Details</h3>
                <p class="mt-1 text-sm text-gray-600">Update the instrument registration information below.</p>
            </div>

            <form action="{{ route('instrument_registration.update', $id) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($sourceTable === 'subapplications')
                        <!-- For Subapplications -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="first_name" 
                                   value="{{ old('first_name', $sourceRecord->first_name ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="surname" class="block text-sm font-medium text-gray-700">Surname</label>
                            <input type="text" name="surname" id="surname" 
                                   value="{{ old('surname', $sourceRecord->surname ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('surname')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="corporate_name" class="block text-sm font-medium text-gray-700">Corporate Name</label>
                            <input type="text" name="corporate_name" id="corporate_name" 
                                   value="{{ old('corporate_name', $sourceRecord->corporate_name ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('corporate_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="fileno" class="block text-sm font-medium text-gray-700">File Number</label>
                            <input type="text" name="fileno" id="fileno" 
                                   value="{{ $sourceRecord->fileno ?? '' }}" readonly
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                        </div>

                    @else
                        <!-- For Registered Instruments -->
                        <div>
                            <label for="Grantor" class="block text-sm font-medium text-gray-700">Grantor</label>
                            <input type="text" name="Grantor" id="Grantor" 
                                   value="{{ old('Grantor', $sourceRecord->Grantor ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('Grantor')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="Grantee" class="block text-sm font-medium text-gray-700">Grantee</label>
                            <input type="text" name="Grantee" id="Grantee" 
                                   value="{{ old('Grantee', $sourceRecord->Grantee ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('Grantee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="instrument_type" class="block text-sm font-medium text-gray-700">Instrument Type</label>
                            <select name="instrument_type" id="instrument_type" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Instrument Type</option>
                                <option value="ST Assignment (Transfer of Title)" 
                                        {{ old('instrument_type', $sourceRecord->instrument_type ?? '') == 'ST Assignment (Transfer of Title)' ? 'selected' : '' }}>
                                    ST Assignment (Transfer of Title)
                                </option>
                                <option value="Sectional Titling CofO" 
                                        {{ old('instrument_type', $sourceRecord->instrument_type ?? '') == 'Sectional Titling CofO' ? 'selected' : '' }}>
                                    Sectional Titling CofO
                                </option>
                                <option value="Mortgage" 
                                        {{ old('instrument_type', $sourceRecord->instrument_type ?? '') == 'Mortgage' ? 'selected' : '' }}>
                                    Mortgage
                                </option>
                                <option value="Assignment" 
                                        {{ old('instrument_type', $sourceRecord->instrument_type ?? '') == 'Assignment' ? 'selected' : '' }}>
                                    Assignment
                                </option>
                                <option value="Lease" 
                                        {{ old('instrument_type', $sourceRecord->instrument_type ?? '') == 'Lease' ? 'selected' : '' }}>
                                    Lease
                                </option>
                            </select>
                            @error('instrument_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="particularsRegistrationNumber" class="block text-sm font-medium text-gray-700">Registration Number</label>
                            <input type="text" name="particularsRegistrationNumber" id="particularsRegistrationNumber" 
                                   value="{{ $sourceRecord->particularsRegistrationNumber ?? '' }}" readonly
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                        </div>
                    @endif

                    <!-- Common Fields -->
                    <div class="md:col-span-2">
                        <label for="propertyDescription" class="block text-sm font-medium text-gray-700">Property Description</label>
                        <textarea name="propertyDescription" id="propertyDescription" rows="3"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('propertyDescription', $sourceRecord->propertyDescription ?? '') }}</textarea>
                        @error('propertyDescription')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Display Only Fields -->
                    <div>
                        <label for="lga" class="block text-sm font-medium text-gray-700">LGA</label>
                        <input type="text" name="lga" id="lga" 
                               value="{{ $sourceRecord->lga ?? '' }}" readonly
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label for="district" class="block text-sm font-medium text-gray-700">District</label>
                        <input type="text" name="district" id="district" 
                               value="{{ $sourceRecord->district ?? '' }}" readonly
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label for="size" class="block text-sm font-medium text-gray-700">Plot Size</label>
                        <input type="text" name="size" id="size" 
                               value="{{ $sourceRecord->size ?? '' }}" readonly
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label for="plotNumber" class="block text-sm font-medium text-gray-700">Plot Number</label>
                        <input type="text" name="plotNumber" id="plotNumber" 
                               value="{{ $sourceRecord->plotNumber ?? '' }}" readonly
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('instrument_registration.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                        Update Instrument
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

<!-- Include SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@if(session('success'))
<script>
    Swal.fire({
        title: 'Success!',
        text: "{{ session('success') }}",
        icon: 'success',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
@endif

@if(session('error'))
<script>
    Swal.fire({
        title: 'Error!',
        text: "{{ session('error') }}",
        icon: 'error',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
@endif

@endsection