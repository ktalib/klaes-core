{{-- Styles and scripts shared by the portal Surveyor's Report pages. --}}
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .ks-rep-section { margin:0 0 16px; padding:16px 18px 18px; border:1px solid var(--ks-line); border-radius:12px; background:#fff; min-width:0; }
    .ks-rep-section legend { display:flex; align-items:center; gap:8px; padding:0 8px; color:var(--ks-blue); font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .ks-rep-section[disabled] .ks-input { background:#F7F8FA; color:var(--ks-ink); }
    .ks-rep-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; }
    .ks-rep-full { grid-column:1 / -1; }
    .ks-rep-yn { display:flex; flex-wrap:wrap; gap:8px 18px; padding-top:6px; }
    .ks-rep-yn label, .ks-rep-checks label { display:inline-flex; align-items:center; gap:7px; font-size:14px; cursor:pointer; }
    .ks-rep-yn input, .ks-rep-checks input { width:16px; height:16px; accent-color:var(--ks-blue); }
    .ks-rep-checks { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:8px 16px; padding-top:4px; }
    .ks-searchable + .select2-container { width:100% !important; }
    .ks-searchable + .select2-container .select2-selection--single { height:44px; border:1px solid #B8BEC5; border-radius:8px; }
    .ks-searchable + .select2-container .select2-selection__rendered { line-height:42px; padding-left:12px; color:var(--ks-ink); font-size:14px; }
    .ks-searchable + .select2-container .select2-selection__arrow { height:42px; }
    .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    @media (max-width:700px) { .ks-rep-grid { grid-template-columns:1fr; } }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    // District and LGA are long lists: searchable (as on the Beacon Request form).
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.ks-searchable').each(function () {
            if (this.closest('fieldset[disabled]')) return;
            jQuery(this).select2({ width: '100%', placeholder: this.dataset.placeholder || '' });
        });
    }
    // One "Other" reveals a required Specify box; hidden and off otherwise.
    var d = document.getElementById('repDistrict'), box = document.getElementById('repDistrictOtherBox'), other = document.getElementById('repDistrictOther');
    function sync() {
        if (!d || !box || !other) return;
        var on = d.value === 'Other';
        box.style.display = on ? '' : 'none';
        other.disabled = !on || !!d.closest('fieldset[disabled]');
        other.required = on;
    }
    if (d) { d.addEventListener('change', sync); if (window.jQuery) jQuery(d).on('change', sync); sync(); }
})();
</script>
@endpush
