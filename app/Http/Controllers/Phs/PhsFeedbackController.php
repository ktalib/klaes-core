<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsFeedbackSubmitted;
use App\Models\Phs\PhsFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PhsFeedbackController extends Controller
{
    /**
     * Record a complaint raised by a member about an incomplete or wrong
     * transaction in a property history search slip.
     */
    public function store(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        $data = $request->validate([
            'category'       => ['required', 'string', 'in:' . implode(',', array_keys(PhsFeedback::CATEGORIES))],
            'category_other' => ['nullable', 'required_if:category,other', 'string', 'max:150'],
            'file_number'    => ['nullable', 'string', 'max:100'],
            'reference_no'   => ['nullable', 'string', 'max:100'],
            'subject'        => ['nullable', 'string', 'max:255'],
            'message'        => ['required', 'string', 'max:2000'],
        ], [
            'message.required'          => 'Please describe the problem with the transaction.',
            'category.in'               => 'Please choose a valid complaint type.',
            'category_other.required_if' => 'Please specify the type of issue.',
        ]);

        // For an "Other" issue, keep the user's specification with the complaint
        // so the Ministry reviewer sees exactly what they meant.
        $message = $data['message'];
        if ($data['category'] === 'other' && !empty($data['category_other'])) {
            $message = 'Issue type (Other): ' . trim($data['category_other']) . "\n\n" . $message;
        }

        $feedback = PhsFeedback::create([
            'phs_institution_id' => $institution->id,
            'phs_member_id'      => $member->id,
            'category'           => $data['category'],
            'file_number'        => $data['file_number'] ?? null,
            'reference_no'       => $data['reference_no'] ?? null,
            'subject'            => isset($data['subject']) ? Str::limit($data['subject'], 250, '') : null,
            'message'            => $message,
            'status'             => 'open',
        ]);

        $feedback->setRelation('institution', $institution);
        $feedback->setRelation('member', $member);

        $this->notify($feedback, $institution, $member);

        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your feedback has been submitted and will be reviewed by the Ministry.',
            'id'      => $feedback->id,
        ]);
    }

    /**
     * Notify the Ministry/KLAES admin (for action) and the organization's
     * super admins (as a confirmation). Best-effort: a mail failure never
     * blocks the feedback from being saved.
     */
    private function notify(PhsFeedback $feedback, $institution, $member): void
    {
        // ---- Ministry / KLAES admin recipients ----
        try {
            $adminEmail = config('mail.admin_email') ?? config('mail.from.address');
            $adminRecipients = collect(explode(',', (string) $adminEmail))
                ->map(fn ($e) => trim($e))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($adminRecipients)) {
                Mail::to($adminRecipients)->send(new PhsFeedbackSubmitted($feedback, true));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // ---- Organization super-admin recipients (confirmation copy) ----
        try {
            $orgRecipients = $institution->members()
                ->where('user_type', 'super_admin')
                ->where('status', 'active')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Always include the member who raised the complaint.
            if ($member->email) {
                $orgRecipients[] = $member->email;
            }
            $orgRecipients = array_values(array_unique($orgRecipients));

            if (!empty($orgRecipients)) {
                Mail::to($orgRecipients)->send(new PhsFeedbackSubmitted($feedback, false));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
