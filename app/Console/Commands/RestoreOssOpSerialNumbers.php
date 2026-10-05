<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Compatibility entry point using the conservative, audited repair planner. */
class RestoreOssOpSerialNumbers extends Command
{
    protected $signature = 'oss:restore-op-serials {--dry-run : Show what would change without writing}';
    protected $description = 'Restore verified missing OSS OP serials with a reversible report';

    public function handle(): int
    {
        $options = ['--table' => 'oss_applications'];
        if (!$this->option('dry-run')) $options['--apply'] = true;
        return $this->call('op:serial-integrity', $options);
    }
}
