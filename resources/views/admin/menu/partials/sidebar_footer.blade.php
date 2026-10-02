<div class="sidebar-footer border-t border-gray-200 p-4">
  <div class="flex items-center gap-3">
    <div class="relative">
      <div data-user-avatar class="h-10 w-10 rounded-full border-2 border-blue-600 cursor-pointer hover:scale-105 transition-transform overflow-hidden flex items-center justify-center bg-gray-100">
        @if(auth()->user()->profile_url)
          <img src="{{ auth()->user()->profile_url }}" alt="User" class="h-full w-full object-cover" />
        @else
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        @endif
      </div>
    </div>
    @php
      // The account's own username, not the generic "User" label that used to sit here.
      $sidebarUser = auth()->user();
      $sidebarUsername = $sidebarUser->username
        ?: (trim($sidebarUser->name) ?: $sidebarUser->email);
      $sidebarIsSupperAdmin = strtolower(trim((string) $sidebarUser->email)) === 'ict_director@klas.com.ng';
    @endphp
    <div class="flex min-w-0 flex-col">
      <span class="truncate text-sm font-medium" title="{{ $sidebarUsername }}">{{ $sidebarUsername }}</span>
      <span class="truncate text-xs text-gray-500" title="{{ $sidebarUser->email }}">
        {{ $sidebarIsSupperAdmin ? __('Supper Admin') : $sidebarUser->email }}
      </span>
    </div>
    <div class="relative ml-auto">
      <button class="p-1.5 rounded-md hover:bg-gray-100" id="userMenuButton">
        <i data-lucide="settings" class="h-4 w-4"></i>
      </button>
      <div class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden" id="userMenu">
        <div class="py-1">
          <div class="px-4 py-2 text-sm font-medium border-b border-gray-100">My Account</div>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="user-circle" class="mr-2 h-4 w-4"></i>
              <span>Profile</span>
            </div>
          </a>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="settings" class="mr-2 h-4 w-4"></i>
              <span>Settings</span>
            </div>
          </a>
          <div class="border-t border-gray-100"></div>
          <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="lock" class="mr-2 h-4 w-4"></i>
              <span>Logout</span>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
