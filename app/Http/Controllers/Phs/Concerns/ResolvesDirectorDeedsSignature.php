<?php

namespace App\Http\Controllers\Phs\Concerns;

use App\Models\User;
use App\Support\SignatureImage;

/**
 * Resolves the Authorized Signatory (Director Deeds) signature for the certified
 * PHS search slip. Shared by the member-facing slip and the staff "Open Search"
 * re-print so both render an identical signature.
 *
 * Pulls the signature image of the Director Deeds in the Lands department
 * (department_id = 8) and returns it as an inline base64 data URI so it embeds
 * reliably when the slip is printed/saved as PDF.
 */
trait ResolvesDirectorDeedsSignature
{
    protected function directorDeedsSignature(): ?string
    {
        $path = User::where('department_id', 8)
            ->where('rank', 'Director Deeds')
            ->value('signature');

        // Path normalisation and data-URI conversion are shared with the Online
        // Legal Search report, which signs the same way.
        return SignatureImage::dataUri($path);
    }
}
