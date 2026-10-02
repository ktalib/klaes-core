{{--
    Read-back of a submitted Statutory Right of Occupancy form.

    Expects: $sections (from SroFormSchema), $answers (the stored form_data),
    and optionally $typeLabel.

    Shared by the applicant's status page and the staff console. Those two live
    under different layouts — the portal defines the LAAS colour tokens, the
    staff admin shell does not — so this partial deliberately uses plain Tailwind
    neutrals with dark: variants, which resolve correctly in both.

    Empty answers are skipped: a page of blank rows tells nobody anything.
--}}
@php
    use App\Support\Laas\SroFormSchema;

    $parts = SroFormSchema::addressParts();

    /** Join an address block into one readable line. */
    $addressLine = function (string $prefix) use ($answers, $parts) {
        $bits = [];
        foreach (array_keys($parts) as $part) {
            $value = trim((string) ($answers[$prefix . $part] ?? ''));
            if ($value !== '') {
                $bits[] = $value;
            }
        }
        return $bits ? implode(', ', $bits) : null;
    };
@endphp

@if(!empty($typeLabel))
    <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 dark:bg-gray-700 dark:text-gray-200">
        <i data-lucide="file-text" class="h-3.5 w-3.5" aria-hidden="true"></i>
        Statutory Right of Occupancy — {{ $typeLabel }}
    </p>
@endif

<div class="space-y-7">
    @foreach($sections as $section)
        @php
            // Work out whether this section has anything at all to show, so an
            // untouched section is dropped rather than printed as a bare heading.
            $rendered = [];

            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    $line = $addressLine($field['prefix']);
                    if ($line) {
                        $rendered[] = ['label' => $field['label'], 'value' => $line, 'wide' => true];
                    }
                    continue;
                }

                if ($field['type'] === 'prev_allocations') {
                    continue; // Rendered as its own table below.
                }

                $value = trim((string) ($answers[$field['key']] ?? ''));
                if ($value !== '') {
                    $rendered[] = [
                        'label' => $field['label'],
                        'value' => $value,
                        'wide'  => $field['type'] === 'textarea',
                    ];
                }
            }

            $hasPrevRows = false;
            $prevRows = [];
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'prev_allocations') {
                    $prevRows = json_decode((string) ($answers['prev_allocation_details'] ?? ''), true) ?: [];
                    $hasPrevRows = (bool) $prevRows;
                }
            }
        @endphp

        @continue(empty($rendered) && !$hasPrevRows)

        <section>
            <h3 class="mb-3 flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">
                <i data-lucide="{{ $section['icon'] }}" class="h-3.5 w-3.5" aria-hidden="true"></i>
                {{ $section['title'] }}
            </h3>

            @if($rendered)
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach($rendered as $row)
                        <div class="{{ $row['wide'] ? 'sm:col-span-2' : '' }}">
                            <dt class="text-xs font-medium text-slate-500 dark:text-gray-400">{!! $row['label'] !!}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-900 dark:text-gray-100">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if($hasPrevRows)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">
                                <th class="pb-2 pr-4">Plot No.</th>
                                <th class="pb-2 pr-4">Location</th>
                                <th class="pb-2">Cert. of Occupancy No.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-gray-700">
                            @foreach($prevRows as $row)
                                <tr class="text-slate-900 dark:text-gray-100">
                                    <td class="py-2 pr-4">{{ $row['plot_no'] ?: '—' }}</td>
                                    <td class="py-2 pr-4">{{ $row['location'] ?: '—' }}</td>
                                    <td class="py-2">{{ $row['cert_no'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</div>
