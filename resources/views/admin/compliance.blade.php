@extends('admin.layouts.master')

@php
  $labels = \App\Models\Milestone::CATEGORY_LABELS;
  $current = request('category');
  $pageTitle = match ($current) {
      'Bank Guarantee' => 'Financial Security (BG)',
      'Licence' => 'Licences & Validity',
      'MC Meeting' => 'MC Meetings',
      default => '3. Compliance Monitor (Detailed View)',
  };
@endphp

@section('title', $pageTitle)
@section('heading', 'Compliance Monitor')
@section('subheading', 'Track status of all contractual, statutory and regulatory obligations')
@section('filters')
  @include('admin.layouts.filters', ['third' => ['name' => 'status', 'label' => 'All Status', 'options' => ['Overdue' => 'At Risk', 'Due Soon' => 'Due Soon', 'Compliant' => 'Compliant', 'Completed' => 'Completed']]])
@endsection

@section('content')
  <!-- Category pills -->
  <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-[11px] font-semibold">
    <a href="{{ route('compliance.index', request()->except(['category', 'page'])) }}"
       class="px-3 py-1 rounded whitespace-nowrap {{ ! $current ? 'bg-slate-900 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">All items</a>
    @foreach ($categories as $category)
      <a href="{{ route('compliance.index', array_merge(request()->except('page'), ['category' => $category])) }}"
         class="px-3 py-1 rounded whitespace-nowrap {{ $current === $category ? 'bg-slate-900 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">{{ $labels[$category] ?? $category }}</a>
    @endforeach
  </div>

  <!-- Compliance table -->
  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3">Compliance Area</th>
            <th class="py-2.5 px-3">Item</th>
            <th class="py-2.5 px-3">Reference Date</th>
            <th class="py-2.5 px-3">Due Date</th>
            <th class="py-2.5 px-3 text-center">Days Left</th>
            <th class="py-2.5 px-3">Status</th>
            <th class="py-2.5 px-3">Remarks</th>
            <th class="py-2.5 px-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($milestones as $m)
            @php
              $days = $m->daysLeft();
              $open = in_array($m->status, \App\Models\Milestone::OPEN_STATUSES, true);
              $daysClass = ! $open ? 'text-slate-300' : ($days < 0 || $days <= 30 ? 'text-red-600' : ($days <= $m->alert_days_before ? 'text-orange-600' : 'text-slate-700'));
            @endphp
            <tr class="hover:bg-slate-50">
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $m->block->code }}</td>
              <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">{{ $labels[$m->category] ?? $m->category }}</td>
              <td class="py-2.5 px-3" title="{{ $m->title }}">{{ $m->shortLabel() }}@if ($m->location_name) <span class="text-slate-400">· {{ $m->location_name }}</span> @endif</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $m->reference_date?->format('d.m.Y') ?? '-' }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $m->due_date->format('d.m.Y') }}</td>
              <td class="py-2.5 px-3 text-center font-black {{ $daysClass }}">{{ $open ? $days : '-' }}</td>
              <td class="py-2.5 px-3">@include('admin.layouts.badge', ['status' => $m->statusLabel()])</td>
              <td class="py-2.5 px-3 text-slate-600 max-w-[220px] truncate" title="{{ $m->remarks ?: $m->title }}">{{ $m->remarks ?: $m->title }}</td>
              <td class="py-2.5 px-3 text-center">
                @can('milestones.edit')
                  <button type="button" onclick="openMilestoneModal(this)" data-milestone="{{ $m->editJson() }}" class="px-1.5 text-slate-500 hover:text-slate-900" title="Update milestone"><i class="fa-solid fa-ellipsis"></i></button>
                @else
                  <a href="{{ route('blocks.show', $m->block_id) }}#milestones" class="px-1.5 text-slate-500 hover:text-slate-900" title="Open block"><i class="fa-solid fa-ellipsis"></i></a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="py-6 text-center text-slate-400">No compliance records match these filters</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Table footer -->
    <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
      <span>Displaying {{ $milestones->count() }} of {{ $milestones->total() }} compliance records</span>
      {{ $milestones->links() }}
      @can('reports.export')
        <a href="{{ route('reports.export', request()->query()) }}" class="px-2.5 py-1 bg-white border border-slate-300 text-slate-700 font-semibold rounded hover:bg-slate-50 flex items-center gap-1.5 text-[11px]">
          <i class="fa-solid fa-file-csv text-emerald-600"></i> Export Compliance Table
        </a>
      @endcan
    </div>
  </div>

  @include('admin.layouts.milestone-modal')
@endsection
