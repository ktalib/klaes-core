<?php

namespace App\Services\Survey;

use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Who asked for a plot showing (user, 2026-11-02), for Record Plot Showing in the office and the
 * Plot Confirmation app. With "Requester is the allottee" ticked the name is the OP's allottee,
 * read from the OP rather than trusted from the form. The address is the full address: from the
 * office's address builder, or one line from the phone (falling back to the allottee's address on
 * record when ticked).
 */
class ShowingRequester
{
    /**
     * @param array<string, mixed> $op an OpLookup record
     * @param bool $required false for the phone's offline queue, where a showing saved before
     *                       these fields existed carries none of them
     * @return array{requester_is_allottee: ?bool, requester_name: ?string, requester_address: ?string}
     */
    public static function resolve(Request $r, array $op, bool $required = true): array
    {
        if (! $r->has('requester_is_allottee') && ! $r->filled('requester_name') && ! $required) {
            return ['requester_is_allottee' => null, 'requester_name' => null, 'requester_address' => null];
        }

        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/', ' ', (string) $v))) === '' ? null : $v;
        $same = $r->boolean('requester_is_allottee');
        $name = $same ? $clean($op['allottee'] ?? null) : $clean($r->input('requester_name'));
        // The office form sends the address builder (req_*): stored as the composed full address,
        // "Street, Plot|House, District, LGA, State". The phone sends one line.
        $errors = [];
        if ($r->hasAny(['req_state', 'req_district', 'req_lga'])) {
            $parts = AddressBuilder::fromPrefixed($r->all(), 'req_');
            if (! AddressBuilder::resolve($parts['district'], $parts['district_other'])) {
                $errors['req_district'] = "Give the requester's district.";
            }
            if (! AddressBuilder::resolve($parts['lga'])) {
                $errors['req_lga'] = "Choose the requester's LGA.";
            }
            $address = $clean(AddressBuilder::personAddress($parts));
        } else {
            $address = $clean($r->input('requester_address')) ?? ($same ? $clean($op['address'] ?? null) : null);
        }

        if (! $name) {
            $errors['requester_name'] = $same
                ? 'This OP has no allottee name on record: untick "Requester is the allottee" and type the name.'
                : "Give the requester's name.";
        } elseif (mb_strlen($name) > 255) {
            $errors['requester_name'] = "The requester's name can be at most 255 characters.";
        }
        if (! $address) {
            $errors['requester_address'] = "Give the requester's address.";
        } elseif (mb_strlen($address) > 500) {
            $errors['requester_address'] = "The requester's address can be at most 500 characters: shorten the street or the Other entries.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['requester_is_allottee' => $same, 'requester_name' => $name, 'requester_address' => $address];
    }
}
