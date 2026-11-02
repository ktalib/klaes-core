{{-- Indexing Pay (Kunle, 2026-11-01): the indexing piece-rate card, saved by
     SystemAdmin\IndexingPaySettingsController and read back by App\Services\IndexingPay\IndexingPaySettings.
     Each save is a version that applies from its date forward; earlier days keep the card they were earned on.
     In-transit File (ITF) is kept quiet by decision and is not on this form. --}}
@php
    $ip = $indexingPay;
    $c = $ip['current'];
    $naira = fn ($v) => '₦' . number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 2);
    $old = fn ($key, $default) => old($key, $default);
    $baseFields = [
        'regular' => ['Regular Land File', 'Base fee per Regular Land File indexed'],
        'sltr' => ['SLTR File', 'Base fee per SLTR File indexed'],
        'kangis_land' => ['KANGIS Land File', 'A land file inside a KANGIS bundle'],
        'kangis' => ['KANGIS File', 'Base fee per KANGIS File indexed'],
    ];
    $rateFields = [
        'trans' => ['Transaction', 'Per transaction record captured'],
        'gr' => ['Ground Rent', 'Per Ground Rent record captured'],
        'bb' => ['Bill Balance', 'Per Bill Balance record captured'],
    ];
@endphp

@unless($ip['ready'])
    <div class="iw-card" style="padding:18px 22px">The Indexing Pay table has not been created yet.</div>
