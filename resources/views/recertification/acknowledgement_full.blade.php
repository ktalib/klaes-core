<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recertification Acknowledgement (Complete)</title>
  <style>
    @page { size: A4; margin: 15mm 15mm 10mm 15mm; }
    body { font-family: Arial, sans-serif; color:#333; line-height:1.4; }
    .page { page-break-after: always; }
    .no-break { page-break-after: auto; }
    .print-btn { text-align:center; margin: 16px 0; }
    @media print { .no-print { display:none; } }
  </style>
</head>
<body>
<div class="no-print" style="text-align: center; margin-top: 15px;"> 
    <button onclick="window.print()">Print This Page</button>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>


  <style>
        @page { size: A4; margin: 15mm 15mm 10mm 15mm; }
        body { font-family: Arial, sans-serif; line-height: 1.4; color: #333; max-width: 210mm; margin: 0 auto; padding: 5px; font-size: 14px; padding-top: 10px; }
        .header-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; margin-top: 10px; }
        .header-text { flex: 1; text-align: center; border-bottom: 1.5px solid #1a5276; padding-bottom: 6px; font-weight: bold; }
        .header-text h1 { color: #1a5276; margin: 3px 0; font-size: 14px; }
        .header-text p { margin: 2px 0; font-size: 11px; }
        .logo-placeholder { width: 70px; height: 70px; border: 1px dashed #999; display: flex; align-items: center; justify-content: center; font-size: 9px; color: #999; margin-left: 15px; }
        .reference-section { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .qr-box { width: 90px; height: 90px; display:flex; align-items:center; justify-content:center; }
        .reference-number { text-align: right; font-weight: bold; margin-top: 5px; font-size: 10px; }
        .date { text-align: right; margin-bottom: 8px; font-size: 10px; }
        .salutation { margin-bottom: 5px; }
        .document-title { text-align: center; font-weight: bold; text-decoration: underline; margin: 8px 0; font-size: 14px; }
        .content { margin-bottom: 10px; }
        .content p { margin: 6px 0; }
        .documents-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; font-weight: bold; }
        .documents-table th, .documents-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .documents-table th { background-color: #f2f2f2; padding: 6px; }
        .checkbox-cell { text-align: center; width: 80px; }
        .footer { margin-top: 20px; }
        .signature-line { border-top: 1px solid #333; width: 180px; margin-top: 20px; }
        .signature-label { margin-top: 4px; font-weight: bold; font-size: 12px; }
        .footer p { margin: 4px 0; font-size: 11px; }
        @media print { body { padding: 0; font-size: 12px; padding-top: 5px; } .no-print { display: none; } .documents-table { font-size: 11px; } .header-container { margin-top: 5px; } }
    </style>
</head>
<body>
@php
    $ref = $application->application_reference ?? 'N/A';
    $file = $application->file_number ?? 'N/A';
    $dateRaw = $application->application_date ?? $application->created_at ?? null;
    try { $dateFmt = $dateRaw ? \Carbon\Carbon::parse($dateRaw)->format('d M Y') : 'N/A'; } catch (\Exception $e) { $dateFmt = 'N/A'; }

    // Build applicant/occupier name
    $occupier = 'Occupier';
    if (($application->applicant_type ?? '') === 'Corporate') {
        $occupier = trim($application->organisation_name ?? 'Occupier');
    } else {
        $parts = [
            trim($application->title ?? ''),
            trim($application->first_name ?? ''),
            trim($application->middle_name ?? ''),
            trim($application->surname ?? ''),
        ];
        $name = trim(implode(' ', array_filter($parts)));
        if (!$name) { $name = trim($application->applicant_name ?? '') ?: 'Occupier'; }
        $occupier = $name;
    }

    // Basic plot/district text
    $plotText = trim(($application->plot_number ?? '')); 
    $layoutDistrict = trim(($application->layout_district ?? ''));

    // Document flags
    $docs = json_decode($application->ack_docs_json ?? '{}', true) ?: [];
    $is = function($k) use ($docs) { return !empty($docs[$k]); };
    $otherText = $docs['doc_other_text'] ?? '';

    // QR payload
    $qrPayload = json_encode([
        'id' => $application->id ?? null,
        'file_number' => $application->file_number ?? null,
        'reference' => $application->application_reference ?? null,
    ]);
    $qrUrl = qr_data_uri($qrPayload, 120);
@endphp

    <div class="header-container">
        <div class="header-text">
            <h1>KANO STATE GEOGRAPHIC INFORMATION SYSTEM (KANGIS)</h1>
            <p>MINISTRY OF LAND AND PHYSICAL PLANNING</p>
            <p>KANO STATE GOVERNMENT</p>
        </div>
        <div class="logo">
        <img src="http://klas.com.ng/assets/logo/logo2.jpg" alt="KANGIS Logo" style="height:80px;float:right;" /></div>
    </div>
    </div>
    
    <div class="reference-section">
        <div class="qr-box">
            <img src="{{ $qrUrl }}" alt="QR Code" style="max-width:100%;max-height:100%;" />
        </div>
        <div class="reference-number">
            Ref: {{ $ref }}<br/>
            File: {{ $file }}
        </div>
    </div>
    
    <div class="date">{{ $dateFmt }}</div>
    
    <div class="salutation">Dear {{ $occupier }},</div>
    
    <div class="document-title">
        ACKNOWLEDGEMENT FOR RECERTIFICATION OF LAND TITLE AND REISSUANCE OF A NEW DIGITAL CERTIFICATE OF OCCUPANCY
    </div>
    
    <div class="content">
        <p>We write to acknowledge the receipt of your Application for Recertification and Reissuance of the new digital Certificate of Occupancy (C-of-O) for the plot located at {{ $plotText ?: '[Plot Address/Number]' }} in {{ $layoutDistrict ?: '[Layout/District]' }}, with old file number {{ $file }} submitted on {{ $dateFmt }}.</p>
        
        <p>2. Please note that your application reference number is {{ $ref }} and you will be issued with a new land file number after your application is validated and processed for issuance of a new digital Certificate of Occupancy.</p>
        
        <p>3. Copies of one or more of the following title documents were submitted during the application process and were checked:</p>
        
        <table class="documents-table">
            <tr>
                <th>Title Document</th>
                <th class="">Status (submitted)</th>
            </tr>
            <tr>
                <td>(a) Right of Occupancy</td>
                <td class="checkbox-cell">{{ $is('doc_ro') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(b) Certificate of Occupancy</td>
                <td class="checkbox-cell">{{ $is('doc_cofo') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(c) Deed of Assignment</td>
                <td class="checkbox-cell">{{ $is('doc_deed_assignment') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(d) Deed of Sublease</td>
                <td class="checkbox-cell">{{ $is('doc_deed_sublease') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(e) Deed of Mortgage</td>
                <td class="checkbox-cell">{{ $is('doc_deed_mortgage') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(f) Deed of Gift</td>
                <td class="checkbox-cell">{{ $is('doc_deed_gift') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(g) Power of Attorney</td>
                <td class="checkbox-cell">{{ $is('doc_poa') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(h) Devolution Order</td>
                <td class="checkbox-cell">{{ $is('doc_devolution') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(i) Letter of Administration</td>
                <td class="checkbox-cell">{{ $is('doc_letter_admin') ? '☑' : '□' }}</td>
            </tr>
            <tr>
                <td>(j) Others...... {{ $is('doc_other') && $otherText ? '(' . $otherText . ')' : '' }}</td>
                <td class="checkbox-cell">{{ $is('doc_other') ? '☑' : '□' }}</td>
            </tr>
        </table>
        
        <p>4. This acknowledgement does not in any way validate the authenticity of your title documents described above. All documents are subject to further verification for authenticity.</p>
    </div>
    
    <div class="footer">
        <p>Yours sincerely,</p>
        <div class="signature-line"></div> <br>
        <div class="signature-label">[Authorized Name]</div>
        <p>For Honourable Commissioner, Ministry of Land and Physical Planning<br>
        Kano State Government</p>
    </div>
    <br> <br>
  <!-- Page 2: Acknowledgement (existing) -->
  <div class="no-break">
    <div class="header" style="display:flex;justify-content:flex-end;margin-top:30px;margin-bottom:10px;">
      <div class="logo">
        <img src="http://klas.com.ng/assets/logo/logo2.jpg" alt="KANGIS Logo" style="height:80px;float:right;" /></div>
    </div>
    <div class="document-title" style="text-align:center;font-weight:bold;font-size:16px;margin:10px 0 20px 0;text-decoration:underline;clear:both;">
      RECERTIFICATION OF LAND TITLE<br />
      COLLECTION OF ACKNOWLEDGEMENT LETTER
    </div>
    <div class="content" style="margin-bottom:10px;text-align:justify;">
      <p>Please note that you may be invited later for an Interview via Phone, SMS, WhatsApp or Email to provide additional information and documentation where necessary. You can always check the status of your application via our website <a href="https://kangis.gov.ng">https://kangis.gov.ng</a> or Contact the KANGIS Customer Service Desk via Phone:  , SMS, WhatsApp, or you can visit the KANGIS Customer Service Desk.</p>
      <p>You can track the progress of your recertification application using the QR code on the front page of the acknowledgement letter.</p>
      <p>Please keep the original acknowledgement letter in a safe place for future reference. It is one of the requirements for the collection of your new digital Certificate of Occupancy.</p>
      <div class="contact-info" style="margin-top:20px;">
        <p><strong>Contact Information:</strong></p>
        <p>KANGIS Customer Service</p>
        <p>KANGIS Complex, 2 Dr. Bala Muhammad Way,</p>
        <p>Nassarawa G.R.A., Kano, Nigeria</p>
        <p>Tel: +234 (0)900 000 0000 | Email: support@kangis.gov.ng</p>
        <p>Website: <a href="https://kangis.gov.ng">https://kangis.gov.ng</a></p>
      </div>
      <div class="collection-section" style="margin-top:40px;">
        <p>Original copy of acknowledgement letter for recertification was collected by me</p>
        <div> Name: <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span> </div>
        <div> Address: <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span><br />
             <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span>
        </div>
        <div> Phone No: <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span> </div>
        <div> Signature: <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span> </div>
        <div> Date: <span style="display:inline-block;min-width:200px;border-bottom:1px solid #333;margin-left:5px;"></span> </div>
      </div>
    </div>
    <div class="no-print" style="text-align:center;margin-top:15px;">
      <button onclick="window.print()">Print This Page</button>
    </div>
  </div>
</body>
</html>
