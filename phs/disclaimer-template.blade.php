<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disclaimer for PHS Portal and Online Legal Search</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 104px 54px 78px 54px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 10.5px; line-height: 1.6; margin: 0; padding: 0; }

        /* Running header (logos + title) — repeats per page */
        .running-header { position: fixed; top: -84px; left: 0; right: 0; height: 76px; }
        .running-header table { width: 100%; }
        .running-header td { vertical-align: middle; }
        .running-header .logo-cell { width: 56px; }
        .running-header .logo-cell img { height: 46px; width: auto; }
        .running-header .logo-cell.r { text-align: right; }
        .running-header .brand { text-align: center; padding: 0 8px; }
        .running-header .brand h1 { color: #166534; font-size: 11px; margin: 0; }
        .running-header .brand p { color: #6b7280; font-size: 8.5px; margin: 1px 0 0; }
        .running-header .rule { border-bottom: 2.5px solid #166534; margin-top: 6px; }

        /* Running footer (logos + page number) — repeats per page */
        .running-footer { position: fixed; bottom: -64px; left: 0; right: 0; height: 58px; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .running-footer table { width: 100%; }
        .running-footer td { vertical-align: middle; }
        .running-footer .logo-cell { width: 50px; }
        .running-footer .logo-cell img { height: 38px; width: auto; }
        .running-footer .logo-cell.r { text-align: right; }
        .running-footer .ctr { text-align: center; font-size: 8px; color: #9ca3af; }
        .running-footer .ctr .pnum:before { content: counter(page); }
        .running-footer .ctr .pcount:before { content: counter(pages); }

        /* Cover */
        .cover { page-break-after: always; text-align: center; }
        .cover h2 { font-size: 18px; letter-spacing: .5px; margin: 120px 0 40px; line-height: 1.4; }
        .cover h3 { font-size: 14px; font-weight: normal; margin: 0 0 36px; }
        .cover .notice { font-size: 13px; font-weight: bold; color: #7f1d1d; letter-spacing: .5px; }

        /* Body */
        h3.section { font-size: 11.5px; color: #166534; text-transform: uppercase; letter-spacing: .4px; margin: 14px 0 5px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; page-break-after: avoid; }
        p.clause { margin: 4px 0 7px; text-align: justify; }
        p.clause strong { color: #111827; }
        p.lead { font-weight: bold; margin: 8px 0 4px; }
        ul.lst { margin: 3px 0 8px; padding-left: 18px; }
        ul.lst li { margin: 2px 0; }
        ul.chk { list-style: none; padding-left: 4px; }
        ul.chk li { margin: 4px 0; }
        ul.chk li:before { content: "\2713  "; color: #166534; font-weight: bold; }
        .caps { text-transform: uppercase; }
        .red { color: #7f1d1d; font-weight: bold; }
        .contact { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; margin: 8px 0; }
        .contact .ico { color: #166534; display: inline-block; width: 14px; text-align: center; }
        .tagline { text-align: center; color: #166534; font-style: italic; margin-top: 10px; }
    </style>
</head>
<body>
@php
    $headerLeft  = 'http://app.klaes.ng/assets/logo/ministry2.png';
    $headerRight = 'http://app.klaes.ng/assets/logo/ministry1.jpg';
    $footerLeft  = 'http://app.klaes.ng/assets/logo/phs-light-logo.jpeg';
    $footerRight = 'http://app.klaes.ng/storage/upload/logo/Klase.png';
    $verifyUrl   = 'www.klaes.gov.ng/verify';
    $lastUpdated = $date ?? now()->format('F j, Y');
@endphp

{{-- Running header --}}
<div class="running-header">
    <table>
        <tr>
            <td class="logo-cell"><img src="{{ $headerLeft }}" alt=""></td>
            <td class="brand">
                <h1>Kano State Ministry of Land &amp; Physical Planning</h1>
                <p>Disclaimer for PHS Portal and Online Legal Search</p>
            </td>
            <td class="logo-cell r"><img src="{{ $headerRight }}" alt=""></td>
        </tr>
    </table>
    <div class="rule"></div>
</div>

{{-- Running footer --}}
<div class="running-footer">
    <table>
        <tr>
            <td class="logo-cell"><img src="{{ $footerLeft }}" alt=""></td>
            <td class="ctr">
                © {{ date('Y') }} Kano State Ministry of Land and Physical Planning. All Rights Reserved.<br>
                Page <span class="pnum"></span> of <span class="pcount"></span>
            </td>
            <td class="logo-cell r"><img src="{{ $footerRight }}" alt=""></td>
        </tr>
    </table>
</div>

{{-- ============================ COVER ============================ --}}
<div class="cover">
    <h2>DISCLAIMER FOR PHS PORTAL AND ONLINE LEGAL SEARCH</h2>
    <h3>DISCLAIMER AND TERMS OF USE</h3>
    <p class="notice">IMPORTANT NOTICE — PLEASE READ CAREFULLY</p>
</div>

{{-- ============================ BODY ============================ --}}
<h3 class="section">1. Authorized Use Only</h3>
<p class="clause">This Property History Search (PHS) Portal and Online Legal Search platform is operated by the Kano State Ministry of Land and Physical Planning under the Kano State Land Administration Enterprise System (KLAES). Access to this platform is restricted to:</p>
<ul class="lst">
    <li><strong>PHS Portal:</strong> Authorized subscribers (Financial and Legal Institutions) with valid annual subscriptions and active search tokens</li>
    <li><strong>Online Legal Search:</strong> Registered users with valid credentials and sufficient tokens</li>
</ul>
<p class="clause">Unauthorized access, attempted access, or use of this platform is strictly prohibited and may result in criminal prosecution under applicable Nigerian laws, including the Cybercrimes (Prohibition, Prevention, etc.) Act 2015.</p>

<h3 class="section">2. No Liability for Alteration or Misuse</h3>
<p class="lead">TAKE NOTICE that:</p>
<p class="clause">2.1 The Kano State Ministry of Land and Physical Planning, its officers, employees, agents, and the Kano State Government shall not be held liable or responsible for any:</p>
<p class="clause">a) Alteration, modification, forgery, or tampering with any Property History Report, Legal Search Report, or any document generated from this platform by any person or entity</p>
<p class="clause">b) Misrepresentation or misuse of search results for fraudulent, illegal, or unauthorized purposes</p>
<p class="clause">c) Reliance on altered, forged, or tampered documents by any third party</p>
<p class="clause">d) Loss, damage, or injury arising from the unauthorized use, distribution, or presentation of search results</p>
<p class="clause">2.2 All reports generated from this platform contain:</p>
<ul class="lst">
    <li>Unique verification codes/QR codes</li>
    <li>Digital watermarks</li>
    <li>Timestamp of generation</li>
    <li>Authorized user identification</li>
</ul>
<p class="clause">It is the responsibility of any recipient to verify the authenticity of reports using the verification features provided on the Ministry's official website.</p>

<h3 class="section">3. Criminal Offense Warning</h3>
<p class="lead">BE ADVISED that under Nigerian law:</p>
<p class="clause">3.1 It is a <span class="red">CRIMINAL OFFENSE</span> punishable by law to:</p>
<p class="clause">a) Falsify, alter, forge, or tamper with any Property History Report, Legal Search Report, Certificate of Occupancy, Right of Occupancy, or any land document issued by or accessible through the Ministry</p>
<p class="clause">b) Use or present any altered, forged, or falsified land document for any purpose, including but not limited to:</p>
<ul class="lst">
    <li>Mortgage applications</li>
    <li>Property transactions</li>
    <li>Court proceedings</li>
    <li>Loan applications</li>
    <li>Due diligence processes</li>
</ul>
<p class="clause">c) Impersonate an authorized user or gain unauthorized access to this platform</p>
<p class="clause">d) Circumvent security measures or attempt to access data beyond authorized scope</p>
<p class="clause">3.2 Applicable Laws and Penalties:</p>
<p class="clause">Such acts constitute offenses under:</p>
<p class="clause">a) The Criminal Code Act (Cap C38 LFN 2004)</p>
<ul class="lst">
    <li>Section 363: Forgery (Punishable by imprisonment up to 14 years)</li>
    <li>Section 364: Uttering forged documents (Punishable by imprisonment up to 14 years)</li>
    <li>Section 366: Fraudulent disposal of property (Punishable by imprisonment up to 7 years)</li>
</ul>
<p class="clause">b) The Cybercrimes (Prohibition, Prevention, etc.) Act 2015</p>
<ul class="lst">
    <li>Section 6: System Interference (Punishable by imprisonment up to 10 years or fine up to ₦10,000,000 or both)</li>
    <li>Section 7: Cyberstalking (Punishable by imprisonment up to 3 years or fine up to ₦7,000,000 or both)</li>
    <li>Section 14: Identity Theft (Punishable by imprisonment up to 7 years or fine up to ₦7,000,000 or both)</li>
</ul>
<p class="clause">c) The Land Use Act 1978</p>
<ul class="lst">
    <li>Section 16: Offenses relating to land transactions</li>
    <li>Fraudulent acquisition or presentation of land documents</li>
</ul>
<p class="clause">d) The Penal Code (Northern States) Federal Provisions Act</p>
<ul class="lst">
    <li>Sections relating to forgery, cheating, and fraud</li>
