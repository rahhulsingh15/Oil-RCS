@extends('admin.layouts.master')

@php
  $t = $block->timeline;
  $l = $block->licence;
  $w = $block->workProgramme;
  $bg = $block->bankGuarantee;
  $inv = $block->investment;
  $d = fn ($date) => $date?->format('d.m.Y') ?? '-';
  $n = fn ($v, $dec = 0) => $v === null ? '-' : number_format($v, $dec);
  $phase = $block->isRelinquished() ? 'Relinquished' : ($t?->current_phase ?? $block->current_stage);
  $boundary = $block->mapBoundary();
  $mapImage = $block->mapImageUrl();

  // Compliance snapshot: status of the milestone(s) behind each row
  $ms = $block->milestones->whereIn('status', \App\Models\Milestone::OPEN_STATUSES);
  $rank = ['Compliant' => 0, 'Due Soon' => 1, 'At Risk' => 2];
  $worst = function ($prefixes) use ($ms, $rank) {
      $status = null;
      foreach ($ms as $m) {
          if (in_array(strtok($m->source_key, ':'), $prefixes, true)) {
              $s = $m->statusLabel();
              $status = $status === null || $rank[$s] > $rank[$status] ? $s : $status;
          }
      }
      return $status;
  };
  $lastMc = $block->mcMeetings->whereNotNull('meeting_date')->sortBy('meeting_date')->last();
  $pendingClearances = $block->clearances->filter(fn ($c) => in_array($c->status, ['applied', 'not_applied']) && ! $c->isSiteClosed())->count();
  $pct = $w?->progressPct();

  $snapshot = [
      ['PEL/PL Validity', $worst(['pel']), $d($l?->validity_date)],
      ['BG Validity', $worst(['bg']), $d($bg?->validity_date)],
      ['CWP Progress', $pct === null ? null : ($pct >= 100 ? 'Compliant' : $worst(['phase'])), $pct === null ? '-' : round($pct).'%'],
      ['Statutory Clearances', $worst(['ec', 'cte', 'cto']) ?? ($block->clearances->isEmpty() ? null : 'Compliant'), $pendingClearances.' pending'],
      ['MC Meetings', $worst(['mc']), $lastMc ? 'Last: '.$lastMc->meeting_date->format('d.m.Y') : 'None held'],
      ['Investment', $inv ? 'Compliant' : null, $inv?->ld_total_musd !== null ? 'LD: '.$n($inv->ld_total_musd, 2).' M USD' : 'No data'],
  ];

  $tabs = ['overview' => 'Overview', 'timeline' => 'Timeline', 'cwp' => 'CWP', 'clearances' => 'Statutory Clearances', 'bg' => 'BG',
           'investment' => 'Investment', 'mc' => 'MC Meetings', 'locations' => 'Locations', 'milestones' => 'Milestones', 'documents' => 'Documents'];
  $th = 'py-2 px-3';
  $row = 'flex justify-between gap-3 py-0.5 border-b border-slate-50';
@endphp

@section('title', '4. Block Detail Page')
@section('heading')
  <span class="text-base">{{ $block->code }}</span>
  <span class="ml-1.5 text-[10px] bg-slate-200 text-slate-700 font-semibold px-2 py-0.5 rounded align-middle">RSC</span>
@endsection
@section('subheading', "{$block->basin} Basin | {$phase} Phase | RSC")
@section('filters')
  <div class="flex flex-wrap items-center gap-2 text-[11px]">
    <span class="text-slate-500 font-medium">Select Block</span>
    <select onchange="window.location = this.value" class="border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-800 font-bold focus:outline-none">
      @foreach ($allBlocks as $other)
        <option value="{{ route('blocks.show', $other) }}" @selected($other->id === $block->id)>{{ $other->code }}</option>
      @endforeach
    </select>
    @can('blocks.edit')
      <a href="{{ route('blocks.edit', $block) }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold no-print"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit Block</a>
    @endcan
    @can('blocks.delete')
      <form method="POST" action="{{ route('blocks.destroy', $block) }}" class="no-print"
            data-confirm="Delete block {{ $block->code }}? ALL its data (licence, BG, clearances, locations, milestones, MC meetings, documents) will be permanently deleted.">
        @csrf @method('DELETE')
        <button type="submit" class="px-3 py-1 border border-red-300 text-red-600 hover:bg-red-50 rounded font-bold"><i class="fa-solid fa-trash-can mr-1"></i> Delete</button>
      </form>
    @endcan
  </div>
