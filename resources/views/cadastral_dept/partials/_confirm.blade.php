{{--
    Confirmation before any action: put data-confirm="What happens" on the form, or on one
    submit button of a form with several. Optional: data-confirm-title, data-confirm-danger.
    Every submit also locks its buttons (shows Working...) so a double click cannot send twice.
    The result comes back as the flash message, shown by the layout pop-up and the _nav banner.
--}}
@once
@push('scripts')
<script src="{{ asset('js/master-delete.js') }}"></script>
<script>
  (function () {
    function lock(form, btn) {
      form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) { b.disabled = true; });
      if (btn) { btn.innerHTML = 'Working…'; }
    }

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (e.defaultPrevented || form.method.toLowerCase() !== 'post') return;
      var btn = e.submitter || null;
      var src = btn && btn.hasAttribute('data-confirm') ? btn : (form.hasAttribute('data-confirm') ? form : null);

      // No confirmation asked: lock after the browser has read the clicked button's value.
      if (!src) { setTimeout(function () { lock(form, btn); }, 0); return; }

      e.preventDefault();
      var title = src.getAttribute('data-confirm-title') || 'Are you sure?';
      var text = src.getAttribute('data-confirm');
      var danger = src.hasAttribute('data-confirm-danger');
      var go = function () {
        if (btn && btn.name) {   // form.submit() leaves out the clicked button, so carry it
          var h = document.createElement('input');
          h.type = 'hidden'; h.name = btn.name; h.value = btn.value;
          form.appendChild(h);
        }
        lock(form, btn);
        form.submit();
      };

      if (typeof Swal === 'undefined') {
        if (window.confirm(title + '\n\n' + text)) go();
        return;
      }
      Swal.fire({
        title: title, text: text, icon: danger ? 'warning' : 'question',
        showCancelButton: true, reverseButtons: true, focusCancel: danger,
        confirmButtonText: 'Yes, continue', cancelButtonText: 'Cancel',
        confirmButtonColor: danger ? '#dc2626' : '#15803d',
      }).then(function (r) { if (r.isConfirmed) go(); });
    });

    // Master Delete of a file (Supper Admin): the shared two-step MasterDelete flow.
    window.cdMasterDelete = function (url, ref, fileNo, after) {
      if (!window.MasterDelete) { Swal.fire({ icon: 'error', title: 'Master Delete is not loaded on this page.' }); return; }
      MasterDelete.confirm({
        url: url,
        reference: ref,
        title: 'Master Delete File',
        lead: 'This permanently deletes <b>' + ref + '</b> (' + fileNo + ') from the Cadastral Department, with everything recorded for it. It cannot be undone.',
        targets: [
          'The file record and its movement history',
          'Its tasks, FMC and FMC log',
          'Its charting cards and beacons',
          'Its field jacket and reassignments',
          'Its correspondence custody log and Yes/No form'
        ],
        keeps: 'Kept: the file number and its indexing, the correspondence file, the plan description and any P&D bill. Its survey job is kept, unattached, for I to S to attach again.',
        onDone: function () { after ? (window.location.href = after) : window.location.reload(); }
      });
    };

    // Back button restores the page from cache with its buttons still locked.
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) window.location.reload();
    });
  })();
</script>
@endpush
@endonce
