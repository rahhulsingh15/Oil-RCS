@extends('admin.layouts.master')

@section('title', 'Admin')
@section('heading', 'Roles & Permissions')
@section('subheading', 'Create roles and choose what each role can see and do')
@section('filters')
  @can('roles.create')
    <a href="{{ route('admin.roles.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]"><i class="fa-solid fa-plus mr-1"></i> Add Role</a>
  @endcan
@endsection

@section('content')
  @include('admin.layouts.admin-tabs')

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Role</th>
            <th class="py-2.5 px-3">Description</th>
            <th class="py-2.5 px-3">Permissions</th>
            <th class="py-2.5 px-3 text-center">Users</th>
            <th class="py-2.5 px-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @foreach ($roles as $role)
            <tr class="hover:bg-slate-50">
              <td class="py-2.5 px-3 font-bold text-slate-900">
                {{ $role->name }}
                @if ($role->is_system)<span class="ml-1 px-1.5 py-0.5 rounded bg-slate-900 text-white text-[8px] uppercase">System</span>@endif
              </td>
              <td class="py-2.5 px-3 text-slate-600">{{ $role->description ?: '-' }}</td>
              <td class="py-2.5 px-3">
                @if ($role->is_system)
                  <span class="text-emerald-700 font-bold">All permissions</span>
                @else
                  {{ $role->permissions_count }} / {{ $totalPermissions }}
                @endif
              </td>
              <td class="py-2.5 px-3 text-center">
                @can('users.view')
                  <a href="{{ route('admin.users.index', ['role' => $role->id]) }}" class="text-blue-600 font-bold hover:underline">{{ $role->users_count }}</a>
                @else {{ $role->users_count }} @endcan
              </td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                @if ($role->is_system)
                  <span class="text-[10px] text-slate-400">Locked</span>
                @else
                  @can('roles.edit')
                    <a href="{{ route('admin.roles.edit', $role) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit permissions"><i class="fa-solid fa-pen-to-square"></i></a>
                  @endcan
                  @can('roles.delete')
                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" data-confirm="Delete role {{ $role->name }}?">
                      @csrf @method('DELETE')
                      <button type="submit" class="px-1.5 text-slate-400 hover:text-red-600 disabled:opacity-30" title="{{ $role->users_count ? 'Has users, cannot delete' : 'Delete' }}" @disabled($role->users_count)><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                  @endcan
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-[10px] text-slate-400">Users whose role does not have "See all blocks" only see the blocks assigned to them on the user page.</p>
@endsection
