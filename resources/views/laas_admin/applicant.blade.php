@extends('layouts.app')

@section('page-title')
    {{ __('LAAS Portal — Applicant') }}
@endsection

@section('content')
@php
    use App\Models\Laas\LaasApplicant;
    use App\Models\Laas\LaasApplication;

    $sysPhone = LaasApplicant::isSystemPhone($applicant->phone);
    $sysEmail = str_ends_with((string) $applicant->email, '@' . config('laas.commissioning_accounts.placeholder_email_domain', 'portal.klaes.local'));
    $isActive = $applicant->status === 'active';
    $pending  = $applicant->contact_status === LaasApplicant::CONTACT_PENDING;
    $reasonLabels = ['no_phone' => 'No phone', 'no_email' => 'No email', 'shared_phone' => 'Shared phone'];
    $reasons  = collect(array_filter(explode(',', (string) $applicant->contact_flag_reason)))->map(fn ($r) => $reasonLabels[$r] ?? $r)->implode(', ');

    $headerActions = [
        ['label' => 'Update contact', 'icon' => 'phone', 'onclick' => "document.getElementById('laasContactModal').classList.remove('hidden')"],
        ['label' => 'Issue sign-in slip', 'icon' => 'printer', 'post' => route('laas-admin.applicants.slip', $applicant->id), 'target' => '_blank',
         'confirm' => 'This resets ' . $applicant->name . "'s password to a new temporary one and opens a slip to print. Continue?"],
        ['label' => $isActive ? 'Suspend' : 'Restore', 'icon' => $isActive ? 'pause-circle' : 'play-circle', 'post' => route('laas-admin.applicants.toggle', $applicant->id)],
    ];
    $detail = fn ($label, $value) => ['label' => $label, 'value' => $value];
@endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS Portal — Applicant',
        'PageDescription' => 'A portal account and every file on it.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('laas-admin.applicants') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Applicants
            </a>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Header --}}
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-900 text-lg font-bold text-white">
                        {{ mb_strtoupper(mb_substr(trim((string) $applicant->name), 0, 1)) ?: '?' }}
                    </span>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $applicant->name }}</h1>
                        <p class="font-mono text-sm text-slate-500">{{ $applicant->username ?: '—' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $isActive ? 'Active' : 'Suspended' }}
                            </span>
                            @if($pending)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">
                                    <i data-lucide="flag" class="h-3 w-3"></i> Contact pending: {{ $reasons }}
                                </span>
                            @endif
                            @if($applicant->must_change_password)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">Has not set own password</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1">
                    <span class="pl-1 text-xs font-semibold text-slate-500">Actions</span>
                    @include('laas_admin.partials._row_actions', ['items' => $headerActions])
                </div>
            </div>

            <div class="mb-6 grid gap-6 lg:grid-cols-3">
                {{-- Details --}}
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Applicant details</h2>
                    <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        @foreach([
                            $detail('Full name', $applicant->name),
                            $detail('Username', $applicant->username),
                            $detail('Phone', $sysPhone ? 'System number (' . $applicant->phone . '), not yet given' : $applicant->phone),
                            $detail('Email', $sysEmail ? 'System email, not yet given' : $applicant->email),
                            $detail('Phone verified', $applicant->phone_verified_at?->format('j M Y')),
                            $detail('NIN', $applicant->nin),
                            $detail('Address', $applicant->address),
                            $detail('Account opened by', $applicant->account_origin === LaasApplicant::ORIGIN_COMMISSIONING ? 'File Commissioning' : 'Self-registered on the portal'),
                            $detail('Registered', $applicant->created_at?->format('j M Y, g:i a')),
                            $detail('Last sign-in', $applicant->last_login_at?->format('j M Y, g:i a') ?? 'Never'),
                        ] as $item)
                            <div>
                                <dt class="text-xs font-semibold text-slate-500">{{ $item['label'] }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-900 {{ in_array($item['label'], ['Username', 'Phone', 'NIN'], true) ? 'font-mono' : '' }}">{{ $item['value'] ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Numbers --}}
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['Files', $stats['files'], 'folder'],
                        ['RoFO signed', $stats['rofo_signed'], 'badge-check'],
                        ['Retired', $stats['retired'], 'archive'],
                        ['Transferred out', $stats['transferred'], 'arrow-right-left'],
                    ] as [$label, $value, $icon])
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <i data-lucide="{{ $icon }}" class="h-4 w-4 text-slate-400"></i>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($value) }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Files --}}
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-900">Files</h2>
                <form method="GET" action="{{ route('laas-admin.applicants.show', $applicant->id) }}" class="flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="File number, reference, plot or location…"
                           class="min-w-[260px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                    @if($search !== '')
                        <a href="{{ route('laas-admin.applicants.show', $applicant->id) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 w-12">S/N</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">File Number</th>
                                <th class="px-4 py-3">Plot No.</th>
                                <th class="px-4 py-3">District</th>
                                <th class="px-4 py-3">LGA</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3">Land Use</th>
                                <th class="px-4 py-3">Stage</th>
                                <th class="px-4 py-3">File status</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applications as $application)
                                @php $p = $property[$application->mls_file_no_id] ?? null; @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-500">{{ $applications->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900">{{ $application->reference_no }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $application->file_number ?: '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700">{{ $application->plot_no ?: ($p->plot_no ?? null) ?: '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700">{{ $p->district ?? null ?: '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700">{{ $p->lga ?? null ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-xs truncate text-xs text-slate-700" title="{{ $application->location }}">{{ $application->location ?: '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-900">{{ $application->land_use ? $landUses->normalize($application->land_use) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                            @if($application->stage === LaasApplication::STAGE_REJECTED) bg-red-100 text-red-700
                                            @elseif($application->stage === LaasApplication::STAGE_ROFO_SIGNED) bg-green-100 text-green-800
                                            @elseif($application->stage === LaasApplication::STAGE_SUBMITTED) bg-blue-100 text-blue-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ LaasApplication::label($application->stage) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($application->isTransferredOut())
                                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700">Transferred</span>
                                        @elseif($application->isRetired())
                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Retired</span>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-[11px] font-semibold text-green-800">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $application->submitted_at?->format('j M Y') ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @include('laas_admin.partials._row_actions', ['items' => [
                                            ['label' => 'Open application', 'icon' => 'file-text', 'url' => route('laas-admin.show', $application->id)],
                                        ]])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-4 py-12 text-center text-slate-500">No files on this account{{ $search !== '' ? ' match this search' : '' }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">{{ $applications->links() }}</div>
        </div>
    </div>
</div>

{{-- ---------- Update contact ---------- --}}
<div id="laasContactModal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('laasContactModal').classList.add('hidden')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">Update contact details</h2>
                <p class="text-xs text-slate-500">{{ $applicant->name }}</p>
            </div>
            <form method="POST" action="{{ route('laas-admin.applicants.contact', $applicant->id) }}" class="space-y-4 px-6 py-5">
                @csrf
                <div>
                    <label for="contact_phone" class="block text-xs font-bold text-slate-700">Phone number (the holder's own)</label>
                    <input id="contact_phone" name="phone" type="text" inputmode="tel" autocomplete="off" placeholder="080xxxxxxxx"
                           value="{{ $sysPhone ? '' : $applicant->phone }}"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                </div>
                <div>
                    <label for="contact_email" class="block text-xs font-bold text-slate-700">Email (optional)</label>
                    <input id="contact_email" name="email" type="email" autocomplete="off" value="{{ $sysEmail ? '' : $applicant->email }}"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="hidden" name="text_signin" value="0">
                    <input type="checkbox" name="text_signin" value="1" checked class="mt-0.5">
                    <span>Text new sign-in details to this phone (resets the password to a temporary one)</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('laasContactModal').classList.add('hidden')"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.getElementById('laasContactModal').classList.add('hidden');
    });
</script>
@endsection
