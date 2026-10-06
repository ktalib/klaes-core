{{-- Virtual Folder System — a SUB-ITEM of Digital File Archive.

     It belongs there: the archive is where scanned pages live, and this is a view
     over exactly those pages. It is included from digital_archive.blade.php rather
     than from menu.blade.php, so there is one definition of the item and moving it
     again means moving one include.

     SUPER ADMINS ONLY. Gated on isSuperAdmin() rather than $hasRole(), because
     $hasRole resolves through ModulePermissions::allows(), which grants the module
     to anyone holding the role AND to super admins. Only the stricter test expresses
     "super admins and nobody else".

     The same rule guards the URLs — routes/vfs.php carries the super.admin
     middleware. A hidden menu item is not access control, and this entry is hidden
     precisely because the module is restricted. --}}
@if(auth()->check() && auth()->user()->isSuperAdmin())
<a href="" onclick="event.preventDefault()"
   class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('vfs.*') ? 'active' : '' }}">
  <i data-lucide="folder-tree" class="h-4 w-4 text-amber-500"></i>
  <span>Virtual Folder System</span>
</a>
@endif
