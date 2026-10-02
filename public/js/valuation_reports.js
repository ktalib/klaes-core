document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('valuation-form');
    const filenoInput = document.getElementById('file_number');
    const submitBtn = document.getElementById('submit-btn');

    // Form Submission
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Browser validation check
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!filenoInput.value) {
                Swal.fire('Required', 'Please select a File Number first.', 'warning');
                return;
            }

            const reportId = document.getElementById('report_id').value;
            const isEdit = reportId !== '';
            const url = isEdit ? `/valuation-reports/${reportId}` : '/valuation-reports';

            // For Laravel to recognize PUT/PATCH we need to send it in the body, 
            // but we'll use a standard FormData approach.
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            submitBtn.disabled = true;
            const originalBtnContent = submitBtn.innerHTML;
            submitBtn.innerHTML = `<i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i> Processing...`;
            if (window.lucide) window.lucide.createIcons();

            fetch(url, {
                method: 'POST', // We use POST for both, Laravel uses _method for spoofing
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: isEdit ? 'Updated!' : 'Generated!',
                            text: result.message,
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            window.location.href = '/valuation-reports';
                        });
                    } else {
                        Swal.fire('Error', result.message || 'Error processing request', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnContent;
                    if (window.lucide) window.lucide.createIcons();
                });
        });
    }

    if (window.lucide) window.lucide.createIcons();
});
