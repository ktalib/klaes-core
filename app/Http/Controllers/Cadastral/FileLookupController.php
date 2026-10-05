<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Services\Cadastral\CadastralRegistryLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one endpoint behind the module's shared file picker
 * (partials/_file_picker): the global file-number selector hands back a
 * number and the tab it came from, and this says which file that is, what the
 * module already holds for it, and whether the calling form may take it.
 *
 * GET with a verb-free route name (cadastral-module.lookup.file), so the
 * permission layer infers `view`. It reads only; every form re-reads the file
 * on save and ignores what the browser filled in.
 */
class FileLookupController extends Controller
{
    public function __construct(private CadastralRegistryLookup $lookup) {}

    public function file(Request $r): JsonResponse
    {
        return response()->json($this->lookup->resolveFile([
            'file_number'      => mb_substr((string) $r->query('file_number', ''), 0, 100),
            'tab'              => mb_substr((string) $r->query('tab', ''), 0, 20),
            'scope'            => (string) $r->query('scope', 'indexed'),
            'purpose'          => (string) $r->query('purpose', ''),
            'file_indexing_id' => (int) $r->query('file_indexing_id', 0),
            'receipt'          => (int) $r->query('receipt', 0),
            'card'             => (int) $r->query('card', 0),
        ]));
    }
}
