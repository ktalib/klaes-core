@extends('layouts.app')
@section('page-title')
    {{ __('Application Details') }}
@endsection

@section('content')
<script>
// Tailwind config
tailwind.config = {
  theme: { 
    extend: {
      colors: {
        primary: '#3b82f6',
        'primary-foreground': '#ffffff',
        muted: '#f3f4f6',
        'muted-foreground': '#6b7280',
        border: '#e5e7eb',
        destructive: '#ef4444',
        'destructive-foreground': '#ffffff',
        secondary: '#f1f5f9',
        'secondary-foreground': '#0f172a',
      }
    }
  }
}
</script>

<style>
/* Custom styles for details page */
.detail-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  border: 1px solid #e5e7eb;
}

.detail-section {
  border-bottom: 1px solid #f3f4f6;
}

.detail-section:last-child {
  border-bottom: none;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 12px 0;
  border-bottom: 1px solid #f9fafb;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 500;
  color: #6b7280;
  min-width: 140px;
  flex-shrink: 0;
}

.detail-value {
  color: #111827;
  text-align: right;
  flex: 1;
  margin-left: 16px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 500;
}

.status-approved {
  background-color: #dcfce7;
  color: #166534;
}

.status-pending {
  background-color: #fef3c7;
  color: #92400e;
}

.status-rejected {
  background-color: #fee2e2;
  color: #991b1b;
}

