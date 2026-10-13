{{--
    One contravention notice letter (one A4 sheet). Shared by the single print
    (notice/print) and the batch print (notice/print_batch), so a notice prints
    the same either way. Expects $notice (with its application loaded).
--}}
@php

    $app      = $notice->application;
    $isSecond = $notice->notice_type === 'second';
    $served   = $notice->served_date ?? $notice->created_at;
    $district = $app->district ?? null;
    $lga      = $app->lga ?? null;
    $address  = array_values(array_filter([
        $notice->recipient_name ?: ($app->owner_name ?? null),
        trim(collect([$district, $lga])->filter()->implode(', ')),
        $app->location ?? null,
    ], fn ($line) => $line !== null && $line !== ''));
    // Location repeats District, LGA when it was built from them.
    $address = array_values(array_unique($address));
    // SPAS ID first; the file number only when the owner gave one (a notice
    // for a record with none carries the SPAS ID in file_number).
    $spaId  = $app->spa_id ?? null;
    $fileNo = $app ? $app->file_number : $notice->file_number;
@endphp
<div class="page">

    <div class="header">
        <div class="date">
            Date:
            <span class="date-line">{{ $served ? \Carbon\Carbon::parse($served)->format('jS F, Y') : '' }}</span>
        </div>
        <div class="ref">
            @if($spaId)SPAS ID: <strong>{{ $spaId }}</strong>@endif
            @if($fileNo && $fileNo !== $spaId)@if($spaId)<br>@endif File No: <strong>{{ $fileNo }}</strong>@endif
        </div>

        <div class="address">
            @for ($i = 0; $i < 3; $i++)
                <div class="address-line">{{ $address[$i] ?? '' }}</div>
            @endfor
        </div>
    </div>

    <div class="salutation">Sir/Ma,</div>

    <div class="main-heading">
        <span class="reminder">{{ $isSecond ? 'REMINDER' : 'NOTICE' }}</span>
        <span class="subject">RE: CONTRAVENTION OF LANDUSE</span>
    </div>

    <div class="content">
        @if ($isSecond)
            <p>
                Refer above and note that the Ministry has served you with a notice to come
                forward and formalize your title but there was no response. This served as a
                reminder to you that your failure will leave the ministry with no option other
                than to invoke its power under either section 9 (4) or 28 (5) of the Land use Act.
            </p>
            <p>Your earlier response is awaited.</p>
        @else
            {{-- The Ministry's First Serve wording, as sent by SMS. --}}
            <p>{{ \App\Models\SpaNotice::FIRST_SERVE_SMS }}</p>
        @endif
    </div>

    <div class="closing">Best Regards,</div>

    <div class="signature">
        <div class="name">GAMBO SULEIMAN GARKO,</div>
        <div class="title">Director Special Assignment,</div>
        <div class="for">For: Honorable Commissioner</div>
    </div>

</div>
