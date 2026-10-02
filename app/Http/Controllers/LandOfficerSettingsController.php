<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LandOfficer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandOfficerSettingsController extends Controller
{
    public function index()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        // Staff only -- this screen is for KLAES users who sign. The document
        // signatories in the same table (Commissioner, Governor) are maintained from
        // Configurable Entries -> Signatories and have no user to show here.
        $officers    = LandOfficer::staffOfficers()->with('user.department')->orderBy('id')->get();

        return view('digital_signatures.index', compact('departments', 'officers'));
    }

    /**
     * AJAX endpoint – return users belonging to a department (0 = all departments).
     */
    public function usersByDepartment(int $departmentId): JsonResponse
    {
        $query = User::where('is_active', 1)->orderBy('first_name');

        if ($departmentId > 0) {
            $query->where('department_id', $departmentId);
        }

        $users = $query->get(['id', 'first_name', 'last_name', 'rank', 'signature', 'department_id']);

        return response()->json($users->map(fn ($u) => [
            'id'        => $u->id,
            'name'      => trim($u->first_name . ' ' . $u->last_name),
            'rank'      => $u->rank ?? '',
            'signature' => $u->signature ? Storage::disk('public')->url(preg_replace('#^public/#', '', $u->signature)) : '',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'           => 'required|integer|exists:sqlsrv.dbo.users,id',
            'rank'              => 'nullable|string|max:255',
            'notification_type' => 'required|in:email,sms,password',
            'signature_file'    => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $signaturePath = null;
        if ($request->hasFile('signature_file')) {
            $signaturePath = $request->file('signature_file')->store('signing_officer_signatures', 'public');
        }

        // Persist rank + signature on the user record.
        $userUpdates = ['rank' => $validated['rank'] ?? $user->rank];
        if ($signaturePath) {
            $userUpdates['signature'] = $signaturePath;
        }
        $user->update($userUpdates);

        // Create or update the signing officer record for this user.
        $officerData = [
            'user_id'           => $user->id,
            'name'              => $user->name,
            'rank'              => $validated['rank'] ?? null,
            'notification_type' => $validated['notification_type'],
        ];
        if ($signaturePath) {
            $officerData['signature_file'] = $signaturePath;
        }

        $officer = LandOfficer::updateOrCreate(
            ['user_id' => $user->id],
            $officerData
        );

        return response()->json([
            'success' => true,
            'message' => 'Signing Officer saved successfully.',
            'data'    => $officer,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $officer = LandOfficer::findOrFail($id);

        $validated = $request->validate([
            'user_id'           => 'required|integer|exists:sqlsrv.dbo.users,id',
            'rank'              => 'nullable|string|max:255',
            'notification_type' => 'required|in:email,sms,password',
            'signature_file'    => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $signaturePath = null;
        if ($request->hasFile('signature_file')) {
            $signaturePath = $request->file('signature_file')->store('signing_officer_signatures', 'public');
        }

        // Persist rank + signature on the user record.
        $userUpdates = ['rank' => $validated['rank'] ?? $user->rank];
        if ($signaturePath) {
            $userUpdates['signature'] = $signaturePath;
        }
        $user->update($userUpdates);

        // Update the signing officer record.
        $officerData = [
            'user_id'           => $user->id,
            'name'              => $user->name,
            'rank'              => $validated['rank'] ?? null,
            'notification_type' => $validated['notification_type'],
        ];
        if ($signaturePath) {
            $officerData['signature_file'] = $signaturePath;
        }

        $officer->update($officerData);

        return response()->json([
            'success' => true,
            'message' => 'Signing Officer updated successfully.',
            'data'    => $officer->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $officer = LandOfficer::findOrFail($id);

        // Remove the signature file from disk and clear the user's signature field
        $user    = $officer->user;
        $sigPath = $officer->signature_file ?? ($user?->signature ?? null);

        if ($sigPath) {
            $cleanPath = preg_replace('#^public/#', '', $sigPath);
            Storage::disk('public')->delete($cleanPath);
        }

        if ($user) {
            $user->update(['signature' => null]);
        }

        $officer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Signing Officer removed successfully.',
        ]);
    }
}
