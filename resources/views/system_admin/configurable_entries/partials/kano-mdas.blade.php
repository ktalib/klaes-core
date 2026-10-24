{{-- Kano MDAs (kano_mdas): the Kano State MDAs offered as an OP Source ("Kano MDAs",
     App\Support\OpSource) and as the External sender on File Movement. Both keep the
     chosen name as text, so a rename is carried onto the permits that hold it, and an
     MDA a permit names can be switched off but not deleted. --}}
@php
    $usageOf = fn ($m) => $mdaUsage[mb_strtolower(trim($m->name))] ?? 0;
    $filtered = $mdaFilters['q'] !== '' || $mdaFilters['category'] !== '' || $mdaFilters['status'] !== '';
@endphp

<div class="iw-card" x-data="{ editing: null, menu: null, menuTop: 0, menuLeft: 0,
        openMenu(id, el) { const r = el.getBoundingClientRect(); this.menuTop = r.bottom + 4; this.menuLeft = Math.max(8, r.right - 210); this.menu = this.menu === id ? null : id; } }"
    @keydown.escape.window="editing = null; menu = null" @scroll.window="menu = null" @resize.window="menu = null">
    <div class="iw-card-head" style="align-items:center">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="landmark" class="h-5 w-5"></i></span> Kano MDAs</div>
            <div class="iw-card-sub">
                {{ $mdaActive }} of {{ $mdaTotal }} offered. Used as the OP Source "Kano MDAs" (File Indexing, FH, PRA) and as the External sender on File Movement.
            </div>
        </div>
        <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="editing = 'new'"><i data-lucide="plus" class="h-4 w-4"></i> Add MDA</button>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 items-center" style="padding:12px 22px;border-bottom:1px solid #f3f4f6">
        <input type="hidden" name="tab" value="kano-mdas">
        <input type="search" name="q" value="{{ $mdaFilters['q'] }}" placeholder="Search MDA name" class="iw-input" style="max-width:280px">
        <select name="category" class="iw-input" style="max-width:180px">
            <option value="">All categories</option>
            @foreach($mdaCategories as $cat)
                <option value="{{ $cat }}" @selected(strcasecmp($mdaFilters['category'], $cat) === 0)>{{ $cat }}</option>
            @endforeach
        </select>
        <select name="status" class="iw-input" style="max-width:150px">
            <option value="">Any status</option>
            <option value="active" @selected($mdaFilters['status'] === 'active')>Active</option>
            <option value="off" @selected($mdaFilters['status'] === 'off')>Off</option>
        </select>
        <button class="iw-btn iw-btn-light iw-btn-sm"><i data-lucide="search" class="h-3.5 w-3.5"></i> Filter</button>
        @if($filtered)
            <a href="{{ route('configurable-entries.index', ['tab' => 'kano-mdas']) }}" class="iw-btn iw-btn-light iw-btn-sm">Clear</a>
            <span class="ce-muted">{{ $mdas->count() }} shown</span>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th style="width:60px">S/N</th><th>MDA</th><th>Category</th><th class="text-right">Display order</th><th>Status</th><th style="width:60px"></th></tr></thead>
            <tbody>
                @forelse($mdas as $m)
                    @php $uses = $usageOf($m); @endphp
                    <tr>
                        <td class="ce-muted">{{ $loop->iteration }}</td>
                        <td class="font-medium text-gray-900">{{ $m->name }}</td>
                        <td>{{ $m->category ?: '—' }}</td>
                        <td class="text-right ce-muted">{{ $m->sort_order }}</td>
                        <td>@if($m->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                        <td class="text-right">
                            <button type="button" class="iw-btn iw-btn-light iw-btn-sm" aria-label="Actions" aria-haspopup="menu"
                                :aria-expanded="menu === {{ $m->id }}" @click.stop="openMenu({{ $m->id }}, $el)">
                                <i data-lucide="more-vertical" class="h-4 w-4"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ce-muted text-center" style="padding:24px">{{ $filtered ? 'No MDA matches the filter.' : 'No MDAs yet. Add the first one.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- One action menu per row, fixed-positioned so the table's overflow cannot clip it. --}}
    @foreach($mdas as $m)
        @php $uses = $usageOf($m); @endphp
        <div x-show="menu === {{ $m->id }}" x-cloak role="menu" @click.outside="menu = null"
            :style="'position:fixed;z-index:60;top:' + menuTop + 'px;left:' + menuLeft + 'px;width:210px'"
            class="bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">
            <button type="button" role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50"
                @click="menu = null; editing = {{ $m->id }}">
                <i data-lucide="pencil" class="h-4 w-4 text-gray-500"></i> Edit
            </button>
            <form method="POST" action="{{ route('configurable-entries.kano-mdas.toggle', $m->id) }}">
                @csrf
                <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50" @click="menu = null">
                    @if($m->is_active)
                        <i data-lucide="toggle-left" class="h-4 w-4 text-gray-500"></i> Switch off
                    @else
                        <i data-lucide="toggle-right" class="h-4 w-4 text-green-600"></i> Switch on
                    @endif
                </button>
            </form>
            @if($uses)
                <div class="flex items-center gap-2 px-3 py-2 text-gray-400 cursor-not-allowed" title="Named on {{ $uses }} Occupancy {{ \Illuminate\Support\Str::plural('Permit', $uses) }}: switch it off instead">
                    <i data-lucide="trash-2" class="h-4 w-4"></i> Delete (in use)
                </div>
            @else
                <form method="POST" action="{{ route('configurable-entries.kano-mdas.delete', $m->id) }}"
                    onsubmit="return confirm(@js('Delete ' . $m->name . '? It will no longer be offered anywhere.'))">
                    @csrf
                    <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-700 hover:bg-red-50" @click="menu = null">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                    </button>
                </form>
            @endif
        </div>
    @endforeach

    {{-- Add / edit. Category offers the listed ones plus one Other with its specify box. --}}
    @foreach($mdas->concat([null]) as $m)
        @php
            $catValue = $m?->category ?? '';
            $catListed = $catValue === '' || in_array($catValue, $mdaCategories, true);
        @endphp
        <div class="iwr-backdrop" x-show="editing === {{ $m ? $m->id : "'new'" }}" x-cloak x-transition.opacity @click.self="editing = null">
            <form method="POST" action="{{ $m ? route('configurable-entries.kano-mdas.update', $m->id) : route('configurable-entries.kano-mdas.store') }}" class="iwr-modal tone-blue"
                x-data="{ category: @js($catListed ? $catValue : 'Other') }">
                @csrf
                <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="landmark" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">Kano MDAs</div><div class="iwr-title">{{ $m ? 'Edit MDA' : 'Add MDA' }}</div></div><button type="button" class="iwr-close" @click="editing = null">✕</button></div>
                <div class="iwr-body space-y-4">
                    <div class="iw-field"><label class="iw-label">MDA name <span class="req">*</span></label><input type="text" name="name" value="{{ $m?->name }}" required maxlength="100" class="iw-input" placeholder="e.g. Kano State Water Board"></div>
                    <div class="iw-grid" style="gap:12px">
                        <div class="iw-field">
                            <label class="iw-label">Category <span class="req">*</span></label>
                            <select name="category" x-model="category" required class="iw-input">
                                <option value="">Select category</option>
                                @foreach($mdaCategories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" name="category_other" x-show="category === 'Other'" x-cloak :required="category === 'Other'" :disabled="category !== 'Other'"
                                value="{{ $catListed ? '' : $catValue }}" maxlength="50" class="iw-input mt-2" placeholder="Specify category *">
                        </div>
                        <div class="iw-field"><label class="iw-label">Display order</label><input type="number" min="0" name="sort_order" value="{{ $m?->sort_order }}" class="iw-input" placeholder="{{ $m ? '' : 'Last' }}"></div>
                    </div>
                    @if($m)<label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($m->is_active)><span></span></span> Offered in the MDA dropdowns</label>@endif
                </div>
                <div class="iwr-foot"><span class="ce-muted">{{ $m && $usageOf($m) ? 'A rename is carried onto the ' . $usageOf($m) . ' OP(s) that name it.' : 'Capitals are kept as typed.' }}</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
            </form>
        </div>
    @endforeach
</div>
