<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Who may approve an SLTR recommendation.
 *
 * Two posts carry the approval by rank -- the Director's and the Deputy's -- and a
 * Supper Admin always has it. Beyond those there is a switch: with Open Approval
 * on the gate stands aside completely and anyone who can reach the screen can
 * approve, which is how the department keeps moving when neither officer is
 * available. The screen itself is reached through the sidebar, whose SLTR entries
 * are gated on their own roles -- so "anyone" means any signed-in user who gets
 * to the page, not only the SLTR department.
 *
 * Open Approval is off unless someone turns it on, and only a Supper Admin can
 * flip it -- see SltrRecommendationController::setApprovalMode(). The rank gate
 * reads the rank string alone; department_id is never consulted, so each post has
 * to be spelled out below exactly as it is stored on the user.
 */
class SltrApproval
{
    /** The row in `settings` that holds the switch. */
    public const SETTING = 'sltr_approval_open';

    /** Ranks that carry the approval on their own, switch or no switch. */
    public const RANKS = ['Director SLTR', 'Deputy Director SLTR'];

    /** Read once per request: this is called for every row-render on the listing. */
    private static ?bool $openCache = null;

    public static function isOpen(): bool
    {
        if (self::$openCache === null) {
            self::$openCache = Setting::where('name', self::SETTING)->value('value') === 'on';
        }

        return self::$openCache;
    }

    public static function setOpen(bool $open): void
    {
        Setting::updateOrCreate(
            ['name' => self::SETTING],
            // parent_id has no default on this table and every existing row uses 1.
            ['value' => $open ? 'on' : 'off', 'type' => 'sltr', 'parent_id' => 1]
        );

        self::$openCache = $open;
    }

    /** Only a Supper Admin may turn Open Approval on or off. */
    public static function isSupperAdmin(?User $user): bool
    {
        return \in_array('supper admin', self::roleNames($user), true);
    }

    /** Does this user hold the rank of one of the two approving posts? */
    public static function hasApprovingRank(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $rank = trim((string) ($user->rank ?? ''));

        foreach (self::RANKS as $carries) {
            if (strcasecmp($rank, $carries) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function allows(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (self::isSupperAdmin($user) || self::hasApprovingRank($user)) {
            return true;
        }

        // Deliberately unconditional: with the switch on, rank and role stop
        // mattering. Turning it off restores the two posts and nothing else.
        return self::isOpen();
    }

    private static function roleNames(?User $user): array
    {
        if (!$user || !method_exists($user, 'assignedRoleNames')) {
            return [];
        }

        return $user->assignedRoleNames();
    }
}
