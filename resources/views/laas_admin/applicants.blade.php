@extends('layouts.app')

@section('page-title')
    {{ __('LAAS Portal — Applicants') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS Portal — Applicants',
        'PageDescription' => 'Portal accounts, and what each one has in flight.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <a href="{{ route('laas-admin.index') }}" class="mb-2 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to queue
                    </a>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Applicants</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Accounts on the public portal. Suspending is reversible; deleting is not.
                    </p>
                </div>
                <a href="{{ route('laas-admin.transfers') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-right-left" class="h-4 w-4"></i> Ownership transfers
                    @if($pendingTransfers > 0)
                        <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $pendingTransfers }}</span>
                    @endif
                </a>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('laas-admin.applicants') }}" class="mb-5 flex flex-wrap gap-2">
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Search name, username, file number, phone or email…"
                       class="min-w-[260px] flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm">
                <select name="contact" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All accounts</option>
                    <option value="pending" @selected($contact === 'pending')>Contact pending update ({{ number_format($pendingContacts) }})</option>
                </select>
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                @if($search !== '' || $contact)
                    <a href="{{ route('laas-admin.applicants') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 w-12">S/N</th>
                                <th class="px-4 py-3">File No.</th>
                                <th class="px-4 py-3">Applicant</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Applications</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Registered</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applicants as $applicant)
                                @php
                                    $total    = $totals[$applicant->id] ?? 0;
                                    $locked   = $inRegistry[$applicant->id] ?? 0;
                                    $isActive = $applicant->status === 'active';
                                    $sysPhone = \App\Models\Laas\LaasApplicant::isSystemPhone($applicant->phone);
                                    $sysEmail = str_ends_with((string) $applicant->email, '@' . config('laas.commissioning_accounts.placeholder_email_domain', 'portal.klaes.local'));
                                    $reasons  = array_filter(explode(',', (string) $applicant->contact_flag_reason));
                                    $reasonLabels = ['no_phone' => 'No phone', 'no_email' => 'No email', 'shared_phone' => 'Shared phone'];

                                    $actions = [
                                        ['label' => 'View profile', 'icon' => 'user', 'url' => route('laas-admin.applicants.show', $applicant->id)],
                                        ['label' => 'Update contact', 'icon' => 'phone', 'onclick' => 'laasOpenContact(' . $applicant->id . ', ' . json_encode($applicant->name) . ', ' . json_encode($sysPhone ? '' : (string) $applicant->phone) . ', ' . json_encode($sysEmail ? '' : (string) $applicant->email) . ')'],
                                        ['label' => 'Issue sign-in slip', 'icon' => 'printer', 'post' => route('laas-admin.applicants.slip', $applicant->id), 'target' => '_blank',
                                         'confirm' => 'This resets ' . $applicant->name . "'s password to a new temporary one and opens a slip to print. Continue?"],
                                        ['label' => 'Merge into another account', 'icon' => 'git-merge', 'onclick' => 'laasOpenMerge(' . $applicant->id . ', ' . json_encode($applicant->name) . ', ' . json_encode((string) $applicant->username) . ')'],
                                        ['label' => $isActive ? 'Suspend' : 'Restore', 'icon' => $isActive ? 'pause-circle' : 'play-circle', 'post' => route('laas-admin.applicants.toggle', $applicant->id)],
                                    ];
                                    if ($locked === 0) {
                                        $actions[] = ['label' => 'Delete', 'icon' => 'trash-2', 'danger' => true,
                                                      'onclick' => 'laasConfirmDelete(' . $applicant->id . ', ' . json_encode($applicant->name) . ', ' . json_encode((string) $applicant->phone) . ', ' . $total . ')'];
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-500">{{ $applicants->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3">
                                        @php $files = $fileNumbers[$applicant->id] ?? []; @endphp
                                        @forelse(array_slice($files, 0, 3) as $fileNo)
                                            <p class="font-mono text-xs text-slate-900">{{ $fileNo }}</p>
                                        @empty
                                            <p class="text-xs text-slate-400">—</p>
                                        @endforelse
                                        @if(count($files) > 3)
                                            <p class="text-xs text-slate-500" title="{{ implode(', ', array_slice($files, 3, 50)) }}">+{{ count($files) - 3 }} more</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('laas-admin.applicants.show', $applicant->id) }}"
                                           class="font-semibold text-slate-900 hover:text-blue-700 hover:underline">{{ $applicant->name }}</a>
                                        @if($applicant->username)
                                            <p class="font-mono text-xs text-slate-500">{{ $applicant->username }}</p>
                                        @endif
                                        @if($applicant->nin)
                                            <p class="text-xs text-slate-500">NIN {{ $applicant->nin }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($sysPhone)
                                            <p class="text-xs italic text-slate-400">System number ({{ $applicant->phone }})</p>
                                        @else
                                            <p class="font-mono text-xs text-slate-900">{{ $applicant->phone }}</p>
                                        @endif
                                        @if($sysEmail)
                                            <p class="text-xs italic text-slate-400">System email</p>
                                        @else
                                            <p class="max-w-[200px] truncate text-xs text-slate-500">{{ $applicant->email }}</p>
                                        @endif
                                        @if($applicant->contact_status === \App\Models\Laas\LaasApplicant::CONTACT_PENDING)
                                            <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                                <i data-lucide="flag" class="h-3 w-3"></i>
                                                Pending: {{ collect($reasons)->map(fn ($r) => $reasonLabels[$r] ?? $r)->implode(', ') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-slate-900">{{ $total }}</p>
                                        @if($locked > 0)
                                            <p class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700"
                                               title="Applications already issued a file number are part of the land registry, so this account cannot be deleted.">
                                                <i data-lucide="lock" class="h-3 w-3"></i> {{ $locked }} in the registry
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                            {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700' }}">
                                            {{ $isActive ? 'Active' : 'Suspended' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ $applicant->created_at?->format('j M Y') ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @include('laas_admin.partials._row_actions', ['items' => $actions])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-slate-500">No applicants match this search.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">{{ $applicants->links() }}</div>
        </div>
    </div>
</div>

{{-- ---------- Update contact ---------- --}}
<div id="laasContactModal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="laasCloseModal('laasContactModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">Update contact details</h2>
                <p id="laasContactName" class="text-xs text-slate-500"></p>
            </div>
            <form id="laasContactForm" method="POST" class="space-y-4 px-6 py-5">
                @csrf
                <div>
                    <label for="contact_phone" class="block text-xs font-bold text-slate-700">Phone number (the holder's own)</label>
                    <input id="contact_phone" name="phone" type="text" inputmode="tel" autocomplete="off" placeholder="080xxxxxxxx"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                </div>
                <div>
                    <label for="contact_email" class="block text-xs font-bold text-slate-700">Email (optional)</label>
                    <input id="contact_email" name="email" type="email" autocomplete="off"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="hidden" name="text_signin" value="0">
                    <input type="checkbox" name="text_signin" value="1" checked class="mt-0.5">
                    <span>Text new sign-in details to this phone (resets the password to a temporary one)</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="laasCloseModal('laasContactModal')"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ---------- Merge accounts ---------- --}}
<div id="laasMergeModal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="laasCloseModal('laasMergeModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">Merge into another account</h2>
                <p class="text-xs text-slate-500">Only for two accounts that are the same person.</p>
            </div>
            <form id="laasMergeForm" method="POST" class="space-y-4 px-6 py-5">
                @csrf
                <p class="text-sm text-slate-700">
                    Every file on <strong id="laasMergeName"></strong> (<span id="laasMergeUser" class="font-mono"></span>)
                    moves to the account below, and this account is suspended.
                </p>
                <div>
                    <label for="target_username" class="block text-xs font-bold text-slate-700">Username of the account to keep</label>
                    <input id="target_username" name="target_username" type="text" required autocomplete="off"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="laasCloseModal('laasMergeModal')"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Merge</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ---------- Delete confirmation ---------- --}}
<div id="laasDeleteModal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="laasCloseDelete()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <i data-lucide="triangle-alert" class="h-5 w-5"></i>
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Delete applicant</h2>
                    <p class="text-xs text-slate-500">This cannot be undone.</p>
                </div>
            </div>

            <form id="laasDeleteForm" method="POST" class="px-6 py-5">
                @csrf
                @method('DELETE')

                <p class="text-sm text-slate-700">
                    You are about to permanently delete
                    <strong id="laasDeleteName" class="text-slate-900"></strong>
                    and <strong id="laasDeleteCount"></strong>, including every timeline entry,
                    uploaded document and desk alert belonging to them.
                </p>

                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs text-amber-900">
                        Suspending the account instead keeps the records and stops them signing in.
                    </p>
                </div>

                <div class="mt-5">
                    <label for="confirm_phone" class="block text-xs font-bold text-slate-700">
                        Type their phone number <span id="laasDeletePhoneHint" class="font-mono text-slate-500"></span> to confirm
                    </label>
                    <input id="confirm_phone" name="confirm_phone" type="text" required autocomplete="off"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" onclick="laasCloseDelete()"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Delete permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // The phone number is echoed into the label so the officer can see exactly
    // what to type — the point is a deliberate pause, not a memory test.
    function laasConfirmDelete(id, name, phone, total) {
        var form = document.getElementById('laasDeleteForm');
        form.action = '{{ url('laas-admin/applicants') }}/' + id;

        document.getElementById('laasDeleteName').textContent = name;
        document.getElementById('laasDeletePhoneHint').textContent = '(' + phone + ')';
        document.getElementById('laasDeleteCount').textContent =
            total === 1 ? 'their 1 application' : 'their ' + total + ' applications';

        document.getElementById('confirm_phone').value = '';
        document.getElementById('laasDeleteModal').classList.remove('hidden');
        document.getElementById('confirm_phone').focus();

        if (window.lucide) window.lucide.createIcons();
    }

    function laasCloseDelete() {
        document.getElementById('laasDeleteModal').classList.add('hidden');
    }

    function laasOpenContact(id, name, phone, email) {
        document.getElementById('laasContactForm').action = '{{ url('laas-admin/applicants') }}/' + id + '/contact';
        document.getElementById('laasContactName').textContent = name;
        document.getElementById('contact_phone').value = phone;
        document.getElementById('contact_email').value = email;
        document.getElementById('laasContactModal').classList.remove('hidden');
        document.getElementById('contact_phone').focus();
    }

    function laasOpenMerge(id, name, username) {
        document.getElementById('laasMergeForm').action = '{{ url('laas-admin/applicants') }}/' + id + '/merge';
        document.getElementById('laasMergeName').textContent = name;
        document.getElementById('laasMergeUser').textContent = username;
        document.getElementById('target_username').value = '';
        document.getElementById('laasMergeModal').classList.remove('hidden');
        document.getElementById('target_username').focus();
    }

    function laasCloseModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            laasCloseDelete();
            laasCloseModal('laasContactModal');
            laasCloseModal('laasMergeModal');
        }
    });
</script>
@endsection
