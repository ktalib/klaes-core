{{-- View profile on Registrations > Surveyors (2026-11-16): loaded into the Surveyor Profile pop-up. --}}
@php
    $statusLabel = ['pending' => 'Waiting', 'active' => 'Approved', 'rejected' => 'Not approved', 'suspended' => 'Suspended'][$a->status] ?? $a->status;
    $rows = [
        'Name' => $a->name,
        'Surveyor ID' => $a->surveyor_id_no,
        'Registration ID' => $a->registration_id,
        'Type' => $a->type_label,
        'Company' => $a->company_name . (($company->beacon_letters ?? '') !== '' ? ' (prefix ' . $company->beacon_letters . ')' : ''),
        'RC No.' => $a->company_rc_no,
        'SURCON No.' => $a->surcon_number,
        'Licence' => $directory ? ($directory->licence_status . ($directory->is_active ? '' : ' · not active in the register')) : null,
        'Username' => $a->username,
        'Phone' => $a->phone,
        'Email' => $a->email,
        'Address' => $a->person_address,
        'Status' => $statusLabel . ($a->review_note ? ' — ' . $a->review_note : ''),
        'Registered' => optional($a->created_at)->format('d/m/Y H:i'),
        'Must change password' => $a->must_change_password ? 'Yes, at the next sign-in' : 'No',
    ];
@endphp
<div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:12px;">
    @if ($a->passport_path)
        <img src="{{ route('survey-module.beacon-requests.accounts.passport', $a) }}" alt="Passport of {{ $a->name }}" style="width:72px;height:90px;object-fit:cover;border-radius:6px;border:1px solid #d1d5db;flex:none;">
    @endif
    <div>
        <p style="font-size:16px;font-weight:700;margin:0;">{{ $a->name }}</p>
        <p class="helper-text" style="margin:2px 0 0;">{{ $a->surveyor_id_no ?: $a->registration_id }} · {{ $statusLabel }}</p>
    </div>
</div>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
    @foreach ($rows as $label => $value)
        <tr style="border-top:1px solid #f1f5f9;">
            <td style="padding:6px 8px 6px 0;color:#64748b;width:38%;vertical-align:top;">{{ $label }}</td>
            <td style="padding:6px 0;vertical-align:top;overflow-wrap:anywhere;">{{ ($value ?? '') !== '' ? $value : '—' }}</td>
        </tr>
    @endforeach
</table>
<p style="font-weight:700;margin:14px 0 6px;">Work on the portal</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
    @foreach (\App\Services\Survey\SurveyorAccountAdmin::WORK as $table => $label)
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;">
            <div style="font-size:18px;font-weight:700;">{{ number_format($work[$table] ?? 0) }}</div>
            <div class="helper-text" style="margin:0;">{{ $label }}</div>
        </div>
    @endforeach
</div>
@if (! $work)
    <p class="helper-text" style="margin-top:8px;">No work yet, so this account can be deleted.</p>
@else
    <p class="helper-text" style="margin-top:8px;">This account has work, so it can be suspended but not deleted.</p>
@endif
