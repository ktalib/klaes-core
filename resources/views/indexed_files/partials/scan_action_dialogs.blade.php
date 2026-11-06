{{-- The Move to NR and Reassign FileNo dialogs for the Indexed Files action menu.
     They were built for Doc-WARE and Page Typing, whose pages define .btn and the rest, so the
     styles they need are repeated here, scoped to the two dialogs. --}}
<style>
  #registry-transfer-modal { z-index: 9999; background-color: rgba(0, 0, 0, 0.5); }
  #registry-transfer-modal > div { position: relative; max-height: 90vh; }
  #registry-transfer-modal .btn,
  #reassign-modal .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    line-height: 1.25rem;
    padding: 0.5rem 1rem;
    transition: all 0.2s;
    cursor: pointer;
  }
  #registry-transfer-modal .btn-primary,
  #reassign-modal .btn-primary { background-color: #3b82f6; color: #fff; }
  #registry-transfer-modal .btn-primary:hover,
  #reassign-modal .btn-primary:hover { background-color: #2563eb; }
  #registry-transfer-modal .btn-outline,
  #reassign-modal .btn-outline { background-color: transparent; border: 1px solid #e5e7eb; }
  #registry-transfer-modal .btn-outline:hover,
  #reassign-modal .btn-outline:hover,
  #registry-transfer-modal .btn-ghost:hover,
  #reassign-modal .btn-ghost:hover { background-color: #f3f4f6; }
  #registry-transfer-modal .btn-ghost,
  #reassign-modal .btn-ghost { background-color: transparent; }
  #registry-transfer-modal .btn-sm,
  #reassign-modal .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
  #registry-transfer-modal .btn-icon,
  #reassign-modal .btn-icon { padding: 0.25rem; }
  #registry-transfer-modal .btn:disabled,
  #reassign-modal .btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

@include('components.edms.registry-transfer-modal')
@include('scan_uploads.partials.reassign_modal')

{{-- After select2, which this page loads above: its file picker is a select2 widget. --}}
<script src="{{ asset('js/scan-reassignment.js') }}"></script>
