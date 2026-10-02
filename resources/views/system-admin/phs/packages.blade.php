@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Create and manage PHS subscription token packages.'])

    <div class="flex-1 p-6">

        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                        <i data-lucide="package" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Token Packages</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">These packages drive the onboarding form, the dashboard purchase modal, and invoice pricing.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('system-admin.phs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back
                    </a>
                    <button type="button" onclick="openPackageModal()" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                        <i data-lucide="plus" class="h-4 w-4"></i> Add Package
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Order</th>
                            <th class="px-5 py-3 text-left">Name</th>
                            <th class="px-5 py-3 text-left">Slug</th>
                            <th class="px-5 py-3 text-right">Bundles</th>
                            <th class="px-5 py-3 text-right">Tokens</th>
                            <th class="px-5 py-3 text-right">Price (₦)</th>
                            <th class="px-5 py-3 text-right">Team</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($packages as $pkg)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-3 text-slate-500">{{ $pkg->display_order }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $pkg->name }}</p>
                                    @if ($pkg->description)<p class="text-xs text-slate-500">{{ $pkg->description }}</p>@endif
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $pkg->slug }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ number_format($pkg->bundles) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format($pkg->tokens) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format($pkg->price) }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ $pkg->team_members }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($pkg->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            data-edit-package='@json($pkg)'>
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('system-admin.phs.packages.destroy', $pkg->id) }}"
                                              onsubmit="return confirm('Delete the “{{ $pkg->name }}” package? Existing wallets are not affected.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-10 text-center text-slate-500">No packages yet. Click “Add Package” to create one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div id="package-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 id="package-modal-title" class="text-base font-bold text-slate-900">Add Package</h3>
            <button type="button" onclick="closePackageModal()" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form id="package-form" method="POST" action="{{ route('system-admin.phs.packages.store') }}" class="px-6 py-5">
            @csrf
            <input type="hidden" name="_method" id="package-form-method" value="POST">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="pkg-name" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="e.g. Professional">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Slug</label>
                    <input type="text" name="slug" id="pkg-slug" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="auto from name">
                    <p class="mt-1 text-[11px] text-slate-400">Lowercase, hyphens. Leave blank to auto-generate.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Display order</label>
                    <input type="number" name="display_order" id="pkg-order" min="0" value="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Bundles <span class="text-rose-500">*</span></label>
                    <input type="number" name="bundles" id="pkg-bundles" min="1" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="5">
                    <p class="mt-1 text-[11px] text-slate-400">1 bundle = {{ number_format(\App\Models\Phs\PhsTokenPackage::TOKENS_PER_BUNDLE) }} tokens.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Tokens (auto)</label>
                    <input type="number" id="pkg-tokens" readonly class="w-full rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600" placeholder="0">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Price (₦) <span class="text-rose-500">*</span></label>
                    <input type="number" name="price" id="pkg-price" min="0" step="0.01" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="100000">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Team members <span class="text-rose-500">*</span></label>
                    <input type="number" name="team_members" id="pkg-team" min="1" value="2" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="is_active" id="pkg-active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Active
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                    <textarea name="description" id="pkg-description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="Short blurb shown to organizations"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onclick="closePackageModal()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700">Save Package</button>
            </div>
        </form>
    </div>
</div>

<script>
    const pkgModal = document.getElementById('package-modal');
    const pkgForm = document.getElementById('package-form');
    const storeUrl = "{{ route('system-admin.phs.packages.store') }}";
    const updateBase = "{{ url('system-admin/phs/packages') }}";
    const TOKENS_PER_BUNDLE = {{ \App\Models\Phs\PhsTokenPackage::TOKENS_PER_BUNDLE }};

    function syncTokens() {
        const bundles = parseInt(document.getElementById('pkg-bundles').value, 10) || 0;
        document.getElementById('pkg-tokens').value = bundles * TOKENS_PER_BUNDLE;
    }

    function openPackageModal() {
        pkgForm.action = storeUrl;
        document.getElementById('package-form-method').value = 'POST';
        document.getElementById('package-modal-title').textContent = 'Add Package';
        pkgForm.reset();
        document.getElementById('pkg-active').checked = true;
        document.getElementById('pkg-order').value = '0';
        document.getElementById('pkg-team').value = '2';
        document.getElementById('pkg-tokens').value = '';
        showPkgModal();
    }

    function editPackage(p) {
        pkgForm.action = `${updateBase}/${p.id}`;
        document.getElementById('package-form-method').value = 'PUT';
        document.getElementById('package-modal-title').textContent = 'Edit Package';
        document.getElementById('pkg-name').value = p.name ?? '';
        document.getElementById('pkg-slug').value = p.slug ?? '';
        document.getElementById('pkg-order').value = p.display_order ?? 0;
        document.getElementById('pkg-bundles').value = p.bundles ?? Math.max(1, Math.round((p.tokens ?? 0) / TOKENS_PER_BUNDLE));
        document.getElementById('pkg-price').value = p.price ?? '';
        document.getElementById('pkg-team').value = p.team_members ?? 2;
        document.getElementById('pkg-active').checked = !!p.is_active;
        document.getElementById('pkg-description').value = p.description ?? '';
        syncTokens();
        showPkgModal();
    }

    document.getElementById('pkg-bundles').addEventListener('input', syncTokens);

    function showPkgModal() { pkgModal.classList.remove('hidden'); pkgModal.classList.add('flex'); }
    function closePackageModal() { pkgModal.classList.add('hidden'); pkgModal.classList.remove('flex'); }

    document.querySelectorAll('[data-edit-package]').forEach((btn) => {
        btn.addEventListener('click', () => editPackage(JSON.parse(btn.dataset.editPackage)));
    });
    pkgModal.addEventListener('click', (e) => { if (e.target === pkgModal) closePackageModal(); });
    window.lucide?.createIcons();
</script>
@endsection
