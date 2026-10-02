<script>
// Direct form submission handler with SweetAlert
function submitPropertyForm() {
    const form = document.getElementById('property-record-form');
    const formData = new FormData(form);

    // Show loading
    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we save your property record',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Submit form via fetch (robust handling with CSRF, timeout, redirects, and non-JSON)
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value || '';
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 20000);
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        signal: controller.signal
    })
    .then(async (response) => {
        clearTimeout(timeoutId);
        if (response.redirected) {
            Swal.close();
            window.location.href = response.url;
            return null;
        }
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok) {
            if (contentType.includes('application/json')) {
                const errData = await response.json().catch(() => ({}));
                const messages = errData.errors ? Object.values(errData.errors).flat() : [errData.message || 'Request failed'];
                throw new Error(messages.join('\n'));
            } else {
                const text = await response.text().catch(() => '');
                throw new Error(text || ('HTTP ' + response.status));
            }
        }
        if (contentType.includes('application/json')) {
            return response.json();
        } else {
            // Non-JSON success, reload to reflect changes
            Swal.close();
            window.location.reload();
            return null;
        }
    })
    .then((data) => {
        if (!data) return;
        if (data.status === 'success' || data.success === true) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Property record created successfully',
                confirmButtonText: 'OK'
            }).then(() => {
                // Reset form and close dialog
                form.reset();
                const dialog = document.getElementById('property-form-dialog');
                if (dialog) {
                    dialog.classList.add('hidden');
                }
                // Reload page to show new record
                window.location.reload();
            });
        } else {
            // Handle validation errors
            let errorMessage = data.message || 'An error occurred';
            if (data.errors) {
                const errorList = Object.values(data.errors).flat();
                errorMessage = errorList.join('\n');
            }
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    })
    .catch((error) => {
        clearTimeout(timeoutId);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An unexpected error occurred. Please try again.',
            confirmButtonText: 'OK'
        });
    });
}

// Override the Alpine.js form submission when the page loads
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('property-record-form');
    const submitBtn = document.getElementById('property-submit-btn');
    
    if (form && submitBtn) {
        // Change button type to prevent default form submission
        submitBtn.type = 'button';
        
        // Remove any existing event listeners and add our custom handler
        submitBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitPropertyForm();
            return false;
        };
        
        // Also prevent form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitPropertyForm();
            return false;
        }, true);
    }
});
</script>