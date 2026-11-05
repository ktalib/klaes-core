<?php

namespace App\Services\FileTracking;

use App\Models\FileSearchRequest;
use App\Models\FileTracker;
use App\Models\SmsSetting;
use App\Services\Sms\KlaesSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Staff SMS for File Requests: "received" (raised from Quick Search) and
 * "logged" (the file is logged out to the officer who asked for it).
 *
 * WHY THIS IS A SERVICE
 * The "logged" message used to be sent only from
 * CreateFileTrackerController::store(), but the File Tracker log-out form posts
 * to /api/file-trackers (Api\FileTrackerApiController::store), so it never fired.
 * It had no sms_dispatch_logs row at all between going live and 2026-11-05.
 * Both store paths now call notifyLogged() here.
 *
 * ONLY A LOG-OUT THAT ANSWERS A REQUEST IS TEXTED
 * The wording says "Your File Request including ... has been logged to your
 * office", so it is sent only when the officer the file is logged to placed an
 * open request for that file. Routine movements (about 100 a day) are not.
 * Once per request: the dedupe key is the request id, and a request already
 * answered by an earlier log-out to the same officer does not match again.
 *
 * The recipient is the officer the file goes to, never the clerk who logged it
 * (see the 2026-10-22 File Requester fix). No phone = a recorded skip.
 */
class FileRequestSmsNotifier
{
    public function __construct(private KlaesSmsDispatcher $dispatcher)
    {
    }

    /**
     * Text the requesting officer that their requested file has been logged to
     * them. Never throws: an SMS must not break the log-out it reports on.
     */
    public function notifyLogged(FileTracker $tracker): void
    {
        try {
            $key = SmsSetting::KEY_FILE_REQUEST_LOGGED;

            if (!$this->dispatcher->enabled($key)) {
                return;
            }

            $request = $this->answeredRequest($tracker);
            if ($request === null) {
                return;
            }

            $phone = $this->requesterPhone($tracker->receiving_officer_id, $request->receiving_officer);

            $this->dispatcher->queue($key, $phone, [
                'FileNo(s)' => $tracker->file_number,
                'Requester Office' => $tracker->receiving_office_name
                    ?: ($tracker->destination ?: $tracker->department),
                'DateTime' => $this->smsMoment($tracker->date_requested ?? $tracker->date_created),
                'Purpose' => $tracker->request_purpose_name ?: 'official use',
                'Timeline' => $this->smsTimeline($tracker),
            ], [
                'dedupe_key' => $key . ':request:' . $request->id,
                'file_number' => $tracker->file_number,
                'subject_type' => 'file_search_requests',
                'subject_id' => $request->id,
                'event_at' => $tracker->date_requested ?? $tracker->date_created ?? $tracker->created_at,
                // Staff numbers are personal; the shared-number guard is for applicants.
                'phone_confirmed' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FileRequestSmsNotifier: file logged SMS not queued', [
                'tracker_id' => $tracker->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The open File Request this log-out answers, or null.
     *
     * A request matches when it is for the same file (case/space-insensitive),
     * not CLOSED, raised before this tracker, names the officer the file is
     * logged to, and has not already been answered by an earlier log-out of the
     * file to that same officer. The oldest matching request wins.
     */
    public function answeredRequest(FileTracker $tracker): ?FileSearchRequest
    {
        $fileNumber = strtoupper(trim((string) $tracker->file_number));
        if ($fileNumber === '' || strtoupper(trim((string) $tracker->file_request_type)) === 'SYSTEM') {
            return null;
        }

        $officerNames = $this->trackerOfficerNames($tracker);
        if ($officerNames === []) {
            return null;
        }

        $loggedAt = $tracker->created_at ?? now();

        $candidates = FileSearchRequest::where('status', '!=', FileSearchRequest::STATUS_CLOSED)
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$fileNumber])
            ->where('created_at', '<=', $loggedAt)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (FileSearchRequest $fr) => in_array(self::normaliseName($fr->receiving_officer), $officerNames, true));

        foreach ($candidates as $fr) {
            $earlierLogOuts = FileTracker::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$fileNumber])
                ->where('created_at', '>=', $fr->created_at)
                ->when($tracker->id, fn ($q) => $q->where('id', '<>', $tracker->id))
                ->where('created_at', '<=', $loggedAt)
                ->pluck('receiving_officer_name');

            $alreadyAnswered = $earlierLogOuts->contains(
                fn ($name) => self::normaliseName($name) === self::normaliseName($fr->receiving_officer)
            );

            if (!$alreadyAnswered) {
                return $fr;
            }
        }

        return null;
    }

    /**
     * Phone of the File Requester: by user id when one was given (officers added
     * by hand carry an "ro_" id and are not users) and that user is still the
     * officer named, else by an exact name match among active users -- only when
     * exactly one user has it.
     */
    public function requesterPhone($userId, ?string $name): ?string
    {
        $users = DB::connection('sqlsrv')->table('users')->where('is_active', 1);

        if (is_numeric($userId)) {
            $row = (clone $users)->where('id', (int) $userId)->first(['first_name', 'last_name', 'phone_number']);
            // The id must still be the officer named on the request.
            if ($row && ($name === null || strcasecmp(
                preg_replace('/\s+/', ' ', trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))),
                preg_replace('/\s+/', ' ', trim($name))
            ) === 0)) {
                return $row->phone_number ?: null;
            }
        }

        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        if ($name === '') {
            return null;
        }

        $matches = $users
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(first_name,'') + ' ' + ISNULL(last_name,'')))) = ?", [mb_strtoupper($name)])
            ->limit(2)
            ->pluck('phone_number');

        return $matches->count() === 1 ? ($matches->first() ?: null) : null;
    }

    /** A moment as the office clock shows it, for an SMS body. */
    public function smsMoment($moment): string
    {
        $timezone = config('klaes_sms.timezone', 'Africa/Lagos');
        $format = config('klaes_sms.datetime_format', 'd/m/Y h:i A');

        try {
            return Carbon::parse($moment ?: now())
                ->setTimezone($timezone)
                ->format($format);
        } catch (\Throwable $e) {
            return Carbon::now($timezone)->format($format);
        }
    }

    /**
     * How long the requester has the file for, in words. timeline_days when
     * set, else counted from the deadline.
     */
    public function smsTimeline(FileTracker $tracker): string
    {
        $days = $tracker->timeline_days;

        if (!$days && !empty($tracker->deadline)) {
            try {
                $days = Carbon::parse($tracker->date_created ?: now())
                    ->startOfDay()
                    ->diffInDays(Carbon::parse($tracker->deadline)->startOfDay());
            } catch (\Throwable $e) {
                $days = null;
            }
        }

        if (!$days) {
            return 'the agreed period';
        }

        return $days . ' ' . ((int) $days === 1 ? 'day' : 'days');
    }

    /** Upper-case, single-spaced, trimmed; '' for blank. */
    public static function normaliseName(?string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $name)));
    }

    /**
     * Names the tracker's receiving officer goes by: the stored name, plus the
     * user's own name when the officer is a system user.
     */
    private function trackerOfficerNames(FileTracker $tracker): array
    {
        $names = [self::normaliseName($tracker->receiving_officer_name)];

        if (is_numeric($tracker->receiving_officer_id)) {
            $user = DB::connection('sqlsrv')->table('users')
                ->where('id', (int) $tracker->receiving_officer_id)
                ->first(['first_name', 'last_name']);
            if ($user) {
                $names[] = self::normaliseName(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
        }

        return array_values(array_unique(array_filter($names, fn ($n) => $n !== '')));
    }
}
