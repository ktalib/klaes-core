<?php

namespace App\Services\InstrumentWorkflow;

use App\Models\InstrumentWorkflow\InstrumentApplication;
use RuntimeException;

/**
 * A workflow action refused because the application is not in a state that
 * allows it. The message is written for the officer, not the log.
 */
class WorkflowGuardException extends RuntimeException
{
    public static function illegal(InstrumentApplication $application, string $target): self
    {
        return new self(sprintf(
            'Application %s is at "%s" and cannot move to "%s".',
            $application->reference,
            $application->stageLabel(),
            InstrumentApplication::labelFor($target)
        ));
    }

    public static function because(string $message): static
    {
        return new static($message);
    }
}

