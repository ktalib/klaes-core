<?php

namespace App\Services\DocumentQr;

use RuntimeException;

/**
 * Thrown when a payload announces itself as a KLAES-Q1 token but fails to
 * decrypt or authenticate.
 *
 * This is deliberately distinct from "no matching record": a rejected token
 * means the paper was altered or forged and should be retained, whereas a
 * reference that simply is not in the register is an ordinary miss. The two
 * produce different verdicts on the verification console ("tampered" vs
 * "notfound") and different words for the officer at the counter.
 */
class InvalidQrToken extends RuntimeException
{
}
