<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Level Agreement (SLA) for KLAES Property History Search (PHS) Portal</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 92px 54px 64px 54px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 10.5px; line-height: 1.6; margin: 0; padding: 0; }

        /* Repeating running header / footer (DomPDF repeats fixed elements per page) */
        .running-header { position: fixed; top: -72px; left: 0; right: 0; height: 64px; }
        .running-header .brand { text-align: center; padding: 0 8px; }
        .running-header .brand h1 { color: #166534; font-size: 9.5px; letter-spacing: .3px; margin: 0; }
        .running-header .rule { border-bottom: 2.5px solid #166534; margin-top: 6px; }

        .running-footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 40px; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .running-footer td { vertical-align: middle; font-size: 8px; color: #9ca3af; }
        .running-footer .note { text-align: left; }
        .running-footer .pg { text-align: right; white-space: nowrap; }
        .running-footer .pg .pnum:before { content: counter(page); }
        .running-footer .pg .pcount:before { content: counter(pages); }

        /* Cover page */
        .cover { page-break-after: always; text-align: center; }
        .cover .doc-code { font-size: 12px; font-weight: bold; letter-spacing: .5px; margin: 30px 0 90px; }
        .cover h2 { font-size: 18px; letter-spacing: 1px; margin: 0 0 60px; }
        .cover .between { font-size: 12px; margin: 0 0 40px; }
        .cover .party { margin: 0 0 50px; }
        .cover .party .nm { font-size: 13px; margin: 0; }
        .cover .party .sub { font-size: 11px; color: #6b7280; margin: 4px 0 0; }
        .cover .amp { font-size: 12px; margin: 0 0 50px; }
        .cover .org-name { font-size: 13px; margin: 0 0 50px; }
        .cover .purpose { font-size: 12px; }

        /* Body */
        h3.section { font-size: 11px; color: #166534; text-transform: uppercase; letter-spacing: .4px; margin: 14px 0 4px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; page-break-after: avoid; }
        h4.sub { font-size: 10.5px; color: #111827; margin: 10px 0 2px; page-break-after: avoid; }
        p.clause { margin: 4px 0 7px; text-align: justify; }
        p.clause strong { color: #111827; }
        ul.lst { margin: 3px 0 8px; padding-left: 18px; }
        ul.lst li { margin: 2px 0; }
        ul.dash { list-style: none; padding-left: 8px; }
        ul.dash li:before { content: "– "; }
        .fill { font-weight: 600; color: #166534; }
        .blank { display: inline-block; min-width: 90px; border-bottom: 1px solid #9ca3af; }
        .caps { text-transform: uppercase; }

        table.grid { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 9.5px; }
        table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.grid th { background: #f1f5f9; color: #166534; }

        .section-block { page-break-inside: avoid; }

        /* Execution / signatures */
        .exec-party { margin: 14px 0 6px; }
        .exec-party .for { font-weight: bold; font-size: 10.5px; }
        .sig-line { border-top: 1px solid #374151; width: 280px; margin-top: 38px; padding-top: 3px; }
        .sig-line p { margin: 1px 0; font-size: 9.5px; color: #374151; }
    </style>
</head>
<body>
@php
    $orgName      = $onboardingRequest->organization_name ?: '[Organization Name]';
    $orgTypeRaw   = trim((string) ($onboardingRequest->organization_type ?? ''));
    $orgType      = $orgTypeRaw !== '' ? ucwords(str_replace('_', ' ', $orgTypeRaw)) : 'Bank / Mortgage Institution / Law Firm';
    $orgAddress   = $onboardingRequest->address ?: '________________________________';
    $contactName  = $onboardingRequest->contact_name ?: '';
    $contactTitle = $onboardingRequest->job_title ?: '';
    $packageName  = $onboardingRequest->initial_token_package ?: ($package['name'] ?? '—');
    $tokens       = $package['tokens'] ?? null;
    $teamMembers  = $package['team_members'] ?? null;
    $pkgPrice     = isset($package['price']) ? (float) $package['price'] : null;
    $perToken     = ($pkgPrice && $tokens) ? $pkgPrice / $tokens : null;
    $lsaRef       = 'PHS-SLA-' . str_pad($onboardingRequest->id, 6, '0', STR_PAD_LEFT);
    $blank        = '________________________________';
@endphp

{{-- Running header (repeats on every page) --}}
<div class="running-header">
    <div class="brand">
        <h1>SERVICE LEVEL AGREEMENT (SLA) FOR KLAES PROPERTY HISTORY SEARCH (PHS) PORTAL</h1>
    </div>
    <div class="rule"></div>
</div>

{{-- Running footer (repeats on every page) --}}
<div class="running-footer">
    <table>
        <tr>
            <td class="note">Ref: {{ $lsaRef }} &nbsp;|&nbsp; Generated electronically by the KLAES PHS Portal on {{ $date }}.</td>
            <td class="pg">Page <span class="pnum"></span> of <span class="pcount"></span></td>
        </tr>
    </table>
</div>

{{-- ============================ COVER ============================ --}}
<div class="cover">
    <div class="doc-code">SERVICE LEVEL AGREEMENT (SLA) FOR KLAES PROPERTY HISTORY SEARCH (PHS) PORTAL</div>
    <h2>SERVICE LEVEL AGREEMENT</h2>
    <p class="between">Between</p>
    <div class="party">
        <p class="nm">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
        <p class="sub">(Kano State Land Administration Enterprise System – KLAES)</p>
    </div>
    <p class="amp">And</p>
    <p class="org-name">{{ $orgName }}</p>
    <p class="purpose">For Access to the Property History Search (PHS) Portal</p>
</div>

{{-- ============================ BODY ============================ --}}
<h3 class="section">1. Parties</h3>
<p class="clause">This Service Level Agreement ("<strong>Agreement</strong>") is entered into on this <span class="blank">&nbsp;</span> day of <span class="blank">&nbsp;</span>, 20<span class="blank" style="min-width:30px">&nbsp;</span>, by and between:</p>
<p class="clause"><strong>1.1</strong> The <strong>Kano State Ministry of Land and Physical Planning</strong>, represented by the Honourable Commissioner, with its principal office at Kano State Secretariat, Kano, Kano State, Nigeria (hereinafter referred to as "<strong>the Ministry</strong>" or "<strong>KLAES</strong>");</p>
<p class="clause" style="text-align:center"><strong>AND</strong></p>
<p class="clause"><strong>1.2</strong> <span class="fill">{{ $orgName }}</span>, a {{ $orgType }} duly incorporated under the laws of the Federal Republic of Nigeria, with its registered office at <span class="fill">{{ $orgAddress }}</span> (hereinafter referred to as "<strong>the Subscriber</strong>" or "<strong>the Organization</strong>").</p>
<p class="clause">(Collectively referred to as "the Parties" and individually as "a Party")</p>

<h3 class="section">2. Recitals</h3>
<p class="clause"><strong>WHEREAS:</strong></p>
<p class="clause">A. The Ministry operates the Kano State Land Administration Enterprise System (KLAES), which includes the Property History Search (PHS) Portal;</p>
<p class="clause">B. The PHS Portal is a specialized digital platform designed to provide Financial and Legal Institutions with secure, verified access to property history records and land administration data within Kano State;</p>
<p class="clause">C. The Organization desires to subscribe to the PHS Portal services for the purpose of conducting property history searches in connection with its legitimate business operations;</p>
<p class="clause">D. The Ministry agrees to provide such services subject to the terms and conditions set forth in this Agreement;</p>
<p class="clause"><strong>NOW THEREFORE</strong>, in consideration of the mutual covenants contained herein, the Parties agree as follows:</p>

<h3 class="section">3. Definitions</h3>
<p class="clause">In this Agreement, unless the context otherwise requires:</p>
<ul class="lst">
    <li><strong>"PHS Portal"</strong> means the Property History Search Portal operated by the Ministry under the KLAES framework</li>
    <li><strong>"Search Token"</strong> means a digital credit unit purchased by the Subscriber to conduct property history searches on the PHS Portal</li>
    <li><strong>"Authorized User"</strong> means an employee or agent of the Subscriber who has been granted login credentials to access the PHS Portal</li>
    <li><strong>"Property History Report"</strong> means the digital document generated by the PHS Portal containing verified land records and property transaction history</li>
    <li><strong>"Confidential Information"</strong> means all non-public information disclosed by either Party, including but not limited to search queries, property data, user credentials, and business information</li>
</ul>

<h3 class="section">4. Scope of Services</h3>
<h4 class="sub">4.1 Services Provided</h4>
<p class="clause">The Ministry shall provide the Subscriber with:</p>
<p class="clause">a) Secure web-based access to the PHS Portal at https://app.klaes.ng/phs/</p>
<p class="clause">b) Ability to conduct property history searches using Search Tokens</p>
<p class="clause">c) Generation of verified Property History Reports containing:</p>
<ul class="lst">
    <li>Certificate of Occupancy details</li>
    <li>Right of Occupancy records</li>
    <li>Deeds registration history</li>
    <li>Encumbrances and caveats</li>
    <li>Property transaction records</li>
    <li>Ownership history</li>
</ul>
<p class="clause">d) User account management for Authorized Users</p>
<p class="clause">e) Technical support as specified in Section 7</p>
<h4 class="sub">4.2 Service Availability</h4>
<p class="clause">The Ministry shall use reasonable efforts to ensure the PHS Portal is available:</p>
<ul class="lst">
    <li><strong>Standard Hours:</strong> 24 hours a day, 7 days a week</li>
    <li><strong>Scheduled Maintenance:</strong> With minimum 48 hours prior notification</li>
    <li><strong>Target Uptime:</strong> 95% monthly availability (excluding scheduled maintenance and force majeure events)</li>
</ul>

<h3 class="section">5. Subscription and Payment Terms</h3>
<h4 class="sub">5.1 Subscription Model</h4>
<p class="clause">a) This Agreement operates on an annual subscription basis with token-based access</p>
<p class="clause">b) The initial subscription term shall be twelve (12) months commencing from the Effective Date</p>
<p class="clause">c) The subscription shall automatically renew for successive twelve (12) month periods unless terminated by either Party with ninety (90) days written notice prior to renewal</p>
<h4 class="sub">5.2 Search Tokens</h4>
<p class="clause">a) The Subscriber shall purchase Search Tokens in advance to conduct property searches</p>
<p class="clause">b) Token pricing: @if($perToken)<span class="fill">₦{{ number_format($perToken, 2) }}</span>@else ₦<span class="blank">&nbsp;</span>@endif per Search Token (subject to annual review with 60 days notice)</p>
<p class="clause">c) Each Property History Report shall consume one (1) Search Token</p>
<p class="clause">d) Tokens are non-transferable and non-refundable except as required by law</p>
<p class="clause">e) Unused tokens at the end of the subscription term may be carried forward for a maximum of ninety (90) days</p>
<h4 class="sub">5.3 Payment Terms</h4>
<p class="clause">a) Annual subscription fee: @if($pkgPrice)<span class="fill">₦{{ number_format($pkgPrice, 2) }}</span>@else ₦<span class="blank">&nbsp;</span>@endif payable within thirty (30) days of invoice</p>
<p class="clause">b) Token purchases: Payable in advance via bank transfer, debit card, or other approved payment methods</p>
<p class="clause">c) All fees are exclusive of VAT and other applicable taxes</p>
<p class="clause">d) Late payments shall attract interest at 2% per month</p>

