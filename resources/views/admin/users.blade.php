@extends('admin.layouts.master')

@section('title', 'Admin')
@section('heading', 'Users')
@section('subheading', 'Portal accounts, their role and the blocks they can see')
@section('filters')
  <form method="GET" class="flex flex-wrap items-center gap-2.5">
    <select name="role" onchange="this.form.submit()" class="text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium">
      <option value="">All Roles</option>
      @foreach ($roles as $role)
        <option value="{{ $role->id }}" @selected(request('role') == $role->id)>{{ $role->name }}</option>
      @endforeach
    </select>
    <select name="status" onchange="this.form.submit()" class="text-[11px] border border-slate-300 rounded px-2.5 py-1 bg-white text-slate-700 font-medium">
      <option value="">All Status</option>
      <option value="active" @selected(request('status') === 'active')>Active</option>
      <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
      <option value="nodal" @selected(request('status') === 'nodal')>Nodal Officers</option>
    </select>
    <div class="relative w-40">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name / email..." class="w-full text-[11px] pl-6 pr-2 py-1 border border-slate-300 rounded bg-white focus:outline-none focus:border-blue-500">
      <i class="fa-solid fa-magnifying-glass absolute left-2 top-2 text-slate-400 text-[10px]"></i>
    </div>
    @can('users.create')
      <a href="{{ route('admin.users.create') }}" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]"><i class="fa-solid fa-plus mr-1"></i> Add User</a>
      <a href="{{ route('admin.users.create', ['nodal' => 1]) }}" class="px-3 py-1 border border-blue-600 text-blue-700 hover:bg-blue-50 rounded font-bold text-[11px] bg-white"><i class="fa-solid fa-user-tie mr-1"></i> Add Nodal Officer</a>
    @endcan
  </form>
@endsection

@section('content')
  @include('admin.layouts.admin-tabs')

  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Name</th>
            <th class="py-2.5 px-3">Email</th>
            <th class="py-2.5 px-3">Role</th>
            <th class="py-2.5 px-3">Blocks</th>
            <th class="py-2.5 px-3">Status</th>
            <th class="py-2.5 px-3">Last Login</th>
            <th class="py-2.5 px-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($users as $user)
            @php $locked = $user->isSuperAdmin() && ! auth()->user()->isSuperAdmin(); $self = auth()->user()->is($user); @endphp
            <tr class="hover:bg-slate-50">
              <td class="py-2.5 px-3 font-bold text-slate-900">{{ $user->name }} @if ($self) <span class="text-[9px] text-slate-400 font-medium">(you)</span> @endif
                @if ($user->is_nodal_officer)<span class="ml-1 px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-bold uppercase">Nodal Officer</span>@endif
                @if ($user->designation)<span class="block text-[10px] font-normal text-slate-500">{{ $user->designation }}</span>@endif
              </td>
              <td class="py-2.5 px-3">{{ $user->email }}@if ($user->phone)<span class="block text-[10px] text-slate-500"><i class="fa-solid fa-phone text-[8px] mr-0.5"></i> {{ $user->phone }}</span>@endif</td>
              <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded bg-slate-100 font-semibold text-[10px]">{{ $user->roleLabel() }}</span></td>
              <td class="py-2.5 px-3">
                @if ($user->canSeeAllBlocks()) <span class="text-emerald-700 font-semibold">All blocks</span>
                @elseif ($user->blocks_count) {{ $user->blocks_count }} assigned
                @else <span class="text-amber-600 font-semibold">None assigned</span> @endif
              </td>
              <td class="py-2.5 px-3">@include('admin.layouts.badge', ['status' => $user->is_active ? 'Active' : 'Inactive'])</td>
              <td class="py-2.5 px-3 whitespace-nowrap text-slate-500">{{ $user->last_login_at?->format('d.m.Y H:i') ?? 'Never' }}</td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                @if ($locked)
                  <span class="text-[10px] text-slate-400" title="Only a Super Admin can manage this account">Locked</span>
                @else
                  @can('users.edit')
                    <a href="{{ route('admin.users.edit', $user) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                  @endcan
                  @can('users.delete')
                    @unless ($self)
                      <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" data-confirm="Delete user {{ $user->name }}?">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-1.5 text-slate-400 hover:text-red-600" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                      </form>
                    @endunless
                  @endcan
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="py-6 text-center text-slate-400">No users found</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
      <span>{{ $users->total() }} users</span>
      {{ $users->links() }}
    </div>
  </div>
@endsection