.print-button {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.print-button:hover {
  background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

@media print {
  .no-print {
    display: none !important;
  }
  
  .detail-card {
    box-shadow: none;
    border: 1px solid #e5e7eb;
  }
}
</style>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="container mx-auto py-6 space-y-6 max-w-6xl px-4 sm:px-6 lg:px-8">
            
            <!-- Header with Back Button -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="{{ url('/recertification') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Applications
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Application Details</h1>
                        <p class="text-gray-600">Complete information for application {{ $application->file_number ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-3 no-print">
                    <!-- <button onclick="window.print()" class="print-button inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer text-white gap-2">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print Details
                    </button> -->
                    <a href="{{ route('recertification.gis-capture', $application->id) }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700 gap-2">
                        <i data-lucide="map" class="h-4 w-4"></i>
                        GIS Data Capture
                    </a>
                    <a href="{{ url('/recertification/' . $application->id . '/edit') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-blue-600 text-white hover:bg-blue-700 gap-2">
                        <i data-lucide="edit" class="h-4 w-4"></i>
                        Edit Application
                    </a>
                </div>
            </div>

            <!-- Application Status Banner -->
            <div class="detail-card p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i data-lucide="check-circle" class="h-8 w-8 text-green-600"></i>
                        </div>
                        <!-- <div>
                            <h2 class="text-xl font-semibold text-gray-900">Application Status</h2>
                            <p class="text-gray-600">Current processing status</p>
                        </div> -->
                    </div>
                    <div class="text-right">
                        <!-- <div class="status-badge status-approved mb-2">
                            <i data-lucide="check" class="h-3 w-3 mr-1"></i>
                            Approved
                        </div> -->
                        <div class="text-sm text-gray-500">
                            Submitted: {{ $application->created_at ? date('d M Y, H:i', strtotime($application->created_at)) : 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Application Information -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                            Application Information
                        </h3>
                        <div class="space-y-1">
                            <!-- <div class="detail-row">
                                <span class="detail-label">Reference Number:</span>
                                <span class="detail-value font-mono font-semibold text-blue-900">{{ $application->application_reference ?? 'N/A' }}</span>
                            </div> -->
                            <div class="detail-row">
                                <span class="detail-label">File Number:</span>
                                <span class="detail-value font-mono font-semibold text-blue-900">{{ $application->file_number ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Application Date:</span>
                                <span class="detail-value">{{ $application->application_date ? date('d M Y', strtotime($application->application_date)) : 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Application Type:</span>
                                <span class="detail-value">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        @if($application->applicant_type === 'Individual') bg-blue-100 text-blue-800
                                        @elseif($application->applicant_type === 'Corporate') bg-purple-100 text-purple-800
                                        @elseif($application->applicant_type === 'Government Body') bg-green-100 text-green-800
                                        @elseif($application->applicant_type === 'Multiple Owners') bg-orange-100 text-orange-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ $application->applicant_type ?? 'N/A' }}
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Reason:</span>
                                <span class="detail-value">{{ $application->application_reason ?? 'N/A' }}</span>
                            </div>
                            @if($application->other_reason)
                            <div class="detail-row">
                                <span class="detail-label">Other Reason:</span>
                                <span class="detail-value">{{ $application->other_reason }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Applicant Information -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            @if($application->applicant_type === 'Corporate')
                                <i data-lucide="building" class="h-5 w-5 text-purple-600"></i>
                                Corporate Information
                            @elseif($application->applicant_type === 'Multiple Owners')
                                <i data-lucide="users" class="h-5 w-5 text-orange-600"></i>
                                Multiple Owners
                            @else
                                <i data-lucide="user" class="h-5 w-5 text-blue-600"></i>
                                Applicant Information
                            @endif
                        </h3>
                        
                        @if($application->applicant_type === 'Corporate')
                            <div class="space-y-1">
                                <div class="detail-row">
                                    <span class="detail-label">Organisation Name:</span>
                                    <span class="detail-value font-semibold">{{ $application->organisation_name ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">CAC Registration No:</span>
                                    <span class="detail-value font-mono">{{ $application->cac_registration_no ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Organisation Type:</span>
                                    <span class="detail-value">{{ $application->type_of_organisation ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Business Type:</span>
                                    <span class="detail-value">{{ $application->type_of_business ?? 'N/A' }}</span>
                                </div>
                            </div>
                        @elseif($application->applicant_type === 'Multiple Owners' && count($owners) > 0)
                            <div class="space-y-4">
                                <div class="text-sm text-gray-600 mb-3">{{ count($owners) }} Owner(s) Listed</div>
                                @foreach($owners as $index => $owner)
                                <div class="border border-orange-200 rounded-lg p-3 bg-orange-50">
                                    <h4 class="font-medium text-orange-900 mb-2">Owner {{ $index + 1 }}</h4>
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div class="detail-row py-1">
                                            <span class="detail-label text-xs">Name:</span>
                                            <span class="detail-value text-xs">{{ trim(($owner->surname ?? '') . ' ' . ($owner->first_name ?? '')) ?: 'N/A' }}</span>
                                        </div>
                                        <div class="detail-row py-1">
                                            <span class="detail-label text-xs">Occupation:</span>
                                            <span class="detail-value text-xs">{{ $owner->occupation ?? 'N/A' }}</span>
                                        </div>
                                        <div class="detail-row py-1">
                                            <span class="detail-label text-xs">Nationality:</span>
                                            <span class="detail-value text-xs">{{ $owner->nationality ?? 'N/A' }}</span>
                                        </div>
                                        <div class="detail-row py-1">
                                            <span class="detail-label text-xs">State:</span>
                                            <span class="detail-value text-xs">{{ $owner->state_of_origin ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-1">
                                <div class="detail-row">
                                    <span class="detail-label">Full Name:</span>
                                    <span class="detail-value font-semibold">{{ trim(($application->title ?? '') . ' ' . ($application->surname ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->middle_name ?? '')) ?: 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Occupation:</span>
                                    <span class="detail-value">{{ $application->occupation ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date of Birth:</span>
                                    <span class="detail-value">{{ $application->date_of_birth ? date('d M Y', strtotime($application->date_of_birth)) : 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Nationality:</span>
                                    <span class="detail-value">{{ $application->nationality ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">State of Origin:</span>
                                    <span class="detail-value">{{ $application->state_of_origin ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Gender:</span>
                                    <span class="detail-value capitalize">{{ $application->gender ?? 'N/A' }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Marital Status:</span>
                                    <span class="detail-value capitalize">{{ $application->marital_status ?? 'N/A' }}</span>
                                </div>
                                @if($application->nin)
                                <div class="detail-row">
                                    <span class="detail-label">NIN:</span>
                                    <span class="detail-value font-mono">{{ $application->nin }}</span>
                                </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="phone" class="h-5 w-5 text-green-600"></i>
                            Contact Information
                        </h3>
                        <div class="space-y-1">
                            <div class="detail-row">
                                <span class="detail-label">Phone Number:</span>
                                <span class="detail-value font-mono">{{ $application->phone_no ?? 'N/A' }}</span>
                            </div>
                            @if($application->whatsapp_phone_no)
                            <div class="detail-row">
                                <span class="detail-label">WhatsApp:</span>
                                <span class="detail-value font-mono">{{ $application->whatsapp_phone_no }}</span>
                            </div>
                            @endif
                            @if($application->alternate_phone_no)
                            <div class="detail-row">
                                <span class="detail-label">Alternate Phone:</span>
                                <span class="detail-value font-mono">{{ $application->alternate_phone_no }}</span>
                            </div>
                            @endif
                            <div class="detail-row">
                                <span class="detail-label">Email Address:</span>
                                <span class="detail-value">{{ $application->email_address ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value">
                                    {{ collect([$application->address_line1, $application->address_line2, $application->city_town, $application->state_name])->filter()->implode(', ') ?: 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plot Details -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="map-pin" class="h-5 w-5 text-red-600"></i>
                            Plot Information
                        </h3>
                        <div class="space-y-1">
                            <div class="detail-row">
                                <span class="detail-label">Plot Number:</span>
                                <span class="detail-value font-semibold">{{ $application->plot_number ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Plot Size:</span>
                                <span class="detail-value">{{ $application->plot_size ? $application->plot_size . ' Ha' : 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Layout/District:</span>
                                <span class="detail-value">{{ $application->layout_district ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">LGA:</span>
                                <span class="detail-value">{{ $application->lga_name ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Current Land Use:</span>
                                <span class="detail-value capitalize">{{ $application->current_land_use ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Plot Status:</span>
                                <span class="detail-value capitalize">{{ $application->plot_status ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Mode of Allocation:</span>
                                <span class="detail-value capitalize">{{ str_replace('-', ' ', $application->mode_of_allocation ?? 'N/A') }}</span>
                            </div>
                            @if($application->start_date)
                            <div class="detail-row">
                                <span class="detail-label">Start Date:</span>
                                <span class="detail-value">{{ date('d M Y', strtotime($application->start_date)) }}</span>
                            </div>
                            @endif
                            @if($application->expiry_date)
                            <div class="detail-row">
                                <span class="detail-label">Expiry Date:</span>
                                <span class="detail-value">{{ date('d M Y', strtotime($application->expiry_date)) }}</span>
                            </div>
                            @endif
                            @if($application->plot_description)
                            <div class="detail-row">
                                <span class="detail-label">Description:</span>
                                <span class="detail-value">{{ $application->plot_description }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Title Holder Information -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="award" class="h-5 w-5 text-yellow-600"></i>
                            Title Holder Information
                        </h3>
                        <div class="space-y-1">
                            <div class="detail-row">
                                <span class="detail-label">Title Holder:</span>
                                <span class="detail-value font-semibold">{{ trim(($application->title_holder_title ?? '') . ' ' . ($application->title_holder_surname ?? '') . ' ' . ($application->title_holder_first_name ?? '') . ' ' . ($application->title_holder_middle_name ?? '')) ?: 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">C-of-O Number:</span>
                                <span class="detail-value font-mono font-semibold">{{ $application->cofo_number ?? 'N/A' }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Registration No:</span>
                                <span class="detail-value font-mono">{{ $application->reg_no ?? 'N/A' }}</span>
                            </div>
                            @if($application->reg_volume || $application->reg_page)
                            <div class="detail-row">
                                <span class="detail-label">Volume/Page:</span>
                                <span class="detail-value">Vol: {{ $application->reg_volume ?? 'N/A' }}, Page: {{ $application->reg_page ?? 'N/A' }}</span>
                            </div>
                            @endif
                            <div class="detail-row">
                                <span class="detail-label">Original Owner:</span>
                                <span class="detail-value">
                                    @if($application->is_original_owner === 1)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i data-lucide="check" class="h-3 w-3 mr-1"></i>
                                            Yes
                                        </span>
                                    @elseif($application->is_original_owner === 0)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i data-lucide="x" class="h-3 w-3 mr-1"></i>
                                            No
                                        </span>
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                            @if($application->is_original_owner === 0)
                                @if($application->instrument_type)
                                <div class="detail-row">
                                    <span class="detail-label">Instrument Type:</span>
                                    <span class="detail-value">{{ $application->instrument_type }}</span>
                                </div>
                                @endif
                                @if($application->acquired_title_holder_name)
                                <div class="detail-row">
                                    <span class="detail-label">Acquired From:</span>
                                    <span class="detail-value">{{ $application->acquired_title_holder_name }}</span>
                                </div>
                                @endif
                            @endif
                            @if($application->commencement_date)
                            <div class="detail-row">
                                <span class="detail-label">Commencement Date:</span>
                                <span class="detail-value">{{ date('d M Y', strtotime($application->commencement_date)) }}</span>
                            </div>
                            @endif
                            @if($application->grant_term)
                            <div class="detail-row">
                                <span class="detail-label">Grant Term:</span>
                                <span class="detail-value">{{ $application->grant_term }} years</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="detail-card">
                    <div class="detail-section p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="credit-card" class="h-5 w-5 text-indigo-600"></i>
                            Payment Information
                        </h3>
                        <div class="space-y-1">
                            <div class="detail-row">
                                <span class="detail-label">Payment Method:</span>
                                <span class="detail-value capitalize">{{ $application->payment_method ?? 'N/A' }}</span>
                            </div>
                            @if($application->receipt_no)
                            <div class="detail-row">
                                <span class="detail-label">Receipt Number:</span>
                                <span class="detail-value font-mono">{{ $application->receipt_no }}</span>
                            </div>
                            @endif
                            @if($application->bank_name)
                            <div class="detail-row">
                                <span class="detail-label">Bank Name:</span>
                                <span class="detail-value">{{ $application->bank_name }}</span>
                            </div>
                            @endif
                            @if($application->payment_amount)
                            <div class="detail-row">
                                <span class="detail-label">Amount Paid:</span>
                                <span class="detail-value font-semibold">₦{{ number_format($application->payment_amount, 2) }}</span>
                            </div>
                            @endif
                            @if($application->payment_date)
                            <div class="detail-row">
                                <span class="detail-label">Payment Date:</span>
                                <span class="detail-value">{{ date('d M Y', strtotime($application->payment_date)) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mortgage & Encumbrance Information (if applicable) -->
            @if($application->has_mortgage === 1 || $application->is_encumbered === 1)
            <div class="detail-card">
                <div class="detail-section p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="shield-alert" class="h-5 w-5 text-orange-600"></i>
                        Mortgage & Encumbrance Details
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($application->is_encumbered === 1)
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Encumbrance Information</h4>
                            <div class="space-y-1">
                                <div class="detail-row">
                                    <span class="detail-label">Encumbered:</span>
                                    <span class="detail-value">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            <i data-lucide="alert-triangle" class="h-3 w-3 mr-1"></i>
                                            Yes
                                        </span>
                                    </span>
                                </div>
                                @if($application->encumbrance_reason)
                                <div class="detail-row">
                                    <span class="detail-label">Reason:</span>
                                    <span class="detail-value">{{ $application->encumbrance_reason }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                        
                        @if($application->has_mortgage === 1)
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Mortgage Information</h4>
                            <div class="space-y-1">
                                <div class="detail-row">
                                    <span class="detail-label">Mortgaged:</span>
                                    <span class="detail-value">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i data-lucide="home" class="h-3 w-3 mr-1"></i>
                                            Yes
                                        </span>
                                    </span>
                                </div>
                                @if($application->mortgagee_name)
                                <div class="detail-row">
                                    <span class="detail-label">Mortgagee:</span>
                                    <span class="detail-value">{{ $application->mortgagee_name }}</span>
                                </div>
                                @endif
                                @if($application->mortgage_registration_no)
                                <div class="detail-row">
                                    <span class="detail-label">Registration No:</span>
                                    <span class="detail-value font-mono">{{ $application->mortgage_registration_no }}</span>
                                </div>
                                @endif
                                @if($application->mortgage_volume || $application->mortgage_page)
                                <div class="detail-row">
                                    <span class="detail-label">Volume/Page:</span>
                                    <span class="detail-value">Vol: {{ $application->mortgage_volume ?? 'N/A' }}, Page: {{ $application->mortgage_page ?? 'N/A' }}</span>
                                </div>
                                @endif
                                <div class="detail-row">
                                    <span class="detail-label">Released:</span>
                                    <span class="detail-value">
                                        @if($application->mortgage_released === 1)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i data-lucide="check" class="h-3 w-3 mr-1"></i>
                                                Yes
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i data-lucide="x" class="h-3 w-3 mr-1"></i>
                                                No
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- System Information -->
            <div class="detail-card no-print">
                <div class="detail-section p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="database" class="h-5 w-5 text-gray-600"></i>
                        System Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="detail-row">
                            <span class="detail-label">Application ID:</span>
                            <span class="detail-value font-mono">{{ $application->id }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value">{{ $application->created_at ? date('d M Y, H:i', strtotime($application->created_at)) : 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value">{{ $application->updated_at ? date('d M Y, H:i', strtotime($application->updated_at)) : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

@endsection