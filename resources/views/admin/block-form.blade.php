@extends('admin.layouts.master')

@php
  $editing = $block->exists;
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  // Current value of a field: old input, else the saved value (dates as Y-m-d for <input type=date>)
  $value = function ($relation, $name) use ($block) {
      $record = $relation ? $block->{$relation} : $block;
      $v = $record?->{$name};
      return old(\App\Http\Controllers\Admin\BlockFormController::inputName($relation, $name), $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v);
  };
@endphp

@section('title', $editing ? 'Edit Block' : 'Add Block')
@section('heading', $editing ? 'Edit Block: '.$block->code : 'Add New Block')
@section('subheading', 'Fields marked * are required. Sections left empty are skipped. Deadlines update automatically after saving.')

@section('content')
  <form method="POST" action="{{ $editing ? route('blocks.update', $block) : route('blocks.store') }}" class="space-y-3.5">
    @csrf
    @if ($editing) @method('PUT') @endif

    @foreach ($sections as $title => [$relation, $fields])
      <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1.5 border-b border-slate-100 mb-3">{{ $title }}</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          @foreach ($fields as $name => $field)
            @php [$label, $type] = $field; $current = $value($relation, $name); $input_name = \App\Http\Controllers\Admin\BlockFormController::inputName($relation, $name); @endphp
            <div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
              <label for="f-{{ $input_name }}" class="block font-semibold text-slate-700 mb-0.5 text-[11px]">{{ $label }}</label>
              @if ($type === 'select')
                <select id="f-{{ $input_name }}" name="{{ $input_name }}" class="{{ $input }}">
                  <option value="">—</option>
                  @foreach (array_unique(array_merge($field[2], $current ? [$current] : [])) as $option)
                    <option value="{{ $option }}" @selected((string) $current === (string) $option)>{{ $option }}</option>
                  @endforeach
                </select>
              @elseif ($type === 'textarea')
                <textarea id="f-{{ $input_name }}" name="{{ $input_name }}" rows="2" class="{{ $input }}">{{ $current }}</textarea>
              @else
                <input id="f-{{ $input_name }}" name="{{ $input_name }}" value="{{ $current }}"
                       type="{{ $type === 'date' ? 'date' : ($type === 'text' ? 'text' : 'number') }}"
                       @if ($type === 'number') step="any" min="0" @elseif ($type === 'int') step="1" min="0" @endif
                       class="{{ $input }}">
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <div class="flex justify-end gap-2">
      <a href="{{ $editing ? route('blocks.show', $block) : route('blocks.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $editing ? 'Save Changes' : 'Add Block' }}</button>
    </div>
  </form>
@endsection
