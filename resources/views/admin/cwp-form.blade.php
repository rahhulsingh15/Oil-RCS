@extends('admin.layouts.master')

@php
  $w = $block->workProgramme;
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
  $val = fn ($name) => old($name, $w?->{$name});
  $groups = [
      'Committed' => ['committed_2d_lkm', 'committed_3d_sqkm', 'committed_wells'],
      'Achieved' => ['achieved_2d_lkm', 'achieved_3d_sqkm', 'achieved_wells'],
      'Additional Work' => ['additional_2d_lkm', 'additional_3d_sqkm', 'additional_wells'],
  ];
@endphp

@section('title', 'Work Programme (CWP)')
@section('heading', 'Edit Work Programme: '.$block->code)
@section('subheading', 'Committed vs achieved 2D, 3D and wells, and the well currently under drilling')

@section('content')
  <form method="POST" action="{{ route('cwp.update', $block) }}" class="space-y-3.5 max-w-4xl">
    @csrf @method('PUT')

    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <div class="grid grid-cols-4 gap-3 text-[11px]">
        <div></div>
        <p class="font-bold uppercase text-[10px] text-slate-500"><abbr title="2-Dimensional Seismic">2D</abbr> (LKM)</p>
        <p class="font-bold uppercase text-[10px] text-slate-500"><abbr title="3-Dimensional Seismic">3D</abbr> (Sq Km)</p>
        <p class="font-bold uppercase text-[10px] text-slate-500">Wells</p>
        @foreach ($groups as $group => $names)
          <p class="font-bold text-slate-800 self-center">{{ $group }}</p>
          @foreach ($names as $name)
            <input name="{{ $name }}" type="number" min="0" step="{{ str_ends_with($name, 'wells') ? '1' : 'any' }}" value="{{ $val($name) }}"
                   aria-label="{{ $fields[$name][0] }}" class="{{ $input }}">
          @endforeach
        @endforeach
      </div>
    </div>

    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1.5 border-b border-slate-100 mb-3">Well Under Drilling</h4>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div>
          <label for="c-wells" class="{{ $label }}">No. of Wells Under Drilling</label>
          <input id="c-wells" name="wells_under_drilling" type="number" min="0" step="1" value="{{ $val('wells_under_drilling') }}" class="{{ $input }}">
        </div>
        <div>
          <label for="c-loc" class="{{ $label }}">Location Under Drilling</label>
          <input id="c-loc" name="location_under_drilling" list="block-locations" maxlength="100" value="{{ $val('location_under_drilling') }}" placeholder="e.g. OBKB-R" class="{{ $input }}">
          <datalist id="block-locations">
            @foreach ($block->locations as $loc)<option value="{{ $loc->name }}">@endforeach
          </datalist>
        </div>
        <div>
          <label for="c-depth" class="{{ $label }}">Current Drilled Depth (m)</label>
          <input id="c-depth" name="current_drilled_depth_m" type="number" min="0" step="any" value="{{ $val('current_drilled_depth_m') }}" class="{{ $input }}">
        </div>
        <div>
          <label for="c-td" class="{{ $label }}">Target Depth (m)</label>
          <input id="c-td" name="target_depth_m" type="number" min="0" step="any" value="{{ $val('target_depth_m') }}" class="{{ $input }}">
        </div>
        <div>
          <p class="{{ $label }}">Completion (auto)</p>
          <div class="flex items-center gap-2 h-[30px]">
            <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div id="c-pct-bar" class="h-full bg-orange-500" style="width: 0%"></div></div>
            <span id="c-pct" class="w-12 text-right font-black text-[12px] text-slate-800">-</span>
          </div>
        </div>
      </div>
      <p class="text-[10px] text-slate-400 mt-2">Completion is calculated automatically: drilled depth ÷ target depth. Set wells under drilling to 0 when drilling is over; the other fields are then cleared. Also update the location's status in Location Inventory.</p>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('cwp.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">Save Changes</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // Live completion % while typing, same rule as the server
  (function () {
    var wells = document.getElementById('c-wells'), depth = document.getElementById('c-depth'), target = document.getElementById('c-td');
    function update() {
      var d = parseFloat(depth.value), t = parseFloat(target.value), w = parseInt(wells.value, 10);
      var pct = (w > 0 && d > 0 && t > 0) ? Math.min(d / t * 100, 100) : null;
      document.getElementById('c-pct').textContent = pct === null ? '-' : pct.toFixed(1) + '%';
      document.getElementById('c-pct-bar').style.width = (pct || 0) + '%';
    }
    [wells, depth, target].forEach(function (el) { el.addEventListener('input', update); });
    update();
  })();
</script>
@endpush
