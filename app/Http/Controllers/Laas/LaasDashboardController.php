<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LaasDashboardController extends Controller
{
    public function index()
    {
        $applicant = Auth::guard('laas')->user();

        $applications = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->orderByDesc('id')
            ->get();

        return view('laas.dashboard', [
            'applications'   => $applications,
            'unreadUpdates'  => $this->unreadCount($applicant->id),
        ]);
    }

    /**
     * The in-portal update feed — the same events that were texted, plus the
     * progress steps that are shown but not worth a text of their own.
     */
    public function notifications()
    {
        $applicant = Auth::guard('laas')->user();

        $events = LaasApplicationEvent::query()
            ->join('laas_applications', 'laas_applications.id', '=', 'laas_application_events.laas_application_id')
            ->where('laas_applications.laas_applicant_id', $applicant->id)
            ->where('laas_application_events.visible_to_applicant', true)
            ->orderByDesc('laas_application_events.id')
            ->limit(200)
            ->get([
                'laas_application_events.*',
                'laas_applications.reference_no',
                'laas_applications.file_number',
            ]);

        return view('laas.notifications', [
            'events'        => $events,
            'unreadUpdates' => $this->unreadCount($applicant->id),
        ]);
    }

    /**
     * Events from the last 7 days, used for the nav badge. There is no per-event
     * read flag on the applicant side — an applicant does not "action" an
     * update, they just want to see what is new since they last looked.
     */
    private function unreadCount(int $applicantId): int
    {
        return LaasApplicationEvent::query()
            ->join('laas_applications', 'laas_applications.id', '=', 'laas_application_events.laas_application_id')
            ->where('laas_applications.laas_applicant_id', $applicantId)
            ->where('laas_application_events.visible_to_applicant', true)
            ->where('laas_application_events.created_at', '>=', now()->subDays(7))
            ->count();
    }
}
