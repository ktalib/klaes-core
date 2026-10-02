@extends('layouts.app')
@section('page-title')
    {{ __('Configurable Entries') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', ['PageTitle' => 'Configurable Entries', 'PageDescription' => 'System Admin · revenue items, bill formulas, departments & roles, file numbers, instrument volumes, pipeline, land rates'])
    @include('instrument_workflow.partials.styles')
    @include('instrument_workflow.partials.ajax')
    @include('instrument_workflow.partials.modal_styles')

    <div class="iw-page space-y-5" data-iw-ajax-root style="max-width:1400px">
        @include('instrument_workflow.partials.flash')

        <div class="flex items-center justify-between gap-3" style="margin-bottom:0">
            <div class="iw-crumbs" style="margin-bottom:0">
                <span>System Admin</span><span>/</span><span class="text-gray-900">Configurable Entries</span>
            </div>
            <button type="button" onclick="window.location.reload()" class="iw-btn iw-btn-secondary" title="Reload Configurable Entries">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
            </button>
        </div>

        <nav class="ce-tabs" aria-label="Configurable entries">
            @foreach($tabs as $key => $meta)
                <a href="{{ isset($meta['route']) ? route($meta['route']) : route('configurable-entries.index', ['tab' => $key]) }}"
                    class="ce-tab {{ $tab === $key ? 'active' : '' }}"
                    style="--ce-accent: {{ $meta['accent'] }}; --ce-tint: {{ $meta['tint'] }}">
                    <i data-lucide="{{ $meta['icon'] }}" class="h-4 w-4"></i>
                    <span>{{ $meta['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @include('system_admin.configurable_entries.partials.' . $tab)
    </div>
</div>

<style>
    /* Each tab carries its own --ce-accent / --ce-tint (ConfigurableEntriesController::TABS),
       so one rule set colours all ten. The icon is a Lucide stroke, which follows
       `color`, so it takes the accent without a rule of its own. */
    .ce-tabs { display: flex; gap: 6px; flex-wrap: wrap; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 6px; }
    .ce-tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 10px; font-size: 14px; font-weight: 600;
              color: var(--ce-accent, #4b5563); background: var(--ce-tint, transparent);
              border: 1px solid transparent; transition: background .15s, border-color .15s, box-shadow .15s; }
    .ce-tab:hover { border-color: var(--ce-accent, #d1d5db); }
    .ce-tab.active { background: var(--ce-accent, #2563eb); border-color: var(--ce-accent, #2563eb); color: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .25); }
    .ce-subtabs { display: flex; flex-wrap: wrap; gap: 8px; padding: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
    .ce-subtab { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border: 1px solid transparent; border-radius: 8px; color: #475569; font-size: 13px; font-weight: 600; transition: background .15s, color .15s, border-color .15s; }
    .ce-subtab:hover { border-color: #94a3b8; background: #fff; }
    .ce-subtab.active { color: #047857; background: #fff; border-color: #6ee7b7; box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }
    .ce-subtab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #e2e8f0; color: #475569; font-size: 11px; }
    .ce-subtab.active .ce-subtab-count { background: #d1fae5; color: #047857; }
    .ce-tiles { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .ce-table { min-width: 100%; font-size: 14px; }
    .ce-table thead tr { background: #f9fafb; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
    .ce-table th { padding: 11px 16px; font-weight: 600; white-space: nowrap; }
    .ce-table td { padding: 11px 16px; border-top: 1px solid #f3f4f6; vertical-align: middle; }
    .ce-table tbody tr:hover { background: #fafafa; }
    .ce-switch { position: relative; display: inline-flex; width: 40px; height: 22px; flex: none; }
    .ce-switch input { opacity: 0; width: 0; height: 0; }
    .ce-switch span { position: absolute; inset: 0; background: #d1d5db; border-radius: 99px; transition: background .15s; cursor: pointer; }
    .ce-switch span::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 99px; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
    .ce-switch input:checked + span { background: #16a34a; }
    .ce-switch input:checked + span::after { transform: translateX(18px); }
    .ce-pill { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #f3f4f6; color: #4b5563; white-space: nowrap; }
    .ce-pill.green { background: #dcfce7; color: #166534; }
    .ce-pill.red { background: #fee2e2; color: #991b1b; }
    .ce-pill.blue { background: #dbeafe; color: #1e40af; }
    .ce-pill.yellow { background: #fef9c3; color: #854d0e; }
    .ce-muted { font-size: 12px; color: #6b7280; }
    .ce-select { width: 100%; font-size: 13px; border: 1px solid #d1d5db; border-radius: 9px; padding: 7px 9px; background: #fff; }
</style>
@endsection
