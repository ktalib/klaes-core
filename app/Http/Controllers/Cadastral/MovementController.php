<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Services\Cadastral\CadastralRegistryLookup;
use Illuminate\Http\Request;

/**
 * File movements, read through from the tracker the rest of KLAES uses.
 *
 * No cadastral movements table exists and none should: file_tracker already
 * holds 52,054 rows and already knows the Cadastral registry (its API maps
 * ?url=cadastral to "Registry 1 - Cadastral"). A second log would immediately
 * disagree with the first.
 */
class MovementController extends Controller
{
    public function __construct(private CadastralRegistryLookup $lookup) {}

    public function index(Request $r)
    {
        $fileNumber = trim((string) $r->query('file_number', ''));

        $tracker   = null;
        $movements = [];

        if ($fileNumber !== '') {
            $found     = $this->lookup->movements($fileNumber);
            $tracker   = $found['tracker'];
            $movements = $found['movements'];
        }

        return view('cadastral_module.registry.movements', compact('fileNumber', 'tracker', 'movements'));
    }
}
