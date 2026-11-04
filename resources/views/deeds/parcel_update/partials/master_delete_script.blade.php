{{-- Handler for partials/master_delete_item. Super Admin only; included once per listing. --}}
@if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
<script>
(function () {
    if (window.parcelMasterDelete) return;

    var URL_TEMPLATE = @js(route('parcel-update.master-delete', ['type' => '__TYPE__', 'id' => 0]));

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    window.parcelMasterDelete = function (type, id, ref, status) {
        var approved = String(status || '').toLowerCase() === 'approved';
        Swal.fire({
            icon: 'warning',
            title: 'Master Delete?',
            html: '<p style="font-size:13px;color:#475569;margin-bottom:8px;"><strong>' + esc(ref) + '</strong> will be removed from this list.</p>'
                + (approved
                    ? '<p style="font-size:12px;color:#b45309;text-align:left;">This application is <strong>approved</strong>. Once deleted it can no longer be commissioned.</p>'
                    : '')
                + '<p style="font-size:12px;color:#64748b;text-align:left;margin-top:6px;">Type <strong>DELETE</strong> to confirm.</p>',
            input: 'text',
            inputPlaceholder: 'DELETE',
            showCancelButton: true,
            confirmButtonText: 'Master Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function (typed) {
                if (String(typed || '').trim().toUpperCase() !== 'DELETE') {
                    Swal.showValidationMessage('Type DELETE to confirm.');
                    return false;
                }
                var url = URL_TEMPLATE.replace('__TYPE__', encodeURIComponent(type)).replace(/\/0\/master-delete$/, '/' + id + '/master-delete');
                return fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                }).then(function (r) {
                    return r.json().catch(function () { return null; }).then(function (body) {
                        if (!r.ok || !body || !body.success) {
                            throw new Error((body && body.message) || 'Master Delete failed (HTTP ' + r.status + ').');
                        }
                        return body;
                    });
                }).catch(function (e) {
                    Swal.showValidationMessage(e.message || 'Master Delete failed.');
                });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;
            Swal.fire({ icon: 'success', title: 'Deleted', text: result.value.message, timer: 1500, showConfirmButton: false })
                .then(function () { window.location.reload(); });
        });
    };
})();
</script>
@endif
