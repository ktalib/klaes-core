<script>
    // Process server data for JavaScript use
    const serverCofoData = @json($approvedApplications ?? []);
</script>

<!-- Include SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Include the external JavaScript file -->
<script src="{{ asset('js/instrument_registration.js') }}"></script>