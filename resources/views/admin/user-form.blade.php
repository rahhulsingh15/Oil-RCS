@extends('admin.layouts.master')

@php
  $editing = $user->exists;
  $selectedBlocks = array_map('intval', old('blocks', $assigned));
  $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500';
  $label = 'block font-semibold text-slate-700 mb-0.5 text-[11px]';
@endphp

@section('title', 'Admin')
@section('heading', $editing ? 'Edit User: '.$user->name : 'Add User')
@section('subheading', 'Account details, role and block access')

@section('content')
  @include('admin.layouts.admin-tabs')

  <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" autocomplete="off" class="space-y-3.5">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3.5">
      <!-- Account -->
      <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs space-y-3">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100">Account Details</h4>
        <div>
          <label for="name" class="{{ $label }}">Full Name *</label>
          <input id="name" name="name" required value="{{ old('name', $user->name) }}" class="{{ $input }}">
        </div>
        <div>
          <label for="email" class="{{ $label }}">Email *</label>
          <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}" class="{{ $input }}">
        </div>
        <div>
          <label for="designation" class="{{ $label }}">Designation / Department</label>
          <input id="designation" name="designation" value="{{ old('designation', $user->designation) }}" class="{{ $input }}">
        </div>
        <div>
          <label for="phone" class="{{ $label }}">Mobile Number</label>
          <input id="phone" name="phone" type="tel" maxlength="20" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 9876543210" class="{{ $input }}">
        </div>

        <label class="flex items-start gap-2 p-2 rounded border border-blue-200 bg-blue-50 text-[11px] cursor-pointer">
          <input type="hidden" name="is_nodal_officer" value="0">
          <input type="checkbox" name="is_nodal_officer" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('is_nodal_officer', $user->is_nodal_officer))>
          <span>
            <span class="block font-bold text-slate-800">Nodal Officer</span>
            <span class="block text-[10px] text-slate-500">Shown as the nodal officer on the Block Detail page of the blocks ticked below. Gets the deadline notifications of those blocks.</span>
          </span>
        </label>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label for="password" class="{{ $label }}">{{ $editing ? 'New Password' : 'Password *' }}</label>
            <input id="password" name="password" type="password" @unless ($editing) required @endunless autocomplete="new-password" class="{{ $input }}">
          </div>
          <div>
            <label for="password_confirmation" class="{{ $label }}">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $input }}">
          </div>
        </div>
        <p class="text-[10px] text-slate-500">Min 8 characters with letters and numbers. @if ($editing) Leave empty to keep the current password. @endif</p>

        <label class="flex items-center gap-2 text-[11px] font-semibold text-slate-700">
          <input type="hidden" name="is_active" value="{{ $isSelf ? 1 : 0 }}">
          <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $user->is_active)) @disabled($isSelf)>
          Active (inactive users cannot sign in) @if ($isSelf) <span class="font-normal text-slate-400">- you cannot deactivate yourself</span> @endif
        </label>
      </div>

      <!-- Role -->
      <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs space-y-2">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800 pb-1 border-b border-slate-100">Role *</h4>
        @if ($isSelf)<p class="text-[10px] text-amber-700">You cannot change your own role.</p>@endif
        @foreach ($roles as $role)
          <label class="flex items-start gap-2 p-2 rounded border border-slate-200 hover:bg-slate-50 cursor-pointer text-[11px]">
            <input type="radio" name="role_id" value="{{ $role->id }}" required class="role-radio mt-0.5"
                   @checked((int) old('role_id', $user->role_id) === $role->id) @disabled($isSelf && $user->role_id !== $role->id)>
            <span>
              <span class="block font-bold text-slate-800">{{ $role->name }}</span>
              <span class="block text-[10px] text-slate-500">{{ $role->description ?: 'No description' }} · {{ $role->isSuperAdmin() ? 'All permissions' : $role->permissions->count().' permissions' }}</span>
            </span>
          </label>
        @endforeach
      </div>
    </div>

    <!-- Block access -->
    <div class="bg-white rounded p-4 border border-oilCardBorder shadow-xs">
      <div class="flex items-center justify-between pb-1 border-b border-slate-100 mb-2">
        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-800">Block Access</h4>
        <span id="block-tools" class="text-[10px] space-x-2">
          <button type="button" class="text-blue-600 font-semibold hover:underline" onclick="tickBlocks(true)">Select all</button>
          <button type="button" class="text-slate-500 font-semibold hover:underline" onclick="tickBlocks(false)">Clear</button>
        </span>
      </div>
      <p id="all-blocks-note" class="hidden mb-2 text-[11px] text-emerald-700"><i class="fa-solid fa-circle-info mr-1"></i>This role can see all blocks, no assignment needed.</p>
      <div id="block-list" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
        @foreach ($blocks as $block)
          <label class="flex items-center gap-2 p-2 rounded border border-slate-200 hover:bg-slate-50 cursor-pointer text-[11px]">
            <input type="checkbox" name="blocks[]" value="{{ $block->id }}" class="block-box rounded border-slate-300" @checked(in_array($block->id, $selectedBlocks, true))>
            <span><span class="block font-bold text-slate-800">{{ $block->code }}</span><span class="text-[10px] text-slate-500">{{ $block->basin }}</span></span>
          </label>
        @endforeach
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.users.index') }}" class="px-3 py-1.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium text-[11px] bg-white">Cancel</a>
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px]">{{ $editing ? 'Save Changes' : 'Create User' }}</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  var allBlockRoles = @json($allBlockRoleIds);
  function tickBlocks(on) { document.querySelectorAll('.block-box').forEach(function (b) { b.checked = on; }); }
  function refreshBlocks() {
    var picked = document.querySelector('.role-radio:checked');
    var all = picked && allBlockRoles.indexOf(parseInt(picked.value, 10)) !== -1;
    document.getElementById('all-blocks-note').classList.toggle('hidden', !all);
    document.getElementById('block-list').classList.toggle('opacity-40', all);
    document.getElementById('block-list').classList.toggle('pointer-events-none', all);
    document.getElementById('block-tools').classList.toggle('invisible', all);
  }
  document.querySelectorAll('.role-radio').forEach(function (r) { r.addEventListener('change', refreshBlocks); });
  refreshBlocks();
</script>
@endpush
