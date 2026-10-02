{{--
 | Digital File Request sub-permissions, plus the SCB Monitor flag.
 |
 | These are not module permissions and deliberately stay out of the grid: they select WHICH
 | requests a user may see and approve on /digital-request, and whether they receive File Search
 | Requests on the mobile app. The gate answers "may this user act in this module"; these answer
 | "in what capacity", which no module × action matrix expresses. Read by
 | User::hasDfrPermission() and User::isScbMonitor().
 |
 | Opens when "Log a File" is granted. The trigger is DELEGATED rather than bound to an element
 | id: the permission grid renders its rows through an Alpine x-for template, so the checkbox
 | does not exist when this script runs, and the old getElementById('role_log_a_file_create')
 | would simply find nothing.
 |
 | Expects:
 |   $mode      'create' | 'edit' — suffixes the ids so both modals can coexist
 |   $dfrPerms  array of granted keys (view_requests | approve_request)
 |   $frScb     bool, whether the user is an SCB Monitor
 --}}

@php
    $mode = $mode ?? 'create';
    $dfrPerms = $dfrPerms ?? [];
    $frScb = $frScb ?? false;
@endphp

<div id="dfr-modal-{{ $mode }}" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div id="dfr-modal-backdrop-{{ $mode }}" class="absolute inset-0 bg-black/50"></div>

    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-[#450a0a] via-[#6b1010] to-[#450a0a] px-5 py-4 flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20">
                <i class="fas fa-file-alt text-white text-sm"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-white">{{ __('Digital File Request Permissions') }}</p>
                <p class="text-xs text-red-200">{{ __('Select what this user can do on /digital-request') }}</p>
            </div>
            <button type="button" id="dfr-modal-close-{{ $mode }}" class="ml-auto text-red-200 hover:text-white transition">
                <i class="fas fa-times text-base"></i>
            </button>
        </div>

        <div class="p-5 space-y-3">
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-red-300 hover:bg-red-50 cursor-pointer transition">
                <input type="checkbox" name="dfr_permissions[]" value="view_requests"
                    {{ in_array('view_requests', $dfrPerms, true) ? 'checked' : '' }}
                    class="h-4 w-4 rounded border-gray-300 text-[#450a0a] focus:ring-red-400">
                <div>
                    <span class="text-sm font-semibold text-gray-800">{{ __('View Request Page') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Can access /digital-request and see all requests') }}</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-red-300 hover:bg-red-50 cursor-pointer transition">
                <input type="checkbox" name="dfr_permissions[]" value="approve_request"
                    {{ in_array('approve_request', $dfrPerms, true) ? 'checked' : '' }}
                    class="h-4 w-4 rounded border-gray-300 text-[#450a0a] focus:ring-red-400">
                <div>
                    <span class="text-sm font-semibold text-gray-800">{{ __('Approve Request') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Can approve or reject file requests') }}</span>
                </div>
            </label>

            <div class="pt-2 mt-1 border-t border-gray-100">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ __('File Request (Mobile)') }}</p>
                <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition">
                    <input type="checkbox" name="fr_permissions" value="SCB"
                        {{ $frScb ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-400">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">{{ __('SCB Monitor (File Searcher)') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Receives File Requests on the mobile app & by email') }}</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" id="dfr-modal-cancel-{{ $mode }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                {{ __('Cancel') }}
            </button>
            <button type="button" id="dfr-modal-done-{{ $mode }}"
                class="px-4 py-2 text-sm font-medium text-white bg-[#450a0a] hover:bg-[#5c0c0c] rounded-lg transition">
                {{ __('Done') }}
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var MODE = @json($mode);
    var modal = document.getElementById('dfr-modal-' + MODE);

    if (!modal) {
        return;
    }

    function close() { modal.classList.add('hidden'); }
    function open() { modal.classList.remove('hidden'); }

    ['dfr-modal-done-', 'dfr-modal-cancel-', 'dfr-modal-close-', 'dfr-modal-backdrop-'].forEach(function (prefix) {
        var el = document.getElementById(prefix + MODE);
        if (el) { el.addEventListener('click', close); }
    });

    /*
     | Delegated: the grid's rows are rendered by Alpine after this runs, and are re-rendered
     | whenever the department filter or search changes. Binding to the element once would work
     | only until the first filter change.
     |
     | The name carries the module, so "Log a File" is matched without needing an id on the
     | checkbox — which also means the grid partial stays unaware of this feature.
     */
    document.addEventListener('change', function (e) {
        var input = e.target;

        if (!input || input.type !== 'checkbox' || input.value !== 'view') {
            return;
        }

        var name = input.getAttribute('name') || '';

        if (name.toLowerCase() !== 'module_perm[log a file][]') {
            return;
        }

        // Granting it asks what capacity; revoking it just closes the question.
        input.checked ? open() : close();
    });
})();
</script>
