<?php

namespace Tests\Unit\Rules;

use App\Rules\NigerianPhone;
use PHPUnit\Framework\TestCase;

/**
 * The rule and public/js/nigerian-phone.js are two halves of one contract:
 * the browser folds what people type into 0XXXXXXXXXX, the rule refuses
 * anything that is not already in that shape. Keep the two in step.
 */
class NigerianPhoneTest extends TestCase
{
    /** @dataProvider validNumbers */
    public function test_it_accepts_canonical_numbers(string $value): void
    {
        $this->assertTrue((new NigerianPhone())->passes('phone', $value));
    }

    public static function validNumbers(): array
    {
        return [
            'MTN'     => ['08012345678'],
            'Airtel'  => ['07098765432'],
            '9mobile' => ['09011122233'],
        ];
    }

    /** @dataProvider invalidNumbers */
    public function test_it_rejects_anything_not_canonical(string $value): void
    {
        $this->assertFalse((new NigerianPhone())->passes('phone', $value));
    }

    public static function invalidNumbers(): array
    {
        return [
            'too short'          => ['0801234567'],
            'too long'           => ['080123456789'],
            'no leading zero'    => ['8012345678'],
            'country code'       => ['2348012345678'],
            'plus country code'  => ['+2348012345678'],
            'spaced'             => ['0801 234 5678'],
            'letters'            => ['abcdefghijk'],
            // 156 of 1,564 staff rows look like this: a number keyed twice, or
            // with trailing digits. Nothing can safely repair them.
            'doubled digits'     => ['0801234567812'],
        ];
    }

    /**
     * Presence is `required`/`nullable`'s job, not this rule's — so the rule
     * can sit on optional fields without making them mandatory.
     */
    public function test_an_empty_value_defers_to_the_presence_rule(): void
    {
        $rule = new NigerianPhone();

        $this->assertTrue($rule->passes('phone', ''));
        $this->assertTrue($rule->passes('phone', '   '));
        $this->assertTrue($rule->passes('phone', null));
    }

    /** @dataProvider normalisable */
    public function test_it_normalises_the_shapes_people_actually_type(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, NigerianPhone::normalize($input));
    }

    public static function normalisable(): array
    {
        return [
            'international spaced' => ['+234 801 234 5678', '08012345678'],
            'country code'         => ['2348012345678', '08012345678'],
            'hyphenated'           => ['0801-234-5678', '08012345678'],
            'missing leading zero' => ['8012345678', '08012345678'],
            'already canonical'    => ['08012345678', '08012345678'],
            'padded'               => ['  08012345678  ', '08012345678'],
            'null stays null'      => [null, null],
        ];
    }

    /**
     * An unrecognisable value comes back untouched rather than mangled, so the
     * officer sees the rule's message about what they actually typed.
     */
    public function test_it_leaves_unrecognisable_values_alone(): void
    {
        $this->assertSame('not a phone', NigerianPhone::normalize('not a phone'));
        $this->assertSame('', NigerianPhone::normalize(''));
    }
}
