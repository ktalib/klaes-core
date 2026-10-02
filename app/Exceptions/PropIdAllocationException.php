<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PropIdAllocationException extends RuntimeException
{
    private string $reason;

    public function __construct(string $message, string $reason = 'general', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public static function missingIdentifiers(): self
    {
        return new self(
            'At least one file identifier is required to allocate a Property ID.',
            'missing_identifiers'
        );
    }

    public static function officialNumberRequired(): self
    {
        return new self(
            'A permanent (non-temporary) file number is required before allocating a Property ID.',
            'official_number_required'
        );
    }
}
