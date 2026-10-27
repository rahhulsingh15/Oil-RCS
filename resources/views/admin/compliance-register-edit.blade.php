@extends('admin.layouts.master')

@php
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
@endphp

@section('title', 'Admin')
@section('heading', 'Edit Rule '.$item->code)
@section('subheading', $item->head.' · '.$item->obligation)

@section('content')
  @include('admin.layouts.admin-tabs')

  <form method="POST" action="{{ route('admin.compliance-items.update', $item) }}" class="bg-white rounded p-4 border border-oilCardBorder shadow-xs max-w-3xl space-y-3">
    @csrf @method('PUT')

    <p class="text-[11px] text-slate-500 pb-2 border-b border-slate-100">
      Article: <b class="text-slate-700">{{ $item->article ?? '-' }}</b> ·
      Trigger: <b class="text-slate-700">{{ $item->trigger_event ?? '-' }}</b> ·
      Frequency: <b class="text-slate-700">{{ $item->frequency }}</b> ({{ $item->compliance_type }})
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div><label for="responsible_department" class="{{ $label }}">Responsible Department</label><input id="responsible_department" name="responsible_department" value="{{ old('responsible_department', $item->responsible_department) }}" class="{{ $input }}"></div>
      <div><label for="approving_authority" class="{{ $label }}">Approving Authority</label><input id="approving_authority" name="approving_authority" value="{{ old('approving_authority', $item->approving_authority) }}" class="{{ $input }}"></div>
      <div>
        <label for="alert_days_before" class="{{ $label }}">Alert (Days Before)</label>
        <input id="alert_days_before" name="alert_days_before" type="number" min="0" max="730" value="{{ old('alert_days_before', $item->alert_days_before) }}" class="{{ $input }}">
        <p class="text-[10px] text-slate-400 mt-0.5">Also updates the {{ $item->milestones()->count() }} milestones that use this rule.</p>
      </div>
      <div><label for="supporting_documents" class="{{ $label }}">Supporting Documents</label><input id="supporting_documents" name="supporting_documents" value="{{ old('supporting_documents', $item->supporting_documents) }}" class="{{ $input }}"></div>
    </div>
    <div><label for="remarks" class="{{ $label }}">Remarks</label><textarea id="remarks" name="remarks" rows="2" class="{{ $input }}">{{ old('remarks', $item->remarks) }}</textarea></div>

    <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
      <a href="{{ route('admin.compliance-items.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px]">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">Save Rule</button>
    </div>
  </form>
@endsection
