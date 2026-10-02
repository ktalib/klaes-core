<div id="userProfilePhotoCard" class="bg-white rounded-xl overflow-hidden" role="region" aria-labelledby="userPhotoTitle">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h2 id="userPhotoTitle" class="text-lg font-semibold text-gray-900">{{ __('Profile Picture') }}</h2>
            <p class="text-sm text-gray-500">{{ $user->name }}</p>
        </div>
        <button type="button" data-dismiss="modal" class="text-gray-500 text-2xl px-2" aria-label="{{ __('Close') }}">&times;</button>
    </div>
    <form id="userProfilePhotoForm" action="{{ route('users.profile-photo.update', $user->id) }}" method="POST" enctype="multipart/form-data" class="p-6">
        @csrf
        <div class="flex justify-center mb-5">
            <img id="userPhotoPreview" src="{{ $user->profile_url }}" alt="{{ __('Profile picture for :name', ['name' => $user->name]) }}"
                class="{{ $user->profile_url ? '' : 'hidden' }} rounded-xl border border-gray-200 object-cover" style="width:160px;height:180px">
            <div id="userPhotoPlaceholder" class="{{ $user->profile_url ? 'hidden' : '' }} rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 text-sm" style="width:160px;height:180px">
                {{ __('No profile picture') }}
            </div>
        </div>
        <label for="userPhotoFile" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Choose a new profile picture') }}</label>
        <input id="userPhotoFile" name="profile" type="file" accept="image/jpeg,image/png,image/gif" class="block w-full border border-gray-300 rounded-lg p-2 text-sm">
        <p class="text-xs text-gray-500 mt-2">{{ __('Use a clear photo of the user. JPG, PNG or GIF, up to 2 MB.') }}</p>
        @if($user->profile_url)
            <p class="text-xs text-gray-500 mt-3">{{ __('Removing this picture will require the user to upload a new photo before using the system.') }}</p>
        @endif
        <p id="userPhotoMessage" class="hidden text-sm mt-4" role="status" aria-live="polite"></p>
        <div class="flex flex-wrap justify-end gap-2 mt-6">
            <button type="button" data-dismiss="modal" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">{{ __('Cancel') }}</button>
            @if($user->profile_url)
                <button type="button" id="userPhotoRemove" class="px-4 py-2 rounded-lg border border-red-300 text-red-600 text-sm">{{ __('Remove Picture') }}</button>
            @endif
            <button type="submit" id="userPhotoUpdate" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm disabled:opacity-50" disabled>{{ __('Update Picture') }}</button>
        </div>
    </form>
</div>
<script>
(function () {
    var card = document.getElementById('userProfilePhotoCard');
    var form = card.querySelector('form');
    var input = card.querySelector('#userPhotoFile');
    var preview = card.querySelector('#userPhotoPreview');
    var placeholder = card.querySelector('#userPhotoPlaceholder');
    var message = card.querySelector('#userPhotoMessage');
    var update = card.querySelector('#userPhotoUpdate');
    var remove = card.querySelector('#userPhotoRemove');
    var original = preview.getAttribute('src');
    var previewUrl = null;
    var busy = false;

    function showMessage(text, ok) {
        message.textContent = text;
        message.className = 'text-sm mt-4 ' + (ok ? 'text-green-700' : 'text-red-600');
    }

    input.addEventListener('change', function () {
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        message.classList.add('hidden');
        var file = input.files[0];
        update.disabled = true;
        preview.src = original || '';
        preview.classList.toggle('hidden', !original);
        placeholder.classList.toggle('hidden', !!original);
        if (!file) { return; }
        if (['image/jpeg', 'image/png', 'image/gif'].indexOf(file.type) === -1 || file.size > 2 * 1024 * 1024) {
            input.value = '';
            showMessage('Choose a JPG, PNG or GIF image no larger than 2 MB.', false);
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        update.disabled = false;
    });

    function save(action) {
        if (busy || (action === 'update' && !input.files.length)) { return; }
        var data = new FormData(form);
        data.set('action', action);
        if (action === 'remove') { data.delete('profile'); }
        busy = true;
        input.disabled = true;
        card.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
        showMessage(action === 'remove' ? 'Removing picture...' : 'Updating picture...', true);
        fetch(form.action, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body.success) {
                    throw new Error(body.errors && body.errors.profile ? body.errors.profile[0] : (body.message || 'The picture could not be saved.'));
                }
                showMessage(body.message, true);
                if (previewUrl) { URL.revokeObjectURL(previewUrl); }
                window.setTimeout(function () { window.location.reload(); }, 800);
            });
        }).catch(function (error) {
            busy = false;
            input.disabled = false;
            card.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
            update.disabled = !input.files.length;
            showMessage(error.message || 'Check your connection and try again.', false);
        });
    }
    form.addEventListener('submit', function (event) { event.preventDefault(); save('update'); });
    if (remove) { remove.addEventListener('click', function () { save('remove'); }); }
})();
</script>
