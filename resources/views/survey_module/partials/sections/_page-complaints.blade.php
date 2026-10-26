@php
    use App\Http\Controllers\Survey\ComplaintController;
    use App\Models\Survey\SurveyComplaint;

    $editing   = $complaint->exists;
    $panelOpen = $editing || $errors->any();
    $svItem    = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $badge = fn ($s) => match ($s) {
        SurveyComplaint::RESOLVED      => 'completed',
        SurveyComplaint::ESCALATED     => 'rejected',
        SurveyComplaint::INVESTIGATING => 'review',
        default                        => 'pending',
    };

    $catVal   = old('category', $complaint->category);
    $catKnown = $catVal === null || $catVal === '' || $catVal === ComplaintController::CATEGORY_OTHER || in_array($catVal, $categories, true);
    $catPick  = $catKnown ? $catVal : ComplaintController::CATEGORY_OTHER;
    $catOther = old('category_other', $catKnown ? '' : $catVal);

    // Beneficiary -> complainant, case and project, for the backfill on selection.
    $benMeta = $beneficiaries->mapWithKeys(fn ($b) => [$b->id => [
        'name'    => $b->full_name,
        'phone'   => $b->phone,
        'case'    => $b->survey_comp_case_id,
        'project' => $b->case?->survey_project_id,
    ]]);
    $filters = ['q', 'status', 'category', 'project'];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('complaintPanel').classList.toggle('open')">
        <i class="fas fa-comment-medical"></i> {{ $editing ? 'Edit Complaint' : 'Log Complaint' }}
    </button>
</div>

