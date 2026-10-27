@extends('admin.layouts.master')

@php
  $editing = $role->exists;
  $modules = \App\Support\Permissions::modules();
  $standard = \App\Support\Permissions::standardActions();
  $selected = old('permissions', $selected);
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
@endphp

@section('title', 'Admin')
@section('heading', $editing ? 'Edit Role: '.$role->name : 'Add Role')
@section('subheading', 'Tick what this role is allowed to see and do')

@section('content')
  @include('admin.layouts.admin-tabs')

  <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" id="role-form" class="space-y-3.5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label for="name" class="block font-semibold text-slate-700 mb-0.5 text-[11px]">Role Name *</label>
        <input id="name" name="name" required value="{{ old('name', $role->name) }}" placeholder="e.g. Finance Team" class="{{ $input }}">
      </div>
      <div>
        <label for="description" class="block font-semibold text-slate-700 mb-0.5 text-[11px]">Description</label>
        <input id="description" name="description" value="{{ old('description', $role->description) }}" class="{{ $input }}">
      </div>
    </div>

    <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
      <div class="px-3.5 py-2.5 border-b border-slate-200 flex items-center justify-between text-[11px]">
        <h4 class="font-bold uppercase tracking-wider text-slate-800">Permissions <span class="ml-1 font-semibold normal-case text-slate-500">(<span id="perm-count">0</span> selected)</span></h4>
        <span class="space-x-2">
          <button type="button" onclick="tickAll(true)" class="text-blue-600 font-semibold hover:underline">Select all</button>
          <button type="button" onclick="tickAll(false)" class="text-slate-500 font-semibold hover:underline">Clear</button>
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-[11px]">
          <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
            <tr>
              <th class="py-2.5 px-3">Module</th>
              @foreach ($standard as $action)<th class="py-2.5 px-3 text-center">{{ ucfirst($action) }}</th>@endforeach
              <th class="py-2.5 px-3">Other</th>
              <th class="py-2.5 px-3 text-center">All</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            @foreach ($modules as $module => $config)
              @php $extras = array_diff_key($config['permissions'], array_flip($standard)); @endphp
              <tr class="hover:bg-slate-50" data-module>
                <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $config['label'] }}</td>
                @foreach ($standard as $action)
                  <td class="py-2.5 px-3 text-center">
                    @isset($config['permissions'][$action])
                      <input type="checkbox" name="permissions[]" value="{{ $module }}.{{ $action }}" data-action="{{ $action }}" title="{{ $config['permissions'][$action] }}"
                             class="perm rounded border-slate-300" @checked(in_array("{$module}.{$action}", $selected, true))>
                    @else
                      <span class="text-slate-300">-</span>
                    @endisset
                  </td>
                @endforeach
                <td class="py-2.5 px-3">
                  @forelse ($extras as $action => $text)
                    <label class="flex items-center gap-1.5 py-0.5">
                      <input type="checkbox" name="permissions[]" value="{{ $module }}.{{ $action }}" data-action="{{ $action }}"
                             class="perm rounded border-slate-300" @checked(in_array("{$module}.{$action}", $selected, true))>
                      {{ $text }}
                    </label>
                  @empty
                    <span class="text-slate-300">-</span>
                  @endforelse
                </td>
                <td class="py-2.5 px-3 text-center"><input type="checkbox" class="row-all rounded border-slate-300" title="All {{ $config['label'] }} permissions"></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.roles.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $editing ? 'Save Changes' : 'Create Role' }}</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  var form = document.getElementById('role-form');

  function refreshPerms() {
    form.querySelectorAll('tr[data-module]').forEach(function (row) {
      var boxes = row.querySelectorAll('.perm'), checked = row.querySelectorAll('.perm:checked').length, all = row.querySelector('.row-all');
      all.checked = checked === boxes.length;
      all.indeterminate = checked > 0 && checked < boxes.length;
    });
    document.getElementById('perm-count').textContent = form.querySelectorAll('.perm:checked').length;
  }

  function tickAll(on) { form.querySelectorAll('.perm').forEach(function (p) { p.checked = on; }); refreshPerms(); }

  form.addEventListener('change', function (e) {
    var row = e.target.closest('tr[data-module]');
    if (!row) return;
    if (e.target.classList.contains('row-all')) {
      row.querySelectorAll('.perm').forEach(function (p) { p.checked = e.target.checked; });
    } else {
      // Any permission needs View; unticking View removes the rest of the row
      var view = row.querySelector('.perm[data-action="view"]');
      if (e.target.checked && view) view.checked = true;
      if (!e.target.checked && e.target === view) row.querySelectorAll('.perm').forEach(function (p) { p.checked = false; });
    }
    refreshPerms();
  });

  refreshPerms();
</script>
@endpush
