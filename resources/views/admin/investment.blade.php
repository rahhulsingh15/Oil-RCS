@extends('admin.layouts.master')

@section('title', 'Investment Monitoring')
@section('heading', 'Investment Monitoring')
@section('subheading', 'Bank guarantees, liquidated damages and paid extensions for each block')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters')
    @can('investment.create')
      <a href="{{ route('investment.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print whitespace-nowrap"><i class="fa-solid fa-plus mr-1"></i> Add Investment</a>
    @endcan
  </div>
@endsection

@php $n = fn ($v, $dec = 0) => $v === null ? '-' : number_format($v, $dec); @endphp

@section('content')
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">BG Submitted (USD)</p>
      <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $n($blocks->sum(fn ($b) => $b->bankGuarantee?->amount_usd ?? 0) / 1e6, 2) }} M</p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">LD Paid (Mill USD)</p>
      <p class="text-2xl font-black text-red-600 mt-0.5">{{ $n($blocks->sum(fn ($b) => $b->investment?->ld_total_musd ?? 0), 2) }}</p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">LD Paid (Cr INR)</p>
      <p class="text-2xl font-black text-red-600 mt-0.5">{{ $n($blocks->sum(fn ($b) => $b->investment?->ld_total_cr_inr ?? 0), 2) }}</p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Paid Extensions (Quarters)</p>
      <p class="text-2xl font-black text-amber-600 mt-0.5">{{ $blocks->sum(fn ($b) => $b->investment?->paid_extension_quarters ?? 0) }}</p>
    </div>
  </div>

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3 text-right">BG (USD)</th>
            <th class="py-2.5 px-3 text-right">BG (INR)</th>
            <th class="py-2.5 px-3">BG Valid Till</th>
            <th class="py-2.5 px-3 text-right">LD Wells (Mill USD)</th>
            <th class="py-2.5 px-3 text-right">LD Total (Cr INR)</th>
            <th class="py-2.5 px-3 text-center">Paid Ext. (Qtrs)</th>
            <th class="py-2.5 px-3 text-right">Paid Ext. (USD)</th>
            <th class="py-2.5 px-3 text-right">Investment Made (INR)</th>
            @canany(['investment.create', 'investment.edit'])<th class="py-2.5 px-3 text-center">Action</th>@endcanany
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($blocks as $block)
            @php $bg = $block->bankGuarantee; $inv = $block->investment; @endphp
            <tr class="hover:bg-slate-50 cursor-pointer {{ $block->isRelinquished() ? 'text-slate-400' : '' }}" onclick="window.location='{{ route('blocks.show', $block) }}#investment'">
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $block->code }}</td>
              <td class="py-2.5 px-3 text-right">{{ $n($bg?->amount_usd) }}</td>
              <td class="py-2.5 px-3 text-right">{{ $n($bg?->amount_inr) }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $bg?->validity_date?->format('d.m.Y') ?? '-' }}</td>
              <td class="py-2.5 px-3 text-right">{{ $n($inv?->ld_wells_musd, 2) }}</td>
              <td class="py-2.5 px-3 text-right font-bold">{{ $n($inv?->ld_total_cr_inr, 2) }}</td>
              <td class="py-2.5 px-3 text-center">{{ $inv?->paid_extension_quarters ?? '-' }}</td>
              <td class="py-2.5 px-3 text-right">{{ $n($inv?->paid_extension_usd) }}</td>
              <td class="py-2.5 px-3 text-right">{{ $n($inv?->invested_total_inr) }}</td>
              @canany(['investment.create', 'investment.edit'])
                <td class="py-2.5 px-3 text-center" onclick="event.stopPropagation()">
                  @if ($inv)
                    @can('investment.edit')
                      <a href="{{ route('investment.edit', $block) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit investment"><i class="fa-solid fa-pen-to-square"></i></a>
                    @endcan
                  @else
                    @can('investment.create')
                      <a href="{{ route('investment.create', ['block' => $block->id]) }}" class="px-1.5 text-blue-600 hover:text-blue-800" title="Add investment details"><i class="fa-solid fa-circle-plus"></i></a>
                    @elsecan('investment.edit')
                      <a href="{{ route('investment.edit', $block) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit investment"><i class="fa-solid fa-pen-to-square"></i></a>
                    @endcan
                  @endif
                </td>
              @endcanany
            </tr>
          @empty
            <tr><td colspan="10" class="py-6 text-center text-slate-400">No blocks match these filters</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-[10px] text-slate-400">"Investment Made" is blank in the client Excel for all blocks.</p>
@endsection
