<!-- Contact Information Section -->
<div class="form-section">
    <div class="flex items-center gap-2 mb-4">
        <i data-lucide="phone" class="h-5 w-5 text-blue-600"></i>
        <h3 class="form-section-title" style="margin-bottom: 0;">Contact Information</h3>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <!-- Date of Birth -->
    <div class="form-group">
        <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
        <input type="date" 
               id="dob" 
               name="dob" 
               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
    </div>

    <!-- NIN and TIN in Grid -->
    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">NIN (National ID)</label>
            <input type="text" 
                   id="nin" 
                   name="nin" 
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="13 digits"
                   maxlength="13"
                   pattern="[0-9]*"
                   inputmode="numeric">
            <p class="mt-1 text-xs text-gray-500">National Identification Number (13 digits)</p>
        </div>

        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">TIN (Tax ID)</label>
            <input type="text" 
                   id="tin" 
                   name="tin" 
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="Tax Identification Number">
        </div>
    </div>

    <!-- RC NO (Registration Certificate - Corporate Only) -->
    <div class="form-group" id="rc-no-group" style="display: none;">
        <label class="block text-sm font-medium text-gray-700 mb-2">RC NO (Registration Certificate)</label>
        <input type="text" 
               id="rc-no" 
               name="rc_no" 
               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
               placeholder="For Corporate entities only">
        <p class="mt-1 text-xs text-gray-500">Required for Corporate file type</p>
    </div>

    <div class="form-group">
        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
        <div class="grid grid-cols-2 gap-3">
            <select id="country-code" name="country_code" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="+234" selected>+234 (NG)</option>
                <option value="+1">+1 (US/CA)</option>
                <option value="+44">+44 (UK)</option>
                <option value="+91">+91 (IN)</option>
                <option value="+86">+86 (CN)</option>
                <option value="+33">+33 (FR)</option>
                <option value="+49">+49 (DE)</option>
                <option value="+81">+81 (JP)</option>
                <option value="+82">+82 (KR)</option>
                <option value="+971">+971 (AE)</option>
                <option value="+966">+966 (SA)</option>
                <option value="+27">+27 (ZA)</option>
                <option value="+254">+254 (KE)</option>
                <option value="+233">+233 (GH)</option>
            </select>
            <input type="text" 
                   id="phone" 
                   name="phone" 
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="e.g., 08012345678"
                   maxlength="15"
                   pattern="[0-9]*"
                   inputmode="numeric">
        </div>
        <p id="phone-hint" class="mt-1 text-xs text-gray-500">For Nigeria (+234): Enter 11 digits starting with 0</p>
        <p id="phone-error" class="mt-1 text-xs text-red-600 hidden"></p>
    </div>
</div>
