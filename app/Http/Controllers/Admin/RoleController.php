<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get();

        return view('admin.roles', [
            'roles' => $roles,
            'totalPermissions' => count(Permissions::keys()),
        ]);
    }

    public function create()
    {
        return view('admin.role-form', [
            'role' => new Role,
            'selected' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
            ]);
            $role->syncPermissionKeys($data['permissions'] ?? []);

            AuditLog::record('created', $role, [], [
                'name' => $role->name,
                'permissions' => $data['permissions'] ?? [],
            ], "Role \"{$role->name}\" created");

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')->with('error', 'The Super Admin role always has every permission and cannot be edited.');
        }

        return view('admin.role-form', [
            'role' => $role,
            'selected' => $role->permissions()->pluck('key')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->is_system, 403, 'The Super Admin role cannot be edited.');

        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $old = ['name' => $role->name, 'description' => $role->description];

            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            $changes = $role->syncPermissionKeys($data['permissions'] ?? []);

            AuditLog::record('updated', $role, $old + ['permissions_removed' => $changes['removed']], [
                'name' => $role->name,
                'description' => $role->description,
                'permissions_added' => $changes['added'],
            ], "Role \"{$role->name}\" updated");
        });

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', "Role \"{$role->name}\" still has users. Move them to another role first.");
        }

        AuditLog::record('deleted', $role, ['name' => $role->name, 'permissions' => $role->permissionKeys()], [],
            "Role \"{$role->name}\" deleted");
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" deleted.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