@else
<div class="grid gap-5" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
    <form method="POST" action="{{ route('configurable-entries.indexing-pay.save') }}" class="iw-card">
        @csrf
        <div class="iw-card-head" style="align-items:center">
            <div>
                <div class="iw-card-title"><span class="iw-icon-tile green"><i data-lucide="wallet" class="h-5 w-5"></i></span> Indexing Pay — Rate Card</div>
                <div class="iw-card-sub">Pay = base fee per file indexed + a rate per record captured. A change applies from the date you choose; days already earned keep their rates.</div>
            </div>
        </div>

        @if($errors->any())
            <div class="mx-5 mb-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <div class="px-5 pb-5 space-y-6">
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-1">Base fee per file (₦) <span class="ce-muted font-normal">— paid to the user who indexed the file</span></h4>
                <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                    @foreach($baseFields as $key => [$label, $hint])
                        <div class="iw-field">
                            <label class="iw-label">{{ $label }} <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0" name="base[{{ $key }}]" value="{{ $old('base.' . $key, $c['base'][$key]) }}" required class="iw-input">
                            <p class="ce-muted mt-1 text-xs">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="ce-muted text-xs mt-2"><i data-lucide="moon" class="inline h-3 w-3"></i> In-transit File (ITF) — kept quiet for now: its fee ({{ $naira($c['base']['itf']) }}) is stored but not used, and ITF files are paid as Regular.</p>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-1">Rate per record (₦) <span class="ce-muted font-normal">— paid to the user who captured the record, on the day captured</span></h4>
                <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                    @foreach($rateFields as $key => [$label, $hint])
                        <div class="iw-field">
                            <label class="iw-label">{{ $label }} <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0" name="rates[{{ $key }}]" value="{{ $old('rates.' . $key, $c['rates'][$key]) }}" required class="iw-input">
                            <p class="ce-muted mt-1 text-xs">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
                <label class="flex items-center gap-3 text-sm mt-3"><input type="hidden" name="sltr_pays_gr_bb" value="0"><span class="ce-switch"><input type="checkbox" name="sltr_pays_gr_bb" value="1" @checked($old('sltr_pays_gr_bb', $c['sltr_pays_gr_bb']))><span></span></span> Pay Ground Rent / Bill Balance on SLTR files <span class="ce-muted">(off on the approved card: "GR/BB not applicable")</span></label>
            </div>

            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                <div class="iw-field"><label class="iw-label">Programme starts <span class="req">*</span></label><input type="date" name="start_date" value="{{ $old('start_date', $c['start_date']) }}" required class="iw-input"></div>
                <div class="iw-field"><label class="iw-label">Programme ends <span class="req">*</span></label><input type="date" name="end_date" value="{{ $old('end_date', $c['end_date']) }}" required class="iw-input"></div>
                <div class="iw-field"><label class="iw-label">Daily file target <span class="req">*</span></label><input type="number" min="0" name="daily_file_target" value="{{ $old('daily_file_target', $c['daily_file_target']) }}" required class="iw-input"><p class="ce-muted mt-1 text-xs">Progress bar only</p></div>
                <div class="iw-field"><label class="iw-label">Monthly target (₦) <span class="req">*</span></label><input type="number" min="0" step="0.01" name="monthly_target" value="{{ $old('monthly_target', $c['monthly_target']) }}" required class="iw-input"><p class="ce-muted mt-1 text-xs">Progress bar only</p></div>
            </div>

            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <div class="iw-field">
                    <label class="iw-label">Staff type(s)</label>
                    <input type="text" name="eligible_staff_types" value="{{ $old('eligible_staff_types', implode(', ', $c['eligible_staff_types'] ?? [])) }}" class="iw-input" placeholder="e.g. MDC, MDCM">
                </div>
                <div class="iw-field">
                    <label class="iw-label">…holding module(s) <span class="req">*</span></label>
                    <input type="text" name="eligible_modules" value="{{ $old('eligible_modules', implode(', ', $c['eligible_modules'])) }}" required class="iw-input">
                </div>
                <div class="iw-field">
                    <label class="iw-label">…or at work station(s)</label>
                    <input type="text" name="eligible_work_stations" value="{{ $old('eligible_work_stations', implode(', ', $c['eligible_work_stations'] ?? [])) }}" class="iw-input" placeholder="e.g. File Indexing">
                </div>
            </div>
            <p class="ce-muted -mt-4 text-xs">A user earns and sees their progress when their staff type is one of these (empty = any) <b>and</b> they hold one of the modules <b>or</b> work at one of the work stations (comma separated). Today that is <strong>{{ number_format($ip['eligibleCount']) }}</strong> user(s).</p>

            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Show progress & earnings</h4>
                <div class="flex flex-wrap gap-6 text-sm">
                    @foreach(['create_page' => 'On the Create File Indexing page', 'profile' => 'On the profile page', 'header' => 'In the page header'] as $key => $label)
                        <label class="flex items-center gap-3"><input type="hidden" name="show[{{ $key }}]" value="0"><span class="ce-switch"><input type="checkbox" name="show[{{ $key }}]" value="1" @checked($old('show.' . $key, $c['show'][$key]))><span></span></span> {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3 items-end" style="grid-template-columns:200px minmax(0,1fr) auto">
                <div class="iw-field"><label class="iw-label">Applies from <span class="req">*</span></label><input type="date" name="effective_from" value="{{ $old('effective_from', now()->toDateString()) }}" required class="iw-input"></div>
                <div class="iw-field"><label class="iw-label">Note</label><input type="text" name="note" maxlength="500" value="{{ old('note') }}" class="iw-input" placeholder="e.g. Approved by Kunle, 01/11/2026"></div>
                <button class="iw-btn iw-btn-success" onclick="return confirm('Save this rate card? It applies from the chosen date onward.')"><i data-lucide="save" class="h-4 w-4"></i> Save</button>
            </div>
        </div>
    </form>

    <div class="space-y-5">
        <div class="iw-card">
            <div class="iw-card-head"><div><div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="calculator" class="h-5 w-5"></i></span> In force today</div><div class="iw-card-sub">How a file is paid under the current card.</div></div></div>
            <div class="px-5 pb-5 text-sm space-y-2">
                @foreach($baseFields as $key => [$label])
                    <div class="flex justify-between border-b border-gray-100 pb-1"><span>{{ $label }}</span><span class="font-mono">{{ $naira($c['base'][$key]) }} + records</span></div>
                @endforeach
                <div class="flex justify-between pt-1"><span>Transaction / Ground Rent / Bill Balance</span><span class="font-mono">{{ $naira($c['rates']['trans']) }} / {{ $naira($c['rates']['gr']) }} / {{ $naira($c['rates']['bb']) }}</span></div>
                <div class="rounded-md bg-green-50 border border-green-100 px-3 py-2 mt-3 text-green-900">
                    Example: a Regular file with 2 GR, 2 BB and 3 transactions =
                    {{ $naira($c['base']['regular']) }} + {{ $naira(2 * $c['rates']['gr']) }} + {{ $naira(2 * $c['rates']['bb']) }} + {{ $naira(3 * $c['rates']['trans']) }}
                    = <strong>{{ $naira($c['base']['regular'] + 2 * $c['rates']['gr'] + 2 * $c['rates']['bb'] + 3 * $c['rates']['trans']) }}</strong>
                </div>
                <p class="ce-muted text-xs">Programme: {{ \Carbon\Carbon::parse($c['start_date'])->format('j M Y') }} – {{ \Carbon\Carbon::parse($c['end_date'])->format('j M Y') }}, weekends included.
                    <a href="{{ route('indexing-earnings.sheet') }}" class="text-blue-600 hover:underline">Open the Indexing Earnings sheet →</a></p>
            </div>
        </div>

        <div class="iw-card">
            <div class="iw-card-head"><div><div class="iw-card-title"><span class="iw-icon-tile yellow"><i data-lucide="history" class="h-5 w-5"></i></span> Versions</div><div class="iw-card-sub">Each applies from its date until the next.</div></div></div>
            <div class="overflow-x-auto">
                <table class="ce-table">
                    <thead><tr><th style="width:50px">S/N</th><th>Applies from</th><th>Regular / SLTR / KG Land / KANGIS</th><th>Trans / GR / BB</th><th>Saved by</th></tr></thead>
                    <tbody>
                        @forelse($ip['versions'] as $v)
                            <tr>
                                <td class="ce-muted">{{ $loop->iteration }}</td>
                                <td class="font-medium">{{ \Carbon\Carbon::parse($v->effective_from)->format('j M Y') }}@if($v->note)<div class="ce-muted text-xs">{{ $v->note }}</div>@endif</td>
                                <td class="font-mono text-xs">{{ $naira($v->values['base']['regular']) }} / {{ $naira($v->values['base']['sltr']) }} / {{ $naira($v->values['base']['kangis_land']) }} / {{ $naira($v->values['base']['kangis']) }}</td>
                                <td class="font-mono text-xs">{{ $naira($v->values['rates']['trans']) }} / {{ $naira($v->values['rates']['gr']) }} / {{ $naira($v->values['rates']['bb']) }}</td>
                                <td class="text-xs">{{ $ip['userNames'][$v->updated_by ?? $v->created_by] ?? '—' }}<div class="ce-muted">{{ \Carbon\Carbon::parse($v->updated_at ?? $v->created_at)->format('j M Y H:i') }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ce-muted" style="padding:14px">None saved yet — the approved card (01/11/2026) applies. Saving creates the first version.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endunless
