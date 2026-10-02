
  <script>
    // Auto-print if this is opened as a print page
    if (window.location.search.includes('url=print')) {
      setTimeout(function() {
        window.print();
      }, 500);
    }
    
    // Setup an observer to watch for table updates
    document.addEventListener('DOMContentLoaded', function() {
      const tableObserver = new MutationObserver((mutations) => {
        // Silent observer
      });
      
      const utilitiesTable = document.getElementById('utilities-table');
      if (utilitiesTable) {
        tableObserver.observe(utilitiesTable.querySelector('tbody'), {
          childList: true,
          subtree: true
        });
      }
    });
  </script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to the update button
    const updateButton = document.getElementById('update-utilities-btn');
    if (updateButton) {
      updateButton.addEventListener('click', function() {
        updateUtilities();
      });
    }
    
    // Function to update utilities via AJAX
    function updateUtilities() {
      const form = document.getElementById('utilities-form');
      const formData = new FormData(form);
      const applicationId = formData.get('application_id');
      
      // Convert form data to JSON structure for the API
      const utilities = [];
      const rows = document.querySelectorAll('.utility-row');
      
      rows.forEach((row, index) => {
        const idInput = row.querySelector(`input[name="utilities[${index}][id]"]`);
        const typeInput = row.querySelector(`input[name="utilities[${index}][utility_type]"]`);
        const dimensionInput = row.querySelector(`input[name="utilities[${index}][dimension]"]`);
        const countInput = row.querySelector(`input[name="utilities[${index}][count]"]`);
        
        if (typeInput && dimensionInput && countInput) {
          utilities.push({
            id: idInput ? idInput.value : null,
            utility_type: typeInput.value,
            dimension: parseFloat(dimensionInput.value) || 0,
            count: parseInt(countInput.value) || 1,
            order: index + 1,
            application_id: applicationId
          });
        }
      });
      
      // Send each utility as a separate request to save
      const savePromises = utilities.map(utility => {
        return axios.post('{{ route('planning-tables.save-utility') }}', utility)
          .then(response => {
            return response.data;
          })
          .catch(error => {
            throw error;
          });
      });
      
      // Process all save operations
      Promise.all(savePromises)
        .then(results => {
          // Show success message
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Utilities updated successfully!',
            timer: 1500,
            showConfirmButton: false
          });
          
          // Hide message after 3 seconds
          setTimeout(() => {
            messageElement.classList.add('hidden');
          }, 3000);
          
          // Refresh the table
          refreshUtilitiesTable(applicationId);
        })
        .catch(error => {
          alert('An error occurred while saving. Please check the console for details.');
        });
    }
    
    // Function to refresh the utilities table
    function refreshUtilitiesTable(applicationId) {
      axios.get(`{{ url('planning-tables/utilities') }}/${applicationId}`)
        .then(response => {
          // Get the table body
          const tableBody = document.querySelector('#utilities-table tbody');
          if (!tableBody) return;
          
          // Clear existing rows
          tableBody.innerHTML = '';
          
          // If no utilities, show empty message
          if (response.data.length === 0) {
            tableBody.innerHTML = `
              <tr>
                <td colspan="4" class="text-center py-4">No utilities added yet.</td>
              </tr>
            `;
            return;
          }
          
          // Add updated rows
          response.data.forEach((utility, index) => {
            const row = document.createElement('tr');
            row.className = 'utility-row';
            
            row.innerHTML = `
              <td>${index + 1}</td>
              <td>
                <input type="text" name="utilities[${index}][utility_type]" value="${utility.utility_type}">
                <input type="hidden" name="utilities[${index}][id]" value="${utility.id || ''}">
              </td>
              <td>
                <input type="number" name="utilities[${index}][dimension]" value="${parseFloat(utility.dimension).toFixed(3)}" step="0.001">
              </td>
              <td>
                <input type="number" name="utilities[${index}][count]" value="${utility.count}">
              </td>
            `;
            
            tableBody.appendChild(row);
          });
        })
        .catch(error => {
          // Silent error
        });
    }
  });
</script>