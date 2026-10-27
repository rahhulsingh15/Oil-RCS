@extends('admin.layouts.master')

@section('title', '2. Block Overview')
@section('heading', 'Block Portfolio Overview')
@section('subheading', 'Geographical and operational view of all RSC blocks')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters')
    @can('blocks.create')
      <a href="{{ route('blocks.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print whitespace-nowrap"><i class="fa-solid fa-plus mr-1"></i> Add Block</a>
    @endcan
  </div>
@endsection

@php
  $phaseOf = fn ($b) => $b->isRelinquished() ? 'Relinquished' : ($b->timeline?->current_phase ?? $b->current_stage);
  $statusOf = fn ($b) => $b->isRelinquished() ? 'Relinquished' : (($statuses[$b->id] ?? 'Compliant') === 'Overdue' ? 'At Risk' : ($statuses[$b->id] ?? 'Compliant'));

  // Data for the map markers
  $mapBlocks = $blocks->filter(fn ($b) => $b->mapPoint())->map(fn ($b) => [
      'code' => $b->code,
      'lat' => $b->mapPoint()[0],
      'lng' => $b->mapPoint()[1],
      'basin' => $b->basin,
      'stage' => $b->isRelinquished() ? 'Relinquished' : (str_contains(strtolower((string) $b->current_stage), 'develop') ? 'Development' : 'Exploration'),
      'phase' => $phaseOf($b),
      'status' => $statusOf($b),
      'url' => route('blocks.show', $b),
  ])->values();
