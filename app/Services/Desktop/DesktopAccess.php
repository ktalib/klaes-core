<?php

namespace App\Services\Desktop;

use App\Models\User;

/**
 * Who may use KLAES-CORE Survey Desktop (user, 2026-11-17): the same people the sidebar shows Survey Plan Management to
 * (admin.menu.partials.modules.survey: $svRecords || $svGkn || $svExaminationVerification || $svPublic || Survey - SG).
 * Version 1 of the app is the map and the survey plans from Survey Plan Upload.
 */
class DesktopAccess
{
    public const ROLES = [
        'Survey - Records', 'Survey - AI Digital Assistant', 'Supper Admin', 'Survey - GKN', 'Survey - Examination/Verification',
        'Survey - Public Survey', 'Survey - SG',
    ];

    /** The token ability every desktop token carries; other tokens (the phone apps') cannot call the desktop API. */
    public const ABILITY = 'desktop-api';

    /** A desktop sign-in lasts this long on a PC; then the user signs in again (plan section 5). */
    public const TOKEN_DAYS = 30;

    public static function allowed(User $user): bool
    {
        foreach (self::ROLES as $role) {
            if ($user->canDo($role, 'view')) {
                return true;
            }
        }

        return false;
    }

    /** The user's Survey roles, for the app's account line. */
    public static function roles(User $user): array
    {
        return array_values(array_filter(self::ROLES, fn ($r) => $user->canDo($r, 'view')));
    }
}
