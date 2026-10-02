<?php

namespace App\Support;

/**
 * Logger for the Primary Application Form (Sectional Titling main application).
 *
 * Writes to storage/logs/primary_form.log — see the "primary_form" channel in
 * config/logging.php and the shared behaviour in {@see ChannelLog}. Covers the form
 * end-to-end: draft autosave, submission, buyer/EDMS processing and file-number
 * allocation.
 */
class PrimaryFormLog extends ChannelLog
{
    public const CHANNEL = 'primary_form';
}
