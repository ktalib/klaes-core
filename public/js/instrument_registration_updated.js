const safeShowElement = typeof window !== 'undefined' && typeof window.showElement === 'function'
  ? window.showElement
  : (el) => {
    if (!el) return;
    el.classList.remove('hidden');
    el.style.display = 'block';
  };

const safeHideElement = typeof window !== 'undefined' && typeof window.hideElement === 'function'
  ? window.hideElement
  : (el) => {
    if (!el) return;
    el.classList.add('hidden');
    el.style.display = 'none';
  };

// Function to select an instrument for single registration
function selectInstrumentForSingleRegistration(id, fileNo, grantor, grantee, instrumentType) {
  // Show loading state
  Swal.fire({
    title: 'Loading',
    text: 'Fetching instrument details...',
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });

  // Find the full application data from the stored data
  let application = null;
  if (window.singleRegistrationData) {
    application = window.singleRegistrationData.find(item => String(item.id) === String(id));
  }

  if (!application) {
    // Fallback: create application object from passed parameters
    application = {
      id: id,
      fileNo: fileNo,
      grantor: grantor,
      grantee: grantee,
      instrumentType: instrumentType,
      duration: '',
      lga: '',
      district: '',
      plotNumber: '',
      plotSize: '',
      plotDescription: '',
      status: 'pending'
    };
  }

  // Show the modal details section
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  safeHideElement(searchSection);
  safeShowElement(detailsSection);

  // Close loading dialog
  Swal.close();

  // Set application data
  const fileNoValue = application.fileno || application.fileNo || application.MLSFileNo || application.KAGISFileNo || 'N/A';
  const propertyDescriptionValueRaw = application.propertyDescription || application.plotDescription || application.PropertyDescription || 'No description available';
  const propertyDescriptionValue = typeof window !== 'undefined' && typeof window.formatPropertyDescription === 'function'
    ? window.formatPropertyDescription(propertyDescriptionValueRaw)
    : propertyDescriptionValueRaw;
  const instrumentTypeValue = application.instrument_type || application.instrumentType || '';
  const durationValue = application.duration || application.leasePeriod || '';
  const grantorValue = application.grantor || application.Grantor || '';
  const granteeValue = application.grantee || application.Grantee || '';
  const lgaValue = application.lga || application.LGA || '';
  const districtValue = application.district || application.District || '';
  const plotNumberValue = application.plotNumber || application.plot_number || '';
  const plotSizeValue = application.size || application.plotSize || application.plot_size || '';
  const plotDescriptionValue = application.propertyDescription || application.plotDescription || application.PropertyDescription || '';

  document.getElementById('selectedFileNo').textContent = fileNoValue;
  document.getElementById('selectedProperty').textContent = propertyDescriptionValue;

  // Populate form fields
  document.getElementById('formInstrumentId').value = application.id;
  document.getElementById('instrumentType').value = instrumentTypeValue;
  document.getElementById('duration').value = durationValue;
  document.getElementById('grantor').value = grantorValue;
  document.getElementById('grantee').value = granteeValue;
  document.getElementById('lga').value = lgaValue;
  document.getElementById('district').value = districtValue;
  document.getElementById('plotNumber').value = plotNumberValue;
  document.getElementById('plotSize').value = plotSizeValue;
  document.getElementById('plotDescription').value = plotDescriptionValue;

  // Set current date and time
  const today = new Date();
  document.getElementById('deedsDate').value = today.toISOString().split('T')[0];

  // Set current time (formatted as HH:MM AM/PM)
  const hours = today.getHours();
  const minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const formattedHours = hours % 12 || 12;
  const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('deedsTime').value = `${formattedHours}:${formattedMinutes} ${ampm}`;

  // Fetch the next serial number with fresh data
  fetchNextSerialNumber(instrumentTypeValue).then(() => {
    console.log('Serial number fetched for single registration modal');
  }).catch(error => {
    console.error('Failed to fetch serial number for single registration:', error);
  });
}

// Updated Open single register modal function
function openSingleRegisterModalUpdated() {
  const modal = document.getElementById('singleRegisterModal');
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  safeShowElement(modal);
  safeShowElement(searchSection);
  safeHideElement(detailsSection);

  // Show loading state
  const unitSearchResults = document.getElementById('unitSearchResults');
  unitSearchResults.innerHTML = `
    <tr>
      <td colspan="5" class="px-6 py-10 text-center text-gray-500">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading available instruments...
      </td>
    </tr>
  `;

  // Fetch all available instruments for single registration (including other instruments)
  const baseUrl = window.location.origin;
  fetch(`${baseUrl}/instrument_registration/get-batch-data?filter=batch`, {
    method: 'GET',
    credentials: 'same-origin'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Single registration data received:', data);

      // Clear loading state
      unitSearchResults.innerHTML = '';

      if (!Array.isArray(data) || data.length === 0) {
        unitSearchResults.innerHTML = `
        <tr>
          <td colspan="5" class="px-6 py-10 text-center text-gray-500">
          No pending applications found.
          </td>
        </tr>
      `;
        return;
      }

      // Filter only pending applications
      const pendingApplications = data.filter(item => item.status === 'pending');

      if (pendingApplications.length === 0) {
        unitSearchResults.innerHTML = `
        <tr>
          <td colspan="5" class="px-6 py-10 text-center text-gray-500">
            No pending applications found.
          </td>
        </tr>
      `;
        return;
      }

      // Populate the table with pending applications
      pendingApplications.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${item.fileno || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantor || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantee || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
          <span class="badge badge-pending">Pending</span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
          <button class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm" onclick="selectInstrumentForSingleRegistration('${item.id}', '${item.fileno}', '${item.grantor}', '${item.grantee}', '${item.instrument_type}')">Select</button>
        </td>
      `;
        unitSearchResults.appendChild(row);
      });

      // Store the data for later use
      window.singleRegistrationData = data;
    })
    .catch(error => {
      console.error('Error fetching single registration data:', error);
      unitSearchResults.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-10 text-center text-red-500">
          Error loading instruments: ${error.message}
        </td>
      </tr>
    `;
    });
}

// Override the original function
if (typeof window !== 'undefined') {
  window.openSingleRegisterModal = openSingleRegisterModalUpdated;
  window.selectInstrumentForSingleRegistration = selectInstrumentForSingleRegistration;
}