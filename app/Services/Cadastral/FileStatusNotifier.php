<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralFileStatusEvent;
use App\Models\Cadastral\CadastralIndexCard;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Who hears that a cadastral file's status changed.
 *
 * THE SOURCE DEPARTMENT CANNOT BE TARGETED CLEANLY, SO IT IS NOT GUESSED AT.
 * The only department link in KLAES is users.department_id, and the one module
 * that notified a whole department by it (ParcelUpdateNotificationService)
 * abandoned it: 4,056 notifications, 96% unread, because nearly everyone in
 * Land (445 users today) had nothing to do with the record. Nothing records who
 * in Land, SLTR, ST, KANGIS or DCIV sent a given file — the receipt's
 * received_from is free text.
 *
 * So, following that service's rule, the recipients are:
 *
 *   1. the officers on the record — who commissioned the card, who logged the
 *      intake receipt;
 *   2. everyone holding the "Cad - Records" role (matched on comma boundaries
 *      in users.assign_role, as ParcelUpdateNotificationService does);
 *
 * minus anyone who has turned in-app notifications off. The source department
 * is NAMED in the message and carried in the data (source_department), so a
 * rule that reaches the right desk there can be added here, in one place, once
 * the Ministry says who that is.
 */
class FileStatusNotifier
{
    public const ROLE = 'Cad - Records';

    public function __construct(private UserNotificationService $notifications) {}

    /**
     * Notify, and mark the event notified. Never throws: a notification is a
     * record of the change, not the change.
     *
     * @return int  notifications written
     */
    public function notify(CadastralIndexCard $card, CadastralFileStatusEvent $event, ?CadastralFileReceipt $receipt): int
    {
        try {
            $recipients = $this->recipients($card, $receipt);

            if ($recipients === []) {
                Log::warning('Cadastral file status: no recipients matched', ['event_id' => $event->id]);

                return 0;
            }

            $source = $receipt?->source_registry;
            $label  = CadastralIndexCard::FILE_STATUSES[$event->to_status] ?? $event->to_status;
            $from   = CadastralIndexCard::FILE_STATUSES[$event->from_status] ?? $event->from_status;
            $title  = "File {$card->file_number} is now {$label}";
            $body   = "{$card->file_number}" . ($card->file_title ? " ({$card->file_title})" : '')
                . " changed from {$from} to {$label}"
                . ($event->effective_date ? ', effective ' . $event->effective_date->format('d M Y') : '')
                . ($source ? ". Source department: {$source}" : '')
                . ". Remarks: {$event->reason}";

            foreach ($recipients as $userId) {
                $this->notifications->create(
                    $userId,
                    'cadastral_file_status',
                    $title,
                    $body,
                    [
                        'card_id'           => $card->id,
                        'event_id'          => $event->id,
                        'file_number'       => $card->file_number,
                        'to_status'         => $event->to_status,
                        'source_department' => $source,
                    ],
                    ['module' => 'cadastral'],
                );
            }

            $event->forceFill(['notified' => true, 'notified_at' => now()])->save();

            return count($recipients);
        } catch (\Throwable $e) {
            Log::warning('Cadastral file status notification failed: ' . $e->getMessage(), ['event_id' => $event->id]);

            return 0;
        }
    }

    /** @return int[] */
    public function recipients(CadastralIndexCard $card, ?CadastralFileReceipt $receipt): array
    {
        $ids = array_merge(
            [$card->commissioned_by, $receipt?->received_by, $receipt?->created_by],
            $this->roleHolders(),
        );

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $this->minusOptedOut($ids);
    }

    private function roleHolders(): array
    {
        return DB::connection('sqlsrv')->table('users')
            ->whereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . self::ROLE . ',%'])
            ->pluck('id')
            ->all();
    }

    /** Absence of a settings row means on, as everywhere else in KLAES. */
    private function minusOptedOut(array $ids): array
    {
        if ($ids === []) {
            return $ids;
        }

        try {
            $off = DB::connection('sqlsrv')->table('user_notification_settings')
                ->whereIn('user_id', $ids)
                ->where('enable_in_app', 0)
                ->pluck('user_id')
                ->all();

            return array_values(array_diff($ids, array_map('intval', $off)));
        } catch (\Throwable) {
            return $ids;
        }
    }
}
