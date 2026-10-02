@extends('layouts.app')
@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Submit or track complaints and feedback about the Online Legal Search service.'])

    <div class="flex-1 p-6 space-y-6">

        {{-- Flash success --}}
        @if(session('success'))
        <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
            <i data-lucide="check-circle-2" class="h-5 w-5 mt-0.5 shrink-0 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

            {{-- Submit Feedback Form --}}
            <div class="xl:col-span-1">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-700">
                            <i data-lucide="message-square-plus" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-900">Submit Feedback</h3>
                            <p class="text-xs text-slate-500">Report a problem or share your experience</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('legal-search-online.admin.feedback.store') }}" class="p-5 space-y-4">
                        @csrf

                        @if($errors->any())
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none"
                                placeholder="Your full name" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none"
                                placeholder="your@email.com" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Phone Number</label>
                            <input type="text" name="phone" value="{{ old('phone') }}"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none"
                                placeholder="e.g. 08012345678">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Reference <span class="text-slate-400">(optional)</span></label>
                            <input type="text" name="reference" value="{{ old('reference') }}"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none"
                                placeholder="e.g. LSOP-XXXXXXXXXXXX">
                            <p class="mt-1 text-[11px] text-slate-400">Provide if your complaint relates to a specific payment.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Subject <span class="text-red-500">*</span></label>
                            <select name="subject"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none" required>
                                <option value="">-- Select subject --</option>
                                <option value="Payment charged but no access" {{ old('subject') === 'Payment charged but no access' ? 'selected' : '' }}>Payment charged but no access</option>
                                <option value="Wrong search results" {{ old('subject') === 'Wrong search results' ? 'selected' : '' }}>Wrong search results</option>
                                <option value="Unable to print report" {{ old('subject') === 'Unable to print report' ? 'selected' : '' }}>Unable to print report</option>
                                <option value="Payment failed but charged" {{ old('subject') === 'Payment failed but charged' ? 'selected' : '' }}>Payment failed but charged</option>
                                <option value="Technical error" {{ old('subject') === 'Technical error' ? 'selected' : '' }}>Technical error</option>
                                <option value="General enquiry" {{ old('subject') === 'General enquiry' ? 'selected' : '' }}>General enquiry</option>
                                <option value="Other" {{ old('subject') === 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Message <span class="text-red-500">*</span></label>
                            <textarea name="message" rows="5"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none resize-none"
                                placeholder="Describe your issue in detail..." required>{{ old('message') }}</textarea>
                        </div>

                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-cyan-700 transition">
                            <i data-lucide="send" class="h-4 w-4"></i>
                            Submit Feedback
                        </button>
                    </form>
                </div>
            </div>

            {{-- Feedback List --}}
            <div class="xl:col-span-2">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-700">
                                <i data-lucide="inbox" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900">{{ $isAdmin ? 'All Submissions' : 'My Submissions' }}</h3>
                                <p class="text-xs text-slate-500">{{ number_format($feedbacks->total()) }} record(s)</p>
                            </div>
                        </div>
                        @if($isAdmin)
                        <span class="inline-flex items-center gap-1 rounded-md bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200">
                            <i data-lucide="shield-check" class="h-3 w-3"></i> Admin View
                        </span>
                        @endif
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($feedbacks as $fb)
                        <div class="p-5 space-y-2" id="fb-{{ $fb->id }}">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-slate-800 text-sm">{{ $fb->subject }}</span>
                                        {!! $fb->statusBadge() !!}
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        {{ $fb->name }}
                                        @if($isAdmin) &mdash; {{ $fb->email }} @endif
                                        &bull; {{ $fb->created_at->format('d M Y, H:i') }}
                                        @if($fb->reference)
                                            &bull; Ref: <span class="font-mono">{{ $fb->reference }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if($isAdmin)
                                <button onclick="openResolveModal({{ $fb->id }}, '{{ $fb->status }}', {{ json_encode($fb->admin_response ?? '') }})"
                                    class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    <i data-lucide="pencil" class="h-3 w-3"></i> Update
                                </button>
                                @endif
                            </div>

                            <p class="text-sm text-slate-600 leading-relaxed bg-slate-50 rounded-md px-3 py-2 border border-slate-100">{{ $fb->message }}</p>

                            @if($fb->admin_response)
                            <div class="flex items-start gap-2 rounded-md border border-cyan-100 bg-cyan-50 px-3 py-2">
                                <i data-lucide="reply" class="h-4 w-4 mt-0.5 text-cyan-600 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] font-bold text-cyan-700 uppercase tracking-wide mb-0.5">Admin Response</p>
                                    <p class="text-xs text-cyan-800">{{ $fb->admin_response }}</p>
                                    @if($fb->resolved_at)
                                        <p class="text-[10px] text-cyan-500 mt-0.5">Resolved {{ $fb->resolved_at->format('d M Y') }}</p>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                        @empty
                        <div class="px-5 py-12 text-center text-slate-400 text-sm">
                            <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-3 opacity-30"></i>
                            <p>No feedback submissions yet.</p>
                        </div>
                        @endforelse
                    </div>

                    @if($feedbacks->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4">
                        {{ $feedbacks->links() }}
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @include('admin.footer')
</div>

{{-- Admin resolve modal --}}
@if($isAdmin)
<div id="resolve-modal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h3 class="font-extrabold text-slate-900">Update Feedback</h3>
            <button onclick="document.getElementById('resolve-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select id="resolve-status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none">
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Admin Response</label>
                <textarea id="resolve-response" rows="4"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-cyan-400 focus:ring-1 focus:ring-cyan-300 outline-none resize-none"
                    placeholder="Provide a response to the user..."></textarea>
            </div>
            <button onclick="submitResolve()"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-700 transition">
                <i data-lucide="save" class="h-4 w-4"></i> Save Update
            </button>
        </div>
    </div>
</div>

<script>
let _resolveId = null;

function openResolveModal(id, status, response) {
    _resolveId = id;
    document.getElementById('resolve-status').value = status;
    document.getElementById('resolve-response').value = response || '';
    document.getElementById('resolve-modal').classList.remove('hidden');
}

function submitResolve() {
    if (!_resolveId) return;
    const status   = document.getElementById('resolve-status').value;
    const response = document.getElementById('resolve-response').value;

    fetch(`/legal-search/online/admin/feedback/${_resolveId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ _method: 'PUT', status, admin_response: response }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('resolve-modal').classList.add('hidden');
            location.reload();
        } else {
            alert(data.message || 'Update failed.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}

// Close modal on backdrop click
document.getElementById('resolve-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
@endif
@endsection
