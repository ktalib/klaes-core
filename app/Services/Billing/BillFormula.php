<?php

namespace App\Services\Billing;

/**
 * A bill formula: item variables joined with +, - and brackets.
 *
 *   "LS_FEE"
 *   "REG_FEE + APPROVAL_FEE + STAMP_DUTY + DICING + TAX"
 *   "(REG_FEE + STAMP_DUTY) - REBATE"
 *
 * Parsed by a small recursive-descent parser; nothing is ever passed to eval.
 * Variables are letters, digits and underscores, starting with a letter, and are
 * matched case-insensitively.
 *
 *   expression := term (('+' | '-') term)*
 *   term       := ('+' | '-') term | VARIABLE | '(' expression ')'
 */
class BillFormula
{
    /** @var list<array{type: string, value: string, at: int}> */
    private array $tokens = [];
    private int $position = 0;
    private array $values = [];

    /** The variables a formula mentions, upper-cased, in order of first use. */
    public static function variables(string $formula): array
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9_]*/', $formula, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[0])));
    }

    /**
     * Problems with a formula, as officer-facing sentences (empty when it is valid).
     *
     * @param list<string> $known the variables that exist for this bill
     */
    public static function problems(string $formula, array $known): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return [];
        }

        $known = array_map('strtoupper', $known);
        $problems = [];

        try {
            (new self())->run($formula, array_fill_keys($known, 0.0), false);
        } catch (BillFormulaException $e) {
            $problems[] = $e->getMessage();
        }

        $unknown = array_diff(self::variables($formula), $known);
        if ($unknown) {
            $problems[] = 'Unknown ' . (count($unknown) === 1 ? 'variable' : 'variables') . ': ' . implode(', ', $unknown) . '. Use: ' . (implode(', ', $known) ?: 'add an item first') . '.';
        }

        return array_values(array_unique($problems));
    }

    /**
     * Works the formula out. An empty formula adds every value.
     *
     * @param array<string, float|int|string> $values variable => amount
     * @throws BillFormulaException
     */
    public static function evaluate(string $formula, array $values): float
    {
        $values = array_change_key_case(array_map('floatval', $values), CASE_UPPER);

        if (trim($formula) === '') {
            return round(array_sum($values), 2);
        }

        return round((new self())->run($formula, $values, true), 2);
    }

    /** "REG_FEE + STAMP_DUTY" with the amounts: "₦20,000.00 + ₦5,000.00". */
    public static function describe(string $formula, array $values): string
    {
        $values = array_change_key_case($values, CASE_UPPER);

        return preg_replace_callback('/[A-Za-z][A-Za-z0-9_]*/', function ($m) use ($values) {
            $key = strtoupper($m[0]);

            return array_key_exists($key, $values) ? '₦' . number_format((float) $values[$key], 2) : $m[0];
        }, trim($formula));
    }

    private function run(string $formula, array $values, bool $strict): float
    {
        $this->tokens = $this->tokenize($formula);
        $this->position = 0;
        $this->values = $values;

        if ($this->tokens === []) {
            throw new BillFormulaException('The formula is empty.');
        }

        $result = $this->expression($strict);

        if ($this->position < count($this->tokens)) {
            $token = $this->tokens[$this->position];
            throw new BillFormulaException($token['value'] === ')'
                ? 'There is a closing bracket without an opening one.'
                : "Expected + or - before “{$token['value']}”.");
        }

        return $result;
    }

    private function tokenize(string $formula): array
    {
        $tokens = [];
        $length = strlen($formula);

        for ($i = 0; $i < $length;) {
            $char = $formula[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }
            if (in_array($char, ['+', '-', '(', ')'], true)) {
                $tokens[] = ['type' => $char, 'value' => $char, 'at' => $i];
                $i++;
                continue;
            }
            if (preg_match('/\G[A-Za-z][A-Za-z0-9_]*/', $formula, $m, 0, $i)) {
                $tokens[] = ['type' => 'var', 'value' => strtoupper($m[0]), 'at' => $i];
                $i += strlen($m[0]);
                continue;
            }

            throw new BillFormulaException("“{$char}” is not allowed. Use item variables with +, - and brackets only.");
        }

        return $tokens;
    }

    private function expression(bool $strict): float
    {
        $total = $this->term($strict);

        while (($token = $this->peek()) && in_array($token['type'], ['+', '-'], true)) {
            $this->position++;
            $value = $this->term($strict);
            $total = $token['type'] === '+' ? $total + $value : $total - $value;
        }

        return $total;
    }

    private function term(bool $strict): float
    {
        $token = $this->peek();

        if (!$token) {
            throw new BillFormulaException('The formula ends with an operator; add an item after it.');
        }

        if ($token['type'] === '+' || $token['type'] === '-') {
            $this->position++;
            $value = $this->term($strict);

            return $token['type'] === '-' ? -$value : $value;
        }

        if ($token['type'] === 'var') {
            $this->position++;
            if (!array_key_exists($token['value'], $this->values)) {
                if ($strict) {
                    throw new BillFormulaException("The formula uses {$token['value']}, which this bill does not have.");
                }

                return 0.0;
            }

            return (float) $this->values[$token['value']];
        }

        if ($token['type'] === '(') {
            $this->position++;
            $value = $this->expression($strict);
            if (($this->peek()['type'] ?? null) !== ')') {
                throw new BillFormulaException('A bracket is not closed.');
            }
            $this->position++;

            return $value;
        }

        throw new BillFormulaException($token['type'] === ')' ? 'Empty brackets, or a bracket closed straight after an operator.' : "Unexpected “{$token['value']}”.");
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }
}

