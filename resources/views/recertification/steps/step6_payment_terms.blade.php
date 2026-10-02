<!-- Step 6: Payment & Terms -->
<div id="step-content-6" class="step-content hidden">
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="credit-card" class="h-5 w-5"></i>
                SECTION D: PAYMENT & TERMS
            </h3>
        </div>
        <div class="p-4 space-y-6">
            <div>
                <h4 class="font-semibold mb-4">D1: APPLICATION TYPE</h4>
                <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Type of Application <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="radio-item">
                            <input type="radio" name="applicationType" value="recertification" required />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Re-certification</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationType" value="reissuance" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Re-issuance</span>
                        </label>
                    </div>
                    <div class="error-message">Application type is required</div>
                </div>
                
                <div class="form-field mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for Application <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="lost-certificate" required />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Lost Certificate</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="damaged-certificate" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Damaged Certificate</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="name-correction" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Name Correction</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="address-change" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Address Change</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="digitization" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Digitization</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="applicationReason" value="others" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Others</span>
                        </label>
                    </div>
                    <div class="error-message">Reason for application is required</div>
                </div>
                
                <div id="other-reason-field" class="form-field mt-4 hidden">
                    <label for="otherReason" class="block text-sm font-medium text-gray-700 mb-1">Please specify other reason:</label>
                    <input
                        type="text"
                        id="otherReason"
                        name="otherReason"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="SPECIFY OTHER REASON"
                    />
                </div>
            </div>
            
            <div>
                <h4 class="font-semibold mb-4">D2: PAYMENT INFORMATION SECTION</h4>
                <div class="grid grid-cols-3 gap-4">
                    <div class="form-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Method of Payment <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-4">
                            <label class="radio-item">
                                <input type="radio" name="paymentMethod" value="online" required />
                                <div class="radio-circle"></div>
                                <span class="text-sm">Online</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="paymentMethod" value="pos" />
                                <div class="radio-circle"></div>
                                <span class="text-sm">PoS</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="paymentMethod" value="bank" />
                                <div class="radio-circle"></div>
                                <span class="text-sm">Bank</span>
                            </label>
                        </div>
                        <div class="error-message">Payment method is required</div>
                    </div>
                    
                    <div class="form-field">
                        <label for="receiptNo" class="block text-sm font-medium text-gray-700 mb-1">Receipt No/Teller No</label>
                        <input
                            type="text"
                            id="receiptNo"
                            name="receiptNo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            placeholder="RECEIPT/TELLER NUMBER"
                        />
                    </div>
                    
                    <div class="form-field">
                        <label for="bankName" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input
                            type="text"
                            id="bankName"
                            name="bankName"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                            placeholder="BANK NAME"
                        />
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="form-field">
                        <label for="paymentAmount" class="block text-sm font-medium text-gray-700 mb-1">Payment Amount (₦)</label>
                        <input
                            type="number"
                            id="paymentAmount"
                            name="paymentAmount"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            placeholder="0.00"
                        />
                    </div>
                    
                    <div class="form-field">
                        <label for="paymentDate" class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input
                            type="date"
                            id="paymentDate"
                            name="paymentDate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        />
                    </div>
                </div>
            </div>
            
            <div class="border-t pt-4">
                <h4 class="font-semibold mb-4">D3: SUPPORTING DOCUMENTS</h4>
                <div class="space-y-3">
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="original-certificate" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">Original Certificate (if available)</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="police-report" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">Police Report (for lost certificates)</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="affidavit" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">Sworn Affidavit</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="identification" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">Valid Identification (NIN, Driver's License, etc.)</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="passport-photos" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">Recent Passport Photographs</span>
                        </label>
                    </div>
                    
                    <!-- New Document Upload Options -->
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="right-of-occupancy" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(a) Right of Occupancy</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="certificate-of-occupancy" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(b) Certificate of Occupancy</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="deed-of-assignment" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(c) Deed of Assignment</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="deed-of-sublease" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(d) Deed of Sublease</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="deed-of-mortgage" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(e) Deed of Mortgage</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="deed-of-gift" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(f) Deed of Gift</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="power-of-attorney" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(g) Power of Attorney</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="devolution-order" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(h) Devolution Order</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="letter-of-administration" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(i) Letter of Administration</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" name="documents[]" value="others" />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">(j) Others......</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="border-t pt-4">
                <h4 class="font-semibold mb-4">Declaration & Terms & Conditions of Service</h4>
                <div class="space-y-4 text-sm">
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="font-semibold mb-2">Terms & Conditions:</p>
                        <ul class="space-y-2 text-xs">
                            <li>
                                a. It is a criminal offence to provide false information or make misleading inputs when
                                completing this form.
                            </li>
                            <li>
                                b. You may be prosecuted if we find out that your Certificate of Occupancy or Land of Grant
                                (RofO) is Fake or Falsified.
                            </li>
                            <li>
                                c. Payment of Re-certification processing fee is non-refundable and does not guarantee issuance
                                of new Digital Certificate.
                            </li>
                            <li>
                                d. Processing time may vary depending on the complexity of the application and verification requirements.
                            </li>
                            <li>
                                e. All supporting documents must be original or certified true copies.
                            </li>
                            <li>
                                f. The applicant is responsible for the accuracy of all information provided.
                            </li>
                        </ul>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" id="agreeTerms" name="agreeTerms" required />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">
                                I agree with the above terms and conditions of service <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <div class="error-message">You must agree to the terms and conditions</div>
                    </div>
                    
                    <div class="form-field">
                        <label class="checkbox-item">
                            <input type="checkbox" id="confirmAccuracy" name="confirmAccuracy" required />
                            <div class="checkbox-box"></div>
                            <span class="text-sm">
                                I confirm that all information provided is accurate and complete <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <div class="error-message">You must confirm the accuracy of information</div>
                    </div>
                </div>
            </div>
            
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                <div class="text-center mb-4">
                    <div class="font-semibold">Signature/Thumb Print</div>
                    <div class="text-xs text-gray-500">
                        (please sign/Thumb Print clearly within the box provided)
                    </div>
                </div>
                <div class="signature-area">
                    <span class="text-gray-500">Signature Area</span>
                </div>
            </div>
            
            <div class="bg-blue-50 p-4 rounded-lg">
                <h5 class="font-semibold mb-2">Contact Information:</h5>
                <div class="text-xs space-y-1">
                    <p>KANGIS Complex 2 Dr Bala Muhammad Way, Nassarawa G.R.A. Kano.</p>
                    <p>Tel: +234 (0)900 0000 00, +234 (0) 900 000 000 +234 (0) 810 0000 000</p>
                    <p>Email: recertification@kangis.gov.ng, info@kangis.gov.ng, support@kangis.gov.ng</p>
                    <p>Website: https://kangis.gov.ng</p>
                </div>
            </div>
        </div>
    </div>
</div>