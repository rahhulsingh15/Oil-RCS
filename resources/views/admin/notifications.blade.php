@extends('admin.layouts.master')

@php $select = 'text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium'; @endphp

@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subheading', 'Deadlines that are coming up or have been missed, for your blocks. Shown in the portal only (no email).')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5 no-print">
    <form method="GET" class="flex flex-wrap items-center gap-2.5">
      <select name="show" onchange="this.form.submit()" class="{{ $select }}">
        <option value="">All</option>
        <option value="unread" @selected(request('show') === 'unread')>Unread only</option>
      </select>
      <select name="type" onchange="this.form.submit()" class="{{ $select }}">
        <option value="">All Types</option>
        <option value="upcoming" @selected(request('type') === 'upcoming')>Due Soon</option>
        <option value="reminder" @selected(request('type') === 'reminder')>Reminder (Half-Days)</option>
        <option value="overdue" @selected(request('type') === 'overdue')>Overdue</option>
      </select>
      <select name="block" onchange="this.form.submit()" class="{{ $select }}">
        <option value="">All Blocks</option>
        @foreach ($blocks as $b)<option value="{{ $b->id }}" @selected(request('block') == $b->id)>{{ $b->code }}</option>@endforeach
      </select>
    </form>
    @if ($unreadCount)
      <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="px-3 py-1 border border-slate-300 bg-white hover:bg-slate-50 rounded font-semibold text-[11px] text-slate-700"><i class="fa-solid fa-check-double mr-1"></i> Mark all as read</button>
      </form>
    @endif
    @can('notifications.run')
      <form method="POST" action="{{ route('notifications.run') }}" data-confirm="Run the deadline check now? New notifications are created only for deadlines not notified before.">
        @csrf
        <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]"><i class="fa-solid fa-rotate mr-1"></i> Run check now</button>
      </form>
    @endcan
  </div>
@endsection

@section('content')
  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3 w-6"></th>
            <th class="py-2.5 px-3">Type</th>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3">Notification</th>
            <th class="py-2.5 px-3">Due Date</th>
            <th class="py-2.5 px-3 text-center">Days</th>
            <th class="py-2.5 px-3">Received</th>
            <th class="py-2.5 px-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($notifications as $n)
            @php
              $d = $n->data;
              $t = $d['type'] ?? '';
              $overdue = $t === 'overdue';
              $reminder = $t === 'reminder';
              $due = \Illuminate\Support\Carbon::parse($d['due_date']);
              $days = (int) today()->diffInDays($due, false);
            @endphp
            <tr class="{{ $n->read_at ? '' : 'bg-blue-50/50' }}">
              <td class="py-2.5 px-3">@unless ($n->read_at)<span class="block w-2 h-2 rounded-full bg-blue-600" title="Unread"></span>@endunless</td>
              <td class="py-2.5 px-3 whitespace-nowrap">
                @if ($overdue)
                  @include('admin.layouts.badge', ['status' => 'At Risk'])
                @elseif ($reminder)
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-800">
                    <i class="fa-solid fa-bell mr-1 text-[9px]"></i> Reminder
                  </span>
                @else
                  @include('admin.layouts.badge', ['status' => 'Due Soon'])
                @endif
              </td>
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $d['block_code'] }}</td>
              <td class="py-2.5 px-3 {{ $n->read_at ? '' : 'font-semibold text-slate-900' }}">
                {{ $d['message'] }}
                <span class="block text-[10px] text-slate-400 font-normal">{{ $d['title'] }}</span>
              </td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $due->format('d.m.Y') }}</td>
              <td class="py-2.5 px-3 text-center font-black {{ $days < 0 ? 'text-red-600' : 'text-amber-600' }}">{{ $days }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap text-slate-500">{{ $n->created_at->format('d.m.Y H:i') }}</td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <a href="{{ route('notifications.open', $n->id) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Open in Compliance Monitor"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <form method="POST" action="{{ route('notifications.toggle', $n->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="px-1.5 text-slate-500 hover:text-blue-700" title="{{ $n->read_at ? 'Mark as unread' : 'Mark as read' }}">
                    <i class="fa-solid {{ $n->read_at ? 'fa-envelope' : 'fa-envelope-open' }}"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="py-8 text-center text-slate-400">
              <i class="fa-regular fa-bell-slash text-xl block mb-1"></i>
              No notifications. They appear here when a deadline of your blocks comes near or is missed.
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
      <span>{{ $unreadCount }} unread · {{ $notifications->total() }} shown</span>
      {{ $notifications->links() }}
    </div>
  </div>
  <p class="text-[10px] text-slate-400">The deadline check runs automatically every day at 08:00. Notifications are sent in two stages: first when entering the alert window (e.g. 30 days before), and a second reminder at half-days (e.g. 15 days before). Overdue deadlines are notified once missed. Completing a deadline marks its notifications as read.</p>
@endsection
