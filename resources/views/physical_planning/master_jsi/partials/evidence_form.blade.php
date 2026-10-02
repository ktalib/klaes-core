<div data-wstep="4" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Evidence</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            Site photographs, coordinates, the survey plan, a sketch or any other supporting document.
        </p>
    </div>
    <div class="p-6 space-y-5">
        @if ($report->evidence->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($report->evidence as $evidence)
            <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                <div class="w-9 h-9 rounded-lg bg-white border border-slate-100 flex items-center justify-center shrink-0">
                    <i data-lucide="{{ $evidence->isImage() ? 'image' : 'file-text' }}"
                        class="w-4 h-4 text-slate-500"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-700 truncate">
                        {{ $evidence->kindLabel() }}
                        @if ($evidence->file_name) — {{ $evidence->file_name }} @endif
                    </p>
                    @if ($evidence->description)
                        <p class="text-[10px] text-slate-400 truncate">{{ $evidence->description }}</p>
                    @elseif ($evidence->value)
                        <p class="text-[10px] text-slate-400 truncate">{{ $evidence->value }}</p>
                    @endif
                </div>
                @if ($evidence->publicUrl())
                <a href="{{ $evidence->publicUrl() }}" target="_blank"
                    class="text-[10px] font-bold text-slate-500 hover:text-slate-700">View</a>
                @endif
                @if (!$report->isApproved())
                <button type="button" onclick="removeEvidence({{ $evidence->id }})"
                    class="text-[10px] font-bold text-red-500 hover:text-red-700">Remove</button>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if ($report->isApproved())
        <p class="text-xs text-slate-400">
            This inspection is approved — attachments are closed. Reject it first if an attachment is wrong.
        </p>
        @else
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">type</label>
                <select id="evidenceKind" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    @foreach (\App\Models\MasterJsiEvidence::KINDS as $kind => $label)
                        <option value="{{ $kind }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="evidenceFileWrap">
                <label class="block text-xs font-bold text-slate-600 mb-1.5">File</label>
                <input type="file" id="evidenceFile"
                    class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2">
            </div>
            <div id="evidenceValueWrap" class="hidden">
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Coordinates</label>
                <input type="text" id="evidenceValue" placeholder="e.g. 9.0765° N, 7.3986° E"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div id="evidenceDescWrap">
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Description (optional)</label>
                <input type="text" id="evidenceDescription" maxlength="500"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <div class="flex items-center justify-end gap-3">
            <span id="evidenceNote" class="text-[10px] text-slate-400"></span>
            <button type="button" onclick="attachEvidence()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition">
                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach
            </button>
        </div>
        @endif
    </div>
</div>