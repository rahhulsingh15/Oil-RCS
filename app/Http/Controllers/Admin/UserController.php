<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('role')
            ->withCount('blocks')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role_id', $request->integer('role')))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->input('status') === 'nodal', fn ($q) => $q->where('is_nodal_officer', true))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        // ?nodal=1 ("Add Nodal Officer" button) pre-ticks the Nodal Officer box
        $user = new User(['is_active' => true, 'is_nodal_officer' => $request->boolean('nodal')]);

        return view('admin.user-form', $this->formData($user, $request->user()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->guardRoleAssignment($request->user(), (int) $data['role_id']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'designation' => $data['designation'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_nodal_officer' => $data['is_nodal_officer'],
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active'],
                'password' => $data['password'],
            ]);
            $user->blocks()->sync($data['blocks'] ?? []);

            AuditLog::record('created', $user, [], $this->auditValues($user), "User {$user->email} created");

            return $user;
        });

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} created.");
    }

    public function edit(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);

        return view('admin.user-form', $this->formData($user, $request->user()));
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->guardTarget($actor, $user);

        $data = $this->validated($request, $user);
        $isSelf = $actor->is($user);
        $this->guardRoleAssignment($actor, (int) $data['role_id']);

        if ($isSelf && (int) $data['role_id'] !== $user->role_id) {
            return back()->withInput()->with('error', 'You cannot change your own role.');
        }
        if ($isSelf && ! $data['is_active']) {
            return back()->withInput()->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->wouldRemoveLastSuperAdmin($user, (int) $data['role_id'], $data['is_active'])) {
            return back()->withInput()->with('error', 'At least one active Super Admin is required.');
        }

        DB::transaction(function () use ($user, $data) {
            $old = $this->auditValues($user);

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'designation' => $data['designation'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_nodal_officer' => $data['is_nodal_officer'],
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active'],
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $passwordChanged = $user->isDirty('password');
            $user->save();
            $user->blocks()->sync($data['blocks'] ?? []);

            $new = $this->auditValues($user->fresh());
            [$oldChanged, $newChanged] = $this->diff($old, $new);
            if ($passwordChanged) {
                $newChanged['password'] = '(changed)';
            }

            if ($newChanged) {
                AuditLog::record('updated', $user, $oldChanged, $newChanged, "User {$user->email} updated");
            }
        });

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        $this->guardTarget($actor, $user);

        if ($actor->is($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($this->wouldRemoveLastSuperAdmin($user, null, false)) {
            return back()->with('error', 'At least one active Super Admin is required.');
        }

        AuditLog::record('deleted', $user, $this->auditValues($user), [], "User {$user->email} deleted");
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} deleted.");
    }

    private function formData(User $user, User $actor): array
    {
        $roles = Role::with('permissions:id,key')->orderByDesc('is_system')->orderBy('name')->get()
            ->reject(fn (Role $role) => $role->isSuperAdmin() && ! $actor->isSuperAdmin() && $user->role_id !== $role->id);

        return [
            'user' => $user,
            'roles' => $roles,
            // Roles that see every block: block assignment does not apply to them
            'allBlockRoleIds' => $roles->filter(fn (Role $r) => $r->isSuperAdmin() || $r->permissions->contains('key', 'blocks.view_all'))
                ->pluck('id')->values(),
            'blocks' => Block::orderBy('code')->get(['id', 'code', 'basin', 'status']),
            'assigned' => $user->exists ? $user->blocks()->pluck('blocks.id')->all() : [],
            'isSelf' => $actor->is($user),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'designation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'blocks' => ['array'],
            'blocks.*' => ['integer', 'exists:blocks,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_nodal_officer'] = $request->boolean('is_nodal_officer');

        return $data;
    }

    /** Only Super Admins may manage Super Admin accounts. */
    private function guardTarget(User $actor, User $target): void
    {
        abort_if($target->isSuperAdmin() && ! $actor->isSuperAdmin(), 403, 'Only a Super Admin can manage Super Admin accounts.');
    }

    /** Only Super Admins may give someone the Super Admin role. */
    private function guardRoleAssignment(User $actor, int $roleId): void
    {
        $role = Role::find($roleId);
        abort_if($role?->isSuperAdmin() && ! $actor->isSuperAdmin(), 403, 'Only a Super Admin can assign the Super Admin role.');
    }

    private function wouldRemoveLastSuperAdmin(User $user, ?int $newRoleId, bool $staysActive): bool
    {
        if (! $user->isSuperAdmin() || ! $user->is_active) {
            return false;
        }

        $stillSuperAdmin = $newRoleId !== null && Role::find($newRoleId)?->isSuperAdmin() && $staysActive;
        if ($stillSuperAdmin) {
            return false;
        }

        $others = User::where('id', '!=', $user->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))
            ->exists();

        return ! $others;
    }

    private function auditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $user->designation,
            'phone' => $user->phone,
            'nodal_officer' => (bool) $user->is_nodal_officer,
            'role' => $user->role?->name,
            'is_active' => (bool) $user->is_active,
            'blocks' => $user->blocks()->pluck('code')->all(),
        ];
    }

    /** Only the fields that changed. */
    private function diff(array $old, array $new): array
    {
        $oldChanged = [];
        $newChanged = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $oldChanged[$key] = $old[$key] ?? null;
                $newChanged[$key] = $value;
            }
        }

        return [$oldChanged, $newChanged];
    }
}
