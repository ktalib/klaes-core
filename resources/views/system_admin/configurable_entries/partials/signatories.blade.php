{{-- Document signatories: the office holders whose name and signature are printed on
     issued documents. Stored as signing_officers rows with no user_id, which is what
     keeps them out of the staff signatory lists -- see LandOfficer::scopeDocumentSignatories. --}}
<div class="space-y-4">
    @php
        // 'A, B and C' rather than implode's 'A and B and C', now that there are three.
        $standardNames = $defaultSignatories;
        $lastStandard  = array_pop($standardNames);
        $standardList  = $standardNames
            ? implode(', ', $standardNames) . ' and ' . $lastStandard
            : $lastStandard;
    @endphp

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-gray-600">
            The name and signature printed on issued documents.
            {{ $standardList }} are always here; add others as needed.
        </p>
        <button type="button" class="ce-pill" onclick="document.getElementById('sig-add-form').classList.toggle('hidden')">
            + Add Signatory
        </button>
    </div>

    {{-- Add a further signatory. Hidden until asked for, so the standard ones stay
         the obvious thing on the screen. --}}
    <form id="sig-add-form" class="hidden ce-table-wrapper p-4" method="POST"
          action="{{ route('configurable-entries.signatories.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                <input type="text" name="rank" required maxlength="255"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                       placeholder="e.g., Surveyor-General">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Name</label>
                <input type="text" name="name" maxlength="255"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                       placeholder="Full name as it should print">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Signature</label>
                <input type="file" name="signature_file" accept="image/png,image/jpeg"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button type="submit" class="ce-pill" style="background:#fdf2f8;color:#9d174d">Add Signatory</button>
        </div>
    </form>

    @foreach($signatories as $sig)
        @php $isDefault = in_array($sig->rank, $defaultSignatories, true); @endphp
        <form class="ce-table-wrapper p-4" method="POST"
              action="{{ route('configurable-entries.signatories.update', $sig->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="ce-pill" style="background:#fdf2f8;color:#9d174d">{{ $sig->rank }}</span>
                    @if($isDefault)
                        <span class="ce-pill">Standard</span>
                    @endif
                </div>
                @unless($isDefault)
                    <button type="submit" class="ce-pill red"
                            formaction="{{ route('configurable-entries.signatories.delete', $sig->id) }}"
                            onclick="return confirm('Remove {{ $sig->rank }}? Its name and signature will be deleted.')">
                        Remove
                    </button>
                @endunless
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="rank" required maxlength="255" value="{{ $sig->rank }}"
                           @readonly($isDefault)
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm {{ $isDefault ? 'bg-gray-50 text-gray-500' : '' }}">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" maxlength="255" value="{{ $sig->name }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                           placeholder="Full name as it should print">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Signature</label>
                    @if($sig->signature_file)
                        <div class="mb-2 rounded-lg border border-gray-200 bg-white p-2 inline-block">
                            <img src="{{ asset('storage/' . $sig->signature_file) }}" alt="{{ $sig->rank }} signature"
                                 style="max-height:56px" onerror="this.parentElement.innerHTML='<span class=\'ce-muted text-xs\'>Signature file missing on disk</span>'">
                        </div>
                    @else
                        <p class="ce-muted text-xs mb-2">No signature uploaded yet.</p>
                    @endif
                    <input type="file" name="signature_file" accept="image/png,image/jpeg"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <p class="ce-muted text-xs mt-1">PNG or JPG, up to 2&nbsp;MB. Uploading replaces the current one.</p>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="submit" class="ce-pill" style="background:#fdf2f8;color:#9d174d">Save</button>
            </div>
        </form>
    @endforeach
</div>
