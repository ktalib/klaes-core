@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Manage Organizational PHS accounts and token balances.'])

    <div class="flex-1 p-6">
        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        @php
            $statCards = [
                ['label' => 'Organizations', 'value' => $stats['institutions'], 'icon' => 'building-2', 'tone' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
                ['label' => 'Active', 'value' => $stats['active'], 'icon' => 'check-circle-2', 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
                ['label' => 'Total tokens', 'value' => $stats['total_tokens'], 'icon' => 'coins', 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
                ['label' => 'Pending invoices', 'value' => $stats['pending_invoices'], 'icon' => 'file-clock', 'tone' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            ];
        @endphp

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($statCards as $card)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($card['value']) }}</p>
                        </div>
                        <div class="grid h-11 w-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-700">
                        <i data-lucide="table-2" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900">Registered organizations</h3>
                        <p class="text-xs text-slate-500">{{ number_format($institutions->count()) }} organization(s) found</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 w-10">S/N</th>
                            <th class="px-4 py-3">Organization</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Members</th>
                            <th class="px-4 py-3">Tokens</th>
                            <th class="px-4 py-3">Onboarded</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($institutions as $institution)
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-4 py-4 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="landmark" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-extrabold text-slate-900">{{ \Illuminate\Support\Str::title($institution->name) }}</p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                                <i data-lucide="mail" class="h-3.5 w-3.5"></i>
                                                {{ $institution->email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-bold capitalize text-slate-700">
                                        <i data-lucide="briefcase-business" class="h-3.5 w-3.5"></i>
                                        {{ str_replace('_', ' ', $institution->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-slate-700">
                                        <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                                        {{ $institution->members_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 font-extrabold text-slate-900">
                                        <i data-lucide="coins" class="h-4 w-4 text-sky-500"></i>
                                        {{ number_format((int) $institution->token_balance) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500 text-xs font-medium">
                                    {{ $institution->created_at?->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    @if($institution->status === 'active')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 ring-1 ring-red-100">
                                            <i data-lucide="pause-circle" class="h-3.5 w-3.5"></i>
                                            {{ ucfirst($institution->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800" href="{{ route('system-admin.phs.institutions.show', $institution->id) }}">
                                            Open
                                            <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                                        </a>
                                        <button type="button"
                                                title="Master delete"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100"
                                                data-phs-delete
                                                data-id="{{ $institution->id }}"
                                                data-name="{{ e($institution->name) }}"
                                                data-members="{{ (int) $institution->members_count }}"
                                                data-tokens="{{ (int) $institution->token_balance }}">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-14 text-center text-slate-400">
                                    <i data-lucide="building-2" class="mx-auto mb-3 h-8 w-8 opacity-40"></i>
                                    No organizations registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

{{-- Master delete: type-to-confirm --}}
<div id="phs-delete-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="flex items-center gap-2 text-base font-extrabold text-rose-700">
                <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                Master delete organization
            </h3>
            <button type="button" onclick="closePhsDeleteModal()" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form id="phs-delete-form" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('DELETE')
            <p class="text-sm text-slate-700">
                <span id="phs-delete-title" class="font-extrabold text-slate-900"></span> and everything attached to it will be
                permanently erased — <span id="phs-delete-meta" class="font-bold"></span>, wallet ledger, search logs, feedback,
                email history, its onboarding request and every uploaded document. This cannot be undone.
            </p>
            <p class="mt-3 rounded-md bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                If you only need to block access, close this and use <span class="font-bold">Suspend</span> on the organization page instead.
            </p>
            <label class="mt-4 block text-xs font-bold uppercase tracking-wide text-slate-600">
                Type <span id="phs-delete-echo" class="font-mono text-rose-700"></span> to confirm
            </label>
            <input type="text" name="confirm_name" id="phs-delete-confirm" autocomplete="off" required
                   class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500">
            <p class="mt-2 text-xs text-slate-500">This action is recorded in the audit trail against your account.</p>
            <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onclick="closePhsDeleteModal()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" id="phs-delete-submit" disabled
                        class="rounded-md bg-rose-600 px-5 py-2 text-sm font-bold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                    Permanently delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const phsDeleteModal = document.getElementById('phs-delete-modal');
    const phsDeleteForm = document.getElementById('phs-delete-form');
    const phsDeleteInput = document.getElementById('phs-delete-confirm');
    const phsDeleteSubmit = document.getElementById('phs-delete-submit');
    const phsInstitutionsBase = "{{ url('system-admin/phs/institutions') }}";
    let phsDeleteName = '';

    function closePhsDeleteModal() {
        phsDeleteModal.classList.add('hidden');
        phsDeleteModal.classList.remove('flex');
    }

    document.querySelectorAll('[data-phs-delete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            phsDeleteName = btn.dataset.name || '';
            phsDeleteForm.action = phsInstitutionsBase + '/' + btn.dataset.id;
            document.getElementById('phs-delete-title').textContent = phsDeleteName;
            document.getElementById('phs-delete-echo').textContent = phsDeleteName;
            document.getElementById('phs-delete-meta').textContent =
                btn.dataset.members + ' member account(s) and ' + Number(btn.dataset.tokens).toLocaleString() + ' unused token(s)';
            phsDeleteInput.value = '';
            phsDeleteInput.placeholder = phsDeleteName;
            phsDeleteSubmit.disabled = true;
            phsDeleteModal.classList.remove('hidden');
            phsDeleteModal.classList.add('flex');
            phsDeleteInput.focus();
        });
    });

    phsDeleteInput.addEventListener('input', function (e) {
        phsDeleteSubmit.disabled = e.target.value.trim().toLowerCase() !== phsDeleteName.trim().toLowerCase();
    });

    phsDeleteModal.addEventListener('click', function (e) {
        if (e.target === phsDeleteModal) closePhsDeleteModal();
    });
</script>
@endsection
