@extends('layouts.app')

@section('page-title')
    {{ __('Admin Settings - Land Officer Signatures') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Admin Settings - Land Officer Signatures',
        'PageDescription' => 'Manage Land Officers, digital signatures, and verification preferences.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-base font-bold text-slate-800 mb-4">Add Land Officer</h2>
                <form id="land-officer-form" class="grid grid-cols-1 md:grid-cols-2 gap-4" enctype="multipart/form-data">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                        <input type="text" name="name" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Rank</label>
                        <input type="text" name="rank" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Linked User</label>
                        <select name="user_id" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                            <option value="">Select User</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Default Verification Method</label>
                        <select name="notification_type" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                            <option value="email">Email OTP</option>
                            <option value="sms">SMS OTP</option>
                            <option value="password">Password Confirmation</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Digital Signature File</label>
                        <input type="file" name="signature_file" accept="image/*" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-700 transition">Save Land Officer</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-800 mb-4">Configured Land Officers</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                            <th class="py-2">Name</th>
                            <th class="py-2">Rank</th>
                            <th class="py-2">Method</th>
                            <th class="py-2">Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($officers as $officer)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $officer->name }}</td>
                                <td class="py-2">{{ $officer->rank ?? '—' }}</td>
                                <td class="py-2">{{ strtoupper($officer->notification_type ?? 'email') }}</td>
                                <td class="py-2">
                                    @if($officer->signature_file)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($officer->signature_file) }}" alt="signature" class="h-10 object-contain">
                                    @else
                                        <span class="text-slate-400">Not set</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@push('scripts')
<script>
document.getElementById('land-officer-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    fetch('{{ route('setting.land-officers.store') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            if (window.Swal) Swal.fire('Success', data.message, 'success').then(function () { location.reload(); });
            else location.reload();
        } else {
            if (window.Swal) Swal.fire('Error', data.message || 'Request failed', 'error');
        }
    })
    .catch(function () {
        if (window.Swal) Swal.fire('Error', 'Request failed', 'error');
    });
});
</script>
@endpush
