@extends('admin.layouts.master')

@section('title', '5. Statutory Clearances')
@section('heading', 'Statutory & Regulatory Clearances')
@section('subheading', 'Track EC, CTE, CTO and other statutory approvals for all blocks and locations')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters', ['third' => ['name' => 'status', 'label' => 'All Status', 'options' => ['Valid', 'Expired', 'Pending', 'Not applied', 'Closed']]])
    @can('clearances.create')
      <a href="{{ route('clearances.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print whitespace-nowrap"><i class="fa-solid fa-plus mr-1"></i> Add Clearances</a>
    @endcan
  </div>
@endsection

@section('content')
  <!-- 5 Metric Cards -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Total Locations</p>
      <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $total }}</p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Locations with EC</p>
      <p class="text-2xl font-black text-emerald-600 mt-0.5">{{ $withEc }} <span class="text-xs font-semibold text-emerald-700">({{ $pct($withEc) }}%)</span></p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Locations with CTE</p>
      <p class="text-2xl font-black text-blue-600 mt-0.5">{{ $withCte }} <span class="text-xs font-semibold text-blue-700">({{ $pct($withCte) }}%)</span></p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Locations with CTO</p>
      <p class="text-2xl font-black text-amber-600 mt-0.5">{{ $withCto }} <span class="text-xs font-semibold text-amber-700">({{ $pct($withCto) }}%)</span></p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Pending / Not Applicable</p>
      <p class="text-2xl font-black text-red-600 mt-0.5">{{ $pending }} <span class="text-xs font-semibold text-red-700">({{ $pct($pending) }}%)</span></p>
    </div>
  </div>

  <!-- Location table -->
  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3">Location</th>
            @foreach (['EC', 'CTE', 'CTO'] as $type)
              <th class="py-2.5 px-3">{{ $type }} Status</th>
              <th class="py-2.5 px-3">{{ $type }} Validity</th>
            @endforeach
            <th class="py-2.5 px-3">Remarks</th>
            @canany(['clearances.edit', 'clearances.delete'])<th class="py-2.5 px-3 text-center">Action</th>@endcanany
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($rows as $row)
            <tr class="hover:bg-slate-50">
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $row->block->code }}</td>
              <td class="py-2.5 px-3 font-semibold text-blue-900 whitespace-nowrap">{{ $row->location }}</td>
              @foreach (['EC', 'CTE', 'CTO'] as $type)
                @php $c = $row->types->get($type); $status = $c?->displayStatus() ?? 'NA'; @endphp
                <td class="py-2.5 px-3">@include('admin.layouts.badge', ['status' => $status])</td>
                <td class="py-2.5 px-3 whitespace-nowrap {{ $status === 'Expired' ? 'text-red-600 font-bold' : ($c?->valid_until ? '' : 'text-slate-400') }}">{{ $c?->valid_until?->format('d.m.Y') ?? 'NA' }}</td>
              @endforeach
              <td class="py-2.5 px-3 text-slate-600 max-w-[240px]">{{ $row->remarks ?? '-' }}</td>
              @canany(['clearances.edit', 'clearances.delete'])
                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                  @can('clearances.edit')
                    <a href="{{ route('clearances.edit', ['block' => $row->block->id, 'location' => $row->location]) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                  @endcan
                  @can('clearances.delete')
                    <form method="POST" action="{{ route('clearances.destroy') }}" class="inline" data-confirm="Delete EC, CTE and CTO of {{ $row->location }} ({{ $row->block->code }})?">
                      @csrf @method('DELETE')
                      <input type="hidden" name="block" value="{{ $row->block->id }}">
                      <input type="hidden" name="location" value="{{ $row->location }}">
                      <button type="submit" class="px-1.5 text-slate-400 hover:text-red-600" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                  @endcan
                </td>
              @endcanany
            </tr>
          @empty
            <tr><td colspan="10" class="py-6 text-center text-slate-400">No clearances match these filters</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-[10px] text-slate-400">"Closed" = location marked Abandoned / Relinquished in the Excel, so its old validity is not counted as expired.</p>
@endsection
