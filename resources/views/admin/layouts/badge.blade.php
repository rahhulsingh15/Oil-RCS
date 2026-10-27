{{-- Status pill with a coloured dot. Usage: @include('admin.layouts.badge', ['status' => 'Compliant']) --}}
@php
  $tone = match ($status) {
      'Compliant', 'Valid', 'Completed', 'Active', 'Drilled', 'Low' => 'green',
      'Due Soon', 'Pending', 'In Progress', 'Medium', 'Under drilling' => 'amber',
      'Overdue', 'At Risk', 'Expired', 'Inactive', 'High' => 'red',
      default => 'gray',
  };
  $class = [
      'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
      'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
      'red' => 'bg-red-50 text-red-700 border-red-200',
      'gray' => 'bg-slate-100 text-slate-600 border-slate-200',
  ][$tone];
@endphp
<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded text-[10px] font-bold border {{ $class }}"><span class="status-dot status-dot-{{ $tone }}"></span>{{ $status }}</span>
