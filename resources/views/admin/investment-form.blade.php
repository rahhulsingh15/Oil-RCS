@extends('admin.layouts.master')

@php
  $adding = $blocks !== null;
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
@endphp

@section('title', 'Investment Monitoring')
@section('heading', $adding ? 'Add Investment' : 'Edit Investment: '.$block->code)
@section('subheading', 'Investment made, liquidated damages (LD), paid extensions and bank guarantee. Leave a field empty if not known.')

@section('content')
  <form method="POST" action="{{ $adding ? route('investment.store') : route('investment.update', $block) }}" class="space-y-3.5 max-w-4xl">
    @csrf
    @unless ($adding) @method('PUT') @endunless

    @if ($adding)
      <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs max-w-sm">
        <label for="i-block" class="{{ $label }}">Block *</label>
        <select id="i-block" name="block_id" required class="{{ $input }} font-bold"
                onchange="if (this.selectedOptions[0].dataset.hasData) { window.location = this.selectedOptions[0].dataset.editUrl; }">
          <option value="">Select block</option>
          @foreach ($blocks as $b)
            <option value="{{ $b->id }}" @selected((int) old('block_id', $block?->id) === $b->id)
                    @if ($b->investment) data-has-data="1" data-edit-url="{{ route('investment.edit', $b) }}" @endif>
              {{ $b->code }}{{ $b->investment ? ' (has data, opens edit)' : '' }}
            </option>
          @endforeach
        </select>
        <p class="text-[10px] text-slate-400 mt-0.5">One investment record per block. Choosing a block that already has data opens its edit page.</p>
      </div>
    @endif

    @foreach (['Investment Made' => 'invested_', 'Liquidated Damages (LD) Paid' => 'ld_', 'Paid Extensions & Total' => ['paid_extension_', 'total_outgo']] as $title => $prefixes)
      <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1.5 border-b border-slate-100 mb-3">{{ $title }}</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          @foreach (\App\Http\Controllers\Admin\InvestmentController::FIELDS as $name => $text)
            @continue(! \Illuminate\Support\Str::startsWith($name, (array) $prefixes))
            <div>
              <label for="i-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
              <input id="i-{{ $name }}" name="{{ $name }}" type="number" min="0" step="{{ $name === 'paid_extension_quarters' ? '1' : 'any' }}"
                     value="{{ old($name, $investment?->{$name}) }}" class="{{ $input }}">
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1.5 border-b border-slate-100 mb-3">Bank Guarantee (BG)</h4>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach (\App\Http\Controllers\Admin\InvestmentController::BG_FIELDS as $name => [$text, $type])
          @php $current = $bg?->{$name}; @endphp
          <div>
            <label for="bg-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="bg-{{ $name }}" name="bg_{{ $name }}" type="{{ $type }}" @if ($type === 'number') min="0" step="any" @endif
                   value="{{ old("bg_{$name}", $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current) }}" class="{{ $input }}">
          </div>
        @endforeach
      </div>
      <p class="text-[10px] text-slate-400 mt-2">Changing "BG Valid Till" updates the BG renewal deadline in the Compliance Monitor.</p>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('investment.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $adding ? 'Add Investment' : 'Save Changes' }}</button>
    </div>
  </form>
@endsection