<h3 class="section">6. Authorized Users and Access Management</h3>
<h4 class="sub">6.1 User Accounts</h4>
<p class="clause">a) The Subscriber may designate up to @if($teamMembers)<span class="fill">{{ $teamMembers }}</span>@else <span class="blank">&nbsp;</span>@endif Authorized Users during the subscription term</p>
<p class="clause">b) Additional Authorized Users may be added upon payment of ₦<span class="blank">&nbsp;</span> per user per annum</p>
<p class="clause">c) The Subscriber shall:</p>
<ul class="lst">
    <li>Provide accurate information for each Authorized User</li>
    <li>Ensure each Authorized User maintains confidential login credentials</li>
    <li>Immediately notify the Ministry of any unauthorized access or credential compromise</li>
    <li>Deactivate access for employees who leave the Organization</li>
</ul>
<h4 class="sub">6.2 Access Restrictions</h4>
<p class="clause">a) Access to the PHS Portal is limited to Authorized Users only</p>
<p class="clause">b) The Subscriber shall not:</p>
<ul class="lst dash">
    <li>Share login credentials with non-authorized personnel</li>
    <li>Use the PHS Portal for purposes unrelated to legitimate business operations</li>
    <li>Attempt to circumvent security measures or access controls</li>
    <li>Use automated scripts or bots to conduct searches without prior written consent</li>
