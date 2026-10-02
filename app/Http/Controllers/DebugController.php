<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DebugController extends Controller
{
    /**
     * Check user roles
     */
    public function checkUserRoles()
    {
        return response()->json(['message' => 'Debug user roles not implemented yet']);
    }

    /**
     * Add sample roles
     */
    public function addSampleRoles()
    {
        return response()->json(['message' => 'Add sample roles not implemented yet']);
    }

    /**
     * Roles departments debug
     */
    public function rolesDepartments()
    {
        return response()->json(['message' => 'Roles departments debug not implemented yet']);
    }
}