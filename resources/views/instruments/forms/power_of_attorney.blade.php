<div id="poa-form-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
          <div class="p-6">
              <div class="flex justify-between items-center mb-2">
                  <div>
                      <h2 class="text-xl font-bold">Register Power of Attorney</h2>
                      <p class="text-gray-500">Enter the details for the new instrument</p>
                  </div>
                  <button id="close-poa-form-btn" type="button" class="text-gray-500 hover:text-gray-700">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                      </svg>
                  </button>
              </div>

              <form id="poa-form" action="{{ route('instruments.store') }}" method="POST" class="space-y-6 mt-6">
                  @csrf
                  <!-- Hidden field for instrument type -->
                  <input type="hidden" name="instrument_type" id="instrument_type" value="Power of Attorney">

                  <!-- Add this to implement the updateFormFileData function if it was missing -->
                  <script>
                      function updateFormFileData() {
                          // Get values from file number fields
                          const mlsFileNo = document.getElementById('mlsPreviewFileNumber')?.value || '';
                          const kangisFileNo = document.getElementById('kangisPreviewFileNumber')?.value || '';
                          const newKangisFileNo = document.getElementById('newKangisPreviewFileNumber')?.value || '';
                          
                          // Set hidden field values
                          document.getElementById('mlsFNo').value = mlsFileNo;
                          document.getElementById('kangisFileNo').value = kangisFileNo;
                          document.getElementById('NewKANGISFileno').value = newKangisFileNo;
                          
                          console.log('File numbers updated:', {
                              mlsFNo: mlsFileNo,
                              kangisFileNo: kangisFileNo,
                              NewKANGISFileno: newKangisFileNo
                          });
                          
                          return true;
                      }
                  </script>




                  <!-- File Number Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">File Number</h3>
                      </div>
                      <div class="p-4">
                          <div class="mb-4">
                              <label class="inline-flex items-center">
                                  <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                                  <span class="ml-2 text-sm">This application has no Extant File Number (Use
                                      Temporary File Number)</span>
                              </label>
                          </div>

                     @include('instruments.partial.fileno')


                      </div>
                  </div>


                   <!-- Particulars Registration Number Section -->
            


                  <!-- Registration Details Section -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">Registration Details</h3>
                    </div>
                    <div class="p-4 space-y-5">
                        <div>
                            <label for="rootRegistrationNumber" class="block text-sm font-medium text-gray-700 mb-1">Registration Number (ROOT TITLE)</label>
                            <div class="flex space-x-2">
                                <input type="text" id="rootRegistrationNumber" name="rootRegistrationNumber" value="0/0/0" 
                                    class="form-input bg-gray-50 border border-gray-300 text-gray-700 rounded-md flex-1" readonly>
                                {{-- <button type="button" id="generate-particulars-btn" 
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Generate Number
                                </button> --}}
                            </div>
                            <p class="text-xs text-gray-500 mt-1.5">Customary Titles are registered as ROOT TITLES with Registration Number 0/0/0 by default.</p>
                        </div>

                        <div>
                            <label for="particularsRegistrationNumber" class="block text-sm font-medium text-gray-700 mb-1">Root Registration Number</label>
                            <input type="text" id="particularsRegistrationNumber" name="particularsRegistrationNumber" 
                                class="form-input w-full border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                placeholder="Enter root registration number">
                        </div>
                    </div>
                </div>

                  <!-- Grantor Information Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">Grantor Information</h3>
                      </div>
                      <div class="p-4">
                          <div class="mb-4">
                              <label class="form-label">Grantor Name</label>
                              <input type="text" name="Grantor" class="form-input" placeholder="Enter grantor's full name" required>
                          </div>

                          <div class="mb-4">
                              <h4 class="text-md font-medium mb-2">Grantor Address</h4>
                              <textarea name="GrantorAddress" class="form-input w-full" rows="3" placeholder="Enter grantor's complete address" required></textarea>
                          </div>
                      </div>
                  </div>

                  <!-- Grantee Information Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">Grantee Information</h3>
                      </div>
                      <div class="p-4">
                          <div class="mb-4">
                              <label class="form-label">Grantee Name</label>
                              <input type="text" name="Grantee" class="form-input" placeholder="Enter grantee's full name" required>
                          </div>

                          <div class="mb-4">
                              <h4 class="text-md font-medium mb-2">Grantee Address</h4>
                              <textarea name="GranteeAddress" class="form-input w-full" rows="3" placeholder="Enter grantee's complete address" required></textarea>
                          </div>
                      </div>
                  </div>

                  <!-- Solicitor Information Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">Solicitor Information</h3>
                      </div>
                      <div class="p-4">
                          <div class="mb-4">
                              <label class="form-label">Solicitor Name</label>
                              <input type="text" name="solicitorName" class="form-input" placeholder="Enter solicitor's full name">
                          </div>

                          <div class="mb-4">
                              <label class="form-label">Solicitor Address</label>
                              <textarea name="solicitorAddress" class="form-input" rows="3" placeholder="Enter solicitor's complete address"></textarea>
                          </div>
                      </div>
                  </div>

                  <!-- Property Details Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">Property Details</h3>
                      </div>
                      <div class="p-4">
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Local Government Area (LGA) Selection --}}
                        @include('instruments.forms.lga')

                        <div>
                        <label for="district" class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" id="district" name="district" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                         </div> 
                         </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                               <div>
                        <label for="plotNumber" class="block text-sm font-medium text-gray-700 mb-1">Plot Number </label>
                        <input type="text" id="plotNumber" name="plotNumber" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                         <div>
                              <label class="form-label">Plot Size</label>
                              <input type="text" name="size" class="form-input"
                                  placeholder="Enter plot size (e.g., 100 × 50 meters)">
                          </div>
                        </div>

                          <div class="mb-4">
                              <label class="form-label">Plot Description</label>
                              <textarea class="form-input" name="propertyDescription" rows="3" placeholder="Enter plot description"></textarea>
                          </div>

                          <div class="mb-4">
                              <label class="inline-flex items-center">
                                  <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                                  <span class="ml-2 text-sm">Include Survey Information</span>
                              </label>
                          </div>
                      </div>
                  </div>

                  <!-- Additional Details Section -->
                  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                      <div class="p-4 border-b border-gray-200">
                          <h3 class="text-lg font-semibold">Additional Details</h3>
                      </div>
                      <div class="p-4">
                          <div class="mb-4">
                              <label class="form-label">Duration</label>
                              <input type="text" name="duration" class="form-input" placeholder="Enter duration (e.g., 5 years)">
                          </div>
                      </div>
                  </div>

                  <!-- Registration Dates Section -->
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">Registration Dates</h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Instrument Date</label>
                                <div class="relative">
                                    <input type="date" name="instrumentDate" class="form-input" value="{{ date('Y-m-d') }}" required>
                                    <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

               

                  <!-- Form Actions -->
                  <div class="flex justify-end gap-2">
                      <button type="button" id="cancel-form-btn"
                          class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                          Cancel
                      </button>
                      <button type="submit"
                          class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
                          Submit
                      </button>
                  </div>
              </form>
          </div>
      </div>
  </div>
