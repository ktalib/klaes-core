<?php

namespace App\Support;

/**
 * Logger for Plot Subdivision (Deeds → Parcel Update → /plot-subdivision).
 *
 * Writes to storage/logs/plot_subdivision.log — see the "plot_subdivision" channel
 * in config/logging.php and the shared behaviour in {@see ChannelLog}.
 */
class PlotSubdivisionLog extends ChannelLog
{
    public const CHANNEL = 'plot_subdivision';
}
