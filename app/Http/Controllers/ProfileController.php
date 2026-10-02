<?php

namespace App\Http\Controllers;

use App\Models\LandOfficer;
use App\Models\User;
use App\Services\ProfilePhotoService;
use App\Support\Concerns\ResolvesWorkStations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ResolvesWorkStations;

    public function index()
    {
        $PageTitle = 'User Profile';
        $PageDescription = 'Manage your profile information';
        $user = Auth::user();
        $workStationOptions = $this->workStationOptions();
        $canEditWorkStation = empty($user->work_station);
        $deputyOptions = User::deputyOptions($user->id);
        // Officer ranks (seniority) for file-request prioritisation.
        $ranks = \App\Models\OfficerRank::options();

        return view('profile.index', compact(
            'PageTitle',
            'PageDescription',
            'workStationOptions',
            'canEditWorkStation',
            'deputyOptions',
            'ranks'
        ));
    }


    public function update(Request $request)
    {
        // Validate the incoming request
        $user = Auth::user();
        $workStationRule = $user->work_station
            ? ['prohibited']
            : $this->workStationValidationRule();
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone_number' => 'nullable|string|max:20',
            'rank' => 'nullable|string|max:255',
            'rank_other' => 'nullable|string|max:255|required_if:rank,' . \App\Models\OfficerRank::OTHER_VALUE,
            'password' => 'nullable|string|min:8|confirmed',
            'profile' => ProfilePhotoService::rules(),
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'work_station' => $workStationRule,
            'is_on_leave' => 'nullable|boolean',
            'leave_start_date' => 'nullable|date',
            'leave_end_date' => 'nullable|date|after_or_equal:leave_start_date',
            'deputy_user_id' => ['nullable', 'integer', 'exists:sqlsrv.users,id', Rule::notIn([$user->id])],
            'out_of_office_from' => 'nullable|date',
            'out_of_office_to' => 'nullable|date|after_or_equal:out_of_office_from',
        ]);

        // Handle profile image upload if provided
        if ($request->hasFile('profile')) {
            app(ProfilePhotoService::class)->store($request->file('profile'), $user);
        }

        // Store/update Lands 12 signature against the user's linked land officer profile.
        if ($request->hasFile('signature_file')) {
            $officer = LandOfficer::where('user_id', $user->id)->first();

            if ($officer && !empty($officer->signature_file)) {
                $oldPath = $officer->signature_file;
                // Old paths: 'public/land_officer_signatures/...' stored on local disk
                // New paths: 'land_officer_signatures/...' stored on public disk
                if (str_starts_with($oldPath, 'public/')) {
                    Storage::disk('local')->delete($oldPath);
                } else {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store on the 'public' disk so the file is web-accessible via /storage/...
            $signaturePath = $request->file('signature_file')->store('land_officer_signatures', 'public');

            if (!$officer) {
                $officer = new LandOfficer();
                $officer->user_id = $user->id;
                $officer->name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
                if ($officer->name === '') {
                    $officer->name = (string) ($user->name ?? $user->email);
                }
                $officer->notification_type = 'email';
            }

            $officer->signature_file = $signaturePath;
            $officer->save();
        }

        // Update user information
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'] ?? null;
        // Seniority for file-request priority; "Other (specify)" adds to the lookup table.
        $user->rank = \App\Models\OfficerRank::resolveSubmittedRank($request->input('rank'), $request->input('rank_other'));

        // Only update password if provided
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->is_password_change = 1; // Mark password as changed
        }

        if (empty($user->work_station) && array_key_exists('work_station', $validated)) {
            $user->work_station = $validated['work_station'];
        }

        // Self-service out-of-office status
        $user->is_on_leave = $request->boolean('is_on_leave');
        $user->leave_start_date = $validated['leave_start_date'] ?? null;
        $user->leave_end_date = $validated['leave_end_date'] ?? null;
        $user->deputy_user_id = $validated['deputy_user_id'] ?? null;
        $user->out_of_office_from = $validated['out_of_office_from'] ?? null;
        $user->out_of_office_to = $validated['out_of_office_to'] ?? null;

        // Save the updated user
        $user->save();
        
        return redirect()->route('profile.index')
                        ->with('success', 'Profile updated successfully.');
    }

    /**
     * Passport photo upload used by the mandatory profile-picture card.
     *
     * Kept separate from update() because that method requires the full profile form;
     * this one accepts the photo on its own so a user locked out by
     * RequireProfilePhoto can satisfy the requirement without filling anything else in.
     */
    public function storePicture(Request $request)
    {
        $request->validate(
            [
                'profile' => ProfilePhotoService::rules(true),
                // The card has already run the face check on this very file, so it can
                // say what it found and save the browser judging the same picture again
                // on the next page load. Optional: an upload from a browser where the
                // detector never loaded simply reports nothing.
                'face_status' => ['nullable', Rule::in([ProfilePhotoService::FACE_PASS, ProfilePhotoService::FACE_FAIL])],
                'face_reason' => ['nullable', 'string', 'max:160'],
            ],
            [
                'profile.required' => __('Please choose a passport photo to upload.'),
                'profile.image' => __('The file must be an image (JPG, PNG or GIF).'),
                'profile.mimes' => __('The file must be an image (JPG, PNG or GIF).'),
                'profile.max' => __('The photo must be 2MB or smaller.'),
            ]
        );

        $user = Auth::user();
        $service = app(ProfilePhotoService::class);
        // store() clears any previous verdict, so this has to come after it.
        $service->store($request->file('profile'), $user);

        if ($request->filled('face_status')) {
            $service->recordFaceVerdict($user, (string) $request->input('face_status'), $request->input('face_reason'));
        }

        $user->save();

        $message = __('Your profile picture has been uploaded successfully. You can now continue using the system.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'profile_url' => $user->profile_url,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Record the browser's face-check verdict on a picture that is already on file.
     *
     * Face detection is client-side (face-api.js, run from js/profile-photo-self-check.js
     * and the shared profile card) — there is no PHP detector — so this endpoint is the
     * only way a verdict reaches the gate that RequireProfilePhoto enforces.
     *
     * Who may report on whom:
     *   - anybody, about their own picture. This is the normal path: the check runs once,
     *     on the owner's next page load after the picture was stored.
     *   - a Super Admin, about anybody's picture. That is how a stock avatar spotted on
     *     the profile card gets flagged without waiting for that person to sign in.
     * Nobody else can flag another account — a verdict here locks someone out of the
     * whole system, so it is not something an ordinary session may do to a colleague.
     *
     * Only 'pass' and 'fail' are accepted. "The detector could not load" is not a verdict
     * and is never reported, so an unreachable model leaves the account exactly as it was.
     */
    public function storeFaceCheck(Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ProfilePhotoService::FACE_PASS, ProfilePhotoService::FACE_FAIL])],
            'reason' => ['nullable', 'string', 'max:160'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $viewer = Auth::user();
        $targetId = (int) ($validated['user_id'] ?? 0);
        $target = $viewer;

        if ($targetId > 0 && $targetId !== (int) $viewer->id) {
            abort_unless($viewer->isSuperAdmin(), 403);
            $target = User::find($targetId);

            if (!$target) {
                return response()->json(['success' => false, 'message' => __('No matching user record was found.')], 404);
            }
        }

        // Nothing to judge: the account has no picture, so the missing-photo rule already
        // covers it and a verdict would only pin itself to an empty path.
        if (!$target->has_profile_photo) {
            return response()->json(['success' => false, 'recorded' => false, 'locked' => true]);
        }

        $service = app(ProfilePhotoService::class);

        if (!$service->recordFaceVerdict($target, $validated['status'], $validated['reason'] ?? null)) {
            // The photo_face_* columns are not on this database yet (code deployed ahead
            // of the SQL script). Report it plainly rather than pretending it was stored.
            return response()->json(['success' => false, 'recorded' => false, 'locked' => false]);
        }

        $target->save();

        return response()->json([
            'success' => true,
            'recorded' => true,
            // The client reloads on true, so the banner, the sidebar lock and the upload
            // card all appear at once instead of at the next navigation.
            'locked' => $target->needs_profile_photo,
        ]);
    }
}
