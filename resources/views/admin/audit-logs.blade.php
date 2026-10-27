@extends('admin.layouts.master')

@php
  $select = 'text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium';
  $eventColor = ['created' => 'text-emerald-700', 'updated' => 'text-blue-700', 'deleted' => 'text-red-600', 'imported' => 'text-violet-700'];
  $show = function ($value) {
      if (is_bool($value)) return $value ? 'Yes' : 'No';
      if (is_array($value)) return $value ? implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $value)) : '-';
      return ($value === null || $value === '') ? '-' : $value;
  };
@endphp

@section('title', 'Admin')
@section('heading', 'Audit Log')
@section('subheading', 'Who changed what, and when')
@section('filters')
  <form method="GET" class="flex flex-wrap items-center gap-2.5">
    <select name="event" onchange="this.form.submit()" class="{{ $select }}">
      <option value="">All Events</option>
      @foreach ($events as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ ucfirst($event) }}</option>@endforeach
    </select>
    <select name="user" onchange="this.form.submit()" class="{{ $select }}">
      <option value="">All Users</option>
      @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()" class="{{ $select }}" title="From">
    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()" class="{{ $select }}" title="To">
  </form>
@endsection

@section('content')
  @include('admin.layouts.admin-tabs')

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Timestamp</th>
            <th class="py-2.5 px-3">User</th>
            <th class="py-2.5 px-3">Event</th>
            <th class="py-2.5 px-3">Action</th>
            <th class="py-2.5 px-3">Changes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700 align-top">
          @forelse ($logs as $log)
            @php $keys = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))); @endphp
            <tr class="hover:bg-slate-50">
              <td class="py-2 px-3 whitespace-nowrap text-slate-600">{{ $log->created_at->format('d.m.Y H:i') }}</td>
              <td class="py-2 px-3 whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
              <td class="py-2 px-3 font-bold {{ $eventColor[$log->event] ?? 'text-slate-600' }}">{{ ucfirst($log->event) }}</td>
              <td class="py-2 px-3">{{ $log->description }}</td>
              <td class="py-2 px-3 min-w-[240px]">
                @if ($keys)
                  <details>
                    <summary class="cursor-pointer text-blue-600 font-semibold">{{ count($keys) }} field(s)</summary>
                    <div class="mt-1 space-y-0.5 text-[10px]">
                      @foreach ($keys as $key)
                        <p>
                          <b class="text-slate-600">{{ ucfirst(str_replace('_', ' ', $key)) }}:</b>
                          @if (array_key_exists($key, $log->old_values ?? []))<span class="line-through text-red-500">{{ $show($log->old_values[$key]) }}</span>@endif
                          @if (array_key_exists($key, $log->new_values ?? []))<span class="text-emerald-700">{{ $show($log->new_values[$key]) }}</span>@endif
                        </p>
                      @endforeach
                    </div>
                  </details>
                @else
                  <span class="text-slate-300">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-6 text-center text-slate-400">No activity recorded</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
      <span>{{ $logs->total() }} entries</span>
      {{ $logs->links() }}
    </div>
  </div>
@endsection
