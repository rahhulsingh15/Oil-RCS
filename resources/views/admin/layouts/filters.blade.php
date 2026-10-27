{{--
  Top filters used on every screen: All Basins / All Blocks / third dropdown / search.
  Pass $third = ['name' => 'phase', 'label' => 'All Phases', 'options' => [...]] to change the third dropdown.
  $filterBasins and $filterBlocks come from AppServiceProvider.
--}}
@php
  $third = $third ?? ['name' => 'phase', 'label' => 'All Phases', 'options' => $filterPhases];
  $select = 'text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium focus:outline-none focus:border-blue-500';
@endphp

<form method="GET" class="flex flex-wrap items-center gap-2.5 no-print">
  @foreach (request()->except(['basin', 'block', $third['name'], 'q', 'page']) as $key => $value)
    @if (is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
  @endforeach

  <select name="basin" onchange="this.form.submit()" class="{{ $select }}">
    <option value="">All Basins</option>
    @foreach ($filterBasins as $basin)
      <option value="{{ $basin }}" @selected(request('basin') === $basin)>{{ $basin }}</option>
    @endforeach
  </select>

  <select name="block" onchange="this.form.submit()" class="{{ $select }}">
    <option value="">All Blocks</option>
    @foreach ($filterBlocks as $b)
      <option value="{{ $b->id }}" @selected(request('block') == $b->id)>{{ $b->code }}</option>
    @endforeach
  </select>

  <select name="{{ $third['name'] }}" onchange="this.form.submit()" class="{{ $select }}">
    <option value="">{{ $third['label'] }}</option>
    @foreach ($third['options'] as $value => $label)
      @php $value = is_int($value) ? $label : $value; @endphp
      <option value="{{ $value }}" @selected(request($third['name']) === $value)>{{ $label }}</option>
    @endforeach
  </select>

  @if ($search ?? true)
    <div class="relative w-36">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Search..." class="w-full text-[11px] pl-6 pr-2 py-1 border border-slate-300 rounded bg-white focus:outline-none focus:border-blue-500">
      <i class="fa-solid fa-magnifying-glass absolute left-2 top-2 text-slate-400 text-[10px]"></i>
    </div>
  @endif

  @if (request()->hasAny(['basin', 'block', $third['name'], 'q']))
    <a href="{{ url()->current() }}{{ request('category') ? '?category='.urlencode(request('category')) : '' }}" class="text-[11px] text-slate-500 hover:text-slate-800" title="Clear filters"><i class="fa-solid fa-xmark"></i></a>
  @endif
</form>
