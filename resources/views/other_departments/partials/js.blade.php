<script>
        // Initialize Lucide icons
        lucide.createIcons();

        // For debugging - log when JS is loaded
        console.log('Deeds JS file loaded');

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM fully loaded');
            
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const tabId = this.getAttribute('data-tab');

                    // Deactivate all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Activate selected tab
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });

            // Close modal button
            document.getElementById('closeModal').addEventListener('click', function () {
                // In a real application, this would close the modal
                alert('Modal closed');
            });

            // Survey form submission
            document.getElementById('submit-survey').addEventListener('click', function (e) {
                e.preventDefault();

                // Show loading message
                Swal.fire({
                    title: 'Processing...',
                    text: 'Submitting survey information',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Get the form data
                const form = document.getElementById('survey-form');
                const formData = new FormData(form);

                // Send AJAX request
                fetch('{{ route('primary-applications.store') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        // Don't set Accept header to force browser's default content negotiation
                    },
                    credentials: 'same-origin'
                })
                    .then(response => {
                        // First check if response is ok
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error('Server error: ' + text);
                            });
                        }

                        // Try to parse as JSON, but handle text responses too
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            return response.json();
                        } else {
                            return response.text().then(text => {
                                return {
                                    success: true,
                                    message: 'Operation completed',
                                    text: text
                                };
                            });
                        }
                    })
                    .then(data => {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'Survey information submitted successfully',
                            confirmButtonColor: '#3085d6'
                        });
                    })
                    .catch(error => {
                        // Show error message
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Failed',
                            text: 'There was an error submitting the survey information. Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    });
            });

            // Edit Deeds Button Functionality
            const editDeedsBtn = document.getElementById('edit-deeds');
            console.log('Edit button element:', editDeedsBtn);
            
            if (editDeedsBtn) {
                console.log('Edit button found, adding click listener');
                editDeedsBtn.addEventListener('click', function() {
                    console.log('Edit button clicked');
                    // Enable all deed fields
                    const deedFields = document.querySelectorAll('.deed-field');
                    console.log('Found deed fields:', deedFields.length);
                    
                    deedFields.forEach(field => {
                        field.disabled = false;
                        field.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        field.classList.add('bg-white');
                    });
                    
                    // Show the submit button
                    const submitBtn = document.getElementById('submit-deeds');
                    if (submitBtn) {
                        submitBtn.classList.remove('hidden');
                    }
                    
                    // Hide the edit button
                    this.classList.add('hidden');
                });
            } else {
                console.error('Edit deeds button not found in the DOM!');
            }

            // Deeds form submission
            const submitDeedsBtn = document.getElementById('submit-deeds');
            if (submitDeedsBtn) {
                submitDeedsBtn.addEventListener('click', function (e) {
                    console.log('Submit button clicked');
                    e.preventDefault();

                    // Show loading message
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Submitting deeds information',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Get the form data
                    const form = document.getElementById('deeds-form');
                    const formData = new FormData(form);

                    // Send AJAX request
                    fetch('{{ route('primary-applications.storeDeeds') }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            // Don't set Accept header to force browser's default content negotiation
                        },
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            // First check if response is ok
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error('Server error: ' + text);
                                });
                            }

                            // Try to parse as JSON, but handle text responses too
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json();
                            } else {
                                return response.text().then(text => {
                                    return {
                                        success: true,
                                        message: 'Operation completed',
                                        text: text
                                    };
                                });
                            }
                        })
                        .then(data => {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message || 'Deeds information submitted successfully',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                // After successful submission, disable the fields again
                                const deedFields = document.querySelectorAll('.deed-field');
                                deedFields.forEach(field => {
                                    field.disabled = true;
                                    field.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                                });
                                
                                // Hide the submit button and show the edit button
                                document.getElementById('submit-deeds').classList.add('hidden');
                                document.getElementById('edit-deeds').classList.remove('hidden');
                            });
                        })
                        .catch(error => {
                            // Show error message
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Submission Failed',
                                text: 'There was an error submitting the deeds information. Please try again.',
                                confirmButtonColor: '#3085d6'
                            });
                        });
                });
            } else {
                console.error('Submit deeds button not found in the DOM!');
            }
        });
    </script>
