<?php

namespace App\Http\Controllers;

use App\Models\OssVerification;
use Illuminate\Http\Request;

class OssVerificationController extends Controller
{
    public function index()
    {
        $verifications = OssVerification::orderBy('created_at', 'desc')->get();
        return view('admin.oss-verifications.index', compact('verifications'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'recommendation' => 'required|string',
            'chairman_name' => 'required|string',
        ]);

        $verification = OssVerification::findOrFail($id);
        $verification->update([
            'recommendation' => $request->recommendation,
            'chairman_name' => $request->chairman_name,
        ]);

        return redirect()->back()->with('success', 'Verification status updated successfully.');
    }
}