</ul>

<h3 class="section">7. Technical Support and Service Desk</h3>
<h4 class="sub">7.1 Support Availability</h4>
<p class="clause">The Ministry shall provide technical support:</p>
<ul class="lst">
    <li><strong>Business Hours:</strong> Monday – Friday, 8:00 AM – 5:00 PM (excluding public holidays)</li>
    <li><strong>Emergency Support:</strong> Available for critical system outages</li>
</ul>
<h4 class="sub">7.2 Response Times</h4>
<table class="grid">
    <thead>
        <tr><th style="width:28px">SN</th><th style="width:90px">Issue Severity</th><th>Definition</th><th>Initial Response</th><th>Resolution Target</th></tr>
    </thead>
    <tbody>
        <tr><td>1</td><td>Critical</td><td>Complete system outage affecting all users</td><td>Within 2 hours</td><td>Within 8 hours</td></tr>
        <tr><td>2</td><td>High</td><td>Major functionality impaired</td><td>Within 4 hours</td><td>Within 24 hours</td></tr>
        <tr><td>3</td><td>Medium</td><td>Minor functionality issue</td><td>Within 1 business day</td><td>Within 3 business days</td></tr>
        <tr><td>4</td><td>Low</td><td>General inquiry or enhancement request</td><td>Within 2 business days</td><td>As scheduled</td></tr>
    </tbody>
