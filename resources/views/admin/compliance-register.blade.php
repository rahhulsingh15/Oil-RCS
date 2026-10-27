@extends('admin.layouts.master')

@section('title', 'Admin')
@section('heading', 'Compliance Register')
@section('subheading', 'RSC obligations from the Master Register, their owner and alert days')
@section('filters')
  <form method="GET" class="relative w-48">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search code / obligation..." class="w-full text-[11px] pl-6 pr-2 py-1 border border-slate-300 rounded bg-white focus:outline-none focus:border-blue-500">
    <i class="fa-solid fa-magnifying-glass absolute left-2 top-2 text-slate-400 text-[10px]"></i>
  </form>
@endsection

@section('content')
  @include('admin.layouts.admin-tabs')

  <div class="flex items-center gap-2 rounded border border-blue-200 bg-blue-50 px-3 py-2 text-[11px] text-blue-900">
    <i class="fa-solid fa-circle-info"></i>
    <span><b>Nodal officers are set per block, not per rule.</b> Each user gets notifications for the blocks assigned to them
      @can('users.view') (<a href="{{ route('admin.users.index') }}" class="font-semibold underline">Admin → Users</a>)@endcan. No emails are sent.</span>
  </div>

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">ID</th>
            <th class="py-2.5 px-3">Compliance / Obligation</th>
            <th class="py-2.5 px-3">Article</th>
            <th class="py-2.5 px-3">Trigger</th>
            <th class="py-2.5 px-3">Frequency</th>
            <th class="py-2.5 px-3 text-center">Alert (Days Before)</th>
            <th class="py-2.5 px-3">Responsible Department</th>
            <th class="py-2.5 px-3 text-center">Tracked</th>
            @can('compliance_register.edit')<th class="py-2.5 px-3 text-center">Action</th>@endcan
          </tr>
        </thead>
        @forelse ($items as $head => $group)
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr class="bg-slate-50/70"><td colspan="9" class="py-1.5 px-3 text-[9px] font-bold uppercase tracking-wider text-slate-500">{{ $head }}</td></tr>
            @foreach ($group as $item)
              <tr class="hover:bg-slate-50">
                <td class="py-2 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $item->code }}</td>
                <td class="py-2 px-3">{{ $item->obligation }}</td>
                <td class="py-2 px-3 whitespace-nowrap">{{ $item->article ?? '-' }}</td>
                <td class="py-2 px-3 text-slate-600">{{ $item->trigger_event ?? '-' }}</td>
                <td class="py-2 px-3 text-slate-600">{{ $item->frequency }}</td>
                <td class="py-2 px-3 text-center font-bold">{{ $item->alert_days_before ?? '-' }}</td>
                <td class="py-2 px-3">
                  {{ $item->responsible_department ?? '-' }}
                </td>
                <td class="py-2 px-3 text-center {{ $item->milestones_count ? 'font-bold' : 'text-slate-300' }}">{{ $item->milestones_count }}</td>
                @can('compliance_register.edit')
                  <td class="py-2 px-3 text-center"><a href="{{ route('admin.compliance-items.edit', $item) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a></td>
                @endcan
              </tr>
            @endforeach
          </tbody>
        @empty
          <tbody><tr><td colspan="9" class="py-6 text-center text-slate-400">No rules found</td></tr></tbody>
        @endforelse
      </table>
    </div>
  </div>
  <p class="text-[10px] text-slate-400">"Tracked" = number of dated milestones using this rule. 0 means the Excel has no dates for it yet.</p>
@endsection
