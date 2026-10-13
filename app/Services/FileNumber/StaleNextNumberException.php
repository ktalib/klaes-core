<?php

namespace App\Services\FileNumber;

use RuntimeException;

/** Move Commissioned File: the number shown on the card was taken before the officer confirmed. */
class StaleNextNumberException extends RuntimeException
{
    public function __construct(public string $currentNext)
    {
        parent::__construct("The next file number is now {$currentNext}. Please confirm again.");
    }
}
