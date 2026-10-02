<?php

namespace App\Services\Tdp;

use RuntimeException;

/**
 * Anything the Title Deed Plan library refuses: an unreachable store, a
 * rejected path, a disallowed extension, a write that failed. The controller
 * turns these into a flash message — never a stack trace.
 */
class TdpException extends RuntimeException
{
}
