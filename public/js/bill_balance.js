document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('bill-balance-modal');
  if (!modal) return;

  const form = document.getElementById('bill-balance-form');
  const methodInput = form.querySelector('input[name="_method"]');
  const titleEl = document.getElementById('bill-balance-modal-title');
  const submitBtn = document.getElementById('wizard-submit-btn');
  const submitBtnLabel = submitBtn ? submitBtn.querySelector('span') : null;
  const storeUrl = form.dataset.storeUrl;
  const amountInput = form.querySelector('input[name="amount"]');
  const areaInput = document.getElementById('area');
  const unitCostInput = document.getElementById('unit_cost');
  const referenceInput = form.querySelector('input[name="reference"]');
  const preparedAtInput = document.getElementById('prepared_at');
  const selectFileBtn = document.getElementById('select-file-number-btn');
  const fileNumberInput = document.getElementById('file_number');
  const applicantNameInput = document.getElementById('applicant_name');
  const applicantAddressInput = document.getElementById('applicant_address');
  const locationStationInput = document.getElementById('location_station');
  const districtHiddenInput = document.getElementById('district');

  const appHouseNumberInput = document.getElementById('app_house_number');
  const appPlotNumberInput = document.getElementById('app_plot_number');
  const appStreetNameInput = document.getElementById('app_street_name');
  const appStreetOtherWrapper = document.getElementById('app-street-other-wrapper');
  const appStreetOtherInput = document.getElementById('app_street_other');
  const appDistrictInput = document.getElementById('app_district');
  const appDistrictOtherWrapper = document.getElementById('app-district-other-wrapper');
  const appDistrictOtherInput = document.getElementById('app_district_other');
  const appLgaInput = document.getElementById('app_lga');
  const appStateInput = document.getElementById('app_state');

  const locHouseNumberInput = document.getElementById('loc_house_number');
  const locPlotNumberInput = document.getElementById('loc_plot_number');
  const locStreetNameInput = document.getElementById('loc_street_name');
  const locStreetOtherWrapper = document.getElementById('loc-street-other-wrapper');
  const locStreetOtherInput = document.getElementById('loc_street_other');
  const locDistrictInput = document.getElementById('loc_district');
  const locDistrictOtherWrapper = document.getElementById('loc-district-other-wrapper');
  const locDistrictOtherInput = document.getElementById('loc_district_other');
  const locLgaInput = document.getElementById('loc_lga');
  const locStateInput = document.getElementById('loc_state');

  const knownDistrictValues = locDistrictInput
    ? Array.from(locDistrictInput.options)
      .map((option) => (option.value || '').trim())
      .filter((value) => value && value.toLowerCase() !== 'other')
    : [];

  const knownStreetValues = locStreetNameInput
    ? Array.from(locStreetNameInput.options)
      .map((option) => (option.value || '').trim())
      .filter((value) => value && value.toLowerCase() !== 'other')
    : [];

  const toUpper = (value) => (value || '').toUpperCase();

  const uppercaseElementValue = (el) => {
    if (!el || typeof el.value !== 'string') return;
    el.value = toUpper(el.value);
  };

  const bindUppercaseInput = (el) => {
    if (!el) return;
    el.addEventListener('input', () => uppercaseElementValue(el));
    el.addEventListener('blur', () => uppercaseElementValue(el));
  };

  // Land use code map for expiry calculation
  const LAND_USE_EXPIRY_YEARS = {
    RES: 99,
    AG: 99, AGRIC: 99,
    COM: 40,
    IND: 40,
  };

  const deriveLandUseCode = (fileNumber) => {
    const upper = String(fileNumber).toUpperCase().trim();
    const segments = upper.split(/[-\/\_\s]+/).filter(Boolean);
    // Scan all segments for a land use code (handles ST-RES-, CON-RES-, CON-RC-COM-, RC-IND-, etc.)
    const landUseCodes = ['AGRIC', 'RES', 'COM', 'IND', 'AG'];
    for (const seg of segments) {
      for (const code of landUseCodes) {
        if (seg === code) return code;
      }
    }
    return null;
  };

  const dateOfExpiryInput = document.getElementById('date_of_expiry');

  // Date of Expiry = Date of Issue + 99 years (auto-calculated; user can still override).
  const calculateExpiryDate = () => {
    if (preparedAtInput && dateOfExpiryInput && preparedAtInput.value) {
      const issue = new Date(preparedAtInput.value);
      if (!isNaN(issue.getTime())) {
        const expiry = new Date(issue);
        expiry.setFullYear(expiry.getFullYear() + 99);
        const yyyy = expiry.getFullYear();
        const mm = String(expiry.getMonth() + 1).padStart(2, '0');
        const dd = String(expiry.getDate()).padStart(2, '0');
        dateOfExpiryInput.value = `${yyyy}-${mm}-${dd}`;
      }
    }
    if (typeof calculateTotalRentOverTerm === 'function') {
      calculateTotalRentOverTerm();
    }
  };

  if (preparedAtInput) {
    preparedAtInput.addEventListener('change', calculateExpiryDate);
  }

  // Auto-calculate Rent Per Annum = Area × Unit Cost (user can still override manually).
  const calculateRent = () => {
    if (areaInput && unitCostInput && amountInput) {
      const area = parseFloat(areaInput.value) || 0;
      const cost = parseFloat(unitCostInput.value) || 0;
      if (area > 0 && cost > 0) {
        amountInput.value = (area * cost).toFixed(2);
      }
    }
    calculateTotalRentOverTerm();
  };

  // Cumulative Annual = Rent Per Annum × |From − To|
  const totalRentOverTermInput = document.getElementById('total-rent-over-term');
  const rentFromYearInput = document.getElementById('rent_from_year');
  const rentToYearInput = document.getElementById('rent_to_year');
  const rentYearRangeDisplay = document.getElementById('rent-year-range-display');
  const rentFromDisplay = document.getElementById('rent-from-display');
  const rentToDisplay = document.getElementById('rent-to-display');
  const rentTermYearsDisplay = document.getElementById('rent-term-years-display');
  const rentTermYearsPlural = document.getElementById('rent-term-years-plural');
  const rentYearRangeInline = document.getElementById('rent_year_range_text');
  const calculateTotalRentOverTerm = () => {
    if (!totalRentOverTermInput || !amountInput) return;
    const rentPerAnnum = parseFloat(amountInput.value) || 0;
    const fromYear = parseInt(rentFromYearInput?.value || '', 10);
    const toYear = parseInt(rentToYearInput?.value || '', 10);
    if (!fromYear || !toYear) {
      totalRentOverTermInput.value = '';
      if (rentYearRangeDisplay) rentYearRangeDisplay.classList.add('hidden');
      if (rentYearRangeInline) rentYearRangeInline.value = '';
      safeCalculateTotal();
      return;
    }
    const years = Math.abs(toYear - fromYear);
    totalRentOverTermInput.value = (rentPerAnnum * years).toFixed(2);
    const pluralSuffix = years === 1 ? '' : 's';
    if (rentYearRangeDisplay) {
      rentYearRangeDisplay.classList.remove('hidden');
      if (rentFromDisplay) rentFromDisplay.textContent = fromYear;
      if (rentToDisplay) rentToDisplay.textContent = toYear;
      if (rentTermYearsDisplay) rentTermYearsDisplay.textContent = years;
      if (rentTermYearsPlural) rentTermYearsPlural.textContent = pluralSuffix;
    }
    if (rentYearRangeInline) {
      rentYearRangeInline.value = `${years} year${pluralSuffix}`;
    }
    safeCalculateTotal();
  };

  // Safely invoke calculateTotal; feeInputs is declared later in the file,
  // so early calls during initialization must be swallowed (TDZ guard).
  const safeCalculateTotal = () => {
    try {
      if (typeof calculateTotal === 'function') calculateTotal();
    } catch (e) { /* feeInputs not yet initialized */ }
  };

  if (areaInput) areaInput.addEventListener('input', calculateRent);
  if (unitCostInput) unitCostInput.addEventListener('input', calculateRent);
  if (amountInput) amountInput.addEventListener('input', calculateTotalRentOverTerm);
  if (rentFromYearInput) rentFromYearInput.addEventListener('change', calculateTotalRentOverTerm);
  if (rentToYearInput) rentToYearInput.addEventListener('change', calculateTotalRentOverTerm);
  // Initialize on load
  calculateTotalRentOverTerm();

  // Initialize GlobalFileNoModal when available
  if (window.GlobalFileNoModal) {
    window.GlobalFileNoModal.init();
  }

  // File number selection handler
  if (selectFileBtn) {
    selectFileBtn.addEventListener('click', () => {
      if (window.GlobalFileNoModal) {
        window.GlobalFileNoModal.open({
          callback: (data) => {
            if (data.fileNumber) {
              fileNumberInput.value = toUpper(data.fileNumber);
              fileNumberInput.classList.add('auto-filled');
              fileNumberInput.style.backgroundColor = '#f1f5f9';
              fileNumberInput.style.color = '#64748b';
              fileNumberInput.readOnly = true;
              
              // Auto-populate applicant name from file record
              if (data.record && data.record.file_name && !applicantNameInput.value.trim()) {
                applicantNameInput.value = toUpper(data.record.file_name);
                applicantNameInput.classList.add('auto-filled');
                applicantNameInput.style.backgroundColor = '#f1f5f9';
                applicantNameInput.style.color = '#64748b';
                applicantNameInput.readOnly = true;
              }
              
              // Fetch residence address from file_indexings table
              fetchResidenceAddress(data.fileNumber);

              // Flag Sectional Titling files and map across their recorded fees
              applyStFileProfile(data.fileNumber);

              // Auto-calculate expiry date based on land use
              calculateExpiryDate();
            }
          }
        });
      } else {
        console.warn('GlobalFileNoModal not available');
      }
    });
  }

  /**
   * Sectional Titling files carry their own fees on the ST Initial Bill, so
   * badge the file and carry those figures across instead of re-costing here.
   *
   * The names cross over — see StBillBalanceResolver::ST_FEE_MAP, which is the
   * source of truth; the server does the mapping and returns Deeds field names.
   */
  function applyStFileProfile(fileNumber) {
    const badge = document.getElementById('st-file-badge');
    const badgeText = document.getElementById('st-file-badge-text');

    const hideBadge = () => {
      if (badge) {
        badge.classList.add('hidden');
        badge.classList.remove('inline-flex');
      }
    };

    hideBadge();
    if (!fileNumber) return;

    fetch(`/bill-balance/st-fees?file_number=${encodeURIComponent(fileNumber)}`)
      .then(response => response.json())
      .then(payload => {
        const d = payload && payload.data;
        if (!payload.success || !d || !d.is_st) return;

        // --- badge ---
        if (badge) {
          badge.classList.remove('hidden');
          badge.classList.add('inline-flex');
        }
        if (badgeText) {
          badgeText.textContent = d.unit_type ? `ST File · ${d.unit_type}` : 'ST File';
        }

        // --- reference: adopt the ST bill's id so both modules share one ---
        if (d.bill_reference && !d.bill_reference_taken && referenceInput) {
          referenceInput.value = d.bill_reference;
          setReferenceDisplay(d.bill_reference);
        } else if (d.bill_reference_taken) {
          console.warn(`ST reference ${d.bill_reference} is already used by another bill balance; keeping the generated DBBL reference.`);
        }

        // --- fees ---
        Object.keys(d.fees || {}).forEach((deedsField) => {
          const input = document.querySelector(`[name="${deedsField}"]`);
          if (!input) return;

          input.value = Number(d.fees[deedsField]).toFixed(2);
          input.classList.add('auto-filled');
          input.style.backgroundColor = '#eef2ff'; // indigo-50, marks an ST-sourced figure
          input.style.color = '#4338ca';
        });

        calculateTotal();
      })
      .catch(error => console.error('ST fee lookup failed:', error));
  }

  // Function to fetch residence address and auto-populate fields
  function fetchResidenceAddress(fileNumber) {
    if (!fileNumber) return;

    fetch(`/bill-balance/residence-address?file_number=${encodeURIComponent(fileNumber)}`)
      .then(response => response.json())
      .then(data => {
        if (!data.success || !data.data) return;
        const d = data.data;

        // Helper: mark a field as auto-filled (greyed-out + disabled)
        const markAutoFilled = (el) => {
          if (!el) return;
          el.classList.add('auto-filled');
          el.style.backgroundColor = '#f1f5f9'; // slate-100
          el.style.color = '#64748b';            // slate-500
          el.readOnly = true;
          if (el.tagName === 'SELECT') {
            el.style.pointerEvents = 'none';
          }
        };

        // Helper: mark a field as suggested (tinted, but still editable)
        const markSuggested = (el) => {
          if (!el) return;
          el.classList.add('auto-filled');
          el.style.backgroundColor = '#f1f5f9'; // slate-100
        };

        // --- Applicant Details card ---
        // Applicant name from file_title
        if (d.file_title && applicantNameInput && !applicantNameInput.value.trim()) {
          applicantNameInput.value = d.file_title;
          markAutoFilled(applicantNameInput);
        }

        // Applicant address directly from residence_address (NOT from property location sub-fields)
        if (d.residence_address && applicantAddressInput && !applicantAddressInput.value.trim()) {
          applicantAddressInput.value = toUpper(d.residence_address);
          markAutoFilled(applicantAddressInput);
        } else if (!d.residence_address) {
          // No residence on file -- an ST unit buyer never has one -- so the property
          // location stands in for it, otherwise the required app_plot_number and
          // app_lga are left for the clerk to retype from the card they just picked.
          // Suggested, not locked: unlike the Location & Rent fields these stay
          // editable, because the property is only standing in for a residence the
          // registry does not hold.
          if (d.plot_number && appPlotNumberInput && !appPlotNumberInput.value.trim()) {
            appPlotNumberInput.value = toUpper(d.plot_number);
            markSuggested(appPlotNumberInput);
          }

          if (d.street_name && appStreetNameInput) {
            setSelectOrOther(appStreetNameInput, d.street_name, appStreetOtherWrapper, appStreetOtherInput);
            markSuggested(appStreetNameInput);
            if (appStreetOtherInput) markSuggested(appStreetOtherInput);
          }

          if (d.district && appDistrictInput) {
            setSelectOrOther(appDistrictInput, d.district, appDistrictOtherWrapper, appDistrictOtherInput);
            markSuggested(appDistrictInput);
            if (appDistrictOtherInput) markSuggested(appDistrictOtherInput);
          }

          if (d.lga && appLgaInput) {
            setSelectValue(appLgaInput, d.lga);
            markSuggested(appLgaInput);
          }

          // Rebuild the readonly applicant address from whatever landed above.
          setTimeout(() => updateApplicantAddressOutput(), 50);
        }

        // --- Location & Rent card ---
        // Plot number (location)
        if (d.plot_number && locPlotNumberInput && !locPlotNumberInput.value.trim()) {
          locPlotNumberInput.value = toUpper(d.plot_number);
          markAutoFilled(locPlotNumberInput);
        }

        // Street name (location)
        if (d.street_name && locStreetNameInput) {
          setSelectOrOther(locStreetNameInput, d.street_name, locStreetOtherWrapper, locStreetOtherInput);
          markAutoFilled(locStreetNameInput);
          if (locStreetOtherInput) markAutoFilled(locStreetOtherInput);
        }

        // District (location)
        if (d.district && locDistrictInput) {
          setSelectOrOther(locDistrictInput, d.district, locDistrictOtherWrapper, locDistrictOtherInput);
          markAutoFilled(locDistrictInput);
          if (locDistrictOtherInput) markAutoFilled(locDistrictOtherInput);
          // Also set the hidden district input
          if (districtHiddenInput) districtHiddenInput.value = d.district;
        }

        // LGA (location)
        if (d.lga && locLgaInput) {
          setSelectValue(locLgaInput, d.lga);
          markAutoFilled(locLgaInput);
        }

        // Location / Station
        if (locationStationInput && !locationStationInput.value.trim()) {
          const locValue = d.location || '';
          if (locValue) {
            locationStationInput.value = toUpper(locValue);
            markAutoFilled(locationStationInput);
          }
        }

        // Trigger location address re-build
        setTimeout(() => updateLocationAddressOutput(), 50);
      })
      .catch(error => {
        console.warn('Error fetching residence address:', error);
      });
  }

  // Helper: set a <select> value, case-insensitive match
  function setSelectValue(selectEl, value) {
    if (!selectEl || !value) return false;
    const normalised = value.trim().toLowerCase();
    for (const opt of selectEl.options) {
      if (opt.value.trim().toLowerCase() === normalised) {
        selectEl.value = opt.value;
        selectEl.dispatchEvent(new Event('change'));
        return true;
      }
    }
    return false;
  }

  // Helper: set select or show "Other" wrapper with free text
  function setSelectOrOther(selectEl, value, otherWrapper, otherInput) {
    if (!selectEl || !value) return;
    const matched = setSelectValue(selectEl, value);
    if (!matched && otherWrapper && otherInput) {
      // Check if there's an "Other" option
      const hasOther = Array.from(selectEl.options).some(o => o.value.toLowerCase() === 'other');
      if (hasOther) {
        selectEl.value = 'Other';
        selectEl.dispatchEvent(new Event('change'));
        otherWrapper.classList.remove('hidden');
        otherInput.value = value;
      } else {
        // No Other option, just set the value directly
        selectEl.value = value;
        selectEl.dispatchEvent(new Event('change'));
      }
    }
  }

  // Fee calculation functionality
  const feeInputs = [
    'Site_Plan_Fee',
    'survey_fee',
    'Processing_Fee',
    'Betterment_Charges',
    'Land_Use_Charge'
  ].map((name) => form.querySelector(`[name="${name}"]`)).filter(Boolean);
  const totalAmountInput = document.getElementById('total-amount');
  const amountInWordsInput = document.getElementById('amount-in-words');
  const amountDepositedInput = document.getElementById('amount-deposited');
  const balanceDueToInput = document.getElementById('balance-due-to');
  const balanceDueAmountInput = document.getElementById('balance-due-amount');

  const getCurrentDateLocal = () => {
    const now = new Date();
    const offset = now.getTimezoneOffset();
    const local = new Date(now.getTime() - offset * 60000);
    return local.toISOString().slice(0, 10);
  };

  const referenceDisplay = document.getElementById('fees-reference-display');

  const setReferenceDisplay = (ref) => {
    if (!referenceDisplay) return;
    if (ref) {
      referenceDisplay.textContent = ref;
      referenceDisplay.classList.remove('hidden');
    } else {
      referenceDisplay.textContent = '';
      referenceDisplay.classList.add('hidden');
    }
  };

  const fetchNextReference = () => {
    if (!referenceInput) return;

    fetch('/bill-balance/next-reference')
      .then(response => response.json())
      .then(result => {
        if (result.success && result.data?.reference) {
          referenceInput.value = result.data.reference;
          setReferenceDisplay(result.data.reference);
        }
      })
      .catch(() => {
        // keep existing value silently
      });
  };

  const isOtherValue = (value) => (value || '').trim().toLowerCase() === 'other';

  const toggleOtherInput = (selectInput, otherWrapper, otherInput) => {
    if (!selectInput || !otherWrapper || !otherInput) return;
    const showOther = isOtherValue(selectInput.value);
    otherWrapper.classList.toggle('hidden', !showOther);
    otherInput.required = showOther;
    if (!showOther) {
      otherInput.value = '';
    }
  };

  const selectedOrOther = (selectInput, otherInput) => {
    if (!selectInput) return '';
    if (isOtherValue(selectInput.value)) {
      return (otherInput?.value || '').trim();
    }
    return (selectInput.value || '').trim();
  };

  const buildApplicantAddress = () => {
    // Only one of plot number OR street joins the address:
    // if plot number has a value, use it; otherwise fall back to street.
    const plotNo = (appPlotNumberInput?.value || '').trim();
    const street = selectedOrOther(appStreetNameInput, appStreetOtherInput);
    const values = [
      // House number is the number on the building; it leads the address when given.
      (appHouseNumberInput?.value || '').trim(),
      plotNo ? plotNo : street,
      selectedOrOther(appDistrictInput, appDistrictOtherInput),
      (appLgaInput?.value || '').trim(),
      (appStateInput?.value || '').trim(),
    ].filter(Boolean);
    return toUpper(values.join(', '));
  };

  const buildLocationAddress = () => {
    const districtValue = selectedOrOther(locDistrictInput, locDistrictOtherInput);
    if (districtHiddenInput) {
      districtHiddenInput.value = districtValue;
    }

    // Only one of plot number OR street joins the address:
    // if plot number has a value, use it; otherwise fall back to street.
    const plotNo = (locPlotNumberInput?.value || '').trim();
    const street = selectedOrOther(locStreetNameInput, locStreetOtherInput);
    const values = [
      // House number is the number on the building; it leads the address when given.
      (locHouseNumberInput?.value || '').trim(),
      plotNo ? plotNo : street,
      districtValue,
      (locLgaInput?.value || '').trim(),
      (locStateInput?.value || '').trim(),
    ].filter(Boolean);
    return toUpper(values.join(', '));
  };

  const updateApplicantAddressOutput = () => {
    const compiledAddress = buildApplicantAddress();
    if (compiledAddress && applicantAddressInput) {
      applicantAddressInput.value = compiledAddress;
    }
  };

  const updateLocationAddressOutput = () => {
    const compiledAddress = buildLocationAddress();
    if (compiledAddress && locationStationInput) {
      locationStationInput.value = compiledAddress;
    }
  };

  // Convert number to words
  function numberToWords(num) {
    if (num === 0) return 'Zero naira only';

    const ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
    const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    const teens = ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    
    function convertHundreds(n) {
      let result = '';
      if (n >= 100) {
        result += ones[Math.floor(n / 100)] + ' hundred ';
        n %= 100;
      }
      if (n >= 20) {
        result += tens[Math.floor(n / 10)];
        if (n % 10 > 0) result += '-' + ones[n % 10];
      } else if (n >= 10) {
        result += teens[n - 10];
      } else if (n > 0) {
        result += ones[n];
      }
      return result.trim();
    }
    
    const billion = Math.floor(num / 1000000000);
    const million = Math.floor((num % 1000000000) / 1000000);
    const thousand = Math.floor((num % 1000000) / 1000);
    const remainder = num % 1000;
    
    let result = '';
    
    if (billion > 0) {
      result += convertHundreds(billion) + ' billion ';
    }
    if (million > 0) {
      result += convertHundreds(million) + ' million ';
    }
    if (thousand > 0) {
      result += convertHundreds(thousand) + ' thousand ';
    }
    if (remainder > 0) {
      result += convertHundreds(remainder);
    }
    
    // Capitalize first letter
    result = result.trim().charAt(0).toUpperCase() + result.trim().slice(1);
    return result + ' naira only';
  }

  // Calculate total fees (Cumulative Annual + other fees)
  function calculateTotal() {
    let total = 0;
    // Use Cumulative Annual (Rent Per Annum × |From − To|) in place of Rent Per Annum
    const cumulativeAnnual = parseFloat(totalRentOverTermInput?.value) || 0;
    total += cumulativeAnnual;
    feeInputs.forEach(input => {
      const value = parseFloat(input.value) || 0;
      total += value;
    });
    
    if (totalAmountInput) {
      totalAmountInput.value = total.toFixed(2);
    }

    // Sync total to step 4 preview
    const step4Total = document.getElementById('step4-total-amount');
    if (step4Total) {
      step4Total.textContent = total.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (amountInWordsInput) {
      amountInWordsInput.value = numberToWords(Math.floor(total));
    }

    const deposited = parseFloat(amountDepositedInput?.value) || 0;
    const balance = total - deposited;
    if (balanceDueToInput) {
      balanceDueToInput.value = balance < 0 ? 'Applicant' : 'Government';
    }
    if (balanceDueAmountInput) {
      balanceDueAmountInput.value = Math.abs(balance).toFixed(2);
    }
  }

  // Add event listeners to fee inputs
  feeInputs.forEach(input => {
    input.addEventListener('input', calculateTotal);
    input.addEventListener('change', calculateTotal);
  });

  amountDepositedInput?.addEventListener('input', calculateTotal);
  amountDepositedInput?.addEventListener('change', calculateTotal);

  // Rent Per Annum also affects the total
  amountInput?.addEventListener('input', calculateTotal);
  amountInput?.addEventListener('change', calculateTotal);

  [appHouseNumberInput, appPlotNumberInput, appStreetNameInput, appStreetOtherInput, appDistrictInput, appDistrictOtherInput, appLgaInput, appStateInput]
    .forEach((input) => {
      input?.addEventListener('input', updateApplicantAddressOutput);
      input?.addEventListener('change', updateApplicantAddressOutput);
    });

  [locHouseNumberInput, locPlotNumberInput, locStreetNameInput, locStreetOtherInput, locDistrictInput, locDistrictOtherInput, locLgaInput, locStateInput]
    .forEach((input) => {
      input?.addEventListener('input', updateLocationAddressOutput);
      input?.addEventListener('change', updateLocationAddressOutput);
    });

  appStreetNameInput?.addEventListener('change', () => {
    toggleOtherInput(appStreetNameInput, appStreetOtherWrapper, appStreetOtherInput);
    updateApplicantAddressOutput();
  });
  appDistrictInput?.addEventListener('change', () => {
    toggleOtherInput(appDistrictInput, appDistrictOtherWrapper, appDistrictOtherInput);
    updateApplicantAddressOutput();
  });

  locStreetNameInput?.addEventListener('change', () => {
    toggleOtherInput(locStreetNameInput, locStreetOtherWrapper, locStreetOtherInput);
    updateLocationAddressOutput();
  });
  locDistrictInput?.addEventListener('change', () => {
    toggleOtherInput(locDistrictInput, locDistrictOtherWrapper, locDistrictOtherInput);
    updateLocationAddressOutput();
  });

  toggleOtherInput(appStreetNameInput, appStreetOtherWrapper, appStreetOtherInput);
  toggleOtherInput(appDistrictInput, appDistrictOtherWrapper, appDistrictOtherInput);
  toggleOtherInput(locStreetNameInput, locStreetOtherWrapper, locStreetOtherInput);
  toggleOtherInput(locDistrictInput, locDistrictOtherWrapper, locDistrictOtherInput);

  // Calculate initial total
  calculateTotal();

  const setValue = (name, value) => {
    const input = form.querySelector(`[name="${name}"]`);
    if (input) {
      input.value = value ?? '';
    }
  };

  const formatAmount = () => {
    if (!amountInput) return;
    const numericValue = Number(amountInput.value || 0);
    amountInput.value = Number.isNaN(numericValue) ? '' : numericValue.toFixed(2);
  };

  const clearAutoFilledStyles = () => {
    form.querySelectorAll('.auto-filled').forEach(el => {
      el.classList.remove('auto-filled');
      el.style.backgroundColor = '';
      el.style.color = '';
      el.readOnly = false;
      el.style.pointerEvents = '';
    });
  };

  const resetForm = () => {
    form.reset();
    clearAutoFilledStyles();
    methodInput.value = 'POST';
    form.action = storeUrl;
    titleEl.textContent = 'Generate Bill Balance';
    if (submitBtnLabel) submitBtnLabel.textContent = 'Generate Bill Balance';
    setReferenceDisplay('');
  };

  const openModal = () => {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  };

  const closeModal = () => {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    resetForm();
  };

  document.querySelectorAll('.open-bill-balance-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const mode = button.dataset.mode || 'create';
      resetForm();

      if (mode === 'edit') {
        const record = JSON.parse(button.dataset.record || '{}');
        const billing = JSON.parse(button.dataset.billing || '{}');
        
        setValue('reference', record.reference);
        setReferenceDisplay(record.reference);
        setValue('file_number', record.file_number);
        setValue('applicant_name', record.applicant_name);
        setValue('applicant_address', record.applicant_address);
        setValue('location_station', record.location_station);

        // Backfill applicant-address sub-fields (Plot / Street / District / LGA / State)
        setValue('app_house_number', record.app_house_number);
        setValue('app_plot_number', record.app_plot_number);
        setValue('app_lga', record.app_lga);
        setValue('app_state', record.app_state);
        if (record.app_street_name && !knownStreetValues.includes((record.app_street_name || '').trim())) {
          setValue('app_street_name', 'Other');
          setValue('app_street_other', record.app_street_name);
        } else {
          setValue('app_street_name', record.app_street_name);
          setValue('app_street_other', '');
        }
        if (record.app_district && !knownDistrictValues.includes((record.app_district || '').trim())) {
          setValue('app_district', 'Other');
          setValue('app_district_other', record.app_district);
        } else {
          setValue('app_district', record.app_district);
          setValue('app_district_other', '');
        }

        // Backfill location sub-fields (Plot / Street / District / LGA / State)
        setValue('loc_house_number', record.loc_house_number);
        setValue('loc_plot_number', record.loc_plot_number);
        setValue('loc_lga', record.loc_lga);
        setValue('loc_state', record.loc_state);
        if (record.loc_street_name && !knownStreetValues.includes((record.loc_street_name || '').trim())) {
          setValue('loc_street_name', 'Other');
          setValue('loc_street_other', record.loc_street_name);
        } else {
          setValue('loc_street_name', record.loc_street_name);
          setValue('loc_street_other', '');
        }
        if (record.district && !knownDistrictValues.includes((record.district || '').trim())) {
          setValue('loc_district', 'Other');
          setValue('loc_district_other', record.district);
        } else {
          setValue('loc_district', record.district);
          setValue('loc_district_other', '');
        }
        setValue('district', record.district);
        setValue('area', record.area);
        setValue('unit_cost', record.unit_cost);
        setValue('amount', record.amount);
        setValue('prepared_at', record.prepared_at);
        setValue('date_of_expiry', record.date_of_expiry);
        setValue('rent_from_year', record.rent_from_year);
        setValue('rent_to_year', record.rent_to_year);

        setValue('bill_balance_reciept', billing.bill_balance_reciept);
        setValue('bill_balance_date', billing.bill_balance_date);
        setValue('Site_Plan_Fee', billing.Site_Plan_Fee);
        setValue('survey_fee', billing.survey_fee);
        setValue('Processing_Fee', billing.Processing_Fee);
        setValue('Betterment_Charges', billing.Betterment_Charges);
        setValue('Land_Use_Charge', billing.Land_Use_Charge);
        setValue('Penalty_Fees', billing.Penalty_Fees);
        
        methodInput.value = 'PUT';
        form.action = button.dataset.updateUrl;
        titleEl.textContent = 'Edit Bill Balance';
        if (submitBtnLabel) submitBtnLabel.textContent = 'Update Bill Balance';
      } else {
        fetchNextReference();
        // Leave Date of Issue blank for the user to select
      }

      toggleOtherInput(appStreetNameInput, appStreetOtherWrapper, appStreetOtherInput);
      toggleOtherInput(appDistrictInput, appDistrictOtherWrapper, appDistrictOtherInput);
      toggleOtherInput(locStreetNameInput, locStreetOtherWrapper, locStreetOtherInput);
      toggleOtherInput(locDistrictInput, locDistrictOtherWrapper, locDistrictOtherInput);
      if (mode !== 'edit') {
        updateApplicantAddressOutput();
        updateLocationAddressOutput();
      }
      calculateTotal();

      // Auto-calculate expiry date whenever modal opens
      calculateExpiryDate();

      openModal();
    });
  });

  modal.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  amountInput?.addEventListener('blur', formatAmount);

  if (window.billBalanceFormShouldOpen) {
    resetForm();
    const data = window.billBalanceOldInput || {};
    const allowedFields = [
      'reference', 'file_number', 'applicant_name', 'applicant_address',
      'location_station', 'district', 'amount', 'prepared_at', 'date_of_expiry',
      'rent_from_year', 'rent_to_year',
      'app_house_number', 'loc_house_number',
      'bill_balance_reciept', 'bill_balance_date',
      'Site_Plan_Fee', 'survey_fee', 'Processing_Fee',
      'Betterment_Charges', 'Land_Use_Charge', 'Penalty_Fees'
    ];
    Object.keys(data).forEach((key) => {
      if (allowedFields.includes(key)) {
        setValue(key, data[key]);
      }
    });

    if (data.district && !knownDistrictValues.includes((data.district || '').trim())) {
      setValue('loc_district', 'Other');
      setValue('loc_district_other', data.district);
    } else {
      setValue('loc_district', data.district);
    }

    toggleOtherInput(appStreetNameInput, appStreetOtherWrapper, appStreetOtherInput);
    toggleOtherInput(appDistrictInput, appDistrictOtherWrapper, appDistrictOtherInput);
    toggleOtherInput(locStreetNameInput, locStreetOtherWrapper, locStreetOtherInput);
    toggleOtherInput(locDistrictInput, locDistrictOtherWrapper, locDistrictOtherInput);
    updateApplicantAddressOutput();
    updateLocationAddressOutput();
    calculateTotal();
    openModal();
  }

  [
    fileNumberInput,
    applicantNameInput,
    applicantAddressInput,
    locationStationInput,
    appHouseNumberInput,
    appPlotNumberInput,
    appStreetOtherInput,
    appDistrictOtherInput,
    locHouseNumberInput,
    locPlotNumberInput,
    locStreetOtherInput,
    locDistrictOtherInput,
    referenceInput,
    districtHiddenInput,
    form.querySelector('input[name="bill_balance_reciept"]'),
    balanceDueToInput,
  ].forEach(bindUppercaseInput);

  form.addEventListener('submit', () => {
    form.querySelectorAll('input[type="text"], textarea').forEach((el) => {
      uppercaseElementValue(el);
    });

    const districtValue = selectedOrOther(locDistrictInput, locDistrictOtherInput);

    if (districtHiddenInput) {
      districtHiddenInput.value = districtValue;
    }

    const applicantCompiled = buildApplicantAddress();
    if (applicantCompiled && applicantAddressInput) {
      applicantAddressInput.value = applicantCompiled;
    }

    const locationCompiled = buildLocationAddress();
    if (locationCompiled && locationStationInput) {
      locationStationInput.value = locationCompiled;
    }

    if (preparedAtInput && !preparedAtInput.value) {
      preparedAtInput.value = getCurrentDateLocal();
    }

    if (referenceInput && !referenceInput.value.trim()) {
      fetchNextReference();
    }
  });
});
