<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which module raised a parcel update — Deeds or Land.
 *
 * Every parcel-update register (Duplex, Change of Purpose, Subdivision, Merger,
 * Extension, Separation) is reachable from both sidebars, and the only thing telling
 * them apart is `?mode=` on the link. That mode has only ever labelled the page; this
 * turns it into something the record carries, so a register showing both modules' work
 * can say which is which.
 *
 * ABSENT MEANS DEEDS. Not every link carries the parameter — the Deeds sidebar's
 * Separation entry has never had one — and the registers already read a missing mode
 * as Deeds when they title the page. Reading it the same way here keeps one answer
 * rather than two, and a link that gains the parameter later does not change what it
 * already meant.
 *
 * Only 'land' is matched positively, so a stray or misspelt value lands on Deeds
 * rather than inventing a third source.
 */
class ParcelUpdateSource
{
    public const DEEDS = 'deeds';
    public const LAND  = 'land';

    /** The source to stamp on a record being captured now. */
    public static function fromRequest(Request $request): string
    {
        return self::normalise($request->input('mode', $request->query('mode')));
    }

    /** 'land' for anything that means Land; 'deeds' for everything else. */
    public static function normalise($mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return in_array($mode, ['land', 'lands'], true) ? self::LAND : self::DEEDS;
    }

    /**
     * How the Source column reads.
     *
     * A record captured before the column existed has no answer, and there is nothing
     * in the row to infer one from — so it says so rather than defaulting to Deeds,
     * which would quietly assert something nobody recorded.
     */
    public static function label(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            self::LAND  => 'Land',
            self::DEEDS => 'Deeds',
            default     => '—',
        };
    }

    /** Tailwind classes for the chip, so all six registers colour it the same. */
    public static function chipClass(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            self::LAND  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::DEEDS => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            default     => 'bg-slate-50 text-slate-400 border-slate-200',
        };
    }
}