@endphp

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .block-pin { width: 14px; height: 14px; border: 2px solid #fff; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,.45); }
    .basin-label { background: transparent; border: 0; box-shadow: none; font: 700 12px Inter, sans-serif; color: #0f172a; text-shadow: 0 0 3px #fff, 0 0 3px #fff, 0 0 3px #fff; }
    .basin-label::before { display: none; }
    .leaflet-tooltip.block-tip { font: 600 11px Inter, sans-serif; padding: 2px 6px; }
  </style>
@endpush

@section('content')
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
    <!-- Left: Map -->
    <div class="lg:col-span-6 bg-white rounded p-2 border border-oilCardBorder shadow-xs">
      <div class="relative z-0 rounded overflow-hidden border border-blue-100" style="height: 400px">
        <div id="blocks-map" class="absolute inset-0"></div>

        <!-- View by -->
        <div class="absolute top-2 left-2 z-[500] bg-white rounded shadow px-2 py-1.5 flex items-center gap-1.5 text-[11px] font-semibold text-slate-700">
          <span>View by:</span>
          <button type="button" data-map-view="basin" class="map-view-btn px-2.5 py-0.5 rounded">Basin</button>
          <button type="button" data-map-view="phase" class="map-view-btn px-2.5 py-0.5 rounded">Phase</button>
        </div>

        <!-- Legend -->
        <div id="map-legend" class="absolute top-2 right-2 z-[500] bg-white rounded shadow px-2.5 py-1.5 space-y-1 text-[10px] font-semibold text-slate-700"></div>

        @if ($mapBlocks->count() < $blocks->count())
          <span class="absolute bottom-1.5 left-2 z-[500] text-[9px] text-slate-500 bg-white/90 px-1 rounded">{{ $blocks->count() - $mapBlocks->count() }} block(s) have no map location yet</span>
        @endif
      </div>
      <p class="text-[10px] text-slate-400 mt-1.5 px-1">Block positions are approximate (Excel has no coordinates). Click a marker or a row for block details.</p>
    </div>

    <!-- Right: Blocks table -->
    <div class="lg:col-span-6 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-bold text-[13px] text-slate-800">Blocks <span class="font-medium text-slate-500">({{ $blocks->count() }})</span></h4>
      </div>
      <div class="relative mb-2">
        <input type="text" oninput="filterBlockRows(this.value)" placeholder="Search block..." class="w-full text-[11px] pl-7 pr-2 py-1.5 border border-slate-300 rounded bg-white focus:outline-none focus:border-blue-500">
        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-[10px]"></i>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead>
            <tr class="text-[10px] text-slate-600 border-b border-slate-200 font-bold bg-slate-50">
              <th class="py-2 px-2.5">Block</th>
              <th class="py-2 px-2.5">Basin</th>
              <th class="py-2 px-2.5">Phase</th>
              <th class="py-2 px-2.5">Compliance Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            @forelse ($blocks as $block)
              <tr class="block-row hover:bg-slate-50 cursor-pointer transition" data-code="{{ $block->code }}"
                  data-search="{{ strtolower($block->code.' '.$block->basin.' '.$phaseOf($block).' '.$statusOf($block)) }}"
                  onclick="window.location='{{ route('blocks.show', $block) }}'">
                <td class="py-2 px-2.5 text-slate-900">{{ $block->code }}</td>
                <td class="py-2 px-2.5 text-slate-600">{{ $block->basin }}</td>
                <td class="py-2 px-2.5 text-slate-600">{{ $phaseOf($block) }}</td>
                <td class="py-2 px-2.5">@include('admin.layouts.badge', ['status' => $statusOf($block)])</td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-4 text-center text-slate-400">No blocks found matching filter</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  (function () {
    var blocks = @json($mapBlocks);
    var tiles = @json(config('block_map.tiles'));

    var stageColors = { Exploration: '#2563eb', Development: '#16a34a', Relinquished: '#dc2626' };
    var palette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2'];
    var basinColors = {};
    blocks.forEach(function (b) { if (!basinColors[b.basin]) basinColors[b.basin] = palette[Object.keys(basinColors).length % palette.length]; });

    // Peninsular India in view, like the approved design
    var map = L.map('blocks-map', { zoomControl: true, scrollWheelZoom: false, attributionControl: true })
      .fitBounds([[7.5, 70], [25.5, 92]]);
    map.zoomControl.setPosition('bottomright');
    L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: 12 }).addTo(map);

    var markers = {};
    blocks.forEach(function (b) {
      var m = L.marker([b.lat, b.lng], { icon: L.divIcon({ className: '', html: '<div class="block-pin"></div>', iconSize: [14, 14] }) })
        .bindTooltip(b.code, { className: 'block-tip', direction: 'top', offset: [0, -8] })
        .bindPopup(
          '<div style="font:12px Inter,sans-serif;min-width:170px">' +
          '<b>' + b.code + '</b><br>' + b.basin + ' · ' + b.phase + '<br>Status: <b>' + b.status + '</b><br>' +
          '<a href="' + b.url + '" style="color:#2563eb;font-weight:600">Open block details &rarr;</a></div>'
        )
        .addTo(map);
      m.block = b;
      markers[b.code] = m;
    });

    // Basin name at the middle of its blocks
    Object.keys(basinColors).forEach(function (basin) {
      var pts = blocks.filter(function (b) { return b.basin === basin; });
      var lat = pts.reduce(function (s, b) { return s + b.lat; }, 0) / pts.length;
      var lng = pts.reduce(function (s, b) { return s + b.lng; }, 0) / pts.length;
      var name = basin === 'Krishna Godavari' ? 'KG Offshore Basin' : basin + ' Basin';
      L.tooltip({ permanent: true, direction: 'right', className: 'basin-label', offset: [14, 0] })
        .setLatLng([lat, lng]).setContent(name).addTo(map);
    });

    function setView(view) {
      var colors = view === 'basin' ? basinColors : stageColors;
      Object.keys(markers).forEach(function (code) {
        var b = markers[code].block;
        markers[code].getElement().querySelector('.block-pin').style.background = colors[view === 'basin' ? b.basin : b.stage];
      });
      document.getElementById('map-legend').innerHTML = Object.keys(colors).map(function (k) {
        var label = k === 'Krishna Godavari' ? 'KG Offshore' : k;
        return '<div class="flex items-center gap-1.5"><span style="width:9px;height:9px;border-radius:50%;background:' + colors[k] + '"></span>' + label + '</div>';
      }).join('');
      document.querySelectorAll('.map-view-btn').forEach(function (btn) {
        var on = btn.dataset.mapView === view;
        btn.classList.toggle('bg-blue-900', on); btn.classList.toggle('text-white', on);
        btn.classList.toggle('hover:bg-slate-100', !on);
      });
    }
    document.querySelectorAll('.map-view-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { setView(btn.dataset.mapView); });
    });
    setView('phase');

    // Hovering a table row points out its block on the map
    document.querySelectorAll('.block-row').forEach(function (row) {
      var m = markers[row.dataset.code];
      if (!m) return;
      row.addEventListener('mouseenter', function () { m.openTooltip(); });
      row.addEventListener('mouseleave', function () { m.closeTooltip(); });
    });

    window.filterBlockRows = function (q) {
      q = q.toLowerCase().trim();
      document.querySelectorAll('.block-row').forEach(function (row) {
        row.classList.toggle('hidden', q !== '' && row.dataset.search.indexOf(q) === -1);
      });
    };
  })();
</script>
@endpush
