{{-- Debug JS for testing --}}
    @include('fileindexing.js.debug')
    
    {{-- New Modular File Indexing JS (ES6 Modules) --}}
    <script>
      window.apiBaseUrl = '{{ url('fileindexing/api') }}';
    </script>

    <script type="module">
      import { initializeFileIndexingInterface } from '{{ asset("js/fileindexing/ui-controller.js") }}';
      
      const bootInterface = () => initializeFileIndexingInterface();

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootInterface, { once: true });
      } else {
        bootInterface();
      }
    </script>

<!-- Select2 CSS and JS for searchable dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

 
<style>
/* Custom Select2 styling for export modals */
.select2-container--default .select2-selection--single {
  height: 38px;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
  color: #374151;
  line-height: 28px;
  padding-left: 0;
  padding-right: 20px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 36px;
  position: absolute;
  top: 1px;
  right: 1px;
  width: 20px;
}

.select2-dropdown {
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #3b82f6;
  color: white;
}

.select2-container--default .select2-results__option[aria-selected=true] {
  background-color: #eff6ff;
  color: #1e40af;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  padding: 0.5rem;
}

.select2-container--default.select2-container--focus .select2-selection--single {
  border-color: #3b82f6;
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}
</style>

<!-- jsPDF + autotable CDN for client-side PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
{{-- <script>
document.addEventListener('DOMContentLoaded', function() {
</script> --}}
