{{--
    Page 2 of every Online Legal Search report: the Disclaimer and Terms of Use.

    Condensed from resources/views/phs/disclaimer-template.blade.php, which is a
    standalone multi-page A4-portrait document. Every one of its ten sections is
    kept, along with the statute citations and penalties — the wording is
    tightened and set in two columns on a landscape sheet so it lands on exactly
    one page. Keep the two in step: if the PHS disclaimer changes substantively,
    change this too.

    Two columns are a table, not CSS columns — DomPDF does not implement
    column-count.
--}}
@php
    $lastUpdated = $lastUpdated ?? now()->format('F j, Y');
    $verifyUrl   = 'www.klaes.gov.ng/verify';
@endphp

<div class="disclaimer-page">
    <div class="dp-head">
        <h1>DISCLAIMER AND TERMS OF USE</h1>
        <p class="dp-sub">Online Legal Search — Kano State Ministry of Land and Physical Planning</p>
        <p class="dp-notice">IMPORTANT NOTICE — PLEASE READ CAREFULLY</p>
    </div>

    <table class="dp-cols">
        <tr>
            <td class="dp-col">
                <h2>1. Authorized Use Only</h2>
                <p>This Online Legal Search platform is operated by the Kano State Ministry of Land and Physical Planning
                   under the Kano State Land Administration Enterprise System (KLAES). Access is restricted to users who
                   have paid the prescribed fee and whose request has been approved by the Ministry. Unauthorized access,
                   attempted access, or use is strictly prohibited and may result in criminal prosecution under applicable
                   Nigerian laws, including the Cybercrimes (Prohibition, Prevention, etc.) Act 2015.</p>

                <h2>2. No Liability for Alteration or Misuse</h2>
                <p><strong>TAKE NOTICE</strong> that the Ministry, its officers, employees, agents and the Kano State
                   Government shall not be liable for: (a) any alteration, modification, forgery or tampering with this
                   report by any person or entity; (b) misrepresentation or misuse of search results for fraudulent,
                   illegal or unauthorized purposes; (c) reliance by any third party on an altered, forged or tampered
                   document; or (d) any loss, damage or injury arising from the unauthorized use, distribution or
                   presentation of search results.</p>
                <p>Every report issued carries a unique verification/QR code, a digital watermark, the timestamp of
                   generation and the identity of the issuing officer. It is the responsibility of any recipient to
                   verify the authenticity of a report using those features.</p>

                <h2>3. Criminal Offence Warning</h2>
                <p><strong>BE ADVISED</strong> that it is a <span class="dp-red">CRIMINAL OFFENCE</span> punishable by law
                   to: (a) falsify, alter, forge or tamper with any Legal Search Report, Certificate of Occupancy, Right
                   of Occupancy or other land document issued by or accessible through the Ministry; (b) use or present
                   any altered, forged or falsified land document for any purpose, including mortgage applications,
                   property transactions, court proceedings, loan applications and due-diligence processes;
                   (c) impersonate an authorized user; or (d) circumvent security measures or access data beyond the
                   authorized scope.</p>
                <p>Such acts constitute offences under: the <strong>Criminal Code Act</strong> (Cap C38 LFN 2004) — s.363
                   Forgery and s.364 Uttering forged documents (up to 14 years' imprisonment), s.366 Fraudulent disposal
                   of property (up to 7 years); the <strong>Cybercrimes Act 2015</strong> — s.6 System Interference (up to
                   10 years or ₦10,000,000 or both), s.7 Cyberstalking (up to 3 years or ₦7,000,000 or both), s.14
                   Identity Theft (up to 7 years or ₦7,000,000 or both); the <strong>Land Use Act 1978</strong> — s.16
                   and provisions on fraudulent acquisition or presentation of land documents; and the
                   <strong>Penal Code (Northern States) Federal Provisions Act</strong> on forgery, cheating and fraud.</p>
                <p>The Ministry reports all suspected forgery, alteration or fraud to law enforcement, cooperates fully
                   with the Nigeria Police Force, the EFCC, the ICPC and other authorities, and pursues criminal and
                   civil remedies to the fullest extent of the law.</p>

                <h2>4. Verification of Authenticity</h2>
                <p>Every genuine report bears the official Ministry letterhead and seal, a unique document reference
                   number, a QR code for instant verification, the authorized officer's signature, the timestamp of
                   generation and an official watermark. To verify a report: visit {{ $verifyUrl }}, scan the QR code with
                   any QR reader, enter the reference number on the Ministry's website, or contact the Ministry directly.</p>
                <p>Any document that lacks these verification features, contains spelling or formatting inconsistencies,
                   has been photocopied or scanned without verification, or shows signs of alteration
                   <span class="dp-red">SHOULD BE REPORTED IMMEDIATELY</span> to the Ministry and to law enforcement.</p>
            </td>

            <td class="dp-col">
                <h2>5. Informational Purpose Only</h2>
                <p>This report is provided for <strong>INFORMATIONAL PURPOSES ONLY</strong>. It does not constitute legal
                   advice or opinion, does not guarantee title or ownership, does not replace comprehensive legal due
                   diligence, and does not warrant that the information is complete, current or free from error. Users are
                   advised to seek independent legal counsel before entering into any property transaction, to conduct a
                   physical inspection of the property, to verify information with multiple sources, and not to rely
                   solely on search results for critical business decisions.</p>

                <h2>6. Data Accuracy Disclaimer</h2>
                <p>While the Ministry makes reasonable efforts to ensure the accuracy of the KLAES database, the
                   information depends on the quality and timeliness of data submitted by various sources; historical
                   records may contain errors, omissions or inconsistencies; recent transactions may not yet be reflected
                   in the system; and discrepancies may exist between digital records and physical documents.</p>
                <p>The Ministry does not warrant that the database is complete or error-free, does not guarantee that all
                   encumbrances, caveats or interests are recorded, and shall not be liable for decisions made on the
                   basis of incomplete or inaccurate information.</p>

                <h2>7. Intellectual Property</h2>
                <p>All reports, data and information accessible through this platform are the intellectual property of the
                   Kano State Ministry of Land and Physical Planning and/or the Kano State Government. Unauthorized
                   reproduction, distribution, commercial exploitation or resale is strictly prohibited and may result in
                   immediate termination of access, forfeiture of all fees paid, and civil and criminal legal action.</p>

                <h2>8. Acceptance of Terms</h2>
                <p><strong>BY ACCESSING AND USING THIS PLATFORM, YOU ACKNOWLEDGE AND AGREE THAT:</strong> you have read,
                   understood and accept this Disclaimer and Terms of Use; you will use the platform only for lawful and
                   authorized purposes; you will not attempt to alter, forge or misuse any document generated; you
                   understand the criminal penalties for document forgery and fraud; you will verify the authenticity of
                   all reports before relying on them; you accept all risks associated with the use of this platform; and
                   you will indemnify the Ministry against claims arising from your misuse.</p>
                <p class="dp-red">If you do not agree to these terms, you must not access or use this platform.</p>

                <h2>9. Reporting Suspicious Activity</h2>
                <p>If you suspect that a Legal Search Report has been altered or forged, that someone is using this
                   platform fraudulently, or that you have received a fraudulent land document, report it immediately to:</p>
                <p class="dp-contact"><strong>Kano State Ministry of Land and Physical Planning</strong><br>
                   Email: phsportal@kano.gov.ng, reportfraud@kano.gov.ng<br>
                   Phone: 0803 123 4567, 0909 876 5432<br>
                   Address: Ministry of Land and Physical Planning, Kano State Secretariat, Kano</p>
                <p>And to law enforcement: the Nigeria Police Force (Cybercrime Unit), the Economic and Financial Crimes
                   Commission (EFCC), and the Independent Corrupt Practices Commission (ICPC).</p>

                <h2>10. Amendments</h2>
                <p>The Ministry reserves the right to amend this Disclaimer at any time without prior notice. Amendments
                   are effective immediately upon posting to the platform, and continued use constitutes acceptance of the
                   amended terms.</p>
                <p><strong>LAST UPDATED:</strong> {{ $lastUpdated }}</p>
                <p>© {{ date('Y') }} Kano State Ministry of Land and Physical Planning — All Rights Reserved.</p>
                <p class="dp-tagline">Secure. Trusted. Government Powered.</p>
            </td>
        </tr>
    </table>
</div>
