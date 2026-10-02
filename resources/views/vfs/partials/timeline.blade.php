{{--
    Status Timeline: structural (SPU/APU) and legal (TSU) events in ONE chronological
    feed. That interleaving is the point of the unified view — separate lists would
    hide the order in which things actually happened to the parcel.
--}}

<div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-bold m-0 mb-1">Status Timeline</h2>
    <p class="text-xs text-gray-500 m-0 mb-5">Every recorded event against this folio, oldest first.</p>

    <div class="vfs-timeline">
        <div class="vfs-timeline-line"></div>

        @foreach($timeline as $event)
            @php
                $chip = $event['presentation']['chip'] ?? ($event['kind'] === 'SPU' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700');
                $nodeColour = match (true) {
                    $event['kind'] === 'ORIGIN' => '#15803d',
                    $event['kind'] === 'SPU' => '#0e7490',
                    ($event['state'] ?? null) === 'LITIGATION_HOLD' => '#dc2626',
                    ($event['state'] ?? null) === 'REVOKED' => '#b91c1c',
                    ($event['state'] ?? null) === 'CANCELLED' => '#1f2937',
                    ($event['state'] ?? null) === 'AMENDED' => '#2563eb',
                    default => '#6b7280',
                };
            @endphp

            <div class="relative" style="margin-bottom: 14px;">
                <div class="vfs-timeline-node" style="border-color: {{ $nodeColour }};"></div>

                <div class="border border-gray-200 rounded-lg px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500" style="width: 80px;">
                            {{ $event['date'] ? \Illuminate\Support\Carbon::parse($event['date'])->format('Y-m-d') : '—' }}
                        </span>

                        <span class="text-xs font-bold px-2 py-0.5 rounded {{ $chip }}">
                            {{ $event['kind'] }}
                        </span>

                        <span class="text-sm font-semibold">{{ $event['type'] }}</span>

                        @if($event['derived'])
                            {{-- The frameworks' own timeline opens with an allotment, but their
                                 trigger_event_type enum admits only SPU/APU/TSU, so there is
                                 nowhere to store one. Derived from the indexing record — labelled
                                 so nobody reads it as a recorded event. --}}
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600" title="Derived from the indexing record, not a recorded event">derived</span>
                        @endif
                    </div>

                    <div class="text-xs text-gray-600 mt-1" style="padding-left: 88px;">
                        {{ collect([
                            $event['reference'],
                            $event['authority'],
                            $event['initiated_by'] ? 'initiated by ' . $event['initiated_by'] : null,
                            $event['see_fileno'] ? 'see ' . $event['see_fileno'] : null,
                        ])->filter()->implode(' · ') ?: '—' }}
                    </div>

                    @if($event['reason'] || $event['remark'])
                        <div class="text-xs text-gray-500 mt-1" style="padding-left: 88px;">
                            {{ \Illuminate\Support\Str::limit(trim(($event['reason'] ?? '') . ' ' . ($event['remark'] ?? '')), 240) }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-600 leading-relaxed bg-gray-50 border border-gray-200 rounded-md px-3 py-3 mt-4 mb-0">
        <strong class="text-gray-700">What this release cannot show:</strong>
        court, case number, parties and injunction type need a dedicated litigation table, which does not exist yet — today those details live in the free-text reason above. Reversals are not linked either, so a resolved litigation and a live one look the same here.
    </p>
</div>
