<div class="no-documents">
    <i data-lucide="file-x" class="no-documents-icon"></i>
    <h3>No Documents Available</h3>
    <p>You need to upload documents before you can classify them. Please go back to the scanning step to upload your documents.</p>
    <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn-primary">
        <i data-lucide="upload" style="width: 1.25rem; height: 1.25rem;"></i>
        Go to Document Scanning
    </a>
</div>