<div class="kpi-grid">
    @foreach ($stats as $s => $n)
        <a class="kpi-card" href="{{ route('survey-module.compensation.complaints', ['status' => $s]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $s }}</div>
            <div class="kpi-value">{{ number_format($n) }}</div>
        </a>
    @endforeach
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="complaintPanel"
     data-sv-modal="{{ $editing ? 'Edit Complaint ' . $complaint->complaint_ref : 'Log Complaint' }}"
     @if ($editing) data-sv-modal-close="{{ route('survey-module.compensation.complaints') }}" @endif>
    <form method="POST" action="{{ $editing ? route('survey-module.compensation.complaints.update', $complaint) : route('survey-module.compensation.complaints.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-user"></i><span>Complainant</span></div>
            @if ($editing)
                <div class="form-group full">
                    <label>Complaint No</label>
                    <input type="text" value="{{ $complaint->complaint_ref }}" readonly style="background:var(--gray-100);color:var(--gray-700);" />
                </div>
            @endif

            <div class="form-group full">
                <label>Beneficiary</label>
                <select name="survey_beneficiary_id" id="cmpBeneficiary">
                    <option value="">— Not a registered beneficiary —</option>
                    @foreach ($beneficiaries as $b)
                        <option value="{{ $b->id }}" @selected((string) old('survey_beneficiary_id', $complaint->survey_beneficiary_id) === (string) $b->id)>
                            {{ $b->full_name }}{{ $b->phone ? ' · ' . $b->phone : '' }}{{ $b->case ? ' · ' . $b->case->case_ref : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="helper-text">Picking a beneficiary fills the complainant, case and project from their record.</p>
            </div>

            <div class="form-group">
                <label>Complainant Name <span class="required">*</span></label>
                <input type="text" name="complainant_name" id="cmpName" maxlength="255"
                       value="{{ old('complainant_name', $complaint->complainant_name) }}" required />
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="complainant_phone" id="cmpPhone" maxlength="50"
                       value="{{ old('complainant_phone', $complaint->complainant_phone) }}" />
            </div>

            <div class="sv-group"><i class="fas fa-link"></i><span>Linked record</span></div>
            <div class="form-group">
                <label>Project</label>
                <select name="survey_project_id" id="cmpProject">
                    <option value="">— None —</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected((string) old('survey_project_id', $complaint->survey_project_id) === (string) $p->id)>{{ $p->project_code }} · {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Case</label>
                <select name="survey_comp_case_id" id="cmpCase">
                    <option value="">— None —</option>
                    @foreach ($cases as $c)
                        <option value="{{ $c->id }}" data-project="{{ $c->survey_project_id }}" @selected((string) old('survey_comp_case_id', $complaint->survey_comp_case_id) === (string) $c->id)>{{ $c->case_ref }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sv-group"><i class="fas fa-comment-dots"></i><span>Complaint</span></div>
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" id="cmpCategory" required>
                    <option value="">— Select —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($catPick === $c)>{{ $c }}</option>
                    @endforeach
                    <option value="{{ ComplaintController::CATEGORY_OTHER }}" @selected($catPick === ComplaintController::CATEGORY_OTHER)>Other (specify)</option>
                </select>
            </div>
            <div class="form-group" id="cmpCategoryOtherBox" @style(['display:none' => $catPick !== ComplaintController::CATEGORY_OTHER])>
                <label>Specify Category <span class="required">*</span></label>
                <input type="text" name="category_other" id="cmpCategoryOther" maxlength="100" value="{{ $catOther }}"
                       @disabled($catPick !== ComplaintController::CATEGORY_OTHER) />
            </div>
            <div class="form-group">
                <label>Date Received <span class="required">*</span></label>
                <input type="date" name="received_date" max="{{ now()->toDateString() }}"
                       value="{{ old('received_date', optional($complaint->received_date)->format('Y-m-d')) }}" required />
            </div>

            <div class="form-group full">
                <label>Subject <span class="required">*</span></label>
                <input type="text" name="subject" maxlength="255" value="{{ old('subject', $complaint->subject) }}"
                       placeholder="e.g. Mango trees under-counted on portion 3" required />
            </div>
            <div class="form-group full">
                <label>Details</label>
                <textarea name="description" maxlength="4000">{{ old('description', $complaint->description) }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <div class="left">
                @if ($editing)
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.complaints') }}"><i class="fas fa-times"></i> Cancel Edit</a>
                @else
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('complaintPanel').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
                @endif
            </div>
            <div class="right">
                <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Log Complaint' }}</button>
            </div>
        </div>
    </form>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search no., name, phone, subject…" style="{{ $inputStyle }}width:250px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (SurveyComplaint::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="category" style="{{ $inputStyle }}">
            <option value="">All Categories</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <select name="project" style="{{ $inputStyle }}max-width:240px;">
            <option value="">All Projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->project_code }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.complaints') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper" x-data="{ st: { open: false, url: '', status: '', ref: '', needsNote: false } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Complaint No</th>
                    <th>Date Received</th>
                    <th>Complainant</th>
                    <th>Project / Case</th>
                    <th>Category</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($complaints as $c)
                    <tr>
                        <td>{{ $complaints->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $c->complaint_ref }}</strong></td>
                        <td>{{ optional($c->received_date)->format('d M Y') }}</td>
                        <td>{{ $c->complainant_name }}@if ($c->complainant_phone)<div style="font-size:12px;color:var(--gray-500);">{{ $c->complainant_phone }}</div>@endif</td>
                        <td>
                            {{ $c->project?->project_code ?: '—' }}
                            @if ($c->case)<div style="font-size:12px;"><a href="{{ route('survey-module.compensation.cases.show', $c->case) }}">{{ $c->case->case_ref }}</a></div>@endif
                        </td>
                        <td>{{ $c->category }}</td>
                        <td>
                            {{ $c->subject }}
                            @if ($c->resolution)
                                <details style="font-size:12px;color:var(--gray-600);margin-top:4px;"><summary>Notes</summary><div style="white-space:pre-line;">{{ $c->resolution }}</div></details>
                            @endif
                        </td>
                        <td><span class="status-badge {{ $badge($c->status) }}"><span class="dot"></span>{{ $c->status }}</span></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @foreach (SurveyComplaint::NEXT[$c->status] ?? [] as $next)
                                        <button type="button" class="{{ $svItem }}"
                                                @click="open = false; st = { open: true, url: @js(route('survey-module.compensation.complaints.status', $c)), status: @js($next), ref: @js($c->complaint_ref), needsNote: @js($next !== SurveyComplaint::INVESTIGATING) }">
                                            <i data-lucide="{{ ['Under Investigation' => 'search', 'Resolved' => 'check-circle', 'Escalated' => 'arrow-up-circle'][$next] }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $next === SurveyComplaint::INVESTIGATING ? ($c->status === SurveyComplaint::ESCALATED ? 'Back to Investigation' : 'Start Investigation') : ($next === SurveyComplaint::RESOLVED ? 'Resolve' : 'Escalate') }}
                                        </button>
                                    @endforeach
                                    @unless ($c->isFrozen())
                                        <a href="{{ route('survey-module.compensation.complaints.edit', $c) }}" class="{{ $svItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endunless
                                    @if ($c->status === SurveyComplaint::OPEN)
                                        <form method="POST" action="{{ route('survey-module.compensation.complaints.destroy', $c) }}"
                                              onsubmit="return confirm('Delete {{ $c->complaint_ref }}? Only for a complaint logged in error.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if ($c->isFrozen())
                                        <div class="px-3 py-2 text-xs text-gray-500">Resolved {{ optional($c->resolved_at)->format('d M Y') }} · locked</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-comments" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No complaints match these filters.
                            @else
                                No complaints logged. Use <strong>Log Complaint</strong> above.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $complaints->firstItem() ?? 0 }}–{{ $complaints->lastItem() ?? 0 }} of {{ number_format($complaints->total()) }} complaints</span>
        <div class="pagination">{{ $complaints->links() }}</div>
    </div>

    {{-- Status change, with the note Resolve and Escalate require. --}}
    <div x-show="st.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="st.open = false">
        <form method="POST" :action="st.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(480px,92vw);" @click.outside="st.open = false">
            @csrf
            <input type="hidden" name="status" :value="st.status" />
            <h3 style="margin:0 0 6px;font-size:16px;font-weight:700;">
                <span x-text="st.ref"></span> → <span x-text="st.status"></span>
            </h3>
            <label style="display:block;font-size:13px;margin:10px 0 4px;">
                <span x-text="st.status === 'Resolved' ? 'How was it resolved?' : (st.status === 'Escalated' ? 'Why escalate, and to whom?' : 'Note (optional)')"></span>
                <span class="required" x-show="st.needsNote">*</span>
            </label>
            <textarea name="resolution" rows="4" maxlength="4000" style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;" :required="st.needsNote"></textarea>
            <p class="helper-text" x-show="st.status === 'Resolved'">A resolved complaint is locked: it can no longer be edited or deleted.</p>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="st.open = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var META = @json($benMeta);
        var ben = document.getElementById('cmpBeneficiary');
        var name = document.getElementById('cmpName'), phone = document.getElementById('cmpPhone');
        var project = document.getElementById('cmpProject'), kase = document.getElementById('cmpCase');
        var filled = null;   // what the last pick put in, so a changed pick clears it

        function apply() {
            var m = META[ben.value];
            if (filled) {
                if (name.value === (filled.name || '')) name.value = '';
                if (phone.value === (filled.phone || '')) phone.value = '';
            }
            if (m) {
                if (!name.value) name.value = m.name || '';
                if (!phone.value) phone.value = m.phone || '';
                project.value = m.project || '';
                kase.value = m.case || '';
            }
            // The server takes case and project from the beneficiary, so lock them while one is picked.
            project.disabled = kase.disabled = !!(m && m.case);
            filled = m || null;
        }
        if (ben) {
            ben.addEventListener('change', apply);
            if (ben.value) { filled = META[ben.value] || null; project.disabled = kase.disabled = !!(filled && filled.case); }
        }
        if (kase) kase.addEventListener('change', function () {
            var o = kase.options[kase.selectedIndex];
            if (o && o.dataset.project) project.value = o.dataset.project;
        });

        var cat = document.getElementById('cmpCategory'), box = document.getElementById('cmpCategoryOtherBox'), other = document.getElementById('cmpCategoryOther');
        if (cat) cat.addEventListener('change', function () {
            var on = cat.value === @json(ComplaintController::CATEGORY_OTHER);
            box.style.display = on ? '' : 'none';
            other.disabled = !on;
            other.required = on;
        });
    })();
</script>