</ul>
<p class="clause">3.3 The Ministry shall:</p>
<ul class="lst">
    <li>Report all suspected cases of document forgery, alteration, or fraud to law enforcement agencies</li>
    <li>Cooperate fully with the Nigeria Police Force, Economic and Financial Crimes Commission (EFCC), Independent Corrupt Practices Commission (ICPC), and other relevant authorities</li>
    <li>Pursue criminal and civil remedies against perpetrators to the fullest extent of the law</li>
</ul>

<h3 class="section">4. Verification of Authenticity</h3>
<p class="clause">4.1 All genuine Property History Reports and Legal Search Reports issued by the Ministry contain:</p>
<p class="clause">a) Official Ministry letterhead and seal</p>
<p class="clause">b) Unique document reference number</p>
<p class="clause">c) QR code for instant verification</p>
<p class="clause">d) Digital signature or authorized officer's signature</p>
<p class="clause">e) Timestamp of generation</p>
<p class="clause">f) Watermark indicating official document</p>
<p class="clause">4.2 To verify the authenticity of any report:</p>
<p class="clause">a) Visit the official verification portal: {{ $verifyUrl }}</p>
<p class="clause">b) Scan the QR code using any QR reader</p>
<p class="clause">c) Enter the unique reference number on the Ministry's website</p>
<p class="clause">d) Contact the Ministry directly at:</p>
<ul class="lst">
    <li>Email: phsportal@kano.gov.ng</li>
    <li>Phone: 0803 123 4567, 0909 876 5432</li>
    <li>Address: Ministry of Land and Physical Planning, Kano State Secretariat, Kano</li>
