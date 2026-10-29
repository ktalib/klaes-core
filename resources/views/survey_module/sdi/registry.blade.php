@extends('survey_module.layouts.klaes')

@section('page-title', 'MDA & Map Type Registry — SDI — KLAES')

@php
    $PageTitle = 'SDI · MDA & Map Type Registry';
    $PageDescription = 'Kano MDAs that request maps, and the map types the GIS unit produces. Retired, never deleted.';
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $field = 'width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;';
    $blankMda = ['url' => route('survey-module.sdi.registry.mdas.store'), 'method' => 'POST', 'title' => 'Add MDA',
                 'category_id' => '', 'name' => '', 'abbreviation' => '', 'contact_name' => '', 'contact_phone' => '', 'contact_email' => '', 'is_custodian' => false];
@endphp

@section('survey-content')
@include('survey_module.sdi._head')
@include('survey_module.partials._flash')

@if ($errors->any())
    <div class="alert alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
@endif

<div x-data="{ mda: null, type: null }">
    <div class="page-header">
        <div class="sdi-nav" style="border:0;margin:0;">
            <a href="{{ route('survey-module.sdi.registry') }}" class="{{ $tab === 'mdas' ? 'on' : '' }}">MDAs <span class="n">{{ number_format($mdas->total()) }}</span></a>
            <a href="{{ route('survey-module.sdi.registry', ['tab' => 'types']) }}" class="{{ $tab === 'types' ? 'on' : '' }}">Map Types <span class="n">{{ $mapTypes->count() }}</span></a>
        </div>
        @if ($tab === 'mdas')
            <button type="button" class="btn btn-primary" @click.stop="mda = @js($blankMda)"><i class="fas fa-plus"></i> Add MDA</button>
        @else
            <button type="button" class="btn btn-primary" @click.stop="type = { url: @js(route('survey-module.sdi.registry.types.store')), method: 'POST', title: 'Add Map Type', name: '' }"><i class="fas fa-plus"></i> Add Map Type</button>
        @endif
    </div>

    @if ($tab === 'mdas')
        <form method="GET" class="table-toolbar">
            <div class="left" style="flex-wrap:wrap;">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or abbreviation…" style="{{ $inputStyle }}width:260px;" />
                <select name="category" style="{{ $inputStyle }}max-width:320px;">
                    <option value="">All Categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                @if (request()->hasAny(['q', 'category']))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.sdi.registry') }}">Clear</a>@endif
            </div>
        </form>

        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead><tr><th>S/N</th><th>MDA</th><th>Abbreviation</th><th>Category</th><th>Contact</th><th>Requests</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($mdas as $md)
                            <tr @style(['opacity:.55' => ! $md->is_active])>
                                <td>{{ $mdas->firstItem() + $loop->index }}</td>
                                <td>{{ $md->name }} @if ($md->is_custodian)<span class="sdi-chip high">Custodian</span>@endif</td>
                                <td>{{ $md->abbreviation ?: '—' }}</td>
                                <td style="font-size:13px;">{{ $md->category?->name }}</td>
                                <td style="font-size:13px;">{{ collect([$md->contact_name, $md->contact_phone, $md->contact_email])->filter()->implode(' · ') ?: '—' }}</td>
                                <td>{{ number_format($md->map_requests_count) }}</td>
                                <td><span class="status-badge {{ $md->is_active ? 'active' : 'archived' }}"><span class="dot"></span>{{ $md->is_active ? 'Active' : 'Retired' }}</span></td>
                                <td>
                                    <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                        <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                                @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                             class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                            <button type="button" class="{{ $svItem }}"
                                                    @click.stop="open = false; mda = @js(['url' => route('survey-module.sdi.registry.mdas.update', $md), 'method' => 'PUT', 'title' => 'Edit ' . $md->name,
                                                        'category_id' => (string) $md->category_id, 'name' => $md->name, 'abbreviation' => (string) $md->abbreviation,
                                                        'contact_name' => (string) $md->contact_name, 'contact_phone' => (string) $md->contact_phone, 'contact_email' => (string) $md->contact_email,
                                                        'is_custodian' => $md->is_custodian])">
                                                <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                            </button>
                                            <form method="POST" action="{{ route('survey-module.sdi.registry.mdas.toggle', $md) }}">
                                                @csrf
                                                <button type="submit" class="{{ $svItem }}"><i data-lucide="{{ $md->is_active ? 'archive' : 'rotate-ccw' }}" class="w-4 h-4 text-gray-500"></i> {{ $md->is_active ? 'Retire' : 'Restore' }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">No MDAs match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Showing {{ $mdas->firstItem() ?? 0 }}–{{ $mdas->lastItem() ?? 0 }} of {{ number_format($mdas->total()) }} MDAs</span>
                <div class="pagination">{{ $mdas->links() }}</div>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead><tr><th>S/N</th><th>Map Type</th><th>Requests</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($mapTypes as $t)
                            <tr @style(['opacity:.55' => ! $t->is_active])>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $t->name }}</td>
                                <td>{{ number_format($t->requests_count) }}</td>
                                <td><span class="status-badge {{ $t->is_active ? 'active' : 'archived' }}"><span class="dot"></span>{{ $t->is_active ? 'Active' : 'Retired' }}</span></td>
                                <td>
                                    <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                        <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                                @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                             class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                            <button type="button" class="{{ $svItem }}"
                                                    @click.stop="open = false; type = @js(['url' => route('survey-module.sdi.registry.types.update', $t), 'method' => 'PUT', 'title' => 'Edit Map Type', 'name' => $t->name])">
                                                <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Rename
                                            </button>
                                            <form method="POST" action="{{ route('survey-module.sdi.registry.types.toggle', $t) }}">
                                                @csrf
                                                <button type="submit" class="{{ $svItem }}"><i data-lucide="{{ $t->is_active ? 'archive' : 'rotate-ccw' }}" class="w-4 h-4 text-gray-500"></i> {{ $t->is_active ? 'Retire' : 'Restore' }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--gray-500);">No map types.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- MDA add / edit --}}
    <template x-if="mda">
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="mda = null">
            <form method="POST" :action="mda.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(560px,94vw);" @click.outside="mda = null">
                @csrf
                <input type="hidden" name="_method" :value="mda.method" />
                <h3 style="margin:0 0 12px;font-size:16px;font-weight:700;" x-text="mda.title"></h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="grid-column:1/-1;font-size:13px;">Name <span class="required">*</span>
                        <input type="text" name="name" maxlength="300" required x-model="mda.name" style="{{ $field }}" /></label>
                    <label style="font-size:13px;">Abbreviation
                        <input type="text" name="abbreviation" maxlength="40" x-model="mda.abbreviation" style="{{ $field }}" /></label>
                    <label style="font-size:13px;">Category <span class="required">*</span>
                        <select name="category_id" required x-model="mda.category_id" style="{{ $field }}">
                            <option value="">— Select —</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select></label>
                    <label style="font-size:13px;">Contact Person
                        <input type="text" name="contact_name" maxlength="200" x-model="mda.contact_name" style="{{ $field }}" /></label>
                    <label style="font-size:13px;">Phone
                        <input type="text" name="contact_phone" maxlength="40" x-model="mda.contact_phone" style="{{ $field }}" /></label>
                    <label style="grid-column:1/-1;font-size:13px;">Email
                        <input type="email" name="contact_email" maxlength="255" x-model="mda.contact_email" style="{{ $field }}" /></label>
                    <label style="grid-column:1/-1;font-size:13px;display:flex;align-items:center;gap:8px;">
                        <input type="hidden" name="is_custodian" value="0" />
                        <input type="checkbox" name="is_custodian" value="1" x-model="mda.is_custodian" /> SDI custodian (the Ministry of Land and Physical Planning)</label>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" @click="mda = null">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </template>

    {{-- Map type add / rename --}}
    <template x-if="type">
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="type = null">
            <form method="POST" :action="type.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(440px,92vw);" @click.outside="type = null">
                @csrf
                <input type="hidden" name="_method" :value="type.method" />
                <h3 style="margin:0 0 12px;font-size:16px;font-weight:700;" x-text="type.title"></h3>
                <label style="font-size:13px;display:block;">Name <span class="required">*</span>
                    <input type="text" name="name" maxlength="150" required x-model="type.name" style="{{ $field }}" /></label>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" @click="type = null">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection
