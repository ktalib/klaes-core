@php
    $isView = $isView ?? false;
    $record = $record ?? null;
    $isEdit = !is_null($record) && !$isView;
    $actionUrl = $isEdit ? route('for-information.update', $record->id) : route('for-information.store');
    $modalTitle = $isView ? 'View For Information Record' : ($isEdit ? 'Edit For Information Record' : 'Generate For Information Record');
@endphp
<div class="bg-white rounded-2xl">
    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">For Information</p>
            <h3 class="text-lg font-semibold text-slate-900">{{ $modalTitle }}</h3>
        </div>
        <button type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:text-slate-700"
            aria-label="Close modal"
            data-dismiss="modal">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
    </div>

    <form action="{{ $actionUrl }}"
        method="post"
        class="js-for-information-form space-y-6">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="modal-body p-0">
            <div class="space-y-6 p-6">
                <div>
                    <p class="text-sm font-medium text-slate-500">For Information Details</p>
                    <p class="text-base font-semibold text-slate-900">Fill in the metadata that appears on the LAND 16 template.</p>
                </div>

                <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                    {{-- File Number Selector (uses Global File Number Modal) --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">File Number</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="hidden" name="file_number" id="fi-file-number" value="{{ $record->file_number ?? '' }}">
                            <input type="text"
                                id="fi-file-number-display"
                                class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400"
                                value="{{ $record->file_number ?? '' }}"
                                placeholder="No file number selected"
                                readonly
                                disabled>
                            @if(!$isView)
                            <button type="button" id="fi-select-fileno"
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
                                Select
                            </button>
                            {{-- <button type="button" id="fi-clear-fileno"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-red-50 hover:text-red-600 hover:border-red-200">
                                Clear
                            </button> --}}
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Title</label>
                        <input type="text"
                            name="title"
                            id="fi-title"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50 read-only:bg-slate-50 read-only:cursor-not-allowed"
                            placeholder="Auto-filled from file number"
                            value="{{ $record->title ?? '' }}"
                            @if($isView) disabled @endif
                            @if(!empty($record->file_number ?? '')) readonly @endif>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">REF/No/LKN</label>
                        <input type="text"
                            name="ref_no"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50"
                            placeholder="e.g. LKN/S/2026/001"
                            value="{{ $record->ref_no ?? '' }}"
                            @if($isView) disabled @endif>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Commencement Day</label>
                        <input type="date"
                            name="commencement_day"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50"
                            
                            value="{{ $record->commencement_day ?? '' }}"
                            @if($isView) disabled @endif>
                    </div>


                    {{-- <div>
                        <label class="block text-sm font-semibold text-slate-700">Signer Name</label>
                        <input type="text"
                            name="signer_name"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50"
                            placeholder="e.g. Musa Yakubu"
                            value="{{ $record->signer_name ?? '' }}"
                            @if($isView) disabled @endif>
                    </div> --}}

                    {{-- <div>
                        <label class="block text-sm font-semibold text-slate-700">Signer Rank</label>
                        <input type="text"
                            name="signer_rank"
                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50"
                            placeholder="e.g. Permanent Secretary"
                            value="{{ $record->signer_rank ?? '' }}"
                            @if($isView) disabled @endif>
                    </div> --}}
                </div>
            </div>
        </div>

        @if(!$isView)
            <div class="modal-footer flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    data-dismiss="modal">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    {{ $isEdit ? 'Update' : 'Generate' }}
                </button>
            </div>
        @endif
    </form>
</div>
