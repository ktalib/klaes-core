<div id="final-tab" class="tab-content">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b">
            <h3 class="text-sm font-medium">Final Conveyance Agreement</h3>
            <p class="text-xs text-gray-500"></p>
        </div>
        <input type="hidden" id="application_id" value="{{ $application->id }}">
        <input type="hidden" name="fileno" value="{{ $application->fileno }}">
        <div class="container mx-auto p-4 bg-gray-100 rounded shadow" id="printable-content">
            <!-- Include existing FinalConveyanceAgreement content here -->
            <!-- ...existing code... -->
        </div>

        <hr class="my-4">

        <div class="flex justify-between items-center">
            <div class="flex gap-2">
                <a href="{{ url()->previous() }}"
                    class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                    <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                    Back
                </a>

                <button id="submit-final-conveyance" type="button"
                    class="flex items-center px-3 py-1 text-xs bg-green-700 text-white rounded-md hover:bg-green-800">
                    <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                    Submit
                </button>

                <button id="print-conveyance"
                    class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                    <i data-lucide="printer" class="w-3.5 h-3.5 mr-1.5"></i>
                    Print Agreement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add print styles at the end of your document -->
<style id="print-styles">
    /* ...existing print styles... */
</style>

<script>
    // Add this to your existing DOMContentLoaded or in a separate script tag
    document.addEventListener('DOMContentLoaded', function() {
        const printButton = document.getElementById('print-conveyance');
        if (printButton) {
            printButton.addEventListener('click', function() {
                // ...existing print functionality...
            });
        }
        
        // Update the submit button to use the sub-actions route
        const submitButton = document.getElementById('submit-final-conveyance');
        if (submitButton) {
            console.log("Submit button found"); // Debug log
            
            submitButton.addEventListener('click', function(e) {
                console.log("Submit button clicked"); // Debug log
                
                // Prevent any form submission
                e.preventDefault();
                e.stopPropagation();
                
                // Show loading state
                Swal.fire({
                    title: 'Submitting...',
                    html: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Get the application ID
                const applicationId = document.getElementById('application_id').value;
                console.log("Application ID for submission:", applicationId); // Debug log
                
                // Send AJAX request to the sub-actions route instead of conveyance.finalize
                fetch('{{ route("sub-actions.conveyance.finalize") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        application_id: applicationId,
                        status: 'completed'
                    })
                })
                .then(response => {
                    console.log("Response status:", response.status); // Debug log
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Data received:", data); // Debug log
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'Final Conveyance Agreement submitted successfully',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Redirect to the previous page
                            window.location.href = "{{ url()->previous() }}";
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to submit Final Conveyance Agreement'
                        });
                    }
                })
                .catch(error => {
                    console.error('Submission error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred: ' + error.message
                    });
                });
                
                return false; // Prevent default behavior
            });
        } else {
            console.error("Submit button not found"); // Debug error
        }
    });
</script>