@endsection

@section('content')
  <!-- Tabs -->
  <div class="flex items-center gap-1 border-b border-slate-200 text-[11px] font-semibold text-slate-600 overflow-x-auto">
    @foreach ($tabs as $key => $label)
      <button type="button" data-tab="{{ $key }}" class="tab-btn px-3 py-1.5 whitespace-nowrap border-b-2 border-transparent hover:text-slate-900">{{ $label }}</button>
    @endforeach
  </div>

  <!-- Overview -->
  <section data-panel="overview" class="tab-panel grid grid-cols-1 lg:grid-cols-12 gap-4">
    <!-- Block map -->
    <div class="lg:col-span-3 bg-white rounded p-1.5 border border-oilCardBorder shadow-xs">
      <div class="relative z-0 rounded overflow-hidden h-full" style="min-height: 300px">
        <!-- Location on map (approximate) -->
        <div id="block-map-view" class="absolute inset-0 {{ $mapImage ? 'hidden' : '' }}">
          <div id="block-map" class="absolute inset-0"></div>
          @if ($boundary && $boundary['approximate'])
            <span class="absolute bottom-1 left-1 z-[500] text-[9px] text-slate-600 bg-white/90 px-1 rounded">Approximate location and extent</span>
          @elseif (! $boundary)
            <div class="absolute inset-0 z-[500] flex items-center justify-center bg-slate-50 text-[11px] text-slate-400">No map location for this block yet</div>
          @endif
        </div>

        <!-- Base map image from the client's slides -->
        @if ($mapImage)
          <div id="block-image-view" class="absolute inset-0 bg-slate-900">
            <img src="{{ $mapImage }}" alt="Base map of block {{ $block->code }}" onclick="openMapImage()" onerror="window.mapImageFailed = true; if (window.mapImageMissing) mapImageMissing();" class="w-full h-full object-cover cursor-zoom-in">
            <span class="absolute bottom-1 left-1 right-1 text-[9px] text-white bg-slate-900/70 px-1.5 py-0.5 rounded">{{ config('block_map.image_source') }} · click to enlarge</span>
          </div>

          <div class="absolute top-1.5 left-1.5 z-[600] bg-white rounded shadow p-0.5 flex text-[10px] font-semibold">
            <button type="button" data-map="image" class="map-toggle px-2 py-0.5 rounded bg-blue-900 text-white">Base Map</button>
            <button type="button" data-map="location" class="map-toggle px-2 py-0.5 rounded text-slate-700">Location</button>
          </div>
        @endif
      </div>
    </div>

    <div class="lg:col-span-4 bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <div>
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100 mb-2">Key Information</h4>
        <dl class="space-y-1 text-[11px]">
          <div class="{{ $row }}"><dt class="text-slate-500">Block name</dt><dd class="font-bold text-slate-800">{{ $block->code }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Basin</dt><dd class="font-medium text-slate-700">{{ $block->basin }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Contract type</dt><dd class="font-medium text-slate-700">RSC ({{ $block->regime }} Round {{ $block->bid_round }})</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Area (Sq. Km)</dt><dd class="font-bold text-blue-800">{{ $n($block->currentArea(), 2) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Current Phase</dt><dd class="font-semibold text-slate-800">{{ $phase }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Category</dt><dd class="font-medium text-slate-700">{{ $block->category }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Operator</dt><dd class="font-bold text-red-600">OIL</dd></div>
          @php $officers = $block->users->where('is_active', true)->where('is_nodal_officer', true); @endphp
          <div class="{{ $row }}">
            <dt class="text-slate-500">Nodal Officer</dt>
            <dd class="font-semibold text-slate-800 text-right">
              @forelse ($officers as $officer)
                <span class="block">{{ $officer->name }}</span>
                <span class="block text-[10px] font-normal text-slate-500">
                  @if ($officer->phone)<a href="tel:{{ $officer->phone }}" class="hover:underline"><i class="fa-solid fa-phone text-[8px]"></i> {{ $officer->phone }}</a> · @endif
                  <a href="mailto:{{ $officer->email }}" class="hover:underline">{{ $officer->email }}</a>
                </span>
              @empty
                <span class="text-amber-600">Not assigned</span>
              @endforelse
            </dd>
          </div>
          <div class="{{ $row }}"><dt class="text-slate-500">State / Districts</dt><dd class="font-medium text-slate-700 text-right">{{ $block->state }}@if ($block->districts) · {{ $block->districts }} @endif</dd></div>
          <div class="flex justify-between py-0.5"><dt class="text-slate-500">Award date (RSC signed)</dt><dd class="font-medium text-slate-700">{{ $d($block->rsc_signing_date) }}</dd></div>
        </dl>
      </div>
    </div>

    <div class="lg:col-span-5 bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-2.5">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800">Compliance Snapshot</h4>
        @can('milestones.view')
          <a href="{{ route('compliance.index', ['block' => $block->id]) }}" class="text-[10px] text-blue-600 font-semibold hover:underline">View All &rarr;</a>
        @endcan
      </div>
      <div class="space-y-2 text-[11px]">
        @foreach ($snapshot as [$label, $status, $value])
          <div class="flex items-center justify-between p-2 rounded bg-slate-50 border border-slate-100">
            <span class="font-medium text-slate-700">{{ $label }}</span>
            <div class="flex items-center gap-3">
              @include('admin.layouts.badge', ['status' => $status ?? 'N/A'])
              <span class="font-semibold text-slate-700 w-28 text-right">{{ $value }}</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <!-- Timeline -->
  <section data-panel="timeline" class="tab-panel hidden grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100 mb-2">Contract Phases</h4>
      @if ($t)
        <dl class="space-y-1 text-[11px]">
          <div class="{{ $row }}"><dt class="text-slate-500">Contractual structure</dt><dd class="font-medium">{{ $t->contractual_structure }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Current phase</dt><dd class="font-bold">{{ $t->current_phase }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Phase-I duration (+ extension)</dt><dd>{{ $t->phase1_duration }} (+{{ $t->phase1_extension_allowed }})</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Phase-I start date</dt><dd>{{ $d($t->phase1_start_date) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Validity (contractual)</dt><dd>{{ $d($t->phase1_validity_contractual) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Validity (current)</dt><dd class="font-bold {{ $t->phase1_validity_current?->isPast() && ! $block->isRelinquished() ? 'text-red-600' : '' }}">{{ $d($t->phase1_validity_current) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Policy dated 11.11.25 availed</dt><dd>{{ $t->policy_2025_availed ?? '-' }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Phase-II duration (+ extension)</dt><dd>{{ $t->phase2_duration ?? '-' }} (+{{ $t->phase2_extension_allowed ?? '-' }})</dd></div>
          <div class="flex justify-between py-0.5"><dt class="text-slate-500">Extensions granted</dt><dd class="text-right whitespace-pre-line">{{ $t->phase1_extensions_granted ?? '-' }}</dd></div>
        </dl>
      @else
        <p class="text-slate-400 text-[11px]">No timeline data</p>
      @endif
    </div>
    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100 mb-2">PEL / PL Validity</h4>
      @if ($l)
        <dl class="space-y-1 text-[11px]">
          <div class="{{ $row }}"><dt class="text-slate-500">Granting authority</dt><dd>{{ $l->granting_authority }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Initial grant date</dt><dd>{{ $l->initial_grant_date_text ?? $d($l->initial_grant_date) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Effective date</dt><dd>{{ $d($l->effective_date) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">PEL to PL converted</dt><dd>{{ $l->pel_to_pl_converted }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Current validity</dt><dd class="font-bold">{{ $d($l->validity_date) }}</dd></div>
          <div class="{{ $row }}"><dt class="text-slate-500">Current year</dt><dd>{{ $l->current_year }}</dd></div>
          <div class="flex justify-between py-0.5"><dt class="text-slate-500">Last year fee rate / amount</dt><dd>{{ $n($l->fee_rate) }} / ₹{{ $n($l->fee_amount) }}</dd></div>
        </dl>
      @else
        <p class="text-slate-400 text-[11px]">No licence data</p>
      @endif
    </div>
  </section>

  <!-- CWP -->
  <section data-panel="cwp" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    @if ($w)
      <div class="overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
            <tr><th class="{{ $th }}">Work</th><th class="{{ $th }}">Committed</th><th class="{{ $th }}">Achieved</th><th class="{{ $th }}">Additional</th><th class="{{ $th }}">Progress</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach ([['2D (LKM)', $w->committed_2d_lkm, $w->achieved_2d_lkm, $w->additional_2d_lkm], ['3D (Sq. Km)', $w->committed_3d_sqkm, $w->achieved_3d_sqkm, $w->additional_3d_sqkm], ['Wells', $w->committed_wells, $w->achieved_wells, $w->additional_wells]] as [$name, $c, $a, $x])
              @php $p = $c > 0 ? min(($a ?? 0) / $c * 100, 100) : null; @endphp
              <tr>
                <td class="{{ $th }} font-semibold whitespace-nowrap">{{ $name }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $n($c, 2) }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $n($a, 2) }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $n($x, 2) }}</td>
                <td class="{{ $th }} whitespace-nowrap">
                  @if ($p !== null)
                    <div class="flex items-center gap-2"><div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full {{ $p >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $p }}%"></div></div><span class="font-bold">{{ round($p) }}%</span></div>
                  @else <span class="text-slate-400">No commitment</span> @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($w->wells_under_drilling)
        <p class="p-3 border-t border-slate-100 text-[11px] text-blue-900 bg-blue-50">
          <i class="fa-solid fa-oil-well mr-1"></i> {{ $w->wells_under_drilling }} well under drilling at <b>{{ $w->location_under_drilling }}</b>:
          {{ $n($w->current_drilled_depth_m) }} m of {{ $n($w->target_depth_m) }} m ({{ $n($w->drilling_completion_pct, 1) }}%)
        </p>
      @endif
    @else
      <p class="p-4 text-slate-400 text-[11px]">No CWP data</p>
    @endif
  </section>

  <!-- Statutory Clearances -->
  <section data-panel="clearances" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-x-auto">
    <table class="w-full text-left text-[11px]">
      <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
        <tr><th class="{{ $th }}">Location</th><th class="{{ $th }}">Type</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Applied On</th><th class="{{ $th }}">Received On</th><th class="{{ $th }}">Valid Till</th><th class="{{ $th }}">Remarks</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($block->clearances->sortBy(['location_name', 'type']) as $c)
          <tr>
            <td class="{{ $th }} font-semibold text-blue-900">{{ $c->location_name }}</td>
            <td class="{{ $th }} font-bold">{{ $c->type }}</td>
            <td class="{{ $th }}">@include('admin.layouts.badge', ['status' => $c->displayStatus()])</td>
            <td class="{{ $th }}">{{ $d($c->applied_on) }}</td>
            <td class="{{ $th }}">{{ $d($c->received_on) }}</td>
            <td class="{{ $th }} font-semibold">{{ $c->valid_until ? $d($c->valid_until) : ($c->note ?? '-') }}</td>
            <td class="{{ $th }} text-slate-600">{{ $c->remarks ?? '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-4 text-center text-slate-400">No statutory clearances recorded</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <!-- BG -->
  <section data-panel="bg" class="tab-panel hidden bg-white rounded p-4 border border-oilCardBorder shadow-xs max-w-xl">
    <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100 mb-2">Bank Guarantee</h4>
    @if ($bg)
      <dl class="space-y-1 text-[11px]">
        <div class="{{ $row }}"><dt class="text-slate-500">Current phase</dt><dd>{{ $bg->phase }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">Amount (USD)</dt><dd class="font-bold">{{ $n($bg->amount_usd) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">Amount (INR)</dt><dd class="font-bold">{{ $n($bg->amount_inr) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">BG validity till</dt><dd class="font-bold">{{ $d($bg->validity_date) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">BG claim date</dt><dd>{{ $d($bg->claim_date) }}</dd></div>
        <div class="flex justify-between py-0.5"><dt class="text-slate-500">Renewal timeline (as per RSC)</dt><dd>{{ $bg->renewal_timeline ?? '-' }}</dd></div>
      </dl>
    @else
      <p class="text-slate-400 text-[11px]">No bank guarantee on record</p>
    @endif
  </section>

  <!-- Investment -->
  <section data-panel="investment" class="tab-panel hidden bg-white rounded p-4 border border-oilCardBorder shadow-xs max-w-xl">
    <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100 mb-2">Investment Details</h4>
    @if ($inv)
      <dl class="space-y-1 text-[11px]">
        <div class="{{ $row }}"><dt class="text-slate-500">Investment made (INR)</dt><dd>{{ $n($inv->invested_total_inr) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">LD paid - Seismic / Wells / Others (Mill USD)</dt><dd>{{ $n($inv->ld_seismic_musd, 2) }} / {{ $n($inv->ld_wells_musd, 2) }} / {{ $n($inv->ld_others_musd, 2) }}</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">LD paid - Total</dt><dd class="font-bold">{{ $n($inv->ld_total_musd, 2) }} Mill USD ({{ $n($inv->ld_total_cr_inr, 2) }} Cr INR)</dd></div>
        <div class="{{ $row }}"><dt class="text-slate-500">Paid extensions availed</dt><dd>{{ $inv->paid_extension_quarters ?? '-' }} quarters · USD {{ $n($inv->paid_extension_usd) }}</dd></div>
        <div class="flex justify-between py-0.5"><dt class="text-slate-500">Total outgo (INR)</dt><dd>{{ $n($inv->total_outgo_inr) }}</dd></div>
      </dl>
    @else
      <p class="text-slate-400 text-[11px]">No investment data for this block</p>
    @endif
  </section>

  <!-- MC Meetings -->
  <section data-panel="mc" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden max-w-3xl">
    <div class="px-3 py-2 border-b border-slate-100 flex items-center justify-between text-[11px]">
      <span class="font-bold text-slate-800">{{ $block->mcMeetings->count() }} MC meetings held</span>
      @can('mc_meetings.view')
        <a href="{{ route('mc-meetings.index', ['block' => $block->id]) }}" class="text-blue-600 font-semibold hover:underline">{{ auth()->user()->can('mc_meetings.edit') ? 'Add meeting' : 'All MC meetings' }} &rarr;</a>
      @endcan
    </div>
    <div class="overflow-x-auto">
      <table class="w-full table-fixed text-left text-[11px] min-w-[500px]">
        <colgroup><col class="w-[24%]"><col class="w-[18%]"><col>@can('mc_meetings.edit')<col class="w-[16%]">@endcan</colgroup>
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200"><tr><th class="{{ $th }}">Meeting</th><th class="{{ $th }}">Date</th><th class="{{ $th }}">Minutes</th>@can('mc_meetings.edit')<th class="{{ $th }} text-right">Action</th>@endcan</tr></thead>
        <tbody class="divide-y divide-slate-100">
          @forelse ($block->mcMeetings->sortByDesc('meeting_no') as $mc)
            @php $latest = $mc->minutes->first(); $more = $mc->minutes->count() - 1; @endphp
            <tr class="h-10">
              <td class="{{ $th }} font-semibold whitespace-nowrap">MC Meeting #{{ $mc->meeting_no }}</td>
              <td class="{{ $th }} whitespace-nowrap">{{ $mc->meeting_date?->format('d.m.Y') ?? 'Not recorded' }}</td>
              <td class="{{ $th }}">
                @if ($latest)
                  <a href="{{ route('documents.download', [$latest, 'inline' => 1]) }}" target="_blank" class="flex items-center gap-1.5 min-w-0 text-blue-700 hover:underline" title="{{ $latest->original_name }}"><i class="fa-solid {{ $latest->icon() }} flex-shrink-0"></i> <span class="truncate">{{ $latest->original_name }}</span></a>
                  @if ($more > 0)<span class="text-[9px] text-slate-400">+{{ $more }} older file{{ $more > 1 ? 's' : '' }}</span>@endif
                @else
                  <span class="text-slate-400">Not uploaded</span>
                @endif
              </td>
              @can('mc_meetings.edit')
                <td class="{{ $th }} text-right whitespace-nowrap">
                  <form method="POST" action="{{ route('mc-meetings.minutes', $mc) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="cursor-pointer text-[10px] font-semibold text-blue-600 hover:underline" title="{{ $latest ? 'Upload a newer version of the minutes' : 'Upload the minutes of this meeting' }}">
                      <i class="fa-solid fa-upload mr-0.5"></i> Upload
                      <input type="file" name="file" class="hidden" accept=".{{ implode(',.', config('documents.extensions')) }}" onchange="this.form.submit()">
                    </label>
                  </form>
                </td>
              @endcan
            </tr>
          @empty
            <tr><td colspan="4" class="py-4 text-center text-slate-400">No MC meetings held yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <!-- Locations -->
  <section data-panel="locations" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-x-auto">
    <table class="w-full text-left text-[11px]">
      <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
        <tr><th class="{{ $th }}">Location</th><th class="{{ $th }}">Target Depth</th><th class="{{ $th }}">EDPMB No. / Date</th><th class="{{ $th }}">Drilling Status</th><th class="{{ $th }}">Key Findings</th><th class="{{ $th }}">Planned</th><th class="{{ $th }}">Current Status</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($block->locations as $loc)
          <tr>
            <td class="{{ $th }} font-bold text-blue-900">{{ $loc->name }}</td>
            <td class="{{ $th }} whitespace-nowrap">{{ $loc->target_depth_m ? $n($loc->target_depth_m).' m' : '-' }}</td>
            <td class="{{ $th }} whitespace-nowrap">{{ $loc->edpmb_no ?? '-' }} / {{ $loc->edpmb_date?->format('d.m.Y') ?? $loc->edpmb_date_text ?? '-' }}</td>
            <td class="{{ $th }}">@include('admin.layouts.badge', ['status' => $loc->drilling_status ?? '-'])</td>
            <td class="{{ $th }} text-slate-600">{{ $loc->key_findings ?? '-' }}</td>
            <td class="{{ $th }} whitespace-nowrap">{{ $loc->planned_completion ?? '-' }}</td>
            <td class="{{ $th }} text-slate-600">{{ $loc->current_status ?? '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-4 text-center text-slate-400">No locations released for this block</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <!-- Milestones -->
  <section data-panel="milestones" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-x-auto">
    <table class="w-full text-left text-[11px]">
      <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
        <tr><th class="{{ $th }}">Item</th><th class="{{ $th }}">Details</th><th class="{{ $th }}">Due Date</th><th class="{{ $th }} text-center">Days Left</th><th class="{{ $th }}">Alert</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Remarks</th>@can('milestones.edit')<th class="{{ $th }} text-center">Action</th>@endcan</tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($block->milestones as $m)
          <tr>
            <td class="{{ $th }} font-semibold whitespace-nowrap">{{ $m->shortLabel() }}</td>
            <td class="{{ $th }} text-slate-600">{{ $m->title }}</td>
            <td class="{{ $th }} whitespace-nowrap">{{ $d($m->due_date) }}</td>
            <td class="{{ $th }} text-center font-bold {{ $m->daysLeft() < 0 ? 'text-red-600' : '' }}">{{ $m->daysLeft() }}</td>
            <td class="{{ $th }} text-slate-500 whitespace-nowrap">{{ $m->alert_days_before }} days before</td>
            <td class="{{ $th }}">@include('admin.layouts.badge', ['status' => $m->statusLabel()])</td>
            <td class="{{ $th }} text-slate-600">{{ $m->remarks ?? '-' }}</td>
            @can('milestones.edit')
              <td class="{{ $th }} text-center"><button type="button" onclick="openMilestoneModal(this)" data-milestone="{{ $m->editJson() }}" class="px-1.5 text-slate-500 hover:text-slate-900" title="Update milestone"><i class="fa-solid fa-ellipsis"></i></button></td>
            @endcan
          </tr>
        @empty
          <tr><td colspan="8" class="py-4 text-center text-slate-400">No milestones for this block</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <!-- Documents -->
  <section data-panel="documents" class="tab-panel hidden bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="px-3 py-2 border-b border-slate-100 flex items-center justify-between text-[11px]">
      <span class="font-bold text-slate-800">{{ $block->documents->count() }} documents</span>
      <span class="flex items-center gap-3">
        @can('documents.view')
          <a href="{{ route('documents.index', ['block' => $block->id]) }}" class="text-blue-600 font-semibold hover:underline">Open in Document Repository &rarr;</a>
        @endcan
        @can('documents.create')
          <button type="button" onclick="openDocumentModal({{ $block->id }})" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold"><i class="fa-solid fa-upload mr-1"></i> Upload</button>
        @endcan
      </span>
    </div>
    @can('documents.view')
      <div class="overflow-x-auto">
        <table class="w-full text-left text-[11px] min-w-[550px]">
          <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
            <tr><th class="{{ $th }}">Document</th><th class="{{ $th }}">Type</th><th class="{{ $th }}">Date</th><th class="{{ $th }}">Size</th><th class="{{ $th }}">Uploaded By</th><th class="{{ $th }} text-center">Action</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse ($block->documents as $doc)
              <tr>
                <td class="{{ $th }}"><i class="fa-solid {{ $doc->icon() }} mr-1"></i> <b class="text-slate-900">{{ $doc->title }}</b><span class="block text-[10px] text-slate-400">{{ $doc->original_name }}</span></td>
                <td class="{{ $th }} whitespace-nowrap">{{ $doc->typeLabel() }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $d($doc->document_date) }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $doc->sizeLabel() }}</td>
                <td class="{{ $th }} whitespace-nowrap">{{ $doc->uploader?->name ?? '-' }}</td>
                <td class="{{ $th }} text-center whitespace-nowrap">
                  <a href="{{ route('documents.download', [$doc, 'inline' => 1]) }}" target="_blank" class="px-1.5 text-slate-500 hover:text-blue-700" title="View"><i class="fa-solid fa-eye"></i></a>
                  <a href="{{ route('documents.download', $doc) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Download"><i class="fa-solid fa-download"></i></a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="py-6 text-center text-slate-400"><i class="fa-regular fa-folder-open text-xl block mb-1"></i>No documents uploaded for this block yet</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @else
      <p class="p-6 text-center text-[11px] text-slate-400">You do not have permission to view documents.</p>
    @endcan
  </section>

  @include('admin.layouts.milestone-modal')
  @include('admin.layouts.document-modal', ['uploadBlocks' => collect([$block])])

  @if ($mapImage)
    <!-- Full-size base map -->
    <div id="map-image-box" class="hidden fixed inset-0 z-50 bg-slate-950/85 flex flex-col items-center justify-center p-4" onclick="closeMapImage()">
      <img src="{{ $mapImage }}" alt="Base map of block {{ $block->code }}" class="max-w-full max-h-[85vh] rounded shadow-2xl">
      <p class="mt-2 text-[11px] text-slate-300">{{ $block->code }} · {{ config('block_map.image_source') }} · click anywhere to close</p>
    </div>
  @endif
@endsection

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .leaflet-tooltip.block-label { background: #fff; border: 1px solid #1d4ed8; color: #1e3a8a; font: 700 10px Inter, sans-serif; padding: 1px 5px; box-shadow: none; }
    .leaflet-tooltip.block-label::before { display: none; }
  </style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  // Block map: outline of the block on a real map
  var blockMap = null, blockBounds = null;
  (function () {
    var boundary = @json($boundary);
    var tiles = @json(config('block_map.tiles'));
    if (!boundary) return;

    blockMap = L.map('block-map', { scrollWheelZoom: false, attributionControl: true });
    L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: 14 }).addTo(blockMap);

    var shape = L.polygon(boundary.points, {
      color: '#1d4ed8', weight: 2, fillColor: '#3b82f6', fillOpacity: 0.25,
      dashArray: boundary.approximate ? '6 4' : null
    }).addTo(blockMap);

    // Small OIL square in the middle, like the design
    L.marker(shape.getBounds().getCenter(), {
      icon: L.divIcon({ className: '', iconSize: [26, 18], html: '<div style="width:26px;height:18px;background:#1d4ed8;color:#fff;border-radius:2px;font:700 8px Inter,sans-serif;display:flex;align-items:center;justify-content:center">OIL</div>' })
    }).bindTooltip(@json($block->code), { permanent: true, direction: 'bottom', offset: [0, 10], className: 'block-label' }).addTo(blockMap);

    blockBounds = shape.getBounds();
    blockMap.fitBounds(blockBounds, { padding: [30, 30] });
  })();

  // Base Map image <-> Location map
  document.querySelectorAll('.map-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var image = btn.dataset.map === 'image';
      document.getElementById('block-image-view').classList.toggle('hidden', !image);
      document.getElementById('block-map-view').classList.toggle('hidden', image);
      document.querySelectorAll('.map-toggle').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('bg-blue-900', on); b.classList.toggle('text-white', on); b.classList.toggle('text-slate-700', !on);
      });
      if (!image && blockMap) setTimeout(function () { blockMap.invalidateSize(); blockMap.fitBounds(blockBounds, { padding: [30, 30] }); }, 0);
    });
  });
  function openMapImage() { document.getElementById('map-image-box').classList.remove('hidden'); }

  // Base map image could not load (e.g. file missing on the server): show the location map instead
  window.mapImageMissing = function () {
    var imageView = document.getElementById('block-image-view');
    if (!imageView) return;
    imageView.classList.add('hidden');
    document.getElementById('block-map-view').classList.remove('hidden');
    document.querySelectorAll('.map-toggle').forEach(function (b) { b.parentNode.classList.add('hidden'); });
    if (blockMap) setTimeout(function () { blockMap.invalidateSize(); blockMap.fitBounds(blockBounds, { padding: [30, 30] }); }, 0);
  };
  if (window.mapImageFailed) window.mapImageMissing();
  function closeMapImage() { document.getElementById('map-image-box').classList.add('hidden'); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.getElementById('map-image-box')) closeMapImage(); });

  (function () {
    function show(name) {
      if (!document.querySelector('[data-panel="' + name + '"]')) name = 'overview';
      if (name === 'overview' && blockMap) setTimeout(function () { blockMap.invalidateSize(); blockMap.fitBounds(blockBounds, { padding: [30, 30] }); }, 0);
      document.querySelectorAll('.tab-btn').forEach(function (b) {
        var on = b.dataset.tab === name;
        b.classList.toggle('border-blue-600', on); b.classList.toggle('text-blue-900', on); b.classList.toggle('font-bold', on);
        b.classList.toggle('border-transparent', !on);
      });
      document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== name); });
    }
    document.querySelectorAll('.tab-btn').forEach(function (b) {
      b.addEventListener('click', function () { history.replaceState(null, '', '#' + b.dataset.tab); show(b.dataset.tab); });
    });
    show(location.hash.slice(1) || 'overview');
  })();
</script>
@endpush