</table>
<h4 class="sub">7.3 Support Channels</h4>
<ul class="lst">
    <li><strong>Email:</strong> phsportal@kano.gov.ng</li>
    <li><strong>Phone:</strong> 0803 123 4567, 0909 876 5432</li>
    <li><strong>Portal Ticketing System:</strong> Available on PHS Portal dashboard</li>
</ul>

<h3 class="section">8. Data Security and Confidentiality</h3>
<h4 class="sub">8.1 Data Protection</h4>
<p class="clause">a) The Ministry shall implement industry-standard security measures to protect:</p>
<ul class="lst">
    <li>Property records and land administration data</li>
    <li>Subscriber search queries and reports</li>
    <li>User credentials and personal information</li>
    <li>Payment and transaction data</li>
</ul>
<p class="clause">b) All data transmissions shall be encrypted using SSL/TLS protocols</p>
<p class="clause">c) Regular security audits and penetration testing shall be conducted</p>
<h4 class="sub">8.2 Confidentiality Obligations</h4>
<p class="clause">a) Both Parties shall maintain the confidentiality of all Confidential Information received from the other Party</p>
<p class="clause">b) Confidential Information shall not be disclosed to third parties except:</p>
<ul class="lst">
    <li>To employees with a legitimate need to know</li>
    <li>As required by law or court order</li>
    <li>With prior written consent of the disclosing Party</li>
</ul>
<p class="clause">c) These confidentiality obligations shall survive termination of this Agreement for a period of five (5) years</p>
<h4 class="sub">8.3 Data Ownership</h4>
<p class="clause">a) All property records and land administration data remain the exclusive property of the Ministry and the Kano State Government</p>
<p class="clause">b) Property History Reports generated for the Subscriber are licensed for the Subscriber's internal business use only</p>
<p class="clause">c) The Subscriber shall not resell, redistribute, or commercially exploit Property History Reports without prior written consent</p>

<h3 class="section">9. Subscriber Obligations</h3>
<p class="clause">The Subscriber shall:</p>
<p class="clause">a) Use the PHS Portal solely for legitimate business purposes related to:</p>
<ul class="lst">
    <li>Mortgage processing and loan underwriting</li>
    <li>Legal due diligence for property transactions</li>
    <li>Property valuation and assessment</li>
    <li>Risk management and compliance</li>
</ul>
<p class="clause">b) Ensure all Authorized Users comply with:</p>
<ul class="lst">
    <li>This Agreement</li>
    <li>Applicable data protection laws</li>
    <li>Professional ethics and confidentiality requirements</li>
