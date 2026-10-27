{{-- Tabs shown on every Admin page --}}
@php
  $tabs = [
      ['Users', 'admin.users.index', 'users.view', 'admin.users.*'],
      ['Roles & Permissions', 'admin.roles.index', 'roles.view', 'admin.roles.*'],
      ['Compliance Register', 'admin.compliance-items.index', 'compliance_register.view', 'admin.compliance-items.*'],
      ['Audit Log', 'admin.audit-logs.index', 'audit_logs.view', 'admin.audit-logs.*'],
  ];
@endphp
<div class="flex items-center gap-1 border-b border-slate-200 text-[11px] font-semibold text-slate-600 overflow-x-auto">
  @foreach ($tabs as [$label, $route, $permission, $pattern])
    @can($permission)
      <a href="{{ route($route) }}" class="px-3 py-1.5 whitespace-nowrap border-b-2 {{ request()->routeIs($pattern) ? 'border-blue-600 text-blue-900 font-bold' : 'border-transparent hover:text-slate-900' }}">{{ $label }}</a>
    @endcan
  @endforeach
</div>
