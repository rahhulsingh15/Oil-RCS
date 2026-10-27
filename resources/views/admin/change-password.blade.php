@extends('admin.layouts.master')

@php $input = 'w-full border border-slate-300 rounded px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-blue-500'; @endphp

@section('title', 'Change Password')
@section('heading', 'Change Password')
@section('subheading', 'Min 8 characters with letters and numbers')

@section('content')
  <form method="POST" action="{{ route('profile.password.update') }}" class="bg-white rounded p-4 border border-oilCardBorder shadow-xs max-w-sm space-y-3 text-[11px]">
    @csrf @method('PUT')
    <div>
      <label for="current_password" class="block font-semibold text-slate-700 mb-0.5">Current Password</label>
      <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="{{ $input }}">
    </div>
    <div>
      <label for="password" class="block font-semibold text-slate-700 mb-0.5">New Password</label>
      <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $input }}">
    </div>
    <div>
      <label for="password_confirmation" class="block font-semibold text-slate-700 mb-0.5">Confirm New Password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $input }}">
    </div>
    <div class="pt-2 border-t border-slate-200 flex justify-end">
      <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold">Update Password</button>
    </div>
  </form>
@endsection
