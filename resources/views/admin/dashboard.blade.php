@extends('admin.layouts.master')

@section('title', '1. Executive Dashboard')
@section('heading', 'RSC Compliance Dashboard')
@section('subheading', 'Real-time view of contractual, statutory and regulatory compliance across all blocks')
@section('filters')
  @include('admin.layouts.filters')
@endsection

@php
  $typeColors = ['Onland' => '#d97706', 'Offshore' => '#0369a1'];
  $typeCounts = array_map('count', $blockTypes);
  $typeTotal = array_sum($typeCounts);
  $donutTotal = array_sum($donut);
  $pendingTotal = $pendingClearances->sum();
  $wellTotals = [
      'drilled' => $wells->sum('drilled'),
      'drilling' => $wells->sum('drilling'),
      'planned' => $wells->sum('planned'),
  ];
  $kpi = 'bg-white rounded px-3 py-2.5 border border-oilCardBorder shadow-xs';
@endphp

@section('content')
  <!-- Row 1: secondary KPIs | main Onland/Offshore chart | secondary compliance -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">

    <!-- Left: compact KPI cards -->
    <div class="lg:col-span-3 grid grid-cols-2 lg:grid-cols-1 gap-3 content-start">
      <div class="{{ $kpi }}">
        <p class="text-[11px] font-medium text-slate-500">Total Blocks</p>
        <p class="text-xl font-black text-slate-900">{{ $blocks->count() }}</p>
        <p class="text-[10px] text-slate-500"><span class="font-bold text-slate-800">{{ $activeCount }} Active</span> | {{ $blocks->count() - $activeCount }} Relinquished</p>
      </div>
      <div class="{{ $kpi }}">
        <p class="text-[11px] font-medium text-slate-500">BGs expiring &le; 90 days</p>
        <p class="text-xl font-black {{ $bg90 ? 'text-red-600' : 'text-slate-900' }}">{{ $bg90 }}</p>
        <p class="text-[10px] font-semibold"><span class="text-red-600">{{ $bg30 }} &le; 30 days</span> <span class="text-slate-300">|</span> <span class="text-orange-600">{{ $bg90 }} &le; 90 days</span></p>
      </div>
      <div class="{{ $kpi }}">
        <p class="text-[11px] font-medium text-slate-500">PEL/PL expiring &le; 1 year</p>
        <p class="text-xl font-black text-amber-500">{{ $pel365 }}</p>
        <p class="text-[10px] text-slate-500">{{ $pel180 }} &le; 6 months <span class="text-slate-300">|</span> <span class="text-amber-600 font-bold">{{ $pel365 }} &le; 1 year</span></p>
      </div>
      <div class="{{ $kpi }}">
        <p class="text-[11px] font-medium text-slate-500">CWP Progress <span class="text-[10px] text-slate-400">(avg. of active blocks)</span></p>
        <div class="mt-1 space-y-1">
          @foreach ($cwpParts as $part => $pct)
            <div class="flex items-center gap-2 text-[11px]">
              <span class="w-9 font-semibold text-slate-600">{{ $part }}</span>
              <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $pct ?? 0 }}%"></div>
              </div>
              <span class="w-9 text-right font-black {{ $pct === null ? 'text-slate-300' : ($pct >= 100 ? 'text-emerald-600' : 'text-amber-600') }}">{{ $pct !== null ? $pct.'%' : '—' }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <!-- Centre: MAIN chart, blocks Onland vs Offshore -->
    <div class="lg:col-span-6 bg-white rounded p-4 border border-oilCardBorder shadow-sm flex flex-col">
      <div class="flex items-center justify-between">
        <h4 class="font-bold text-[13px] uppercase tracking-wider text-slate-800">Blocks: Onland vs Offshore</h4>
        <span class="text-[10px] text-slate-400">Active blocks (relinquished not included)</span>
      </div>
      <div class="relative w-52 h-52 sm:w-64 sm:h-64 mx-auto my-3 flex items-center justify-center">
        <canvas id="typeDonutCanvas"></canvas>
        <div class="absolute text-center pointer-events-none">
          <p class="text-4xl font-extrabold text-slate-900 leading-none">{{ $typeTotal }}</p>
          <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mt-1">Blocks</p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-100 text-center">
        @foreach ($blockTypes as $type => $codes)
          <div>
            <p class="text-[11px] font-bold flex items-center justify-center gap-1.5" style="color: {{ $typeColors[$type] }}"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $typeColors[$type] }}"></span>{{ $type }}</p>
            <p class="text-xl font-black text-slate-900">{{ count($codes) }} <span class="text-[11px] font-semibold text-slate-500">({{ $typeTotal ? round(count($codes) / $typeTotal * 100) : 0 }}%)</span></p>
            <p class="text-[9px] text-slate-400 leading-tight">{{ implode(', ', $codes) ?: '-' }}</p>
          </div>
        @endforeach
      </div>
    </div>

    <!-- Right: compliance metrics (secondary) -->
    <div class="lg:col-span-3 grid grid-cols-2 lg:grid-cols-1 gap-3 content-start">
      <div class="{{ $kpi }}">
        <p class="text-[11px] font-medium text-slate-500">Pending Statutory Clearances</p>
        <p class="text-xl font-black {{ $pendingTotal ? 'text-red-600' : 'text-slate-900' }}">{{ $pendingTotal }}</p>
        <p class="text-[10px] font-semibold"><span class="text-red-600">{{ $pendingClearances['EC'] ?? 0 }} EC</span> <span class="text-slate-300">|</span> <span class="text-orange-500">{{ $pendingClearances['CTE'] ?? 0 }} CTE</span> <span class="text-slate-300">|</span> <span class="text-orange-500">{{ $pendingClearances['CTO'] ?? 0 }} CTO</span></p>
      </div>
      <div class="{{ $kpi }} col-span-2 lg:col-span-1">
        <p class="text-[11px] font-medium text-slate-500">Compliance Status (active blocks)</p>
        <div class="flex items-center gap-3 mt-1">
          <div class="relative w-20 h-20 flex-shrink-0">
            <canvas id="s1DonutCanvas"></canvas>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none"><span class="text-sm font-extrabold text-slate-900">{{ $donutTotal }}</span></div>
          </div>
          <div class="space-y-0.5 text-[10px]">
            @foreach (['Compliant' => ['emerald', 'green'], 'Due Soon' => ['amber', 'amber'], 'At Risk' => ['red', 'red']] as $label => [$text, $dot])
              <p class="font-semibold text-{{ $text }}-600 flex items-center"><span class="status-dot status-dot-{{ $dot }}"></span>{{ $label }}: {{ $donut[$label] }}</p>
            @endforeach
          </div>
        </div>
      </div>

      @can('milestones.view')
        <!-- Notifications (in the portal only) -->
        <div class="{{ $kpi }} col-span-2 lg:col-span-1">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-medium text-slate-500"><i class="fa-regular fa-bell mr-1"></i> Notifications</p>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $notificationsUnread ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $notificationsUnread }} unread</span>
          </div>
          <div class="mt-1.5 space-y-1 text-[10px]">
            @forelse ($notificationsLatest as $n)
              @php $overdue = ($n->data['type'] ?? '') === 'overdue'; @endphp
              <a href="{{ route('notifications.open', $n->id) }}" class="flex items-center gap-1.5 hover:underline">
                <i class="fa-solid {{ $overdue ? 'fa-circle-exclamation text-red-600' : 'fa-clock text-amber-500' }}"></i>
                <span class="truncate"><b>{{ $n->data['block_code'] }}</b> · {{ $n->data['item'] }} · {{ \Illuminate\Support\Carbon::parse($n->data['due_date'])->format('d.m.Y') }}</span>
              </a>
            @empty
              <p class="text-slate-400">No unread notifications.</p>
            @endforelse
          </div>
          <a href="{{ route('notifications.index') }}" class="block mt-1.5 text-[10px] text-blue-600 font-semibold hover:underline">View all notifications &rarr;</a>
        </div>
      @endcan
    </div>
  </div>

  <!-- Row 2: wells + OALP rounds -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
    <div class="lg:col-span-7 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800">Wells: Drilled, Under Drilling &amp; Planned</h4>
        <div class="flex items-center gap-3 text-[10px] font-semibold">
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-600"></span> Drilled ({{ $wellTotals['drilled'] }})</span>
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-orange-500"></span> Under drilling ({{ $wellTotals['drilling'] }})</span>
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-600"></span> Planned ({{ $wellTotals['planned'] }})</span>
        </div>
      </div>
      @if ($wells->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <p class="text-[10px] font-bold uppercase text-slate-500 mb-1">Overall</p>
            <div class="h-52"><canvas id="wellsTotalCanvas"></canvas></div>
          </div>
          <div class="md:col-span-2 md:border-l md:border-slate-100 md:pl-3">
            <p class="text-[10px] font-bold uppercase text-slate-500 mb-1">Block-wise</p>
            <div class="h-52"><canvas id="wellsCanvas"></canvas></div>
          </div>
        </div>
      @else
        <p class="py-12 text-center text-[11px] text-slate-400">No well locations for the selected blocks</p>
      @endif
      <p class="text-[10px] text-slate-400 mt-1">Source: Location inventory (Excel). Blocks without released locations are not shown.</p>
    </div>

    <div class="lg:col-span-5 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
      <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 mb-2">Blocks by OALP Round</h4>
      <div class="h-56"><canvas id="roundsCanvas"></canvas></div>
    </div>
  </div>

  <!-- Row 3: deadlines + latest updates -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
    <div class="lg:col-span-6 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-2">
          <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800">Key Upcoming Deadlines</h4>
          <span class="text-[9px] bg-slate-100 text-slate-600 font-semibold px-1.5 py-0.5 rounded">(Next 90 days)</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[11px]">
            <thead>
              <tr class="text-[9px] uppercase text-slate-400 border-b border-slate-100 font-bold">
                <th class="pb-1.5">Date</th><th class="pb-1.5">Block</th><th class="pb-1.5">Item</th><th class="pb-1.5 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @forelse ($deadlines as $m)
                @php
                  $days = $m->daysLeft();
                  $pill = match (true) {
                      $days < 0 => 'bg-red-600 text-white border-red-600',
                      $days <= 30 => 'bg-red-50 text-red-600 border-red-200',
                      $days <= 60 => 'bg-orange-50 text-orange-600 border-orange-200',
                      default => 'bg-amber-50 text-amber-700 border-amber-200',
                  };
                @endphp
                <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('blocks.show', $m->block_id) }}'">
                  <td class="py-1.5 font-semibold whitespace-nowrap {{ $days <= 30 ? 'text-red-600' : 'text-slate-700' }}">{{ $m->due_date->format('d M Y') }}</td>
                  <td class="py-1.5 font-medium text-slate-800 whitespace-nowrap">{{ $m->block->code }}</td>
                  <td class="py-1.5 text-slate-600">{{ $m->shortLabel() }}</td>
                  <td class="py-1.5 text-right whitespace-nowrap"><span class="font-bold px-1.5 py-0.5 rounded text-[10px] border {{ $pill }}">{{ $days < 0 ? abs($days).' days late' : $days.' days' }}</span></td>
                </tr>
              @empty
                <tr><td colspan="4" class="py-4 text-center text-slate-400">No deadlines in the next 90 days</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @can('milestones.view')
        <div class="pt-2 text-right">
          <a href="{{ route('compliance.index', request()->only(['basin', 'block', 'phase'])) }}" class="text-[10px] text-blue-600 font-semibold hover:underline">View All Obligations &rarr;</a>
        </div>
      @endcan
    </div>

    <div class="lg:col-span-6 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800">Latest Milestone Updates</h4>
        <span class="text-[9px] bg-slate-100 text-slate-600 font-semibold px-1.5 py-0.5 rounded">(Last 5 changes)</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead>
            <tr class="text-[9px] uppercase text-slate-400 border-b border-slate-100 font-bold">
              <th class="pb-1.5">When</th><th class="pb-1.5">Block</th><th class="pb-1.5">Item</th><th class="pb-1.5">Change</th><th class="pb-1.5">By</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse ($latestUpdates as $log)
              @php $m = $log->auditable; @endphp
              <tr>
                <td class="py-1.5 text-slate-600 whitespace-nowrap">{{ $log->created_at->format('d M, H:i') }}</td>
                <td class="py-1.5 font-medium text-slate-800 whitespace-nowrap">{{ $m?->block?->code ?? '-' }}</td>
                <td class="py-1.5 text-slate-600">{{ $m?->shortLabel() ?? 'Deleted milestone' }}</td>
                <td class="py-1.5 text-slate-700">
                  @foreach ($log->new_values ?? [] as $field => $value)
                    <span class="whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $field)) }}: <span class="line-through text-red-500">{{ $log->old_values[$field] ?? '-' }}</span> &rarr; <b>{{ $value ?? '-' }}</b></span>@if (! $loop->last)<span class="text-slate-300"> | </span>@endif
                  @endforeach
                </td>
                <td class="py-1.5 text-slate-600 whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="py-4 text-center text-slate-400">No milestone has been updated yet</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = 'Inter, sans-serif';

    // Main: blocks by type
    var types = @json($typeCounts);
    var typeColors = @json($typeColors);
    new Chart(document.getElementById('typeDonutCanvas'), {
      type: 'doughnut',
      data: {
        labels: Object.keys(types),
        datasets: [{ data: Object.values(types), backgroundColor: Object.keys(types).map(function (t) { return typeColors[t]; }), borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '66%',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + c.raw + ' blocks'; } } } }
      }
    });

    // Small: compliance status of active blocks
    new Chart(document.getElementById('s1DonutCanvas'), {
      type: 'doughnut',
      data: { labels: ['Compliant', 'Due Soon', 'At Risk'], datasets: [{ data: @json(array_values($donut)), backgroundColor: ['#16a34a', '#d97706', '#dc2626'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } }
    });

    // Wells overall (totals across the selected blocks)
    var totals = @json($wellTotals);
    var totalEl = document.getElementById('wellsTotalCanvas');
    if (totalEl) new Chart(totalEl, {
      type: 'bar',
      data: {
        labels: ['Drilled', 'Under drilling', 'Planned'],
        datasets: [{ data: [totals.drilled, totals.drilling, totals.planned], backgroundColor: ['#059669', '#f97316', '#2563eb'], borderRadius: 3, maxBarThickness: 34 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, layout: { padding: { top: 16 } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
          x: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } }
        },
        plugins: { legend: { display: false } }
      },
      plugins: [{
        id: 'totalValues',
        afterDatasetsDraw: function (chart) {
          var ctx = chart.ctx;
          ctx.save(); ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = '#0f172a';
          chart.getDatasetMeta(0).data.forEach(function (bar, i) { ctx.fillText(chart.data.datasets[0].data[i], bar.x, bar.y - 4); });
          ctx.restore();
        }
      }]
    });

    // Wells per block
    var wells = @json($wells);
    var wellsEl = document.getElementById('wellsCanvas');
    if (wellsEl) {
      var codes = Object.keys(wells);
      var series = function (key) { return codes.map(function (c) { return wells[c][key]; }); };
      new Chart(wellsEl, {
        type: 'bar',
        data: {
          labels: codes,
          datasets: [
            { label: 'Drilled', data: series('drilled'), backgroundColor: '#059669', borderRadius: 3, maxBarThickness: 22 },
            { label: 'Under drilling', data: series('drilling'), backgroundColor: '#f97316', borderRadius: 3, maxBarThickness: 22 },
            { label: 'Planned', data: series('planned'), backgroundColor: '#2563eb', borderRadius: 3, maxBarThickness: 22 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' }, title: { display: true, text: 'No. of wells', font: { size: 10 } } },
            x: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } }
          },
          plugins: { legend: { display: false } }
        }
      });
    }

    // Blocks per OALP round
    var rounds = @json($rounds);
    new Chart(document.getElementById('roundsCanvas'), {
      type: 'bar',
      data: {
        labels: Object.keys(rounds),
        datasets: [{ data: Object.values(rounds), backgroundColor: Object.values(rounds).map(function (v) { return v ? '#1d4ed8' : '#cbd5e1'; }), borderRadius: 3, maxBarThickness: 26 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' }, title: { display: true, text: 'No. of blocks', font: { size: 10 } } },
          x: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } }
        },
        layout: { padding: { top: 16 } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + c.raw + ' block' + (c.raw === 1 ? '' : 's'); } } } }
      },
      // Count printed on top of every bar (0 included)
      plugins: [{
        id: 'barValues',
        afterDatasetsDraw: function (chart) {
          var ctx = chart.ctx;
          ctx.save();
          ctx.font = '700 11px Inter, sans-serif';
          ctx.textAlign = 'center';
          chart.getDatasetMeta(0).data.forEach(function (bar, i) {
            var value = chart.data.datasets[0].data[i];
            ctx.fillStyle = value ? '#0f172a' : '#94a3b8';
            ctx.fillText(value, bar.x, bar.y - 4);
          });
          ctx.restore();
        }
      }]
    });
  });
</script>
@endpush
