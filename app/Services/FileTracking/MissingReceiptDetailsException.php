<?php

namespace App\Services\FileTracking;

use RuntimeException;

/**
 * A File Movement receipt that still needs a request purpose and/or receiving
 * officer (files logged before those fields existed have neither). The page
 * asks for $needs and sends the receipt again.
 */
class MissingReceiptDetailsException extends RuntimeException
{
    /** @param string[] $needs 'purpose' and/or 'officer' */
    public function __construct(public readonly array $needs)
    {
        parent::__construct(in_array('purpose', $needs, true) && in_array('officer', $needs, true)
            ? 'Choose a request purpose and a receiving officer to receive this file.'
            : (in_array('purpose', $needs, true)
                ? 'Choose a request purpose, or choose Other and specify it.'
                : 'Choose a receiving officer, or choose Other and specify the name.'));
    }
}
