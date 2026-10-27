@extends('admin.layouts.master')

@section('title', 'Location Inventory')
@section('heading', 'Location Inventory')
@section('subheading', 'Drilling locations released, their status and plans')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters', ['third' => ['name' => 'drilling', 'label' => 'All Drilling Status', 'options' => ['Drilled', 'Under drilling', 'Not drilled']]])
    @can('locations.create')
      <a href="{{ route('locations.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print whitespace-nowrap"><i class="fa-solid fa-plus mr-1"></i> Add Location</a>
    @endcan
  </div>
@endsection

@section('content')
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Total Locations</p>
      <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $locations->count() }}</p>
    </div>
    @foreach (['Drilled' => 'text-emerald-600', 'Under drilling' => 'text-blue-600', 'Not drilled' => 'text-amber-600'] as $status => $color)
      <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
        <p class="text-[11px] font-medium text-slate-500">{{ $status }}</p>
        <p class="text-2xl font-black {{ $color }} mt-0.5">{{ $locations->where('drilling_status', $status)->count() }}</p>
      </div>
    @endforeach
  </div>

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3">Location</th>
            <th class="py-2.5 px-3">Target Depth</th>
            <th class="py-2.5 px-3">EDPMB No. / Date</th>
            <th class="py-2.5 px-3">Drilling Status</th>
            <th class="py-2.5 px-3">Key Findings</th>
            <th class="py-2.5 px-3">Expected Start</th>
            <th class="py-2.5 px-3">Planned Completion</th>
            <th class="py-2.5 px-3">Current Status</th>
            @canany(['locations.edit', 'locations.delete'])<th class="py-2.5 px-3 text-center">Action</th>@endcanany
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($locations as $loc)
            <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('blocks.show', $loc->block_id) }}#locations'">
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $loc->block->code }}</td>
              <td class="py-2.5 px-3 font-semibold text-blue-900">{{ $loc->name }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $loc->target_depth_m ? number_format($loc->target_depth_m).' m' : '-' }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $loc->edpmb_no ?? '-' }} / {{ $loc->edpmb_date?->format('d.m.Y') ?? $loc->edpmb_date_text ?? '-' }}</td>
              <td class="py-2.5 px-3">@include('admin.layouts.badge', ['status' => $loc->drilling_status ?? '-'])</td>
              <td class="py-2.5 px-3 text-slate-600">{{ $loc->key_findings ?? '-' }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $loc->expected_drilling_start ?? '-' }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $loc->planned_completion ?? '-' }}</td>
              <td class="py-2.5 px-3 text-slate-600 max-w-[220px]">{{ $loc->current_status ?? '-' }}</td>
              @canany(['locations.edit', 'locations.delete'])
                <td class="py-2.5 px-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                  @can('locations.edit')
                    <a href="{{ route('locations.edit', $loc) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                  @endcan
                  @can('locations.delete')
                    <form method="POST" action="{{ route('locations.destroy', $loc) }}" class="inline" data-confirm="Delete location {{ $loc->name }} ({{ $loc->block->code }})?">
                      @csrf @method('DELETE')
                      <button type="submit" class="px-1.5 text-slate-400 hover:text-red-600" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                  @endcan
                </td>
              @endcanany
            </tr>
          @empty
            <tr><td colspan="10" class="py-6 text-center text-slate-400">No locations match these filters</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
