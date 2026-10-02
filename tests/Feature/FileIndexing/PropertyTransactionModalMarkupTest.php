<?php

namespace Tests\Feature\FileIndexing;

use App\Models\User;
use App\Services\KanoLgaDirectory;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The "Add Property Transaction Details" card is one enormous Alpine component whose whole
 * state object lives inside a double-quoted x-data="..." attribute. A single raw double
 * quote anywhere in that expression — in a string, in a JS comment, or in JSON echoed from
 * Blade — ends the attribute where the browser sees it, and every line after it renders on
 * the page as visible source text.
 *
 * That has now happened twice: once from Blade's raw JSON directive emitting unescaped
 * quotes, and once from a JS comment quoting a value. It is invisible to a Blade compile
 * check and to any test that locates the attribute's end by searching for a later landmark,
 * so it is asserted here the way a browser actually parses: opening quote to the very next
 * quote.
 */
class PropertyTransactionModalMarkupTest extends TestCase
{
    private const VIEW = 'fileindexing.partial.property_transaction_modal';

    private function render(): string
    {
        // The partial reads the session and the signed-in user for its CSRF field.
        $user = User::on('sqlsrv')->first();
        if ($user) {
            Auth::login($user);
        }
        app('session.store')->start();

        return view(self::VIEW)->render();
    }

    /**
     * The value a browser would hand to Alpine: from the opening quote to the NEXT quote,
     * then HTML-decoded.
     */
    private function xDataAsTheBrowserSeesIt(string $html): string
    {
        $open = strpos($html, '<div x-data="');
        $this->assertNotFalse($open, 'The modal no longer has an x-data attribute to check.');

        $start = $open + strlen('<div x-data="');
        $end   = strpos($html, '"', $start);
        $this->assertNotFalse($end, 'The x-data attribute is never closed.');

        return html_entity_decode(substr($html, $start, $end - $start), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function test_the_x_data_attribute_is_not_cut_short_by_a_stray_quote(): void
    {
        $value = $this->xDataAsTheBrowserSeesIt($this->render());

        // A truncated attribute always leaves braces open, and always ends mid-expression.
        $this->assertSame(
            substr_count($value, '{'),
            substr_count($value, '}'),
            "The x-data attribute ends with unbalanced braces, which means a raw double quote "
            . "closed it early. It ends with:\n..." . substr($value, -200)
        );
        $this->assertSame(
            substr_count($value, '['),
            substr_count($value, ']'),
            'The x-data attribute ends with unbalanced brackets.'
        );
        $this->assertStringEndsWith('}', rtrim($value));
    }

    /** The whole component must survive, not just the opening lines. */
    public function test_the_x_data_attribute_still_carries_the_components_methods(): void
    {
        $value = $this->xDataAsTheBrowserSeesIt($this->render());

        foreach ([
            'submitTransactions()',      // the last method in the object
            'isLgaOpType(transaction)',
            'handleOpTypeChange(transaction)',
            'regParticularsLocked(transaction)',
            'catalogEntryFor(transaction)',  // the one Type + one Category pair, the
            'typeOptionsFor(transaction)',   // catalogue that drives it, and the Old
            'categoryOptionsFor(transaction)',
            'handleTypeChange(transaction)',
            'opRegNoRequired(transaction)',  // rule it relaxes — all inside the attribute
            'kanoLgaAuthorities:',
        ] as $member) {
            $this->assertStringContainsString(
                $member,
                $value,
                "{$member} is outside the x-data attribute the browser sees — it is being "
                . 'rendered onto the page as text instead of evaluated.'
            );
        }
    }

    /** All 44 authority names have to arrive intact inside the attribute. */
    public function test_the_forty_four_local_governments_reach_the_component(): void
    {
        $value = $this->xDataAsTheBrowserSeesIt($this->render());

        $this->assertSame(1, preg_match('/kanoLgaAuthorities:\s*(\[.*?\])/s', $value, $m));

        $names = json_decode($m[1], true);
        $this->assertIsArray($names, 'The LGA list did not survive as valid JSON inside the attribute.');
        $this->assertCount(44, $names);
        $this->assertSame(app(KanoLgaDirectory::class)->fullNames(), $names);
    }

    /**
     * x-bind rewrites an UNDEFINED dotted expression to an empty string, and an empty
     * string on a boolean attribute means the attribute is present. So `:disabled="a.b"`
     * DISABLES the field whenever `b` was never declared on `a` — which is how every
     * Transaction Type dropdown ended up locked: only the object addTransaction() builds
     * declared totRequired, and the card opens with, and update mode loads, other shapes.
     *
     * A bare dotted path on a boolean attribute is therefore never safe here. Compare it
     * (`=== true`) or negate it (`!!`) so the expression always yields a real boolean.
     */
    public function test_no_boolean_attribute_is_bound_to_a_bare_dotted_path(): void
    {
        $html = $this->render();

        $booleans = 'disabled|readonly|required|checked|selected|multiple|open|hidden';
        preg_match_all(
            '/:(' . $booleans . ')="([A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z0-9_$]+)+)"/',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $offenders = array_map(fn ($m) => ':' . $m[1] . '="' . $m[2] . '"', $matches);

        $this->assertSame([], $offenders, implode(PHP_EOL, [
            'These bindings turn ON whenever the property is undefined:',
            implode(PHP_EOL, $offenders),
            'Write them as "=== true" or "!!" so an undefined value stays falsy.',
        ]));
    }

    /** No Alpine attribute anywhere in the card may be cut short the same way. */
    public function test_no_alpine_attribute_is_truncated_by_a_raw_quote(): void
    {
        $html = $this->render();
        $broken = [];

        foreach (['x-data', 'x-init', 'x-show', 'x-text', 'x-model', ':class', ':name'] as $attr) {
            $offset = 0;
            while (($i = strpos($html, $attr . '="', $offset)) !== false) {
                $start = $i + strlen($attr) + 2;
                $end   = strpos($html, '"', $start);
                $value = substr($html, $start, $end - $start);
                $offset = $end + 1;

                $opens  = substr_count($value, '{') + substr_count($value, '[');
                $closes = substr_count($value, '}') + substr_count($value, ']');
                if ($opens !== $closes) {
                    $broken[] = $attr . ': ...' . substr($value, -120);
                }
            }
        }

        $this->assertSame([], $broken, "Truncated Alpine attribute(s):\n" . implode("\n", $broken));
    }
}