</ul>
<p class="clause">4.3 Any document that:</p>
<ul class="lst">
    <li>Lacks verification features</li>
    <li>Contains spelling errors or formatting inconsistencies</li>
    <li>Has been photocopied or scanned without verification</li>
    <li>Shows signs of alteration</li>
</ul>
<p class="clause red caps">Should be reported immediately to the Ministry and law enforcement authorities.</p>

<h3 class="section">5. Informational Purpose Only</h3>
<p class="clause">5.1 Property History Reports and Legal Search Results are provided for <strong>INFORMATIONAL PURPOSES ONLY</strong> and:</p>
<p class="clause">a) Do NOT constitute legal advice or opinion</p>
<p class="clause">b) Do NOT guarantee title or ownership</p>
<p class="clause">c) Do NOT replace the need for comprehensive legal due diligence</p>
<p class="clause">d) Do NOT warrant that the information is complete, current, or free from errors</p>
<p class="clause">5.2 Users are advised to:</p>
<p class="clause">a) Seek independent legal counsel before entering into any property transaction</p>
<p class="clause">b) Conduct physical inspection of properties</p>
<p class="clause">c) Verify information with multiple sources</p>
<p class="clause">d) Not rely solely on search results for critical business decisions</p>

<h3 class="section">6. Data Accuracy Disclaimer</h3>
<p class="clause">6.1 While the Ministry makes reasonable efforts to ensure the accuracy of information contained in the KLAES database:</p>
<p class="clause">a) Information is dependent on the quality and timeliness of data submitted by various sources</p>
<p class="clause">b) Historical records may contain errors, omissions, or inconsistencies</p>
<p class="clause">c) Recent transactions may not yet be reflected in the system</p>
<p class="clause">d) Discrepancies may exist between digital records and physical documents</p>
<p class="clause">6.2 The Ministry:</p>
<p class="clause">a) Does NOT warrant that the database is complete or error-free</p>
<p class="clause">b) Does NOT guarantee that all encumbrances, caveats, or interests are recorded</p>
<p class="clause">c) Shall NOT be liable for decisions made based on incomplete or inaccurate information</p>

