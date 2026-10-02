<div class="p-6 border-t border-gray-200 flex justify-between items-center text-sm text-gray-500 mt-auto">
  <div>&copy; {{ date('Y') }} LAnd ADmin Enterprise System. All rights reserved.</div>
  @php
    $footerDefaultLogo = asset('assets/logo/las.jpg');
    $footerLogo = $footerDefaultLogo;
    if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
      if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
        $footerLogo = asset('storage/uploads/logo.jpeg');
      } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png')) {
        $footerLogo = asset('storage/upload/logo/logo.png');
      }
    }
  @endphp
  <div class="flex items-center">
    <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="Logo" style="width: 200px; height: auto;">
  </div>
</div>
