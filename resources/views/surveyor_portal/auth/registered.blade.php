@extends('surveyor_portal.layouts.guest')

@section('title', 'Registered — KNSSGOF Portal')

@section('card')
    <h1 class="text-lg font-bold mb-2">Registration received</h1>
    <p class="ks-help mb-4">Your Registration ID is below. Keep it. The Office of the Surveyor General will confirm your registration, and we will text you when you can sign in.</p>
    <div class="text-center font-bold text-lg p-3 rounded-lg mb-4" style="background:#E8EDF5;color:var(--ks-blue);">{{ $registrationId }}</div>
    <a class="ks-btn ks-btn-primary w-full" href="{{ route('surveyor-portal.login') }}">Back to sign in</a>
@endsection
