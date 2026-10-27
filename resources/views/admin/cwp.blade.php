@extends('admin.layouts.master')

@section('title', 'Work Programme (CWP)')
@section('heading', 'Committed Work Programme')
@section('subheading', '2D / 3D seismic and wells committed vs achieved for each block')
@section('filters')
  @include('admin.layouts.filters')
@endsection

@php $n = fn ($v, $dec = 0) => $v === null ? '-' : number_format($v, $dec); @endphp

@section('content')
  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3" rowspan="2">Block</th>
            <th class="py-2.5 px-3" rowspan="2">Phase</th>
            <th class="py-1.5 px-3 text-center border-l border-slate-200" colspan="3">Committed</th>
            <th class="py-1.5 px-3 text-center border-l border-slate-200" colspan="3">Achieved</th>
            <th class="py-1.5 px-3 text-center border-l border-slate-200" colspan="3">Progress</th>
            <th class="py-2.5 px-3 border-l border-slate-200" rowspan="2">Under Drilling</th>
            @can('blocks.edit')<th class="py-2.5 px-3 text-center" rowspan="2">Action</th>@endcan
          </tr>
          <tr>
            @foreach (['Committed', 'Achieved'] as $g)
              <th class="py-1.5 px-3 border-l border-slate-200">2D (LKM)</th><th class="py-1.5 px-3">3D (Sq Km)</th><th class="py-1.5 px-3">Wells</th>
            @endforeach
            <th class="py-1.5 px-3 border-l border-slate-200">2D</th><th class="py-1.5 px-3">3D</th><th class="py-1.5 px-3">Wells</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($blocks as $block)
            @php $w = $block->workProgramme; @endphp
            <tr class="hover:bg-slate-50 cursor-pointer {{ $block->isRelinquished() ? 'text-slate-400' : '' }}" onclick="window.location='{{ route('blocks.show', $block) }}#cwp'">
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $block->code }}</td>
              <td class="py-2.5 px-3">{{ $block->isRelinquished() ? 'Relinquished' : $block->timeline?->current_phase }}</td>
              <td class="py-2.5 px-3 border-l border-slate-100">{{ $n($w?->committed_2d_lkm) }}</td>
              <td class="py-2.5 px-3">{{ $n($w?->committed_3d_sqkm) }}</td>
              <td class="py-2.5 px-3">{{ $n($w?->committed_wells) }}</td>
              <td class="py-2.5 px-3 border-l border-slate-100">{{ $n($w?->achieved_2d_lkm, 2) }}</td>
              <td class="py-2.5 px-3">{{ $n($w?->achieved_3d_sqkm, 2) }}</td>
              <td class="py-2.5 px-3">{{ $n($w?->achieved_wells) }}</td>
              @foreach (array_keys(\App\Models\WorkProgramme::PARTS) as $part)
                @php $pct = $w?->partPct($part); @endphp
                <td class="py-2.5 px-3 {{ $loop->first ? 'border-l border-slate-100' : '' }}" title="{{ $pct === null ? 'No commitment for '.$part : $part.' progress' }}">
                  @if ($pct !== null)
                    <div class="flex items-center gap-1.5">
                      <div class="w-10 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $pct }}%"></div></div>
                      <span class="font-bold {{ $pct >= 100 ? 'text-emerald-600' : 'text-amber-600' }}">{{ round($pct) }}%</span>
                    </div>
                  @else <span class="text-slate-300">-</span> @endif
                </td>
              @endforeach
              <td class="py-2.5 px-3 border-l border-slate-100">
                @if ($w?->wells_under_drilling)
                  <span class="font-semibold">{{ $w->location_under_drilling }}</span> <span class="text-slate-400">({{ $n($w->drilling_completion_pct, 0) }}%)</span>
                  @if ($w->current_drilled_depth_m)<span class="block text-[10px] text-slate-400">{{ $n($w->current_drilled_depth_m) }} / {{ $n($w->target_depth_m) }} m</span>@endif
                @else - @endif
              </td>
              @can('blocks.edit')
                <td class="py-2.5 px-3 text-center" onclick="event.stopPropagation()">
                  <a href="{{ route('cwp.edit', $block) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit work programme / under drilling"><i class="fa-solid fa-pen-to-square"></i></a>
                </td>
              @endcan
            </tr>
          @empty
            <tr><td colspan="13" class="py-6 text-center text-slate-400">No blocks match these filters</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
