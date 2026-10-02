@extends('layouts.app')
@section('page-title', $PageTitle)

{{--
    Identify your Customer (IYC) — approver view.

    SCOPE: the name check below establishes only that the name the applicant typed
    matches text OCR read off the document they uploaded. It is NOT evidence that
    the document is genuine, unaltered, or presented by its rightful holder. Judge
    the document yourself; the score is an aid, not a finding.
--}}
@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', [
        'PageTitle' => $PageTitle,
        'PageDescription' => 'Identification submitted with this Online Legal Search request.',
    ])

    <div class="flex-1 p-6 space-y-6">

        <div>
            <a href="{{ route('legal-search-online.admin.requests', ['highlight' => $searchRequest->id]) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                &larr; Back to requests
            </a>
        </div>

        @php
            $statusStyles = [
                'verified' => ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-200', 'Verified'],
                'review'   => ['bg-amber-50', 'text-amber-800', 'ring-amber-200', 'Needs review'],
                'failed'   => ['bg-red-50', 'text-red-700', 'ring-red-200', 'Not verified'],
                'pending'  => ['bg-slate-100', 'text-slate-700', 'ring-slate-200', 'Pending'],
            ];
            $vs = $statusStyles[$verification->id_verification_status] ?? $statusStyles['pending'];

            $barStyles = [
                'matched'     => ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-200', 'Confirmed'],
                'unconfirmed' => ['bg-slate-100', 'text-slate-700', 'ring-slate-200', 'Not confirmed'],
                'rejected'    => ['bg-red-50', 'text-red-700', 'ring-red-200', 'Rejected by the roll'],
            ];
            $bs = $barStyles[$verification->bar_number_status] ?? null;
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Applicant --}}
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">Applicant</h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $vs[0] }} {{ $vs[1] }} {{ $vs[2] }}">
                        Name check: {{ $vs[3] }}
                        @if($verification->id_name_match_score !== null)
                            · {{ (int) $verification->id_name_match_score }}%
                        @endif
                    </span>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Customer type</dt>
                        <dd class="font-semibold text-slate-800">{{ $verification->customerTypeLabel() }}</dd>
                    </div>

                    @if($verification->isLawyer())
                        <div class="flex justify-between gap-6 px-5 py-3">
                            <dt class="text-slate-500">Call-to-Bar number</dt>
                            <dd class="font-semibold text-slate-800">{{ $verification->call_to_bar_number ?: '—' }}</dd>
                        </div>
                        @if($bs)
                            <div class="flex justify-between gap-6 px-5 py-3">
                                <dt class="text-slate-500">Call-to-Bar check</dt>
                                <dd>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $bs[0] }} {{ $bs[1] }} {{ $bs[2] }}">{{ $bs[3] }}</span>
                                </dd>
                            </div>
                        @endif
                    @endif

                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Full name</dt>
                        <dd class="font-semibold text-slate-800">{{ $verification->applicant_full_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Phone number</dt>
                        <dd class="font-semibold text-slate-800">{{ $verification->applicant_phone }}</dd>
                    </div>
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Address</dt>
                        <dd class="font-semibold text-slate-800 text-right">{{ $verification->applicant_address }}</dd>
                    </div>
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-semibold text-slate-800">{{ $verification->requester_email ?: $searchRequest->requester_email }}</dd>
                    </div>
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Means of identification</dt>
                        <dd class="font-semibold text-slate-800 text-right">{{ $verification->identificationLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-6 px-5 py-3">
                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="text-slate-600">{{ optional($verification->created_at)->format('d M Y, g:i A') ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Document --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-800">Uploaded identification</h2>
                </div>
                <div class="p-5 space-y-3">
                    @if($verification->id_front_path)
                        {{-- Streamed through the approver-gated route; the storage path is
                             never rendered. A plain broken-image icon on failure tells nobody
                             anything useful, so a JS probe fetches the same URL first and shows
                             the real HTTP status (403 not-an-approver, 404 file gone, 500 disk
                             misconfigured) instead of leaving this silent. --}}
                        <div id="idDocFront" data-doc-url="{{ route('legal-search-online.admin.verifications.document', ['id' => $verification->id, 'side' => 'front'], false) }}">
                            <a href="{{ route('legal-search-online.admin.verifications.document', ['id' => $verification->id, 'side' => 'front'], false) }}"
                               target="_blank" rel="noopener"
                               class="block overflow-hidden rounded-lg border border-slate-200 hover:ring-2 hover:ring-blue-300">
                                <img src="{{ route('legal-search-online.admin.verifications.document', ['id' => $verification->id, 'side' => 'front'], false) }}"
                                     alt="Uploaded identification"
                                     class="w-full h-auto object-contain bg-slate-50">
                            </a>
                            <p class="text-xs text-slate-500 mt-1">Click to open full size in a new tab.</p>
                        </div>
                        <div id="idDocFrontError" class="hidden rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"></div>
                    @else
                        <p class="text-sm text-slate-500">No document is on file for this request.</p>
                    @endif

                    @if($verification->id_back_path)
                        <a href="{{ route('legal-search-online.admin.verifications.document', ['id' => $verification->id, 'side' => 'back'], false) }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:underline">
                            View reverse side
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- What this check does and does not establish. Stated on the screen the
             approver decides from, not only in the code. --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <strong>This is an ID name check only.</strong>
            It confirms that the name entered matches text found on the uploaded document.
            It is <strong>not</strong> evidence that the document is genuine, that it has not been
            altered, that the uploader is the person it depicts, or that the ID number is valid
            with the issuing authority. Satisfy yourself of the document before approving.
        </div>

        {{-- Files covered by the same payment, so a multi-file basket is visible. --}}
        @if($siblings->count() > 1)
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-800">
                        This identification covers {{ $siblings->count() }} files on one payment
                    </h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach($siblings as $sib)
                            <tr class="{{ $sib->id === $searchRequest->id ? 'bg-amber-50' : '' }}">
                                <td class="px-5 py-3 font-bold text-slate-900">{{ $sib->request_no }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-700">{{ $sib->file_number }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ ucfirst($sib->status) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if($sib->id !== $searchRequest->id)
                                        <a href="{{ route('legal-search-online.admin.requests.preview', $sib->id) }}"
                                           target="_blank" rel="noopener"
                                           class="text-sm font-semibold text-blue-700 hover:underline">Preview report</a>
                                    @else
                                        <span class="text-xs font-semibold text-slate-400">Viewing</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    var wrap = document.getElementById('idDocFront');
    if (!wrap) { return; }

    var url = wrap.getAttribute('data-doc-url');
    var errorBox = document.getElementById('idDocFrontError');

    // A bare <img> gives no way to tell "not authorised" from "file missing" from
    // "server misconfigured" apart from a generic broken-image icon. Fetching the
    // same URL directly surfaces the actual HTTP status, which is what actually
    // needs diagnosing when this fails.
    fetch(url, { credentials: 'same-origin' }).then(function (res) {
        if (res.ok) { return; }

        wrap.classList.add('hidden');
        errorBox.classList.remove('hidden');

        var reason;
        if (res.status === 401) {
            reason = 'You are not signed in, or your session has expired. Reload this page after signing in again.';
        } else if (res.status === 403) {
            reason = 'Your account is not recognised as a Director / Deputy Director (or Super Admin), so viewing identification documents is blocked for you specifically — the rest of this page loaded because that check is separate. Ask an administrator to confirm your account's rank/role.';
        } else if (res.status === 404) {
            reason = 'The document could not be found on the server. It may have been removed, or the stored path no longer matches a file on disk.';
        } else {
            reason = 'The server returned an unexpected error (HTTP ' + res.status + ') while loading this document. Check storage/logs/laravel.log for the underlying exception.';
        }

        errorBox.innerHTML = '<strong>Could not load this document (HTTP ' + res.status + ').</strong><br>' + reason;
    }).catch(function () {
        wrap.classList.add('hidden');
        errorBox.classList.remove('hidden');
        errorBox.innerHTML = '<strong>Could not reach the server to load this document.</strong> Check your network connection and try again.';
    });
})();
</script>
@endpush
@endsection
