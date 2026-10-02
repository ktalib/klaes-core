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
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('laas-admin.applicants') }}" class="mb-5 flex flex-wrap gap-2">
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Search name, phone or email…"
                       class="min-w-[260px] flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm">
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                @if($search !== '')
                    <a href="{{ route('laas-admin.applicants') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
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
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ $applicant->name }}</p>
                                        @if($applicant->nin)
                                            <p class="text-xs text-slate-500">NIN {{ $applicant->nin }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-xs text-slate-900">{{ $applicant->phone }}</p>
                                        <p class="max-w-[200px] truncate text-xs text-slate-500">{{ $applicant->email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-slate-900">{{ $total }}</p>
                                        @if($locked > 0)
                                            <p class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
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
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('laas-admin.applicants.toggle', $applicant->id) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    {{ $isActive ? 'Suspend' : 'Restore' }}
                                                </button>
                                            </form>

                                            @if($locked > 0)
                                                {{-- Not merely disabled: the server refuses this too. --}}
                                                <span title="This applicant has {{ $locked }} application(s) already issued a file number. They are part of the land registry and cannot be deleted."
                                                      class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-400">
                                                    <i data-lucide="shield" class="h-3.5 w-3.5"></i> Protected
                                                </span>
                                            @else
                                                <button type="button"
                                                        onclick="laasConfirmDelete({{ $applicant->id }}, @js($applicant->name), @js($applicant->phone), {{ $total }})"
                                                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-slate-500">No applicants match this search.</td>
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

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') laasCloseDelete();
    });
</script>
@endsection
