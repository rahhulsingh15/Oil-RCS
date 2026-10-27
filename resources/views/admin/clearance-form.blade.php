@extends('admin.layouts.master')

@php
  $editing = $location !== null;
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
  $date = fn ($c, $field) => $c?->{$field}?->format('Y-m-d');
@endphp

@section('title', 'Statutory Clearances')
@section('heading', $editing ? "Edit Clearances: {$location}" : 'Add Clearances')
@section('subheading', 'EC, CTE and CTO of one location. Validity dates create the renewal deadlines automatically.')

@section('content')
  <form method="POST" action="{{ $editing ? route('clearances.update') : route('clearances.store') }}" class="space-y-3.5 max-w-5xl">
    @csrf
    @if ($editing)
      @method('PUT')
      <input type="hidden" name="original_block" value="{{ $blockId }}">
      <input type="hidden" name="original_location" value="{{ $location }}">
    @endif

    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <label for="c-block" class="{{ $label }}">Block *</label>
        <select id="c-block" name="block_id" required class="{{ $input }} font-bold" @if ($editing) disabled @endif>
          <option value="">Select block</option>
          @foreach ($blocks as $b)
            <option value="{{ $b->id }}" @selected((int) old('block_id', $blockId) === $b->id)>{{ $b->code }}</option>
          @endforeach
        </select>
        @if ($editing)<input type="hidden" name="block_id" value="{{ $blockId }}">@endif
      </div>
      <div>
        <label for="c-location" class="{{ $label }}">Location *</label>
        <input id="c-location" name="location_name" required maxlength="100" value="{{ old('location_name', $location) }}" placeholder="e.g. OBRA" class="{{ $input }}">
      </div>
      <div>
        <label for="c-project" class="{{ $label }}">Project</label>
        <input id="c-project" name="project" maxlength="100" value="{{ old('project', $project) }}" placeholder="e.g. Mahanadi Basin Project" class="{{ $input }}">
      </div>
      <div class="sm:col-span-3">
        <label for="c-remarks" class="{{ $label }}">Remarks</label>
        <input id="c-remarks" name="remarks" maxlength="2000" value="{{ old('remarks', $remarks) }}" placeholder="e.g. Abandoned / Land acquisition in progress" class="{{ $input }}">
        <p class="text-[10px] text-slate-400 mt-0.5">Writing "Abandoned" or "Relinquished" here marks the site as closed, so its old validity is not counted as expired.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3.5">
      @foreach (\App\Models\Clearance::TYPES as $type)
        @php $t = strtolower($type); $c = $types->get($type); @endphp
        <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs space-y-2.5">
          <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1.5 border-b border-slate-100">
            {{ $type }} <span class="normal-case font-medium text-slate-500">· {{ ['EC' => 'Environmental Clearance', 'CTE' => 'Consent to Establish', 'CTO' => 'Consent to Operate'][$type] }}</span>
          </h4>
          <div>
            <label for="{{ $t }}-status" class="{{ $label }}">Status *</label>
            <select id="{{ $t }}-status" name="{{ $t }}_status" required class="{{ $input }}">
              @foreach (\App\Http\Controllers\Admin\ClearanceController::STATUSES as $value => $text)
                <option value="{{ $value }}" @selected(old("{$t}_status", $c?->status ?? 'not_applied') === $value)>{{ $text }}</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="{{ $t }}-applied" class="{{ $label }}">Applied On</label>
              <input id="{{ $t }}-applied" type="date" name="{{ $t }}_applied_on" value="{{ old("{$t}_applied_on", $date($c, 'applied_on')) }}" class="{{ $input }}">
            </div>
            <div>
              <label for="{{ $t }}-received" class="{{ $label }}">Received On</label>
              <input id="{{ $t }}-received" type="date" name="{{ $t }}_received_on" value="{{ old("{$t}_received_on", $date($c, 'received_on')) }}" class="{{ $input }}">
            </div>
          </div>
          <div>
            <label for="{{ $t }}-valid" class="{{ $label }}">Valid Until</label>
            <input id="{{ $t }}-valid" type="date" name="{{ $t }}_valid_until" value="{{ old("{$t}_valid_until", $date($c, 'valid_until')) }}" class="{{ $input }}">
          </div>
          <div>
            <label for="{{ $t }}-note" class="{{ $label }}">Note</label>
            <input id="{{ $t }}-note" name="{{ $t }}_note" maxlength="255" value="{{ old("{$t}_note", $c?->note) }}" placeholder="e.g. Applied, pending due to land acquisition" class="{{ $input }}">
          </div>
        </div>
      @endforeach
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('clearances.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $editing ? 'Save Changes' : 'Add Clearances' }}</button>
    </div>
  </form>
@endsection
