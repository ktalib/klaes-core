<script>
$(document).ready(function() {
    // Fix 1: Status Tab Filtering (No Page Reload)
    $('.tab-button').off('click').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        
        // Update active tab button
        $('.tab-button').removeClass('active bg-white shadow');
        $(this).addClass('active bg-white shadow');
        
        // Handle different tab content
        if (tabId === 'indexed') {
            // Show indexed files tab content
            $('#indexed-files-content').show();
            $('#tracking-files-content').hide();
            loadIndexedFiles();
            // Enable header button functionality for indexed files
            updateHeaderButtonForIndexedTab();
        } else {
            // Show tracking files tab content
            $('#indexed-files-content').hide();
            $('#tracking-files-content').show();
            
            // Reset header button for other tabs
            $('#header-track-btn').prop('disabled', false);
            $('#header-track-text').text('Track New File');
            
            // Show/hide rows based on tab for tracking files without page reload
            if (tabId === 'all') {
                $('.file-row').show();
            } else {
                $('.file-row').hide();
                // Map tab IDs to status values for filtering
                const statusMap = {
                    'in-process': ['in_process', 'active'],
                    'pending': ['pending', 'checked_out'],
                    'on-hold': ['on_hold', 'overdue'],
                    'completed': ['completed', 'returned', 'archived']
                };
                
                if (statusMap[tabId]) {
                    statusMap[tabId].forEach(status => {
                        $('.file-row[data-status="' + status + '"]').show();
                    });
                } else {
                    $('.file-row[data-status="' + tabId + '"]').show();
                }
            }
        }
    });

    // Fix 2: Generate Real QR Codes
    if (typeof QRCode !== 'undefined') {
        generateQRCodesForSelectedFile();
    }
    
    // Function to generate QR codes for the selected file
    function generateQRCodesForSelectedFile() {
        @if($selectedFile)
            const qrElement = document.getElementById('qr-code-{{ $selectedFile->id }}');
            if (qrElement) {
                const qrData = JSON.stringify({
                    id: "TRK-{{ str_pad($selectedFile->id, 6, '0', STR_PAD_LEFT) }}",
                    fileNumber: "{{ $selectedFile->fileIndexing->file_number ?? 'N/A' }}",
                    fileTitle: "{{ addslashes($selectedFile->fileIndexing->file_title ?? 'No Title') }}",
                    currentLocation: "{{ addslashes($selectedFile->current_location ?? 'Not Set') }}",
                    currentHandler: "{{ addslashes($selectedFile->current_handler ?? 'Not Assigned') }}",
                    status: "{{ $selectedFile->status }}",
                    dateReceived: "{{ $selectedFile->date_received ? $selectedFile->date_received->format('Y-m-d') : 'Not Set' }}",
                    dueDate: "{{ $selectedFile->due_date ? $selectedFile->due_date->format('Y-m-d') : 'Not Set' }}",
                    rfidTag: "{{ $selectedFile->rfid_tag ?? 'Not Assigned' }}",
                    qrCode: "{{ $selectedFile->qr_code ?? 'Not Assigned' }}",
                    trackingUrl: "{{ route('filetracker.index', ['selected' => $selectedFile->id]) }}"
                });
                
                // Clear previous content
                qrElement.innerHTML = '';
                
                // Create new QR code
                const qr = new QRCode(qrElement, {
                    text: qrData,
                    width: 96,
                    height: 96,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        @endif
    }
    
    // Function to generate QR code for any tracking record
    function generateQRCodeForTracking(trackingId, fileData) {
        const qrElement = document.getElementById('qr-code-' + trackingId);
        if (qrElement && typeof QRCode !== 'undefined') {
            const qrData = JSON.stringify({
                id: "TRK-" + String(trackingId).padStart(6, '0'),
                fileNumber: fileData.file_number || 'N/A',
                fileTitle: fileData.file_title || 'No Title',
                currentLocation: fileData.current_location || 'Not Set',
                currentHandler: fileData.current_handler || 'Not Assigned',
                status: fileData.status || 'Unknown',
                dateReceived: fileData.date_received || 'Not Set',
                dueDate: fileData.due_date || 'Not Set',
                rfidTag: fileData.rfid_tag || 'Not Assigned',
                qrCode: fileData.qr_code || 'Not Assigned',
                trackingUrl: window.location.origin + '/filetracker?selected=' + trackingId
            });
            
            // Clear previous content
            qrElement.innerHTML = '';
            
            // Create new QR code
            const qr = new QRCode(qrElement, {
                text: qrData,
                width: 96,
                height: 96,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    }

    // Make functions globally available
    window.generateQRCodeForTracking = generateQRCodeForTracking;
    window.generateQRCodesForSelectedFile = generateQRCodesForSelectedFile;
});
</script>