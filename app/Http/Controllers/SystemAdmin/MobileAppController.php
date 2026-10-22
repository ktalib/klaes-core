<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * System Admin > KLAES Mobile Apps.
 *
 * Admin side (show/update): upload the APK and icon, edit the details.
 * Public side (publicPage/download/icon): the page a shared link opens, so
 * field officers can install the app on a phone without signing in first.
 */
class MobileAppController extends Controller
{
    private const DISK = 'private_files';

    private const MAX_APK_KB = 512000; // 500 MB; php.ini allows 512M

    public function show(Request $request, MobileApp $mobileApp)
    {
        $this->authoriseManage($request);

        return view('system-admin.mobile-apps.show', [
            'app' => $mobileApp,
            'apps' => MobileApp::orderBy('id')->get(['id', 'slug', 'name']),
        ]);
    }

    public function update(Request $request, MobileApp $mobileApp)
    {
        $this->authoriseManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'version' => ['nullable', 'string', 'max:50'],
            'min_android' => ['nullable', 'string', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'apk' => ['nullable', 'file', 'max:'.self::MAX_APK_KB],
        ]);

        // An APK is a zip, so MIME sniffing reports application/zip; the
        // extension is the meaningful check here.
        $apk = $request->file('apk');
        if ($apk && strtolower($apk->getClientOriginalExtension()) !== 'apk') {
            throw ValidationException::withMessages(['apk' => 'The app file must be an .apk file.']);
        }

        $updates = [
            'name' => trim(preg_replace('/\s+/', ' ', $data['name'])),
            'version' => isset($data['version']) ? trim($data['version']) : null,
            'min_android' => isset($data['min_android']) ? trim($data['min_android']) : null,
            'short_description' => isset($data['short_description']) ? trim($data['short_description']) : null,
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'updated_by' => $request->user()->id,
        ];

        $dir = 'app/private/mobile_apps/'.$mobileApp->slug;
        $disk = Storage::disk(self::DISK);
        $oldFiles = [];

        if ($icon = $request->file('icon')) {
            $updates['icon_path'] = $disk->putFileAs($dir, $icon, 'icon-'.now()->format('YmdHis').'.'.strtolower($icon->getClientOriginalExtension()));
            $oldFiles[] = $mobileApp->icon_path;
        }

        if ($apk) {
            $updates['apk_path'] = $disk->putFileAs($dir, $apk, $mobileApp->slug.'-'.now()->format('YmdHis').'.apk');
            $updates['apk_original_name'] = $apk->getClientOriginalName();
            $updates['apk_size'] = $apk->getSize();
            $updates['apk_uploaded_at'] = now();
            $oldFiles[] = $mobileApp->apk_path;
        }

        if ((isset($updates['icon_path']) && ! $updates['icon_path']) || (isset($updates['apk_path']) && ! $updates['apk_path'])) {
            return back()->withInput()->with('error', 'The file could not be saved to storage. Nothing was changed.');
        }

        $mobileApp->update($updates);

        // The replaced files are superseded, not records: keep the folder to
        // the current version only.
        foreach (array_filter($oldFiles) as $old) {
            $disk->delete($old);
        }

        return redirect()->route('system-admin.mobile-apps.show', $mobileApp)
            ->with('success', $mobileApp->name.' updated.');
    }

    public function publicPage(MobileApp $mobileApp)
    {
        return view('system-admin.mobile-apps.public', ['app' => $mobileApp]);
    }

    public function download(MobileApp $mobileApp)
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($mobileApp->hasApk() && $disk->exists($mobileApp->apk_path), 404, 'This app has not been uploaded yet.');

        DB::connection('sqlsrv')->table('mobile_apps')->where('id', $mobileApp->id)->increment('download_count');

        $name = preg_replace('/[^A-Za-z0-9]+/', '-', $mobileApp->name);
        $name = trim($name, '-').($mobileApp->version ? '-v'.preg_replace('/[^A-Za-z0-9.]+/', '', $mobileApp->version) : '').'.apk';

        return $disk->download($mobileApp->apk_path, $name, [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function icon(MobileApp $mobileApp)
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($mobileApp->icon_path && $disk->exists($mobileApp->icon_path), 404);

        return response()->file($disk->path($mobileApp->icon_path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Same rule as the sidebar: System Settings, or super admin. */
    private function authoriseManage(Request $request): void
    {
        $user = $request->user();

        $allowed = $user && (
            $user->canDo('System Settings', 'view')
            || $user->canDo('Supper Admin', 'view')
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
        );

        abort_unless($allowed, 403, 'You do not have permission to manage the mobile apps.');
    }
}
