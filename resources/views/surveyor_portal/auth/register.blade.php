@extends('surveyor_portal.layouts.guest')

@section('title', 'Register — KSSGOF Portal')
@section('width', '520px')

@section('card')
    <h1 class="text-lg font-bold mb-1">Register as a surveyor</h1>
    <p class="ks-help mb-4">The Office of the Surveyor General confirms each registration. We will text you when you can sign in.</p>
    @php $type = old('surveyor_type'); $co = old('company_choice'); @endphp
    <form method="POST" action="{{ route('surveyor-portal.register.submit') }}" class="space-y-4">
        @csrf
        <div>
            <span class="ks-label">Surveyor Type <span class="ks-req">*</span></span>
            <div class="flex gap-6 text-sm">
                @foreach (['Government' => 'Public', 'Private' => 'Private'] as $v => $l)
                    <label class="flex items-center gap-2"><input type="radio" name="surveyor_type" value="{{ $v }}" required @checked($type === $v) onchange="ksCompany()"> {{ $l }}</label>
                @endforeach
            </div>
            @error('surveyor_type')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div id="ksCompanyBox" style="{{ $type === 'Private' ? '' : 'display:none;' }}">
            <label class="ks-label" for="company_choice">Company <span class="ks-req">*</span></label>
            <select class="ks-input" id="company_choice" name="company_choice" onchange="ksCompany()">
                <option value="">Select company…</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $co === (string) $c->id)>{{ $c->name }}</option>@endforeach
                <option value="other" @selected($co === 'other')>Other (specify)</option>
            </select>
            @error('company_choice')<p class="ks-error">{{ $message }}</p>@enderror
            <div id="ksCompanyOther" class="mt-2" style="{{ $co === 'other' ? '' : 'display:none;' }}">
                <input class="ks-input" name="company_other" value="{{ old('company_other') }}" maxlength="255" placeholder="Specify your company *" aria-label="Specify your company">
                @error('company_other')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="ks-label" for="name">Surveyor's Name <span class="ks-req">*</span></label>
            <input class="ks-input" id="name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name">
            @error('name')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="phone">Phone <span class="ks-req">*</span></label>
                <input class="ks-input" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" required autocomplete="tel" placeholder="08031234567">
                @error('phone')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="email">Email <span class="ks-req">*</span></label>
                <input class="ks-input" id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" required autocomplete="email">
                @error('email')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="ks-label" for="surcon_number">SURCON No.</label>
            <input class="ks-input" id="surcon_number" name="surcon_number" value="{{ old('surcon_number') }}" maxlength="50">
            @error('surcon_number')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="password">Password <span class="ks-req">*</span></label>
                <input class="ks-input" id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
                @error('password')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="password_confirmation">Confirm Password <span class="ks-req">*</span></label>
                <input class="ks-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        <button class="ks-btn ks-btn-primary w-full">Register</button>
    </form>
    <p class="ks-help text-center mt-4">Already registered? <a href="{{ route('surveyor-portal.login') }}" style="color:var(--ks-blue);font-weight:600;">Sign in</a></p>
@endsection

@push('scripts')
<script>
    function ksCompany() {
        var t = document.querySelector('input[name="surveyor_type"]:checked'), priv = !!(t && t.value === 'Private');
        var sel = document.getElementById('company_choice');
        document.getElementById('ksCompanyBox').style.display = priv ? '' : 'none';
        sel.required = priv;
        var other = priv && sel.value === 'other', box = document.getElementById('ksCompanyOther');
        box.style.display = other ? '' : 'none';
        box.querySelector('input').required = other;
    }
    ksCompany();
</script>
@endpush
