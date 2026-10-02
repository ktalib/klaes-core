<div class="space-y-3">
    <!-- Section A: Metadata -->
    <div class="border rounded-md p-3 bg-white shadow-sm border-emerald-100">
        <h4 class="font-medium mb-2 text-emerald-800 border-b pb-1 text-xs uppercase tracking-wide">Section A -
            Instrument Metadata</h4>
        <div class="grid grid-cols-2 gap-3">
            <x-instrument-input id="instrumentNo" label="Instrument No." icon="hash" placeholder="Enter No."
                class="w-full" />
            <div class="w-full">
                <label for="landUse" class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                    </div>
                    <select id="landUse" name="landUse"
                        class="w-full pl-9 px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm">
                        <option value="">Select Use</option>
                        <option value="Residential">Residential</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Agricultural">Agricultural</option>
                        <option value="Instructional">Instructional</option>
                        <option value="Mixed Use">Mixed Use</option>
                    </select>
                </div>
            </div>
            <x-instrument-input id="dateOfExecution" label="Date of Execution" type="date" icon="calendar"
                class="w-full" />
            <x-instrument-input id="dateOfRegistration" label="Date of Registration" type="date" icon="calendar"
                class="w-full" />
        </div>
    </div>

    <!-- Section B: Donor Details -->
    <div class="border rounded-md p-3 bg-white shadow-sm border-emerald-100">
        <h4 class="font-medium mb-2 text-emerald-800 border-b pb-1 text-xs uppercase tracking-wide">Section B - Donor
            (Giver) Details</h4>
        <div class="grid grid-cols-2 gap-3">
            <x-instrument-input id="donorPhone" label="Phone/Email" icon="phone" placeholder="Contact" class="w-full" />
            <x-instrument-input id="donorNationality" label="Nationality" icon="flag" placeholder="Nationality"
                class="w-full" />
            <!-- Custom Select for ID Document -->
            <div class="w-full">
                <label for="donorIdDocument" class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="credit-card" class="h-4 w-4 text-gray-400"></i>
                    </div>
                    <select id="donorIdDocument" name="donorIdDocument"
                        class="w-full pl-9 px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm">
                        <option value="">Select ID</option>
                        <option value="National ID">National ID</option>
                        <option value="International Passport">International Passport</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Voter's Card">Voter's Card</option>
                    </select>
                </div>
            </div>
            <x-instrument-input id="donorIdNumber" label="ID Number" icon="hash" placeholder="ID No" class="w-full" />
        </div>
    </div>

    <!-- Section C: Donee Details -->
    <div class="border rounded-md p-3 bg-white shadow-sm border-emerald-100">
        <h4 class="font-medium mb-2 text-emerald-800 border-b pb-1 text-xs uppercase tracking-wide">Section C - Donee
            (Receiver) Details</h4>
        <div class="grid grid-cols-2 gap-3">
            <x-instrument-input id="doneePhone" label="Phone/Email" icon="phone" placeholder="Contact" class="w-full" />
            <x-instrument-input id="doneeNationality" label="Nationality" icon="flag" placeholder="Nationality"
                class="w-full" />
            <!-- Custom Select for ID Document -->
            <div class="w-full">
                <label for="doneeIdDocument" class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="credit-card" class="h-4 w-4 text-gray-400"></i>
                    </div>
                    <select id="doneeIdDocument" name="doneeIdDocument"
                        class="w-full pl-9 px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm">
                        <option value="">Select ID</option>
                        <option value="National ID">National ID</option>
                        <option value="International Passport">International Passport</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Voter's Card">Voter's Card</option>
                    </select>
                </div>
            </div>
            <x-instrument-input id="doneeIdNumber" label="ID Number" icon="hash" placeholder="ID No" class="w-full" />
        </div>
    </div>

    {{-- <!-- Section D: Property Info -->
    <div class="border rounded-md p-3 bg-white shadow-sm border-emerald-100">
        <h4 class="font-medium mb-2 text-emerald-800 border-b pb-1 text-xs uppercase tracking-wide">Section D - Gifted
            Property Information</h4>
        <div class="grid grid-cols-3 gap-3">
            <x-instrument-input id="surveyPlanNo" label="Survey Plan No." icon="map-pin" placeholder="Plan No"
                class="w-full" />
            <x-instrument-input id="propertySize" label="Size (m²/Ha)" icon="maximize" placeholder="Size"
                class="w-full" />
            <x-instrument-input id="consideration" label="Consideration" icon="heart" placeholder="Amount"
                class="w-full" />
            <x-instrument-input id="encumbrances" label="Encumbrances" icon="alert-circle" placeholder="Encumbrances"
                class="w-full" />
            <x-instrument-textarea id="supportingDocs" label="Supporting Docs" icon="file-text"
                placeholder="List documents" class="w-full" rows="1" />
        </div>
    </div>

    <!-- Section E: Registration -->
    <div class="border rounded-md p-3 bg-white shadow-sm border-emerald-100">
        <h4 class="font-medium mb-2 text-emerald-800 border-b pb-1 text-xs uppercase tracking-wide">Section E -
            Registration</h4>
        <div class="grid grid-cols-3 gap-3">
            <x-instrument-input id="registrarName" label="Registrar's Name" icon="user" placeholder="Name"
                class="w-full" />
            <x-instrument-input id="registrationDate" label="Registration Date" type="date" icon="calendar"
                class="w-full" />
            <x-instrument-input id="volumePageNo" label="Volume & Page No." icon="book" placeholder="Vol/Page"
                class="w-full" />
            <x-instrument-input id="registrarSignature" label="Registrar's Signature" icon="pen-tool" placeholder="Ref"
                class="w-full" />
            <x-instrument-input id="blockchainHash" label="Blockchain Hash" icon="link" placeholder="Hash"
                class="w-full" />
        </div>
    </div> --}}
</div>