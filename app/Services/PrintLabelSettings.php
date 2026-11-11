<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Print File Label settings per registry (CR 10112026 item 3, user 2026-11-11). For now one switch: Override
 * Shelf/Rack, for registries with no defined racks. While it is on, labels print with no shelf/rack and none is saved.
 * Survey first; the other registries' PFL pages take the same key-per-registry row.
 */
class PrintLabelSettings
{
    private const TABLE = 'print_label_settings';

    public static function shelfRackOff(string $registry): bool
    {
        if (! Schema::connection('sqlsrv')->hasTable(self::TABLE)) return false;

        return (bool) DB::connection('sqlsrv')->table(self::TABLE)->where('registry', $registry)->value('shelf_rack_off');
    }

    /** Switch Override Shelf/Rack on or off for a registry, with an audit entry. Returns the new value. */
    public static function setShelfRackOff(string $registry, bool $off): bool
    {
        $db = DB::connection('sqlsrv');
        $before = self::shelfRackOff($registry);
        $now = now();
        $db->transaction(function () use ($db, $registry, $off, $now) {
            $exists = $db->table(self::TABLE)->where('registry', $registry)->exists();
            $values = ['shelf_rack_off' => $off, 'updated_by' => Auth::id(), 'updated_at' => $now];
            $exists
                ? $db->table(self::TABLE)->where('registry', $registry)->update($values)
                : $db->table(self::TABLE)->insert($values + ['registry' => $registry, 'created_at' => $now]);
        });

        if ($before !== $off) {
            try {
                app(AuditService::class)->logAction('Print Label Shelf/Rack Override', 'PrintLabelSetting', null,
                    ['registry' => $registry, 'shelf_rack_off' => $before], ['registry' => $registry, 'shelf_rack_off' => $off],
                    ucfirst($registry) . ' Print File Label: Override Shelf/Rack ' . ($off ? 'switched on (labels without shelf/rack)' : 'switched off'));
            } catch (\Throwable $e) {
                // The setting is saved; a failed audit entry must not undo it.
            }
        }

        return $off;
    }
}
