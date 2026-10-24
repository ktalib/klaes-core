<?php

namespace App\Support;

use App\Models\LandRecommendation;
use App\Models\LandUse;
use App\Models\Lga;
use App\Models\Purpose;
use Illuminate\Support\HtmlString;

/**
 * Inline editing on the RofO / Land Recommendation tables (public/js/inline-batch-edit.js,
 * saved by LandRecommendationController::inlineUpdate).
 *
 *   <td ... {{ \App\Support\InlineEdit::attrs($rec, 'plot_number', $canInlineEdit) }}>
 *
 * Emits nothing when the user may not edit, so the cell stays an ordinary cell.
 */
class InlineEdit
{
    /** field => editor kind (must match LandRecommendationController::INLINE_FIELDS). */
    public const KINDS = [
        'plot_number'        => 'text',
        'layout_plan_no'     => 'text',
        'applicant_name'     => 'text',
        'applicant_address'  => 'text',
        'term'               => 'text',
        'development_period' => 'text',
        'ground_rent'        => 'money',
        'survey_fees'        => 'money',
        'development_value'  => 'money',
        'preparation_fees'   => 'money',
        'development_charge' => 'charge',
        'land_use_purpose'   => 'land_use_purpose',
        'location'           => 'location',
    ];

    /**
     * System (Super) Admins only, for now (2026-10-23). Everyone else sees no Edit
     * mode switch and the save route refuses them. To open it up later, widen this
     * one check — e.g. to canDo('Letter of Grant (RofO)', 'edit').
     */
    public static function canEdit(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    /** The raw value the editor opens with — composite fields as JSON. */
    public static function value(LandRecommendation $rec, string $field): string
    {
        switch (self::KINDS[$field] ?? 'text') {
            case 'money':
                $v = $rec->{$field};
                return $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
            case 'land_use_purpose':
                return json_encode([
                    'land_use_id'  => $rec->land_use_id,
                    'purpose_id'   => $rec->purpose_id,
                    'purpose_text' => (string) $rec->purpose_of_clause,
                ]);
            case 'location':
                return json_encode(['district' => (string) $rec->district, 'lga' => (string) $rec->lga]);
            default:
                return (string) ($rec->{$field} ?? '');
        }
    }

    public static function attrs(LandRecommendation $rec, string $field, bool $canEdit): HtmlString
    {
        if (!$canEdit || !isset(self::KINDS[$field])) {
            return new HtmlString('');
        }

        $e = fn ($v) => e((string) $v);
        $html = ' data-inline-edit data-rec-id="' . $e($rec->id) . '" data-field="' . $e($field) . '"'
            . ' data-kind="' . $e(self::KINDS[$field]) . '" data-value="' . $e(self::value($rec, $field)) . '"'
            . ' data-printed="' . (int) ($rec->rofo_print_count ?? 0) . '"';

        if ($field === 'location') {
            $html .= ' data-location-text="' . $e($rec->location) . '"';
        }

        return new HtmlString($html);
    }

    /** The same set of fields for a JSON row (batch expanders build cells in JS). */
    public static function rowValues(LandRecommendation $rec): array
    {
        $out = [];
        foreach (array_keys(self::KINDS) as $field) {
            $out[$field] = self::value($rec, $field);
        }
        return $out;
    }

    /**
     * Lookups the dropdown editors need: land uses, their purposes and the LGAs —
     * the same sources the Edit form uses. Small and fixed, so sent once per page.
     */
    public static function lookups(): array
    {
        return [
            'landUses' => LandUse::orderBy('landuse')->get(['id', 'landuse'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->landuse])->values(),
            'purposes' => Purpose::orderBy('name')->get(['id', 'landuseid', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'land_use_id' => (int) $p->landuseid, 'name' => $p->name])->values(),
            'lgas'     => Lga::where('is_active', true)->orderBy('name')->pluck('name')->values(),
            'districtsUrl' => url('api/reference/districts'),
        ];
    }
}
