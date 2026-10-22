{{-- Page 1: Acknowledgement letter. Needs $application (a recertification_applications row). --}}
@php
    $ref = $application->application_reference ?? '';
    $oldFile = trim($application->file_number ?? '');

    $fmt = function ($raw) {
        try { return $raw ? \Carbon\Carbon::parse($raw)->format('d F, Y') : null; } catch (\Exception $e) { return null; }
    };
    $submitted = $fmt($application->application_date ?? $application->created_at ?? null);
    $letterDate = $fmt($application->ack_docs_date ?? null) ?: now()->format('d F, Y');

    if (($application->applicant_type ?? '') === 'Corporate') {
        $occupier = trim($application->organisation_name ?? '');
    } else {
        $occupier = trim(implode(' ', array_filter([
            trim($application->title ?? ''),
            trim($application->first_name ?? ''),
            trim($application->middle_name ?? ''),
            trim($application->surname ?? ''),
        ])));
    }
    $occupier = $occupier ?: 'Occupier';

    // Plot No., then District, then LGA
    $plot = trim($application->plot_number ?? '');
    if ($plot !== '' && !preg_match('/^plot\b/i', $plot)) { $plot = 'Plot No. ' . $plot; }
    $place = implode(', ', array_filter([
        trim($application->layout_district ?? ''),
        trim($application->lga_name ?? '') !== '' ? trim($application->lga_name) . ' LGA' : '',
    ]));

    $docs = json_decode($application->ack_docs_json ?? '{}', true) ?: [];
    $tick = fn ($k) => !empty($docs[$k]) ? '&#10003;' : '';
    $otherText = trim($docs['doc_other_text'] ?? '');
    $titleDocs = [
        'a' => ['doc_ro', 'Right of Occupancy'],
        'b' => ['doc_cofo', 'Certificate of Occupancy'],
        'c' => ['doc_deed_assignment', 'Deed of Assignment'],
        'd' => ['doc_deed_sublease', 'Deed of Sublease'],
        'e' => ['doc_deed_mortgage', 'Deed of Mortgage'],
        'f' => ['doc_deed_gift', 'Deed of Gift'],
        'g' => ['doc_poa', 'Power of Attorney'],
        'h' => ['doc_devolution', 'Devolution Order'],
        'i' => ['doc_letter_admin', 'Letter of Administration'],
    ];

    $qrUrl = qr_data_uri(json_encode([
        'id' => $application->id ?? null,
        'file_number' => $application->file_number ?? null,
        'reference' => $application->application_reference ?? null,
    ]), 120);
@endphp
<div class="ack-page">
    <div class="ack-head">
        <div class="ack-head-text">
            <div>KANO STATE GEOGRAPHIC INFORMATION SYSTEM (KANGIS)</div>
            <div>MINISTRY OF LAND AND PHYSICAL PLANNING</div>
            <div>KANO STATE GOVERNMENT</div>
        </div>
        <img class="ack-seal" src="{{ asset('assets/logo/kangis.jpg') }}" alt="KANGIS" />
    </div>

    <div class="ack-meta">
        <div>
            <div class="ack-qr"><img src="{{ $qrUrl }}" alt="QR Code" /></div>
            <div class="ack-date">{{ $letterDate }}</div>
        </div>
        <div class="ack-ref">
            {{ $ref ?: 'N/A' }}
            <small>Application Reference Number</small>
        </div>
    </div>

    <div class="ack-salutation">Dear {{ $occupier }},</div>

    <div class="ack-title">
        ACKNOWLEDGEMENT FOR RECERTIFICATION OF LAND TITLE AND RE-ISSUANCE OF A NEW DIGITAL CERTIFICATE OF OCCUPANCY
    </div>

    <div class="ack-body">
        <p>We write to acknowledge the receipt of your Application for Recertification and Re-issuance of the new digital Certificate of Occupancy (C-of-O) for the plot located at {{ $plot ?: '……………' }} in {{ $place ?: '……………' }}, with old file number {{ $oldFile ?: '……………' }} submitted on {{ $submitted ?: '……………' }}.</p>

        <p>2. Please note that your application reference number is <strong>{{ $ref ?: '……………' }}</strong> and you will be issued with a new land file number after your application is validated and processed for issuance of a new digital Certificate of Occupancy.</p>

        <p>3. Copies of one or more of the following title documents were submitted during the application process and were checked:</p>

        <table class="ack-docs">
            <tr>
                <th>Title Document</th>
                <th class="ack-status">Status (submitted)</th>
            </tr>
            @foreach ($titleDocs as $letter => [$key, $label])
                <tr>
                    <td>({{ $letter }}) {{ $label }}</td>
                    <td class="ack-status"><span class="ack-box">{!! $tick($key) !!}</span></td>
                </tr>
            @endforeach
            <tr>
                <td class="ack-other">(j) Others{{ $otherText !== '' && !empty($docs['doc_other']) ? ': ' . $otherText : '……………………………………' }}</td>
                <td class="ack-status"><span class="ack-box">{!! $tick('doc_other') !!}</span></td>
            </tr>
        </table>

        <p>4. This acknowledgement does not in any way validate the authenticity of your title documents described above. All documents are subject to further verification for authenticity.</p>
    </div>

    <div class="ack-sign">
        <p>Yours sincerely,</p>
        <div class="ack-sign-space"></div>
        <p>For Honourable Commissioner, Ministry of Land and Physical Planning</p>
        <p>Kano State Government</p>
    </div>

    <div class="klaes-footer"><img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES" /></div>
</div>
