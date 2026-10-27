@extends('admin.layouts.master')

@php
  $editing = $location->exists;
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
@endphp

@section('title', 'Location Inventory')
@section('heading', $editing ? 'Edit Location: '.$location->name : 'Add Location')
@section('subheading', 'Well location details and drilling status')

@section('content')
  <form method="POST" action="{{ $editing ? route('locations.update', $location) : route('locations.store') }}" class="bg-white rounded p-4 border border-oilCardBorder shadow-xs space-y-3 max-w-4xl">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <label for="l-block" class="{{ $label }}">Block *</label>
        <select id="l-block" name="block_id" required class="{{ $input }} font-bold">
          <option value="">Select block</option>
          @foreach ($blocks as $b)
            <option value="{{ $b->id }}" @selected((int) old('block_id', $location->block_id) === $b->id)>{{ $b->code }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="l-name" class="{{ $label }}">Location Name *</label>
        <input id="l-name" name="name" required maxlength="50" value="{{ old('name', $location->name) }}" placeholder="e.g. OBRD" class="{{ $input }}">
      </div>
      <div>
        <label for="l-status" class="{{ $label }}">Drilling Status *</label>
        <select id="l-status" name="drilling_status" required class="{{ $input }}">
          @foreach (\App\Http\Controllers\Admin\LocationController::DRILLING_STATUSES as $status)
            <option value="{{ $status }}" @selected(old('drilling_status', $location->drilling_status) === $status)>{{ $status === 'Not drilled' ? 'Planned (Not drilled)' : $status }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label for="l-td" class="{{ $label }}">Target Depth (m)</label>
        <input id="l-td" name="target_depth_m" type="number" min="0" step="any" value="{{ old('target_depth_m', $location->target_depth_m) }}" class="{{ $input }}">
      </div>
      <div>
        <label for="l-edpmb" class="{{ $label }}">EDPMB No.</label>
        <input id="l-edpmb" name="edpmb_no" maxlength="20" value="{{ old('edpmb_no', $location->edpmb_no) }}" class="{{ $input }}">
      </div>
      <div>
        <label for="l-edpmb-date" class="{{ $label }}">EDPMB Date</label>
        <input id="l-edpmb-date" name="edpmb_date" type="date" value="{{ old('edpmb_date', $location->edpmb_date?->format('Y-m-d')) }}" class="{{ $input }}">
        @if ($location->edpmb_date_text && ! $location->edpmb_date)
          <p class="text-[10px] text-amber-600 mt-0.5">Excel had "{{ $location->edpmb_date_text }}" (not a valid date)</p>
        @endif
      </div>

      <div>
        <label for="l-actual" class="{{ $label }}">Actual Drilled Depth</label>
        <input id="l-actual" name="actual_drilled_depth" maxlength="50" value="{{ old('actual_drilled_depth', $location->actual_drilled_depth) }}" placeholder="e.g. 1460 m" class="{{ $input }}">
      </div>
      <div>
        <label for="l-eds" class="{{ $label }}">Expected Drilling Start (EDS)</label>
        <input id="l-eds" name="expected_drilling_start" maxlength="50" value="{{ old('expected_drilling_start', $location->expected_drilling_start) }}" placeholder="e.g. June 2027" class="{{ $input }}">
      </div>
      <div>
        <label for="l-edc" class="{{ $label }}">Planned Completion (EDC)</label>
        <input id="l-edc" name="planned_completion" maxlength="50" value="{{ old('planned_completion', $location->planned_completion) }}" placeholder="e.g. July 2027" class="{{ $input }}">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label for="l-targets" class="{{ $label }}">Primary / Secondary Targets</label>
        <textarea id="l-targets" name="targets" rows="2" class="{{ $input }}">{{ old('targets', $location->targets) }}</textarea>
      </div>
      <div>
        <label for="l-findings" class="{{ $label }}">Key Findings</label>
        <textarea id="l-findings" name="key_findings" rows="2" class="{{ $input }}">{{ old('key_findings', $location->key_findings) }}</textarea>
      </div>
      <div>
        <label for="l-studies" class="{{ $label }}">Studies Carried Out</label>
        <textarea id="l-studies" name="studies_carried_out" rows="2" class="{{ $input }}">{{ old('studies_carried_out', $location->studies_carried_out) }}</textarea>
      </div>
      <div>
        <label for="l-current" class="{{ $label }}">Current Status / Remarks</label>
        <textarea id="l-current" name="current_status" rows="2" class="{{ $input }}">{{ old('current_status', $location->current_status) }}</textarea>
      </div>
    </div>

    <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
      <a href="{{ route('locations.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px]">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $editing ? 'Save Changes' : 'Add Location' }}</button>
    </div>
  </form>
@endsection
