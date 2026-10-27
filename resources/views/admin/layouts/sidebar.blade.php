@php
  $user = auth()->user();
  $category = request('category');

  // Admin menu opens the first admin page this user is allowed to see
  $adminRoute = collect([
      'users.view' => 'admin.users.index',
      'roles.view' => 'admin.roles.index',
      'compliance_register.view' => 'admin.compliance-items.index',
      'audit_logs.view' => 'admin.audit-logs.index',
  ])->first(fn ($route, $permission) => $user->can($permission));

  // [label, icon, url, permission, is active]
  $menu = [
      ['Dashboard', 'fa-border-all', route('dashboard'), 'dashboard.view', request()->routeIs('dashboard')],
      ['Block Overview', 'fa-map-location-dot', route('blocks.index'), 'blocks.view', request()->routeIs('blocks.*')],
      ['Compliance Monitor', 'fa-shield-halved', route('compliance.index'), 'milestones.view', request()->routeIs('compliance.index') && ! in_array($category, ['Bank Guarantee', 'Licence'])],
      ['Work Programme (CWP)', 'fa-calendar-check', route('cwp.index'), 'blocks.view', request()->routeIs('cwp.*')],
      ['Statutory Clearances', 'fa-certificate', route('clearances.index'), 'clearances.view', request()->routeIs('clearances.*')],
      ['Financial Security (BG)', 'fa-vault', route('compliance.index', ['category' => 'Bank Guarantee']), 'milestones.view', request()->routeIs('compliance.index') && $category === 'Bank Guarantee'],
      ['Licences & Validity', 'fa-file-contract', route('compliance.index', ['category' => 'Licence']), 'milestones.view', request()->routeIs('compliance.index') && $category === 'Licence'],
      ['Investment Monitoring', 'fa-arrow-trend-up', route('investment.index'), 'investment.view', request()->routeIs('investment.*')],
      ['MC Meetings', 'fa-users', route('mc-meetings.index'), 'mc_meetings.view', request()->routeIs('mc-meetings.*')],
      ['Location Inventory', 'fa-location-dot', route('locations.index'), 'locations.view', request()->routeIs('locations.*')],
      ['Document Repository', 'fa-folder-open', route('documents.index'), 'documents.view', request()->routeIs('documents.*')],
      ['Reports & Downloads', 'fa-file-arrow-down', route('reports.index'), 'reports.view', request()->routeIs('reports.*')],
  ];
@endphp

<!-- ============================================================== -->
<!-- LEFT SIDEBAR (#0b1626 Deep Slate Navy)                          -->
<!-- ============================================================== -->
<aside id="app-sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-56 bg-oilSidebar text-slate-300 flex flex-col flex-shrink-0 h-full border-r border-slate-800/80 text-[12px] -translate-x-full lg:translate-x-0 transition-transform">

  <!-- Brand -->
  <div class="p-3.5 flex items-center gap-2.5 border-b border-slate-800/80">
    <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center p-0.5 shadow-sm flex-shrink-0">
      <div class="w-full h-full rounded-full border-2 border-red-600 flex flex-col items-center justify-center bg-white">
        <span class="text-[11px] font-black tracking-tighter text-red-600 leading-none">OIL</span>
      </div>
    </div>
    <div class="leading-tight overflow-hidden">
      <h1 class="font-bold text-[13px] text-white tracking-tight truncate">RSC Compliance Portal</h1>
      <p class="text-[10px] text-sky-400 font-medium">Aarohan 2.0</p>
    </div>
    <button type="button" onclick="toggleSidebar(false)" class="ml-auto lg:hidden text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">
    @foreach ($menu as [$label, $icon, $url, $permission, $active])
      @can($permission)
        <a href="{{ $url }}" class="w-full flex items-center gap-3 px-3 py-2 rounded transition {{ $active ? 'bg-oilSidebarActive text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-oilSidebarHover font-medium' }}">
          <i class="fa-solid {{ $icon }} w-4 text-center {{ $active ? 'text-white' : 'text-slate-400' }}"></i>
          <span>{{ $label }}</span>
        </a>
      @endcan
    @endforeach

    @if ($adminRoute)
      <div class="pt-2 border-t border-slate-800/80 my-1">
        <a href="{{ route($adminRoute) }}" class="w-full flex items-center gap-3 px-3 py-2 rounded transition {{ request()->routeIs('admin.*') ? 'bg-oilSidebarActive text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-oilSidebarHover font-medium' }}">
          <i class="fa-solid fa-gear w-4 text-center {{ request()->routeIs('admin.*') ? 'text-white' : 'text-slate-400' }}"></i>
          <span>Admin</span>
        </a>
      </div>
    @endif
  </nav>
</aside>
<div id="sidebar-backdrop" class="fixed inset-0 bg-slate-950/50 z-30 hidden lg:hidden" onclick="toggleSidebar(false)"></div>
