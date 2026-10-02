<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Isolated test page for profile-picture face detection.
 *
 * Deliberately does nothing but render a view: detection runs entirely in the browser,
 * so there is no upload endpoint, no temporary file, no database write and no log entry.
 * Nothing here is wired into the real profile-picture upload — that stays untouched
 * until the behaviour on /test/face-detection has been approved.
 */
class FaceDetectionTestController extends Controller
{
    public function index(Request $request)
    {
        // Admin-only: this is a diagnostic page, not part of the normal workflow.
        abort_unless(optional($request->user())->isSuperAdmin(), 403);

        return view('test.face-detection', [
            'PageTitle' => 'Face Detection Test',
            'PageDescription' => 'Isolated test page — checks whether a picture contains a human face',
        ]);
    }
}