</ul>
<p class="clause">c) Maintain accurate and up-to-date organizational information</p>
<p class="clause">d) Promptly report any suspected security breaches or system anomalies</p>
<p class="clause">e) Not attempt to:</p>
<ul class="lst">
    <li>Reverse engineer the PHS Portal</li>
    <li>Access data beyond authorized scope</li>
    <li>Interfere with system operations</li>
    <li>Impersonate other users or entities</li>
</ul>

<h3 class="section">10. Intellectual Property Rights</h3>
<h4 class="sub">10.1 Ministry Rights</h4>
<p class="clause">a) The Ministry retains all intellectual property rights in:</p>
<ul class="lst">
    <li>The PHS Portal software and infrastructure</li>
    <li>Property records and land administration databases</li>
    <li>Search algorithms and report templates</li>
    <li>Trademarks, logos, and branding</li>
</ul>
<h4 class="sub">10.2 Limited License</h4>
<p class="clause">The Ministry grants the Subscriber a non-exclusive, non-transferable, revocable license to:</p>
<ul class="lst">
    <li>Access and use the PHS Portal during the subscription term</li>
    <li>Generate and retain Property History Reports for internal business use</li>
    <li>Display reports to clients in connection with legitimate transactions</li>
</ul>

<h3 class="section">11. Warranties and Disclaimers</h3>
<h4 class="sub">11.1 Ministry Warranties</h4>
<p class="clause">The Ministry warrants that:</p>
<p class="clause">a) It has the legal authority to provide access to the property records contained in the PHS Portal</p>
<p class="clause">b) The PHS Portal will perform substantially in accordance with the documentation provided</p>
<p class="clause">c) Reasonable efforts will be made to ensure the accuracy of property records based on information available in the Ministry's databases</p>
<h4 class="sub">11.2 Disclaimer</h4>
<p class="clause caps">Except as expressly warranted in Section 11.1, the PHS Portal and all services are provided "as is" and "as available" without warranties of any kind, either express or implied, including but not limited to:</p>
<p class="clause caps">a) Implied warranties of merchantability, fitness for a particular purpose, or non-infringement</p>
<p class="clause caps">b) Warranties regarding the accuracy, completeness, or currency of property records (which are dependent on the quality and timeliness of information submitted to the Ministry)</p>
<p class="clause caps">c) Warranties that the Portal will be uninterrupted, error-free, or free from viruses or other harmful components</p>
<h4 class="sub">11.3 No Legal Advice</h4>
<p class="clause">The Ministry does not provide legal advice through the PHS Portal. Property History Reports are informational tools and should not be construed as legal opinions. Subscribers should seek independent legal counsel for transaction-specific advice.</p>

<h3 class="section">12. Limitation of Liability</h3>
<h4 class="sub">12.1 Exclusion of Consequential Damages</h4>
<p class="clause caps">In no event shall the Ministry be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to:</p>
<p class="clause">a) Loss of profits, revenue, or business opportunities</p>
<p class="clause">b) Loss of data or corruption of records</p>
<p class="clause">c) Business interruption</p>
<p class="clause">d) Damages arising from reliance on Property History Reports</p>
<h4 class="sub">12.2 Cap on Liability</h4>
<p class="clause caps">The Ministry's total aggregate liability under this Agreement, whether in contract, tort (including negligence), strict liability, or otherwise, shall not exceed the total amount paid by the Subscriber to the Ministry under this Agreement in the twelve (12) months preceding the event giving rise to the claim.</p>
<h4 class="sub">12.3 Exceptions</h4>
<p class="clause">The limitations in this Section 12 shall not apply to:</p>
<p class="clause">a) Liability arising from the Ministry's gross negligence or willful misconduct</p>
<p class="clause">b) Breach of confidentiality obligations</p>
<p class="clause">c) Infringement of intellectual property rights</p>
<p class="clause">d) Liability that cannot be excluded under applicable law</p>