<h3 class="section">7. Intellectual Property</h3>
<p class="clause">All reports, data, and information accessible through this platform are the intellectual property of the Kano State Ministry of Land and Physical Planning and/or the Kano State Government.</p>
<p class="clause">Unauthorized reproduction, distribution, commercial exploitation, or resale of reports or data is strictly prohibited and may result in:</p>
<ul class="lst">
    <li>Immediate termination of access</li>
    <li>Forfeiture of all fees and tokens</li>
    <li>Civil and criminal legal action</li>
</ul>

<h3 class="section">8. Acceptance of Terms</h3>
<p class="lead caps">By accessing and using this platform, you acknowledge and agree that:</p>
<ul class="chk">
    <li>You have read, understood, and accept this Disclaimer and Terms of Use</li>
    <li>You will use the platform only for lawful and authorized purposes</li>
    <li>You will not attempt to alter, forge, or misuse any documents generated</li>
    <li>You understand the criminal penalties for document forgery and fraud</li>
    <li>You will verify the authenticity of all reports before reliance</li>
    <li>You accept all risks associated with the use of this platform</li>
    <li>You will indemnify the Ministry against claims arising from your misuse</li>
</ul>
<p class="clause red caps">If you do not agree to these terms, you must not access or use this platform.</p>

<h3 class="section">9. Reporting Suspicious Activity</h3>
<p class="clause">If you suspect that:</p>
<ul class="lst">
    <li>A Property History Report or Legal Search Report has been altered or forged</li>
    <li>Someone is using this platform fraudulently</li>
    <li>You have received a fraudulent land document</li>
</ul>
<p class="lead caps">Immediately report to:</p>
<div class="contact">
    <strong>Kano State Ministry of Land and Physical Planning</strong><br>
    <span class="ico">&#9993;</span> Email: phsportal@kano.gov.ng, reportfraud@kano.gov.ng<br>
    <span class="ico">&#9742;</span> Phone: 0803 123 4567, 0909 876 5432<br>
    <span class="ico">&#8962;</span> Address: Ministry of Land and Physical Planning, Kano State Secretariat, Kano
</div>
<p class="lead caps">And to law enforcement:</p>
<ul class="lst">
    <li>Nigeria Police Force — Cybercrime Unit</li>
    <li>Economic and Financial Crimes Commission (EFCC)</li>
    <li>Independent Corrupt Practices Commission (ICPC)</li>
</ul>

<h3 class="section">10. Amendments</h3>
<p class="clause">The Ministry reserves the right to amend this Disclaimer at any time without prior notice. Amendments shall be effective immediately upon posting to the platform. Continued use of the platform constitutes acceptance of amended terms.</p>
<p class="clause"><strong>LAST UPDATED:</strong> {{ $lastUpdated }}</p>
<p class="clause">© {{ date('Y') }} KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING — All Rights Reserved</p>
<p class="clause">Secure. Trusted. Government Powered."</p>
{{-- <p class="clause">These documents are comprehensive and legally sound. You may want to have them reviewed by the Ministry's legal department before final implementation.<p> --}}
</body>
</html>
