<?php

namespace App\Http\Controllers\LandRegistration;

use App\Http\Controllers\CoroiController;

/**
 * The Confirmation of Registration of Instrument, as the Land Registry issues it.
 *
 * Same lookup, same QR code, same cor_exists bookkeeping as the Deeds
 * certificate - only the template differs, so this is a single property
 * override rather than a copy of the controller.
 *
 * What the Land template changes:
 *   - the header reads LAND REGISTRY, not DEEDS REGISTRY/DEPARTMENT
 *   - the red box cites the Land Registry
 *   - the bottom carries the registry's own stamp block (DEEDS OF SALE
 *     REGISTERED ... DIRECTOR LAND) in place of REGISTRAR OF DEEDS
 */
class LandCorController extends CoroiController
{
    protected string $view = 'land_registration.cor.index';
}
