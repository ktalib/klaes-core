<?php

namespace App\Http\Controllers\LandRegistration;

use App\Http\Controllers\RDSController;

/**
 * The Registration Details Sheet, as the Land Registry prints it.
 *
 * All the work - reading deed_registrations, the rds_tracking record, the print
 * count and the ORIGINAL/COPY watermark - is the Deeds controller's, unchanged.
 * The only difference is the template it renders and the register it sends the
 * user back to, so this class is three property overrides rather than a copy of
 * several hundred lines that would then drift.
 *
 * The Land template differs in what it must say, not how it is built: the
 * authority block reads LAND REGISTRY and the signature line reads DIRECTOR
 * LAND, per config('land_registration.authority').
 */
class LandRdsController extends RDSController
{
    protected string $printView = 'land_registration.rds.print';
    protected string $printRouteName = 'land-registration.rds.print';
    protected string $indexRouteName = 'land-registration.registration.index';
}
