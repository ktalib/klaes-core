<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Models\Survey\SurveyRegisterFile;
use App\Services\CadastralDept\DeskAccess;
use App\Services\CadastralDept\IntakeLookup;
use App\Services\CadastralDept\VerificationFileStatus;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\SurveyRegisterSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examination/Verification > Register File (user transcript, 2026-10-25):
 * "use the same as the Cadastral register file", Verification only for now
 * (Conversion and Direct are shown greyed out, kept for later).
 *
 * The register lists files sent by Cadastral (pulled by SurveyRegisterSync, one
 * line per hand-over) and files registered here. The registration form is a copy
 * of the Cadastral one; the file lookups and File Status checks reuse the same
 * Cadastral services through Survey's own routes, so Survey staff need no
 * Cadastral desk access. Cadastral never sees Survey's own registrations.
 */
class RegisterFileController extends Controller
{
    public function index(Request $r, SurveyRegisterSync $sync)
    {
        $added = $sync->pull();

        $base = fn () => SurveyRegisterFile::query();
        $q = $base()->with('cadastralCase');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")
                ->orWhere('register_ref', 'like', "%$term%")
                ->orWhere('cadastral_case_ref', 'like', "%$term%")
                ->orWhere('file_title', 'like', "%$term%"));
        }
        if (array_key_exists($p = $r->query('purpose'), SurveyRegisterFile::PURPOSES)) $q->where('purpose', $p);
        if (in_array($s = $r->query('source'), [SurveyRegisterFile::SOURCE_CADASTRAL, SurveyRegisterFile::SOURCE_SURVEY], true)) $q->where('source', $s);
        if (array_key_exists($st = $r->query('status'), SurveyRegisterFile::STATUSES)) $q->where('status', $st);

        $stats = ['total' => $base()->count()];
        foreach (array_keys(SurveyRegisterFile::STATUSES) as $s) $stats[$s] = $base()->where('status', $s)->count();

        return view('survey_module.register_files.index', [
            'files'        => $q->orderByDesc('received_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'stats'        => $stats,
            'added'        => $added,
            'def'          => app(WorkflowDefinition::class),
            'canOpenCases' => DeskAccess::can($r->user(), 'view'),
        ]);
    }

    public function create(WorkflowDefinition $def)
    {
        return view('survey_module.register_files.create', ['def' => $def]);
    }

    /** Same checks as the Cadastral register: the file is re-read, only its number comes from the browser. */
    public function store(Request $r, IntakeLookup $lookup)
    {
        $data = $r->validate([
            'workflow'          => ['required', Rule::in(['verification'])],
            'file_type'         => 'required|string|max:20',
            'sub_type'          => 'nullable|string|max:30',
            'allocation_route'  => 'nullable|string|max:30',
            'file_source'       => 'required|string|max:20',
            'file_source_other' => 'nullable|required_if:file_source,other|string|max:100',
            'file_number'       => 'required|string|max:100',
            'file_indexing_id'  => 'nullable|integer|min:1',
            'file_system'       => 'nullable|string|max:20',
            'tracking_id'       => 'nullable|string|max:50',
            'received_at'       => 'nullable|date',
            'remarks'           => 'nullable|string|max:2000',
        ], [
            'workflow.in'                  => 'Only Verification files can be registered in Survey for now.',
            'file_number.required'         => 'Pick the file from the list.',
            'file_type.required'           => 'Choose the file type.',
            'file_source.required'         => 'Choose where the file came from.',
            'file_source_other.required_if'=> 'Specify where the file came from.',
        ]);

        $found = $lookup->resolve([
            'file_number'      => $data['file_number'],
            'tab'              => $data['file_system'] ?? '',
            'file_indexing_id' => (int) ($data['file_indexing_id'] ?? 0),
            'workflow'         => 'verification',
        ]);
        if ($found['status'] !== 'ok') {
            return back()->withInput()->with('error', $found['message'] ?: 'Pick the file again.');
        }
        $file = $found['file'];

        $line = SurveyRegisterFile::create([
            'register_ref'          => SurveyRegisterFile::nextRef('register_ref', 'SRF', 5),
            'purpose'               => SurveyRegisterFile::PURPOSE_VERIFICATION,
            'source'                => SurveyRegisterFile::SOURCE_SURVEY,
            'workflow'              => 'verification',
            'file_type'             => $data['file_type'],
            'sub_type'              => $data['sub_type'] ?? null,
            'allocation_route'      => $data['allocation_route'] ?? null,
            'file_source'           => $data['file_source'],
            'file_source_other'     => $data['file_source_other'] ?? null,
            'file_number'           => $file['file_number'],
            'file_indexing_id'      => $file['file_indexing_id'],
            'file_system'           => $data['file_system'] ?? null,
            'tracking_id'           => $data['tracking_id'] ?? null,
            'file_title'            => $file['title'],
            'plot_no'               => $file['plot'],
            'district'              => $file['district'],
            'lga'                   => $file['lga'],
            'location'              => $file['location'],
            'correspondence_fileno' => $found['correspondence']['fileno'] ?? null,
            'received_at'           => $data['received_at'] ?? now(),
            'status'                => SurveyRegisterFile::RECEIVED,
            'remarks'               => $data['remarks'] ?? null,
        ]);

        $open = SurveyRegisterFile::where('file_number', $line->file_number)->where('id', '!=', $line->id)
            ->whereNotIn('status', [SurveyRegisterFile::CLEARED, SurveyRegisterFile::RETURNED])->count();

        return redirect()->route('survey-module.workflow.register-file.index')
            ->with('success', "{$line->register_ref}: {$line->file_number} registered for verification."
                . ($open ? " Note: the file already has {$open} open line(s) in this register." : ''));
    }

    /** Start work, clear (back to the requesting unit) or return with a query. */
    public function status(Request $r, SurveyRegisterFile $file)
    {
        $data = $r->validate([
            'action'  => ['required', Rule::in(['start', 'clear', 'return'])],
            'finding' => 'nullable|string|max:50',
            'note'    => 'nullable|string|max:2000',
        ]);

        if ($file->isFrozen()) {
            return back()->with('error', "{$file->register_ref} is {$file->statusLabel()} and locked.");
        }

        $note = trim((string) ($data['note'] ?? ''));
        $attrs = [];

        switch ($data['action']) {
            case 'start':
                if ($file->status !== SurveyRegisterFile::RECEIVED) {
                    return back()->with('error', "{$file->register_ref} is already {$file->statusLabel()}.");
                }
                $attrs['status'] = SurveyRegisterFile::IN_PROGRESS;
                break;

            case 'clear':
                if ($file->status !== SurveyRegisterFile::IN_PROGRESS) {
                    return back()->with('error', "Start work on {$file->register_ref} before clearing it.");
                }
                if (! array_key_exists($data['finding'] ?? '', SurveyRegisterFile::FINDINGS[$file->purpose] ?? [])) {
                    throw ValidationException::withMessages(['finding' => 'Record the finding before clearing the file.']);
                }
                $attrs = ['status' => SurveyRegisterFile::CLEARED, 'finding' => $data['finding']];
                break;

            case 'return':
                if ($note === '') {
                    throw ValidationException::withMessages(['note' => 'Say why the file is being returned.']);
                }
                $attrs['status'] = SurveyRegisterFile::RETURNED;
                break;
        }

        if (in_array($attrs['status'], [SurveyRegisterFile::CLEARED, SurveyRegisterFile::RETURNED], true)) {
            $attrs['closed_at'] = now();
            $attrs['closed_by'] = Auth::id();
        }
        if ($note !== '' || isset($attrs['finding'])) {
            $stamp = now()->format('d M Y H:i') . ' · ' . (Auth::user()?->name ?? 'system') . ' · ' . (SurveyRegisterFile::STATUSES[$attrs['status']] ?? '');
            $what  = trim(($attrs['finding'] ?? null ? SurveyRegisterFile::FINDINGS[$file->purpose][$attrs['finding']] . '. ' : '') . $note);
            $attrs['notes'] = trim(($file->notes ? $file->notes . "\n" : '') . "[$stamp] $what");
        }

        $file->update($attrs);

        $msg = "{$file->register_ref} is now " . $file->statusLabel() . '.';
        if ($file->source === SurveyRegisterFile::SOURCE_CADASTRAL && $file->isFrozen()) {
            $msg .= ' Send the file back to Cadastral; their desk records the outcome there.';
        }

        return back()->with('success', $msg);
    }

    /* ------------------------- the form's lookups ------------------------- */

    public function lookupFile(Request $r, IntakeLookup $lookup): JsonResponse
    {
        return response()->json($lookup->resolve([
            'file_number'      => mb_substr((string) $r->query('file_number', ''), 0, 100),
            'tab'              => mb_substr((string) $r->query('tab', ''), 0, 20),
            'file_indexing_id' => (int) $r->query('file_indexing_id', 0),
            'workflow'         => 'verification',
        ]));
    }

    public function lookupFiles(Request $r, IntakeLookup $lookup): JsonResponse
    {
        return response()->json($lookup->options('verification', mb_substr((string) $r->query('q', ''), 0, 100)));
    }

    public function lookupFileStatus(Request $r, VerificationFileStatus $status)
    {
        $data = $r->validate(['file_number' => 'required|string|max:100', 'file_indexing_id' => 'nullable|integer']);
        $case = new CadastralCase();
        $case->file_number = $data['file_number'];
        $case->file_indexing_id = $data['file_indexing_id'] ?? null;

        return view('survey_module.register_files._file_status', ['rows' => $status->results($case)]);
    }
}