<h3 class="section">13. Indemnification</h3>
<h4 class="sub">13.1 Subscriber Indemnity</h4>
<p class="clause">The Subscriber shall indemnify, defend, and hold harmless the Ministry, its officers, employees, and agents from and against any claims, damages, losses, liabilities, costs, and expenses (including reasonable legal fees) arising from:</p>
<p class="clause">a) The Subscriber's breach of this Agreement</p>
<p class="clause">b) Unauthorized use of the PHS Portal by the Subscriber's employees or agents</p>
<p class="clause">c) Misuse of Property History Reports</p>
<p class="clause">d) Violation of applicable laws or third-party rights</p>
<h4 class="sub">13.2 Ministry Indemnity</h4>
<p class="clause">The Ministry shall indemnify the Subscriber against third-party claims alleging that the PHS Portal (as provided by the Ministry) infringes any copyright, trademark, or patent, provided that the Subscriber:</p>
<p class="clause">a) Promptly notifies the Ministry of such claim</p>
<p class="clause">b) Allows the Ministry sole control of the defense and settlement</p>
<p class="clause">c) Provides reasonable cooperation in the defense</p>

<h3 class="section">14. Term and Termination</h3>
<h4 class="sub">14.1 Term</h4>
<p class="clause">This Agreement shall commence on the Effective Date and continue for the initial twelve (12) month subscription term, with automatic renewal for successive twelve (12) month periods unless terminated as provided herein.</p>
<h4 class="sub">14.2 Termination for Convenience</h4>
<p class="clause">Either Party may terminate this Agreement without cause by providing ninety (90) days written notice to the other Party prior to the end of the then-current subscription term.</p>
<h4 class="sub">14.3 Termination for Cause</h4>
<p class="clause">Either Party may terminate this Agreement immediately upon written notice if:</p>
<p class="clause">a) The other Party materially breaches this Agreement and fails to cure such breach within thirty (30) days of written notice</p>
<p class="clause">b) The other Party becomes insolvent, files for bankruptcy, or has a receiver appointed</p>
<p class="clause">c) The other Party ceases to conduct business in the normal course</p>
<h4 class="sub">14.4 Effect of Termination</h4>
<p class="clause">Upon termination:</p>
<p class="clause">a) The Subscriber's access to the PHS Portal shall cease</p>
<p class="clause">b) The Subscriber may retain Property History Reports generated prior to termination for record-keeping and ongoing transaction purposes</p>
<p class="clause">c) Unused Search Tokens shall be forfeited unless termination is initiated by the Ministry for convenience</p>
<p class="clause">d) Confidentiality obligations shall survive termination</p>
<p class="clause">e) Sections 11 (Warranties), 12 (Limitation of Liability), 13 (Indemnification), 15 (Force Majeure), 16 (Governing Law), and 17 (Dispute Resolution) shall survive termination</p>

<h3 class="section">15. Force Majeure</h3>
<p class="clause">Neither Party shall be liable for any failure or delay in performing its obligations under this Agreement (except payment obligations) if such failure or delay is caused by circumstances beyond its reasonable control, including but not limited to:</p>
<p class="clause">a) Acts of God, natural disasters, or epidemics</p>
<p class="clause">b) War, terrorism, civil unrest, or government action</p>
<p class="clause">c) Power outages, telecommunications failures, or internet service disruptions</p>
<p class="clause">d) Labor disputes or strikes</p>
<p class="clause">e) Fires, floods, or other catastrophic events</p>
<p class="clause">The affected Party shall notify the other Party within five (5) business days of the force majeure event and use reasonable efforts to mitigate its impact.</p>

<h3 class="section">16. Governing Law and Jurisdiction</h3>
<h4 class="sub">16.1 Governing Law</h4>
<p class="clause">This Agreement shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria and the laws of Kano State.</p>
<h4 class="sub">16.2 Jurisdiction</h4>
<p class="clause">The Parties submit to the exclusive jurisdiction of the courts of Kano State, Nigeria, for the resolution of any disputes arising out of or relating to this Agreement.</p>

<h3 class="section">17. Dispute Resolution</h3>
<h4 class="sub">17.1 Amicable Resolution</h4>
<p class="clause">In the event of any dispute, controversy, or claim arising out of or relating to this Agreement, the Parties shall first attempt to resolve the matter amicably through good-faith negotiations between senior executives within thirty (30) days of written notice of the dispute.</p>
<h4 class="sub">17.2 Mediation</h4>
<p class="clause">If negotiation fails, the Parties agree to submit the dispute to mediation before a mutually agreed mediator in Kano, Kano State, Nigeria.</p>
<h4 class="sub">17.3 Litigation</h4>
<p class="clause">If mediation is unsuccessful within sixty (60) days, either Party may pursue legal remedies in the courts of competent jurisdiction in Kano State, Nigeria.</p>

