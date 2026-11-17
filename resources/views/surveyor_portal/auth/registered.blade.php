@extends('surveyor_portal.layouts.guest')

@section('title', 'Registered — KN-MLPP-SG Portal')

@push('styles')
<style>
    .ks-reg-who { display:flex; align-items:center; gap:12px; padding:14px; border-radius:12px; background:#F3F6FA; margin-bottom:12px; }
    .ks-reg-avatar { flex:0 0 46px; width:46px; height:46px; border-radius:50%; display:grid; place-items:center; background:var(--ks-blue); color:#fff !important; font-weight:800; font-size:17px; }
    .ks-reg-who b { display:block; color:#102A4C; font-size:16px; line-height:1.25; }
    .ks-reg-who span { color:var(--ks-soft); font-size:13px; }
    .ks-reg-list { border:1px solid var(--ks-line); border-radius:12px; margin-bottom:12px; }
    .ks-reg-row { display:flex; justify-content:space-between; gap:14px; padding:10px 14px; border-top:1px solid #EEF0F3; font-size:14px; }
    .ks-reg-row:first-child { border-top:0; }
    .ks-reg-row dt { color:var(--ks-soft); flex:none; }
    .ks-reg-row dd { margin:0; color:var(--ks-ink); font-weight:600; text-align:right; word-break:break-word; }
    .ks-reg-status { display:inline-block; padding:2px 10px; border-radius:999px; background:#FEF3C7; color:#92400E; font-size:12.5px; font-weight:700; }
</style>
@endpush

@section('card')
    {{-- The registrant's details (user, 2026-11-15: "should be the user's details, not the code"). --}}
    <h1 class="text-lg font-bold mb-2">Registration received</h1>
    <p class="ks-help mb-4">Thank you. These are the details you registered. The Office of the Surveyor General will confirm your registration, and we will text you when you can sign in.</p>

    @php $initials = collect(preg_split('/\s+/', trim($account->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''); @endphp
    <div class="ks-reg-who">
        <span class="ks-reg-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
        <div><b>{{ $account->name }}</b><span>{{ $account->isCompany() ? 'Company account' : 'Surveyor' }}{{ $account->company_name ? ' · ' . $account->company_name : '' }}</span></div>
    </div>

    <dl class="ks-reg-list">
        <div class="ks-reg-row"><dt>Username</dt><dd>{{ $account->username ?: '—' }}</dd></div>
        <div class="ks-reg-row"><dt>Full name</dt><dd>{{ $account->name }}</dd></div>
        <div class="ks-reg-row"><dt>Phone</dt><dd>{{ $account->phone ?: '—' }}</dd></div>
        <div class="ks-reg-row"><dt>Email</dt><dd>{{ $account->email ?: '—' }}</dd></div>
        @if ($account->company_name)
            <div class="ks-reg-row"><dt>Company</dt><dd>{{ $account->company_name }}{{ $account->company_rc_no ? ' (RC ' . preg_replace('/^RC\s*/i', '', $account->company_rc_no) . ')' : '' }}</dd></div>
        @endif
        @if ($account->surcon_number)
            <div class="ks-reg-row"><dt>SURCON No.</dt><dd>{{ $account->surcon_number }}</dd></div>
        @endif
        @if ($address !== '')
            <div class="ks-reg-row"><dt>Address</dt><dd>{{ $address }}</dd></div>
        @endif
        @php $st = ['pending' => 'Awaiting approval', 'active' => 'Approved', 'rejected' => 'Declined', 'suspended' => 'Suspended'][$account->status] ?? ucfirst((string) $account->status); @endphp
        <div class="ks-reg-row"><dt>Status</dt><dd><span class="ks-reg-status" @if ($account->status === 'active') style="background:#DCFCE7;color:#166534;" @endif>{{ $st }}</span></dd></div>
    </dl>

    <p class="ks-help mb-4" style="font-size:12.5px;">Sign in with your <b>username</b> and the password you chose, once you are approved.</p>
    <a class="ks-btn ks-btn-primary w-full" href="{{ route('surveyor-portal.login') }}" data-signin-dialog-trigger>Back to sign in</a>
@endsection
