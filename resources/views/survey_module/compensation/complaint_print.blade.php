{{--
    Compensation Complaint Form, Survey Department: the printed form as amended
    by hand on 2026-10-29 (docs/KLAES Survey Module – Workflow_V02.md 2.4).
    Blank for walk-in complainants, or filled from a logged complaint for signing.

    Standalone, no app chrome: this is signed and filed.
--}}
@php
    use App\Models\Survey\SurveyComplaint;
    use App\Models\Survey\SurveyProject;

    $c   = $complaint;
    $v   = fn ($x) => $blank ? '' : (string) $x;
    $box = fn (bool $on) => (! $blank && $on) ? '&#9746;' : '&#9744;';
    $amt = fn ($x) => ($blank || $x === null) ? '' : number_format((float) $x, 2);
    $cat = $blank ? null : $c->category_key;
    $sch = $blank ? null : $c->compensation_type;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Compensation Complaint Form{{ $blank ? '' : ' ' . $c->complaint_ref }}</title>
    <style>
        @page { size: A4; margin: 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; }
        .sheet { max-width: 180mm; margin: 0 auto; }
        .page-break { page-break-before: always; }

        .head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #999; padding-bottom: 8px; }
        .head img { height: 70px; }
        .head .t { text-align: center; color: #1a4d38; }
        .head .t .m { font-weight: bold; font-size: 14px; text-transform: uppercase; }
        .head .t .d { font-weight: bold; font-size: 18px; color: #000; margin-top: 4px; }
        .head .t .f { font-weight: bold; font-size: 15px; margin-top: 2px; }
        .ref { text-align: right; font-size: 11px; margin: 6px 0 0; }

        .instr { border-top: 2px solid #1a4d38; margin-top: 10px; padding-top: 6px; font-size: 11.5px; }
        .instr i { font-style: italic; }
        h2 { font-size: 12.5px; color: #1a4d38; text-transform: uppercase; letter-spacing: .04em; margin: 14px 0 6px;
             padding-bottom: 3px; border-bottom: 1px solid #c9a54a; }
        h3 { font-size: 12px; margin: 10px 0 2px; }
        .desc { font-size: 11px; margin: 0 0 6px 18px; }
        .desc i { font-style: italic; }

        table.f { width: 100%; border-collapse: collapse; }
        table.f th { background: #1a4d38; color: #fff; text-align: left; padding: 6px 8px; font-size: 11.5px; }
        table.f td { border: 1px solid #9aa9a2; padding: 6px 8px; vertical-align: top; }
        table.f td.l { width: 45%; background: #f1f4f2; font-weight: bold; font-size: 11.5px; }
        table.f td.v { height: 30px; white-space: pre-wrap; }
        table.f td.tall { height: 52px; }

        .opts span { display: inline-block; margin-right: 18px; }
        .docs { width: 100%; border-collapse: collapse; }
        .docs td { padding: 4px 6px; background: #f1f4f2; border-bottom: 2px solid #fff; width: 50%; }
        .line { display: inline-block; border-bottom: 1px solid #000; min-width: 40mm; padding: 0 4px; }
        .decl p { margin: 6px 0 12px; }
        .decl .row { display: flex; align-items: flex-end; gap: 6px; margin: 12px 0; white-space: nowrap; }
        .decl .row .line { min-width: 0 !important; flex: 1; }
        .decl .row .line.s { flex: .6; }

        table.off { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.off th { background: #1a1a1a; color: #fff; padding: 6px; font-size: 11px; font-weight: normal; }
        table.off td { border: 1px solid #555; padding: 6px; vertical-align: top; height: 70px; font-size: 11px; }
        .foot { margin-top: 14px; text-align: center; font-size: 10px; color: #444; }

        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">

    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('survey-module.compensation.complaints', $blank ? [] : ['tab' => $c->category_key]) }}">Back</a>
    </div>

    {{-- ================= Page 1 ================= --}}
    <div class="head">
        <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="" />
        <div class="t">
            <div class="m">Kano State Ministry of Land and Physical Planning</div>
            <div class="d">SURVEY DEPARTMENT</div>
            <div class="f">COMPENSATION COMPLAINT FORM</div>
        </div>
        <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" />
    </div>
    @unless ($blank)
        <div class="ref"><strong>Complaint No.:</strong> {{ $c->complaint_ref }}</div>
    @endunless

    <div class="instr">
        <strong>Instructions:</strong> <i>This form must be completed by the aggrieved party or their authorised representative. Please select
        the appropriate complaint category and provide all required details.</i><br />
        <i>All fields marked with (*) are mandatory. Fields explicitly marked "Optional" do not require completion.</i>
    </div>

    <h2>Section 1: Complainant Details</h2>
    <table class="f">
        <tr><th>Field</th><th>Details</th></tr>
        <tr><td class="l">Full Name of Farm/Property Owner *</td><td class="v">{{ $v($c->complainant_name) }}</td></tr>
        <tr><td class="l">Farm No.</td><td class="v">{{ $v($c->farm_no) }}</td></tr>
        <tr><td class="l">Section</td><td class="v">{{ $v($c->section_no) }}</td></tr>
        <tr>
            <td class="l">Project <span style="font-weight:normal;">(Layout / Facility / Public — Road, School, etc.)</span></td>
            <td class="v">{{ $blank ? '' : collect([$c->project ? $c->project->project_code . ' · ' . $c->project->name : null, $c->project_type])->filter()->implode(' — ') }}</td>
        </tr>
        <tr><td class="l">Phone Number *</td><td class="v">{{ $v($c->complainant_phone) }}</td></tr>
        <tr><td class="l">Email Address (Optional)</td><td class="v">{{ $v($c->complainant_email) }}</td></tr>
        <tr><td class="l">Name of Authorised Representative (if applicable)</td><td class="v">{{ $v($c->representative_name) }}</td></tr>
        <tr><td class="l">Relationship to Owner</td><td class="v">{{ $v($c->representative_relationship) }}</td></tr>
    </table>

    <h2>Section 2: Property &amp; Compensation Details</h2>
    <table class="f">
        <tr><th>Field</th><th>Details</th></tr>
        <tr><td class="l">File Reference / CO Number *</td><td class="v">{{ $v($c->file_reference) }}</td></tr>
        <tr><td class="l">Map Sheet No (MS No) *</td><td class="v">{{ $v($c->map_sheet_no) }}</td></tr>
        <tr><td class="l">Location / District *</td><td class="v">{{ $blank ? '' : collect([$c->district, $c->lga])->filter()->implode(', ') }}</td></tr>
        <tr>
            <td class="l">
                {!! $box($sch === SurveyProject::SCHEME_MONETARY) !!} A — Original Compensation Amount (₦) * <span style="font-weight:normal;">(Monetary)</span>
            </td>
            <td class="v">{{ $sch === SurveyProject::SCHEME_MONETARY ? $amt($c->original_amount) : '' }}</td>
        </tr>
        <tr>
            <td class="l">
                {!! $box($sch === SurveyProject::SCHEME_LAND) !!} B — No. of Plots * <span style="font-weight:normal;">(Land to Land)</span>
            </td>
            <td class="v">{{ $sch === SurveyProject::SCHEME_LAND ? $v($c->original_plots) : '' }}</td>
        </tr>
    </table>

    <h2>Section 3: Complaint Category (Select One)</h2>
    <h3>{!! $box($cat === SurveyComplaint::CAT_INADEQUATE) !!} Category 1: Inadequate Compensation</h3>
    <p class="desc"><strong>Description:</strong> <i>The assessed value of the economic trees, crops, or structures does not reflect the current market value or the actual quantity on the land.</i></p>
    <table class="f">
        <tr><td class="l">Details of Discrepancy *</td><td class="v tall">{{ $cat === SurveyComplaint::CAT_INADEQUATE ? $v($c->description) : '' }}</td></tr>
        <tr>
            <td class="l">{!! $box($cat === SurveyComplaint::CAT_INADEQUATE && $sch === SurveyProject::SCHEME_MONETARY) !!} A — Expected/Correct Valuation (₦) *</td>
            <td class="v">{{ $cat === SurveyComplaint::CAT_INADEQUATE && $sch === SurveyProject::SCHEME_MONETARY ? $amt($c->expected_amount) : '' }}</td>
        </tr>
        <tr>
            <td class="l">{!! $box($cat === SurveyComplaint::CAT_INADEQUATE && $sch === SurveyProject::SCHEME_LAND) !!} B — Expected/Correct No. of Plots * <span style="font-weight:normal;">(Land to Land)</span></td>
            <td class="v">{{ $cat === SurveyComplaint::CAT_INADEQUATE && $sch === SurveyProject::SCHEME_LAND ? $v($c->expected_plots) : '' }}</td>
        </tr>
    </table>

    <div class="foot">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING · COMPENSATION COMPLAINT FORM · Page 1 of 2</div>

    {{-- ================= Page 2 ================= --}}
    <div class="page-break"></div>

    <h3>{!! $box($cat === SurveyComplaint::CAT_MISIDENTIFICATION) !!} Category 2: Misidentification of Farmland/Property</h3>
    <p class="desc"><strong>Description:</strong> <i>The farm owner assigned an authorized person to show/identify the farmland, and this person incorrectly identified the wrong farm/plot for compensation.</i></p>
    <table class="f">
        @php $m = $cat === SurveyComplaint::CAT_MISIDENTIFICATION; @endphp
        <tr><td class="l">Name of Field Officer</td><td class="v">{{ $m ? $v($c->field_officer_name) : '' }}</td></tr>
        <tr><td class="l">Correct Plot/Coordinates of the Actual Farm</td><td class="v">{{ $m ? $v($c->correct_plot_coordinates) : '' }}</td></tr>
        <tr><td class="l">Details of the Misidentification</td><td class="v tall">{{ $m ? $v($c->description) : '' }}</td></tr>
    </table>

    <h3>{!! $box($cat === SurveyComplaint::CAT_BOUNDARY) !!} Category 3: Boundary Dispute</h3>
    @php $bd = $cat === SurveyComplaint::CAT_BOUNDARY; @endphp
    <p class="desc opts">
        <strong>Type of Boundary Dispute:</strong>
        @foreach (SurveyComplaint::BOUNDARY_TYPES as $bt)
            <span>{!! $box($bd && $c->boundary_type === $bt) !!} {{ $bt }}</span>
        @endforeach
    </p>
    <table class="f">
        <tr><td class="l">Name of Affected Neighbor/Party (if applicable)</td><td class="v">{{ $bd ? $v($c->affected_party) : '' }}</td></tr>
        <tr><td class="l">Details of the Encroachment/Dispute</td><td class="v tall">{{ $bd ? $v($c->description) : '' }}</td></tr>
    </table>

    <h2>Section 4: Supporting Documents</h2>
    <p class="desc" style="margin-left:0;"><i>Please attach the following where applicable:</i></p>
    <table class="docs">
        <tr>
            <td>{!! $box($c->hasDocument('compensation_card')) !!} Copy of Original Compensation Card</td>
            <td>{!! $box($c->hasDocument('valid_id')) !!} Valid Identification (NIN / Voter's Card / Passport)</td>
        </tr>
        <tr>
            <td>{!! $box($c->hasDocument('proof_of_ownership')) !!} Proof of Ownership / Allocation Letter</td>
            <td>{!! $box($c->hasDocument('photographs')) !!} Photographs of the Property/Farm</td>
        </tr>
        <tr><td colspan="2">{!! $box($c->hasDocument('other')) !!} Any Other Relevant Documents:</td></tr>
    </table>
    <p><strong>Other relevant documents / reference:</strong> <span class="line" style="min-width:110mm;">{{ $v($c->other_documents) }}</span></p>

    <h2>Section 5: Declaration &amp; Signature</h2>
    <div class="decl">
        <p>I hereby declare that the information provided in this complaint form is true and accurate to the best of my knowledge. I
        understand that providing false information may lead to the rejection of this complaint and potential legal consequences.</p>
        <div class="row">
            <strong>Complainant's Name:</strong> <span class="line" style="min-width:60mm;">{{ $v($c->complainant_name) }}</span>
            <strong>Signature:</strong> <span class="line s" style="min-width:35mm;">&nbsp;</span>
            <strong>Date:</strong> <span class="line s" style="min-width:25mm;">&nbsp;</span>
        </div>
        <div class="row">
            <strong>Authorized Representative's Name (if applicable):</strong> <span class="line" style="min-width:45mm;">{{ $v($c->representative_name) }}</span>
            <strong>Signature:</strong> <span class="line s" style="min-width:25mm;">&nbsp;</span>
            <strong>Date:</strong> <span class="line s" style="min-width:20mm;">&nbsp;</span>
        </div>
    </div>

    <h2>For Official Use Only (Survey Department)</h2>
    <table class="off">
        <tr><th>Action Taken By</th><th>Date Received</th><th>Assigned To</th><th>Status</th><th>Remarks</th></tr>
        <tr>
            <td></td>
            <td>{{ $blank ? '' : optional($c->received_date)->format('d M Y') }}</td>
            <td>{{ $v($c->assigned_to) }}</td>
            <td style="white-space:nowrap;">
                @foreach (SurveyComplaint::STATUSES as $s)
                    {!! $box($c->status === $s) !!} {{ $s }}<br />
                @endforeach
            </td>
            <td style="white-space:pre-wrap;font-size:10px;">{{ $v($c->resolution) }}</td>
        </tr>
    </table>
    <p style="margin-top:18px;"><strong>Signature of Receiving Officer:</strong> <span class="line" style="min-width:70mm;">&nbsp;</span>
        <strong>Date:</strong> <span class="line" style="min-width:30mm;">&nbsp;</span></p>

    <div class="foot">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING · COMPENSATION COMPLAINT FORM · Page 2 of 2</div>
</div>
</body>
</html>
