@extends('admin.layouts.master')

@php
  $labels = \App\Models\Milestone::CATEGORY_LABELS;
  $priority = fn ($days) => match (true) { $days <= 30 => 'High', $days <= 90 => 'Medium', default => 'Low' };
@endphp

@section('title', '6. Reports & Alerts')
@section('heading', 'Reports, Alerts & Downloads')
@section('subheading', 'Generate reports, view upcoming actions and download data')
@section('filters')
  <form method="GET" class="flex items-center gap-2 no-print">
    <select name="days" onchange="this.form.submit()" class="text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium">
      @foreach (\App\Http\Controllers\Admin\ReportController::WINDOWS as $w)
        <option value="{{ $w }}" @selected($window === $w)>Next {{ $w }} days</option>
      @endforeach
    </select>
    <select name="category" onchange="this.form.submit()" class="text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium">
      <option value="">All Compliance Areas</option>
      @foreach ($categories as $c)
        <option value="{{ $c }}" @selected(request('category') === $c)>{{ $labels[$c] ?? $c }}</option>
      @endforeach
    </select>
  </form>
@endsection

@section('content')
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
    <!-- Left: tabs -->
    <div class="lg:col-span-8 bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
      <div class="flex items-center gap-1 text-[11px] font-semibold mb-2.5 no-print">
        <button type="button" data-tab="actions" class="tab-btn px-3 py-1 rounded">Upcoming Actions</button>
        <button type="button" data-tab="reports" class="tab-btn px-3 py-1 rounded">Reports</button>
        <button type="button" data-tab="downloads" class="tab-btn px-3 py-1 rounded">Data Downloads</button>
      </div>

      <!-- Upcoming Actions -->
      <div data-panel="actions" class="tab-panel overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead>
            <tr class="text-[9px] uppercase text-slate-400 border-b border-slate-200 font-bold bg-slate-50">
              <th class="py-2 px-2.5">Date</th>
              <th class="py-2 px-2.5">Block</th>
              <th class="py-2 px-2.5">Compliance Area</th>
              <th class="py-2 px-2.5">Action Required</th>
              <th class="py-2 px-2.5 text-center">Days Left</th>
              <th class="py-2 px-2.5 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            @forelse ($actions as $m)
              @php $days = $m->daysLeft(); $p = $days < 0 ? 'At Risk' : $priority($days); @endphp
              <tr class="hover:bg-slate-50">
                <td class="py-2 px-2.5 font-bold whitespace-nowrap {{ $days <= 30 ? 'text-red-600' : 'text-slate-700' }}">{{ $m->due_date->format('d M Y') }}</td>
                <td class="py-2 px-2.5 font-semibold text-slate-900 whitespace-nowrap">{{ $m->block->code }}</td>
                <td class="py-2 px-2.5 text-slate-600">{{ $m->shortLabel() }}</td>
                <td class="py-2 px-2.5 text-slate-800">{{ $m->title }}</td>
                <td class="py-2 px-2.5 text-center font-black {{ $days <= 30 ? 'text-red-600' : ($days <= 90 ? 'text-orange-600' : 'text-emerald-600') }}">{{ $days }}</td>
                <td class="py-2 px-2.5 text-center">@include('admin.layouts.badge', ['status' => $p])</td>
              </tr>
            @empty
              <tr><td colspan="6" class="py-6 text-center text-slate-400">Nothing due in the next {{ $window }} days</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- Reports: summary by area -->
      <div data-panel="reports" class="tab-panel hidden overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead>
            <tr class="text-[9px] uppercase text-slate-400 border-b border-slate-200 font-bold bg-slate-50">
              <th class="py-2 px-2.5">Compliance Area</th>
              <th class="py-2 px-2.5 text-center">At Risk</th>
              <th class="py-2 px-2.5 text-center">Due Soon</th>
              <th class="py-2 px-2.5 text-center">Open Items</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            @forelse ($byCategory as $category => $c)
              <tr>
                <td class="py-2 px-2.5 text-slate-800">{{ $labels[$category] ?? $category }}</td>
                <td class="py-2 px-2.5 text-center {{ $c['overdue'] ? 'text-red-600 font-bold' : 'text-slate-400' }}">{{ $c['overdue'] }}</td>
                <td class="py-2 px-2.5 text-center {{ $c['dueSoon'] ? 'text-amber-600 font-bold' : 'text-slate-400' }}">{{ $c['dueSoon'] }}</td>
                <td class="py-2 px-2.5 text-center">{{ $c['total'] }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-6 text-center text-slate-400">No open items</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- Data Downloads -->
      <div data-panel="downloads" class="tab-panel hidden space-y-2 text-[11px]">
        @can('reports.export')
          @foreach ([['All obligations', []], ['At Risk & Due Soon only', ['status' => 'attention']], ['Bank Guarantees', ['category' => 'Bank Guarantee']], ['PEL/PL Validity', ['category' => 'Licence']], ['Statutory Clearances', ['category' => 'Statutory Clearance']]] as [$name, $query])
            <a href="{{ route('reports.export', $query) }}" class="flex items-center justify-between p-2.5 rounded bg-slate-50 border border-slate-100 hover:bg-slate-100">
              <span class="flex items-center gap-2 font-semibold text-slate-800"><i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i> {{ $name }}</span>
              <span class="text-blue-600 font-semibold">Download CSV</span>
            </a>
          @endforeach
        @else
          <p class="py-6 text-center text-slate-400">You do not have permission to download data.</p>
        @endcan
      </div>
    </div>

    <!-- Right: actions -->
    <div class="lg:col-span-4 space-y-3 no-print">
      <button type="button" onclick="window.print()" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[12px] rounded shadow-xs flex items-center justify-center gap-2 transition">
        <i class="fa-solid fa-file-invoice"></i> Generate Compliance Report
      </button>

      @can('reports.export')
        <a href="{{ route('reports.export') }}" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[12px] rounded shadow-xs flex items-center justify-center gap-2 transition">
          <i class="fa-solid fa-file-excel"></i> Export to Excel
        </a>
      @endcan

      <div class="bg-white rounded p-3.5 border border-oilCardBorder shadow-xs">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 mb-2">Scheduled Reports</h4>
        <div class="space-y-2 text-[11px]">
          @foreach ([['Monthly Compliance Report', 'fa-regular fa-file-pdf text-blue-600', now()->addMonthNoOverflow()->startOfMonth()], ['Quarterly Management Report', 'fa-regular fa-file-excel text-emerald-600', now()->addQuarterNoOverflow()->firstOfQuarter()], ['Annual RSC Compliance Report', 'fa-solid fa-book-bookmark text-amber-600', now()->month >= 4 ? now()->addYear()->setDate(now()->year + 1, 4, 1) : now()->setDate(now()->year, 4, 1)]] as [$name, $icon, $next])
            <div class="flex items-center justify-between p-2 rounded bg-slate-50 border border-slate-100">
              <div class="flex items-center gap-2">
                <i class="{{ $icon }} text-sm"></i>
                <div>
                  <p class="font-bold text-slate-800">{{ $name }}</p>
                  <p class="text-[9px] text-slate-400">Next run: {{ $next->format('d M Y') }}</p>
                </div>
              </div>
              <span class="text-[9px] font-semibold text-slate-400">Soon</span>
            </div>
          @endforeach
        </div>
        <p class="mt-2 text-[10px] text-slate-400">Automatic emailing of these reports starts with the alert scheduler.</p>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    function show(name) {
      document.querySelectorAll('.tab-btn').forEach(function (b) {
        var on = b.dataset.tab === name;
        b.classList.toggle('bg-blue-100', on); b.classList.toggle('text-blue-900', on); b.classList.toggle('font-bold', on);
        b.classList.toggle('text-slate-600', !on); b.classList.toggle('hover:bg-slate-100', !on);
      });
      document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== name); });
    }
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.tab); }); });
    show('actions');
  })();
</script>
@endpush
