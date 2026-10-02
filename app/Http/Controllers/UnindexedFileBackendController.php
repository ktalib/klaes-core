<?php

namespace App\Http\Controllers;

/**
 * Dedicated facade for the unindexed-file API endpoints so they can be
 * consumed by multiple UIs without touching the primary FileIndexController bindings.
 */
class UnindexedFileBackendController extends FileIndexController
{
    // Intentionally empty – inherits all behaviours from FileIndexController.
}

