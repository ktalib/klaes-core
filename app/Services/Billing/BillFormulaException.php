<?php

namespace App\Services\Billing;

/**
 * A bill formula that cannot be parsed or worked out. The message is
 * officer-facing (BillFormula::problems shows it as-is).
 */
class BillFormulaException extends \RuntimeException
{
}
