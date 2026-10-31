<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOpRecord as OP;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyOpShowingPhoto as Photo;
use App\Models\Survey\SurveyorListEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Showing History on Compensation Status → Verification (Records)
 * (docs/KLAES Survey Module – Workflow_V02.md 5.1; recording 286, 2026-10-29).
 *
 * After an OP is registered the surveyor goes to the field to show the allottee
 * the site. Each visit is recorded: surveyor, date, time, photographs. A revisit
 * becomes the next showing; from the 2nd showing on, the reason is required
 * (Change of Ownership or Verification). The latest showing is shown first and
 * older ones fold away, so the history never grows long on screen.
 */
class OpShowingController extends Controller
{
    public const SURVEYOR_OTHER = '__other__';

    public const MAX_PHOTOS = 6;

    /** How many showings are shown open; the rest are folded. */
    public const SHOW_OPEN = 2;

    public function index(OP $op)
    {
        return view('survey_module.compensation.op_showings', [
            'op'        => $op->load(['case.project.coFile', 'beneficiary']),
            'showings'  => Showing::with('photos')->where('survey_op_record_id', $op->id)->orderByDesc('showing_no')->get(),
            'surveyors' => SurveyorListEntry::query()->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
                ->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']),
        ]);
    }

    public function store(Request $r, OP $op)
    {
        $next = (int) Showing::where('survey_op_record_id', $op->id)->max('showing_no') + 1;

        $data = $r->validate([
            'surveyor'       => 'required|string|max:50',
            'surveyor_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === self::SURVEYOR_OTHER)],
            'shown_on'       => 'required|date|before_or_equal:today',
            'shown_at'       => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reason'         => [Rule::requiredIf($next > 1), 'nullable', Rule::in(Showing::REASONS)],
            'notes'          => 'nullable|string|max:2000',
            'photos'         => 'nullable|array|max:' . self::MAX_PHOTOS,
            'photos.*'       => 'image|mimes:jpg,jpeg,png|max:10240',
        ], [
            'surveyor.required'       => 'Choose the surveyor who showed the site.',
            'surveyor_other.required' => "Type the surveyor's name.",
            'shown_at.regex'          => 'Give the time as HH:MM.',
            'reason.required'         => 'From the 2nd showing, say why: Change of Ownership or Verification.',
            'photos.max'              => 'At most ' . self::MAX_PHOTOS . ' photographs per showing.',
            'photos.*.image'          => 'Photographs must be JPG or PNG images.',
            'photos.*.max'            => 'Each photograph can be at most 10 MB.',
        ]);

        // Surveyor: a Survey staff member, or a name typed under Other (matched to the list if it is on it).
        $staffId = null;
        if ($data['surveyor'] === self::SURVEYOR_OTHER) {
            $name = trim(preg_replace('/\s+/', ' ', (string) $data['surveyor_other']));
            $match = SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($name));
            $staffId = $match?->id;
            $name = $match?->full_name ?? $name;
        } elseif (preg_match('/^staff:(\d+)$/', $data['surveyor'], $m) && ($s = SurveyorListEntry::find((int) $m[1]))) {
            $staffId = $s->id;
            $name = $s->full_name;
        } else {
            throw ValidationException::withMessages(['surveyor' => 'Choose the surveyor who showed the site.']);
        }

        $written = [];
        try {
            $showing = DB::connection('sqlsrv')->transaction(function () use ($r, $op, $data, $staffId, $name, $next, &$written) {
                $showing = Showing::create([
                    'survey_op_record_id' => $op->id,
                    // Re-read inside the transaction so two officers saving at once do not share a number.
                    'showing_no'          => max($next, (int) Showing::where('survey_op_record_id', $op->id)->lockForUpdate()->max('showing_no') + 1),
                    'shown_on'            => $data['shown_on'],
                    'shown_at'            => $data['shown_at'],
                    'surveyors_list_id'   => $staffId,
                    'surveyor_name'       => $name,
                    'reason'              => $next > 1 ? $data['reason'] : null,
                    'notes'               => trim((string) ($data['notes'] ?? '')) ?: null,
                ]);

                foreach ((array) $r->file('photos', []) as $file) {
                    $ext = strtolower($file->extension() ?: 'jpg');
                    $path = Storage::disk(Photo::DISK)->putFileAs("app/survey/op-showings/{$op->id}", $file, Str::uuid() . '.' . $ext);
                    $written[] = $path;
                    Photo::create([
                        'survey_op_showing_id' => $showing->id,
                        'path'                 => $path,
                        'original_name'        => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime'                 => $file->getMimeType(),
                        'size'                 => $file->getSize(),
                    ]);
                }

                return $showing;
            });
        } catch (\Throwable $e) {
            foreach ($written as $p) Storage::disk(Photo::DISK)->delete($p);
            throw $e;
        }

        return redirect()->route('survey-module.compensation.op-verification.showings', $op)
            ->with('success', "{$showing->label} recorded" . ($showing->photos()->count() ? ' with ' . $showing->photos()->count() . ' photograph(s)' : '') . '.');
    }

    public function photo(Photo $photo)
    {
        abort_unless(Storage::disk(Photo::DISK)->exists($photo->path), 404);

        return Storage::disk(Photo::DISK)->response($photo->path, $photo->original_name ?: basename($photo->path));
    }
}
