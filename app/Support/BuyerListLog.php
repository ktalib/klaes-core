<?php

namespace App\Support;

/**
 * Logger for the Add/Edit Buyers screen (Sectional Titling buyers list).
 *
 * Writes to storage/logs/buyer_list.log — see the "buyer_list" channel in
 * config/logging.php and the shared behaviour in {@see ChannelLog}.
 *
 * Covers the whole screen, not just the database writes: BuyerListController logs
 * every add/import/update/delete, and the browser posts its own session trace to
 * BuyerListDiagnosticsController::clientLog(). Both land here, stamped with the
 * same user/ip/route, so a report of "the form closed and I lost everything" can
 * be read as one timeline instead of guessed at.
 */
class BuyerListLog extends ChannelLog
{
    public const CHANNEL = 'buyer_list';
}
