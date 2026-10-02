@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Token balances across all organizations.'])

    <div class="flex-1 p-6">
        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                        <i data-lucide="wallet" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Organization Wallets</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Current balances, lifetime allocation and usage. Use View Ledger to see full history or make a manual adjustment.</p>
                    </div>
                </div>
                <a href="{{ route('system-admin.phs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left w-10">S/N</th>
                            <th class="px-5 py-3 text-left">Organization</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3 text-right">Allocated</th>
                            <th class="px-5 py-3 text-right">Used</th>
                            <th class="px-5 py-3 text-left">Last Top-up</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($wallets as $w)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-3 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ \Illuminate\Support\Str::title($w['inst']->name) }}</td>
                                <td class="px-5 py-3 text-right font-bold text-slate-900">{{ number_format($w['balance']) }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ number_format($w['allocated']) }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ number_format($w['used']) }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $w['last_topup'] ? \Illuminate\Support\Carbon::parse($w['last_topup'])->format('M j, Y') : '—' }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($w['inst']->status === 'active')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Suspended</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('system-admin.phs.institutions.show', ['id' => $w['inst']->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            <i data-lucide="list" class="h-3.5 w-3.5"></i> View Ledger
                                        </a>
                                        <button type="button"
                                            class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700"
                                            data-adjust='@json(['id' => $w['inst']->id, 'name' => $w['inst']->name, 'balance' => $w['balance']])'>
                                            <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i> Adjust
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No organizations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Manual adjustment modal --}}
<div id="adjust-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-bold text-slate-900">Manual Token Adjustment</h3>
            <button type="button" onclick="closeAdjust()" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="adjust-form" method="POST" class="px-6 py-5">
            @csrf
            <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3 text-sm">
                <p class="font-semibold text-slate-900" id="adj-org">—</p>
                <p class="text-slate-500">Current balance: <span class="font-semibold text-slate-700" id="adj-balance">0</span> tokens</p>
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold text-slate-600">Tokens (use a negative number to deduct) <span class="text-rose-500">*</span></label>
                <input type="number" name="tokens" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="e.g. 500 or -200">
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold text-slate-600">Reason / notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Reason for this adjustment"></textarea>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onclick="closeAdjust()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
    const adjustModal = document.getElementById('adjust-modal');
    const adjustForm = document.getElementById('adjust-form');
    const adjustBase = "{{ url('system-admin/phs/institutions') }}";

    function openAdjust(d) {
        adjustForm.action = `${adjustBase}/${d.id}/tokens`;
        document.getElementById('adj-org').textContent = d.name || '—';
        document.getElementById('adj-balance').textContent = Number(d.balance || 0).toLocaleString();
        adjustForm.reset();
        adjustModal.classList.remove('hidden'); adjustModal.classList.add('flex');
    }
    function closeAdjust() { adjustModal.classList.add('hidden'); adjustModal.classList.remove('flex'); }

    document.querySelectorAll('[data-adjust]').forEach((btn) => {
        btn.addEventListener('click', () => openAdjust(JSON.parse(btn.dataset.adjust)));
    });
    adjustModal.addEventListener('click', (e) => { if (e.target === adjustModal) closeAdjust(); });
    window.lucide?.createIcons();
</script>
@endsection