<h3 class="section">18. General Provisions</h3>
<h4 class="sub">18.1 Entire Agreement</h4>
<p class="clause">This Agreement, together with any schedules and exhibits attached hereto, constitutes the entire agreement between the Parties with respect to the subject matter hereof and supersedes all prior or contemporaneous agreements, understandings, negotiations, and discussions, whether oral or written.</p>
<h4 class="sub">18.2 Amendments</h4>
<p class="clause">No amendment or modification of this Agreement shall be valid or binding unless made in writing and signed by authorized representatives of both Parties.</p>
<h4 class="sub">18.3 Severability</h4>
<p class="clause">If any provision of this Agreement is held to be invalid, illegal, or unenforceable, the validity, legality, and enforceability of the remaining provisions shall not in any way be affected or impaired thereby.</p>
<h4 class="sub">18.4 Waiver</h4>
<p class="clause">No waiver of any provision of this Agreement shall be effective unless made in writing and signed by the waiving Party. No failure or delay by a Party in exercising any right shall operate as a waiver thereof.</p>
<h4 class="sub">18.5 Assignment</h4>
<p class="clause">The Subscriber shall not assign, transfer, or subcontract any of its rights or obligations under this Agreement without the prior written consent of the Ministry. The Ministry may assign this Agreement in connection with a merger, acquisition, or transfer of substantially all of its assets.</p>
<h4 class="sub">18.6 Notices</h4>
<p class="clause">All notices under this Agreement shall be in writing and delivered by:</p>
<p class="clause">a) Registered mail with return receipt requested</p>
<p class="clause">b) Reputable courier service</p>
<p class="clause">c) Email with confirmation of receipt</p>
<p class="clause">Notices shall be sent to the addresses specified in Section 1 or such other addresses as may be designated in writing.</p>
<h4 class="sub">18.7 Relationship of Parties</h4>
<p class="clause">Nothing in this Agreement shall be construed to create a partnership, joint venture, or agency relationship between the Parties. Each Party is an independent contractor.</p>
<h4 class="sub">18.8 Third-Party Beneficiaries</h4>
<p class="clause">This Agreement is intended solely for the benefit of the Parties and their respective successors and permitted assigns. No third party shall have any right to enforce any provision of this Agreement.</p>
<h4 class="sub">18.9 Counterparts</h4>
<p class="clause">This Agreement may be executed in counterparts, each of which shall be deemed an original, but all of which together shall constitute one and the same instrument. Electronic signatures shall be deemed valid and binding.</p>

<h3 class="section">19. Schedules</h3>
<ul class="lst">
    <li>Schedule A: Subscription Fees and Token Pricing</li>
    <li>Schedule B: Authorized Users List</li>
    <li>Schedule C: Service Specifications and Technical Requirements</li>
    <li>Schedule D: Data Protection and Privacy Policy</li>
</ul>

<h3 class="section">20. Execution</h3>
<p class="clause">IN WITNESS WHEREOF, the Parties hereto have caused this Agreement to be executed by their duly authorized representatives as of the date first written above.</p>

<div class="section-block">
    <div class="exec-party">
        <p class="for">FOR: KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
        <div class="sig-line">
            <p>For Honourable Commissioner</p>
            <p>Kano State Ministry of Land and Physical Planning</p>
            <p>Date: {{ $blank }}</p>
        </div>
    </div>

    <div class="exec-party">
        <p class="for">FOR: {{ strtoupper($orgName) }}</p>
        <div class="sig-line">
            <p>Name: {{ $contactName ?: $blank }}</p>
            <p>Title: {{ $contactTitle ?: $blank }}</p>
            <p>{{ $orgName }}</p>
            <p>Date: {{ $blank }}</p>
        </div>
    </div>

    <div class="exec-party">
        <p class="for">WITNESSED BY:</p>
        <div class="sig-line">
            <p>Name: {{ $blank }}</p>
            <p>Title: {{ $blank }}</p>
            <p>Date: {{ $blank }}</p>
        </div>
    </div>
</div>
</body>
</html>
