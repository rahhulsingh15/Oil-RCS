<!-- Top bar: screen title, alerts bell, user menu -->
<header class="bg-white border-b border-slate-200 px-4 py-2.5 flex items-center justify-between flex-shrink-0 z-20">
  <div class="flex items-center gap-2 min-w-0">
    <button type="button" onclick="toggleSidebar(true)" class="lg:hidden p-1 text-slate-600"><i class="fa-solid fa-bars"></i></button>
    <h2 class="font-bold text-[15px] text-slate-900 leading-tight truncate">@yield('title', 'Dashboard')</h2>
  </div>

  <div class="flex items-center gap-2">
    @can('milestones.view')
      <div class="relative">
        <button type="button" onclick="toggleMenu('alerts-dropdown-box', event)" class="p-1.5 text-slate-600 hover:text-slate-900 rounded relative" title="Notifications">
          <i class="fa-regular fa-bell text-[15px]"></i>
          @if ($bellUnread)
            <span class="absolute top-0.5 right-0.5 min-w-[14px] h-[14px] px-0.5 rounded-full bg-red-600 text-white text-[8px] font-bold flex items-center justify-center">{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
          @endif
        </button>

        <div id="alerts-dropdown-box" data-menu class="hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white border border-slate-200 rounded-md shadow-lg z-50 text-xs">
          <div class="px-3 py-2 border-b border-slate-100 flex items-center justify-between">
            <p class="font-bold text-slate-800 text-[11px]">Notifications <span class="font-medium text-slate-500">({{ $bellUnread }} unread)</span></p>
            @if ($bellUnread)
              <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="text-[10px] font-semibold text-blue-600 hover:underline">Mark all as read</button>
              </form>
            @endif
          </div>
          <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 text-[11px]">
            @forelse ($bellItems as $n)
              @php
                $d = $n->data;
                $t = $d['type'] ?? '';
                $overdue = $t === 'overdue';
                $reminder = $t === 'reminder';
                $days = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($d['due_date']), false);
                $icon = $overdue ? 'fa-circle-exclamation text-red-600' : ($reminder ? 'fa-bell text-orange-600' : 'fa-clock text-amber-500');
                $label = $overdue ? 'Overdue' : ($reminder ? 'Reminder' : 'Due Soon');
                $labelColor = $overdue ? 'text-red-600' : ($reminder ? 'text-orange-700' : 'text-amber-700');
              @endphp
              <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-2 px-3 py-2 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-blue-50/60' }}">
                <i class="fa-solid {{ $icon }} mt-0.5"></i>
                <span class="min-w-0 flex-1">
                  <span class="flex items-center gap-1.5">
                    <span class="font-bold {{ $labelColor }}">{{ $label }}</span>
                    <span class="font-semibold text-slate-800 truncate">{{ $d['block_code'] }} · {{ $d['item'] }}</span>
                    @unless ($n->read_at)<span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600 flex-shrink-0"></span>@endunless
                  </span>
                  <span class="block text-[10px] text-slate-500">
                    Due {{ \Illuminate\Support\Carbon::parse($d['due_date'])->format('d M Y') }} ·
                    {{ $days < 0 ? abs($days).' days overdue' : $days.' days left' }} · {{ $n->created_at->diffForHumans() }}
                  </span>
                </span>
              </a>
            @empty
              <p class="px-3 py-5 text-center text-slate-400">No notifications yet.</p>
            @endforelse
          </div>
          <a href="{{ route('notifications.index') }}" class="block px-3 py-2 border-t border-slate-100 text-center text-[11px] text-blue-600 font-semibold hover:underline">View all notifications</a>
        </div>
      </div>
    @endcan

    <div class="relative">
      <button type="button" onclick="toggleMenu('user-menu-box', event)" class="flex items-center gap-2 pl-2 border-l border-slate-200">
        <span class="w-7 h-7 rounded-full bg-slate-700 text-white text-[10px] font-bold flex items-center justify-center">{{ auth()->user()->initials() }}</span>
        <span class="text-[11px] font-semibold text-slate-700 hidden sm:inline">{{ auth()->user()->name }}</span>
        <i class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i>
      </button>
      <div id="user-menu-box" data-menu class="hidden absolute right-0 mt-2 w-52 bg-white border border-slate-200 rounded-md shadow-lg z-50 py-1 text-[11px]">
        <div class="px-3 py-2 border-b border-slate-100">
          <p class="font-bold text-slate-800 truncate">{{ auth()->user()->email }}</p>
          <p class="text-[10px] text-slate-500">{{ auth()->user()->roleLabel() }}</p>
        </div>
        <a href="{{ route('glossary') }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-book-open w-4 text-slate-400"></i> Short Forms</a>
        <a href="{{ route('profile.password') }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-key w-4 text-slate-400"></i> Change Password</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50"><i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Sign Out</button>
        </form>
      </div>
    </div>
  </div>
</header>
