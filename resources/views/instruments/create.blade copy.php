@extends('layouts.app')
@section('page-title')
    {{ __('Instrument Capture') }}
@endsection


@section('content')
@include('instruments.create.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
 
      <div class="min-h-screen p-6">
        <div class="max-w-6xl mx-auto">
            <div class="card p-6">
                <h1 class="text-2xl font-bold mb-4">Instrument  Types</h1>
                <p class="text-gray-600 mb-6">Select an instrument type to capture</p>
                
                <!-- Instrument Type Selection - All 18 Types -->
                <div class="grid grid-cols-3 gap-3 mb-6">
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-blue-50 border-blue-200 hover:bg-blue-100" data-type="power-of-attorney">
                        <h3 class="font-medium text-blue-800">Power of Attorney</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">01</span>A legal document granting authority to a person (the attorney) to act on behalf of another (the donor) in property-related matters.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-green-50 border-green-200 hover:bg-green-100" data-type="irrevocable-power-of-attorney">
                        <h3 class="font-medium text-green-800">Irrevocable Power of Attorney</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-green-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">02</span>A non-revocable legal instrument that permanently empowers the attorney to act on behalf of the donor in managing or transferring land/property rights.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-purple-50 border-purple-200 hover:bg-purple-100" data-type="deed-of-mortgage">
                        <h3 class="font-medium text-purple-800">Deed of Mortgage</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-purple-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">03</span>A formal agreement used to secure a loan against landed property, with the lender holding interest until full repayment.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-red-50 border-red-200 hover:bg-red-100" data-type="tripartite-mortgage">
                        <h3 class="font-medium text-red-800">Tripartite Mortgage</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-red-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">04</span>A three-party agreement involving the borrower, lender, and property owner, typically used where the borrower is not the titleholder.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-yellow-50 border-yellow-200 hover:bg-yellow-100" data-type="deed-of-assignment">
                        <h3 class="font-medium text-yellow-800">Deed of Assignment</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-yellow-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">05</span>A document that legally transfers ownership of an interest in land or property from one party (assignor) to another (assignee).</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-indigo-50 border-indigo-200 hover:bg-indigo-100" data-type="deed-of-lease">
                        <h3 class="font-medium text-indigo-800">Deed of Lease</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-indigo-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">06</span>A contractual document that grants possession and use of land or property to a lessee for a specified period under agreed terms.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-pink-50 border-pink-200 hover:bg-pink-100" data-type="deed-of-sub-lease">
                        <h3 class="font-medium text-pink-800">Deed of Sub-Lease</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-pink-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">07</span>An agreement where a lessee (not the owner) leases part or all of the leased property to another party.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100" data-type="deed-of-sub-division">
                        <h3 class="font-medium text-orange-800">Deed of Sub-Division</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">09</span>A legal instrument used to divide a single parcel of land into multiple plots, each with its own separate title or interest.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100" data-type="deed-of-sub-division">
                        <h3 class="font-medium text-orange-800">Deed of Sub-Division</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">09</span>A legal instrument used to divide a single parcel of land into multiple plots, each with its own separate title or interest.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-cyan-50 border-cyan-200 hover:bg-cyan-100" data-type="deed-of-merger">
                        <h3 class="font-medium text-cyan-800">Deed of Merger</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-cyan-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">10</span>A document that combines multiple property interests or parcels into a single title or ownership.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-lime-50 border-lime-200 hover:bg-lime-100" data-type="deed-of-surrender">
                        <h3 class="font-medium text-lime-800">Deed of Surrender</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-lime-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">11</span>A legal agreement in which a tenant or lessee voluntarily returns possession of property to the landlord or lessor before the lease expires.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-violet-50 border-violet-200 hover:bg-violet-100" data-type="deed-of-variation">
                        <h3 class="font-medium text-violet-800">Deed of Variation</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-violet-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">12</span>A document used to modify the terms or conditions of an existing land-related agreement without invalidating it.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-emerald-50 border-emerald-200 hover:bg-emerald-100" data-type="deed-of-assent">
                        <h3 class="font-medium text-emerald-800">Deed of Assent</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-emerald-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">13</span>A probate instrument used by executors or administrators to formally transfer property from a deceased's estate to beneficiaries.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-rose-50 border-rose-200 hover:bg-rose-100" data-type="deed-of-release">
                        <h3 class="font-medium text-rose-800">Deed of Release</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-rose-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">14</span>A document that discharges or releases a party from a previous claim, interest, or mortgage on a property.</p>
                    </button>
                    {{-- <button class="instrument-type-btn p-2 border rounded-lg text-center bg-sky-50 border-sky-200 hover:bg-sky-100" data-type="right-of-occupancy">
                        <h3 class="font-medium text-sky-800">Right of Occupancy (RofO)</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-sky-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">15</span>A statutory land tenure instrument granting an individual or entity the right to occupy and use land in accordance with the Land Use Act.</p>
                    </button> --}}
                    {{-- <button class="instrument-type-btn p-2 border rounded-lg text-center bg-amber-50 border-amber-200 hover:bg-amber-100" data-type="certificate-of-occupancy">
                        <h3 class="font-medium text-amber-800">Certificate of Occupancy (CofO)</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-amber-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">16</span>An official government-issued certificate that legally proves the right to occupy and use a specific parcel of land.</p>
                    </button> --}}
                    {{-- <button class="instrument-type-btn p-2 border rounded-lg text-center bg-slate-50 border-slate-200 hover:bg-slate-100" data-type="sectional-titling-c-of-o">
                        <h3 class="font-medium text-slate-800">Sectional Titling Certificate of Occupancy</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-slate-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">17</span>A specialized CofO issued for individual units within a multi-unit development, such as apartments or condominiums, under the Sectional Titling framework.</p>
                    </button>
                    <button class="instrument-type-btn p-2 border rounded-lg text-center bg-gray-50 border-gray-200 hover:bg-gray-100" data-type="sltr-c-of-o">
                        <h3 class="font-medium text-gray-800">Systematic Land Titling and Registration (SLTR) Certificate of Occupancy</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-gray-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">18</span>A CofO issued under the SLTR scheme to formalize land rights, especially in informal or previously undocumented settlements.</p>
                    </button> --}}
                    {{-- <button class="instrument-type-btn p-2 border rounded-lg text-center bg-emerald-50 border-emerald-200 hover:bg-emerald-100" data-type="st-assignment">
                        <h3 class="font-medium text-emerald-800">ST Assignment (Transfer of Title)</h3>
                        <p class="text-xs text-black text-justify flex items-start gap-2"><span class="inline-flex items-center justify-center w-6 h-6 bg-emerald-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">19</span>A specialized assignment document for sectional title properties that transfers ownership from Kano State Government to the new title holder.</p>
                    </button> --}}
                </div>
            </div>
        </div>
    </div>

    <!-- Instrument Registration Form Dialog -->
    <div id="registration-dialog" class="dialog-backdrop hidden">
        <div class="dialog-content animate-fade-in">
            <div class="p-6 border-b flex items-center justify-between">
                <div>
                    <h2 id="dialog-title" class="text-lg font-semibold">Register Instrument</h2>
                    <p class="text-sm text-gray-600">Enter the details for the new instrument</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeRegistrationDialog()" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <form id="registration-form" class="p-6 space-y-6">
                <!-- File Number Section -->
                <div class="space-y-4 border rounded-md p-4 bg-gray-50">
                    <h3 class="text-lg font-medium">File Number</h3>
                    <div class="flex items-center space-x-2 mb-4">
                        <input type="checkbox" id="isTemporaryFileNo" class="checkbox">
                        <label for="isTemporaryFileNo" class="label">
                            This application has no Extant File Number (Use Temporary File Number)
                        </label>
                        <i data-lucide="info" class="h-4 w-4 text-gray-400 cursor-help" title="For applications without an existing file number, a temporary file number will be generated."></i>
                    </div>
                    <div id="temporary-file-section" class="space-y-2 hidden">
                        <label for="temporaryFileNo" class="label">Temporary File Number</label>
                        <div class="flex gap-2">
                            <input id="temporaryFileNo" name="temporaryFileNo" class="input bg-muted" readonly>
                            <button type="button" id="regenerate-temp-btn" class="btn btn-outline">Regenerate</button>
                        </div>
                        <p class="text-xs text-gray-500">This temporary file number will be used until a permanent file number is assigned.</p>
                    </div>
                    <div id="regular-file-section" class="space-y-4" x-data="{ showManualEntry: false }">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-medium text-gray-700">Select File Number</label>
                            <button type="button" @click="showManualEntry = !showManualEntry" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <span x-text="showManualEntry ? 'Use Smart Selector' : 'Enter Fileno manually'"></span>
                            </button>
                        </div>
                        
                        <!-- Smart File Number Selector (Default) -->
                        <div x-show="!showManualEntry" x-transition>
                            @include('instruments.partial.smart_fileno_selector')
                        </div>
                        
                        <!-- Manual File Number Entry -->
                        <div x-show="showManualEntry" x-transition>
                            @include('instruments.partial.fileno')
                        </div>
                    </div>
                </div>

                <!-- Registration Details Section -->
                <div id="registration-details-section" class="space-y-4 border rounded-md p-4 bg-gray-50">
                    <h3 class="text-lg font-medium">Registration Details</h3>
                    <div class="flex items-center space-x-2 mb-4">
                        <input type="checkbox" id="isTemporaryRegNo" class="checkbox">
                        <label for="isTemporaryRegNo" class="label text-blue-600 font-semibold">
                            <i data-lucide="info" class="inline h-4 w-4 mr-1"></i>
                            Use Temporary Registration Number
                        </label>
                        <i data-lucide="info" class="h-4 w-4 text-gray-400 cursor-help" title="For applications without an existing registration number, a temporary registration number will be used."></i>
                    </div>
                    <div id="reg-no-section" class="space-y-2 hidden">
                        <label for="regNo" class="label">Registration Number (ROOT TITLE)</label>
                        <input id="regNo" name="regNo" value="0/0/0" readonly class="input bg-muted">
                        <p class="text-xs text-gray-500">Customary Titles are registered as ROOT TITLES with Registration Number 0/0/0 by default.</p>
                    </div>
                    <div id="rootRegNoSection" class="space-y-2">
                        <label for="rootRegNo" class="label">Root Registration Number</label>
                        <input id="rootRegNo" name="rootRegNo" class="input" placeholder="Enter root registration number" required>
                    </div>  
                </div>

                <!-- First Party Section -->
                <div class="border rounded-md p-4 bg-gray-50">
                    <h3 id="first-party-title" class="text-lg font-medium mb-3">Grantor Information</h3>
                    
                    <div class="space-y-2 mb-4">
                        <label for="firstPartyName" id="first-party-label" class="label">Grantor Name</label>
                        <input id="firstPartyName" name="firstPartyName" class="input" placeholder="Enter grantor's full name">
                    </div>

                    <div class="space-y-3 border rounded-md p-3 bg-white">
                        <h4 id="first-party-address-title" class="font-medium">Grantor Address</h4>
                        <div class="space-y-2">
                            <label for="firstPartyStreet" class="label">Street Address</label>
                            <input id="firstPartyStreet" name="firstPartyStreet" class="input" placeholder="Enter street address">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <label for="firstPartyCity" class="label">City</label>
                                <input id="firstPartyCity" name="firstPartyCity" class="input" placeholder="Enter city">
                            </div>
                            <div class="space-y-2">
                                <label for="firstPartyState" class="label">State</label>
                                <input id="firstPartyState" name="firstPartyState" class="input" placeholder="Enter state">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <label for="firstPartyPostalCode" class="label">Postal Code</label>
                                <input id="firstPartyPostalCode" name="firstPartyPostalCode" class="input" placeholder="Enter postal code">
                            </div>
                            <div class="space-y-2">
                                <label for="firstPartyCountry" class="label">Country</label>
                                <input id="firstPartyCountry" name="firstPartyCountry" class="input" placeholder="Enter country">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Party Section -->
                <div class="border rounded-md p-4 bg-gray-50">
                    <h3 id="second-party-title" class="text-lg font-medium mb-3">Grantee Information</h3>
                    
                    <div class="space-y-2 mb-4">
                        <label for="secondPartyName" id="second-party-label" class="label">Grantee Name</label>
                        <input id="secondPartyName" name="secondPartyName" class="input" placeholder="Enter grantee's full name">
                    </div>

                    <div class="space-y-3 border rounded-md p-3 bg-white">
                        <h4 id="second-party-address-title" class="font-medium">Grantee Address</h4>
                        <div class="space-y-2">
                            <label for="secondPartyStreet" class="label">Street Address</label>
                            <input id="secondPartyStreet" name="secondPartyStreet" class="input" placeholder="Enter street address">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <label for="secondPartyCity" class="label">City</label>
                                <input id="secondPartyCity" name="secondPartyCity" class="input" placeholder="Enter city">
                            </div>
                            <div class="space-y-2">
                                <label for="secondPartyState" class="label">State</label>
                                <input id="secondPartyState" name="secondPartyState" class="input" placeholder="Enter state">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <label for="secondPartyPostalCode" class="label">Postal Code</label>
                                <input id="secondPartyPostalCode" name="secondPartyPostalCode" class="input" placeholder="Enter postal code">
                            </div>
                            <div class="space-y-2">
                                <label for="secondPartyCountry" class="label">Country</label>
                                <input id="secondPartyCountry" name="secondPartyCountry" class="input" placeholder="Enter country">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solicitor Information Section -->
                <div class="border rounded-md p-4 bg-gray-50">
                    <h3 class="text-lg font-medium mb-3">Solicitor Information</h3>
                    
                    <div class="space-y-2 mb-4">
                        <label for="solicitorName" class="label">Solicitor Name</label>
                        <input id="solicitorName" name="solicitorName" class="input" placeholder="Enter solicitor's full name">
                    </div>

                    <div class="space-y-2">
                        <label for="solicitorAddress" class="label">Solicitor Address</label>
                        <textarea id="solicitorAddress" name="solicitorAddress" class="textarea" placeholder="Enter solicitor's complete address"></textarea>
                    </div>
                </div>

                <!-- Property Details Section -->
                <div class="border rounded-md p-4 bg-gray-50">
                    <h3 class="text-lg font-medium mb-3">Property Details</h3>
                    
                    <div class="space-y-2">
                        <label for="plotDescription" class="label">Plot Description</label>
                        <textarea id="plotDescription" name="plotDescription" class="textarea" placeholder="Enter plot description"></textarea>
                    </div>

                    <div class="space-y-2 mt-4">
                        <label for="plotSize" class="label">Plot Size</label>
                        <input id="plotSize" name="plotSize" class="input" placeholder="Enter plot size (e.g., 100 x 50 meters)">
                    </div>

                    <div class="space-y-2 mt-4">
                        <label for="propertyLocation" class="label">Property Location</label>
                        <input id="propertyLocation" name="propertyLocation" class="input" placeholder="Enter property location">
                    </div>

                    <div class="space-y-2 mt-4">
                        <label for="surveyorName" class="label">Name of Surveyor</label>
                        <input id="surveyorName" name="surveyorName" class="input" placeholder="Enter name of surveyor">
                    </div>

                    <div class="flex items-center space-x-2 mt-4">
                        <input type="checkbox" id="surveyInfo" name="surveyInfo" class="checkbox">
                        <label for="surveyInfo" class="label">Include Survey Information</label>
                    </div>

                    <div id="survey-info-section" class="space-y-4 mt-4 border-t pt-4 hidden">
                        <h4 class="font-medium">Survey Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="lga" class="label">LGA (Local Government Area)</label>
                                <select id="lga" name="lga" class="select">
                                    <option value="">Select LGA</option>
                                    <option value="Ajingi">Ajingi</option>
                                    <option value="Albasu">Albasu</option>
                                    <option value="Bagwai">Bagwai</option>
                                    <option value="Bebeji">Bebeji</option>
                                    <option value="Bichi">Bichi</option>
                                    <option value="Bunkure">Bunkure</option>
                                    <option value="Dala">Dala</option>
                                    <option value="Dambatta">Dambatta</option>
                                    <option value="Dawaki Kudu">Dawaki Kudu</option>
                                    <option value="Dawaki Tofa">Dawaki Tofa</option>
                                    <option value="Doguwa">Doguwa</option>
                                    <option value="Fagge">Fagge</option>
                                    <option value="Gabasawa">Gabasawa</option>
                                    <option value="Garko">Garko</option>
                                    <option value="Garun Mallam">Garun Mallam</option>
                                    <option value="Gaya">Gaya</option>
                                    <option value="Gezawa">Gezawa</option>
                                    <option value="Gwale">Gwale</option>
                                    <option value="Gwarzo">Gwarzo</option>
                                    <option value="Kabo">Kabo</option>
                                    <option value="Kano Municipal">Kano Municipal</option>
                                    <option value="Karaye">Karaye</option>
                                    <option value="Kibiya">Kibiya</option>
                                    <option value="Kiru">Kiru</option>
                                    <option value="Kumbotso">Kumbotso</option>
                                    <option value="Kunchi">Kunchi</option>
                                    <option value="Kura">Kura</option>
                                    <option value="Madobi">Madobi</option>
                                    <option value="Makoda">Makoda</option>
                                    <option value="Minjibir">Minjibir</option>
                                    <option value="Nasarawa">Nasarawa</option>
                                    <option value="Rano">Rano</option>
                                    <option value="Rimin Gado">Rimin Gado</option>
                                    <option value="Rogo">Rogo</option>
                                    <option value="Shanono">Shanono</option>
                                    <option value="Sumaila">Sumaila</option>
                                    <option value="Takai">Takai</option>
                                    <option value="Tarauni">Tarauni</option>
                                    <option value="Tofa">Tofa</option>
                                    <option value="Tsanyawa">Tsanyawa</option>
                                    <option value="Tudun Wada">Tudun Wada</option>
                                    <option value="Ungogo">Ungogo</option>
                                    <option value="Warawa">Warawa</option>
                                    <option value="Wudil">Wudil</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="district" class="label">District</label>
                                <input id="district" name="district" class="input" placeholder="Enter district">
                            </div>
                            <div class="space-y-2">
                                <label for="plotNumber" class="label">Plot Number</label>
                                <input id="plotNumber" name="plotNumber" class="input" placeholder="Enter plot number">
                            </div>
                           
                            <div class="space-y-2">
                                <label for="propertyLocationSurvey" class="label">Address</label>
                                <input id="propertyLocationSurvey" name="propertyLocationSurvey" class="input" placeholder="Enter property location">
                            </div>

                             <div class="space-y-2">
                                <label for="surveyorNameSurvey" class="label">Name of Surveyor</label>
                                <input id="surveyorNameSurvey" name="surveyorNameSurvey" class="input" placeholder="Enter name of surveyor">
                            </div>
                            
                        </div>
                    </div>
                </div>

                <!-- Instrument-Specific Fields Section -->
                <div id="instrument-specific-section" class="border rounded-md p-4 bg-gray-50">
                    <h3 class="text-lg font-medium mb-3">Additional Details</h3>
                    
                    <div id="instrument-fields" class="space-y-4">
                        <!-- Dynamic fields will be inserted here -->
                    </div>

                    <!-- Registration Dates -->
                    <div class="border-t pt-4 mt-4">
                        {{-- <h4 class="font-medium mb-3">Registration Dates</h4> --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2 hidden">
                                <label for="registrationDate" class="label">Registration Date</label>
                                <div class="date-picker">
                                    <input id="registrationDate" name="registrationDate" type="date" class="input date-picker-input" value="00">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label for="entryDate" class="label">Entry Date</label>
                                <div class="date-picker">
                                    <input id="entryDate" name="entryDate" type="date" class="input date-picker-input" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="p-6 border-t flex justify-end gap-2">
                <button type="button" id="cancel-btn" class="btn btn-outline">Cancel</button>
                <button type="button" id="submit-btn" class="btn btn-primary">Submit</button>
            </div>
        </div>
    </div>

        </div>

       
    </div>

@include('instruments.create.js')
 
        <!-- Footer -->
        @include('admin.footer')
    </div>

@include('instruments.create.js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const surveyCheckbox = document.getElementById('surveyInfo');
    const surveySection = document.getElementById('survey-info-section');
    if (surveyCheckbox && surveySection) {
        surveyCheckbox.addEventListener('change', function() {
            if (surveyCheckbox.checked) {
                surveySection.classList.remove('hidden');
            } else {
                surveySection.classList.add('hidden');
            }
        });
    }
});
</script>
@endsection