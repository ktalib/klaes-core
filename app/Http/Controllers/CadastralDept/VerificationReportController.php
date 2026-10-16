<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralVerificationReport as Report;
use App\Services\CadastralDept\VerificationReports;
use App\Services\DocumentQr\QrRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The Cadastral Verification Report Sheet of a Verification file. */
class VerificationReportController extends Controller
{
    use RunsWorkflowActions;

    public function __construct(private VerificationReports $reports)
    {
    }

    public function show(int $case)
    {
        $case = CadastralCase::findOrFail($case);
        abort_unless($this->reports->applies($case), 404);
        $report = $this->reports->forCase($case);

        return view('cadastral_dept.verification_report.show', [
            'case' => $case,
            'report' => $report,
            'installed' => VerificationReports::installed(),
            'editable' => $this->reports->editable($case),
            'general' => $this->reports->general($case),
            'v' => $this->reports->values($case, $report),
            'signOff' => $this->reports->signOff($case),
        ]);
    }

    public function save(Request $request, int $case)
    {
        $in = fn (array $list) => ['nullable', Rule::in($list)];
        $text = 'nullable|string|max:300';
        $data = $request->validate([
            'application_date' => 'nullable|string|max:40',
            'allottee_name' => $text,
            'application_type' => 'nullable|string|max:100',
            'plot_no' => 'nullable|string|max:100',
            'layout_no' => 'nullable|string|max:150',
            'district_location' => $text,
            'fmc_commission_date' => 'nullable|string|max:40',
            'original_holder_name' => $text,
            'original_holder_ref' => 'nullable|string|max:150',
            'original_holder_date' => 'nullable|string|max:40',
            'current_holder_name' => $text,
            'current_holder_ref' => 'nullable|string|max:150',
            'current_holder_date' => 'nullable|string|max:40',
            'current_transaction_nature' => $in(array_merge(Report::NATURES, ['Other'])),
            'current_transaction_other' => 'nullable|required_if:current_transaction_nature,Other|string|max:90',
            'caveat_status' => $in(Report::ENCUMBRANCE),
            'caveat_ref' => $text,
            'mortgage_status' => $in(Report::ENCUMBRANCE),
            'mortgage_ref' => $text,
            'release_status' => $in(Report::RELEASE),
            'release_ref' => $text,
            'dciv_status' => $in(Report::DCIV_STATUS),
            'dciv_officer' => 'nullable|string|max:200',
            'dciv_genuineness' => $in(Report::GENUINENESS),
            'dciv_remarks' => 'nullable|string|max:2000',
            'title_status' => $in(Report::TITLE_STATUS),
            'title_ref' => $text,
            'parcel_update_status' => $in(Report::PARCEL_UPDATE),
            'survey_plan_status' => $in(Report::SURVEY_PLAN),
            'charting_status' => $in(Report::CHARTING),
            'verdict' => $in(array_keys(Report::VERDICTS)),
            'remarks' => 'nullable|required_if:verdict,conditions|required_if:verdict,rejected|string|max:4000',
        ], [
            'current_transaction_other.required_if' => 'Section 2: specify the other nature of transaction.',
            'remarks.required_if' => 'Section 6: give the remarks / conditions for this verdict.',
        ]);

        // "Other" keeps the officer's words, as the sheet's "Other: ____".
        if (($data['current_transaction_nature'] ?? null) === 'Other') {
            $data['current_transaction_nature'] = 'Other: ' . trim($data['current_transaction_other']);
        }
        unset($data['current_transaction_other']);

        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $this->reports->save($case, $data, auth()->id()),
            "{$case->case_ref}: Verification Report Sheet saved.", route('cadastral-dept.cases.verification-report.show', $case->id));
    }

    /** The sheet as printed; the QR opens the file's page in KLAES. */
    public function print(int $case, QrRenderer $qr)
    {
        $case = CadastralCase::findOrFail($case);
        abort_unless($this->reports->applies($case), 404);
        $report = $this->reports->forCase($case);
        if ($report) {
            $this->reports->countPrint($report);
        }

        return view('cadastral_dept.verification_report.print', [
            'case' => $case,
            'report' => $report,
            'v' => $this->reports->values($case, $report),
            'signOff' => $this->reports->signOff($case),
            'qr' => $qr->dataUri(route('cadastral-dept.cases.show', $case->id), 140),
        ]);
    }
}
