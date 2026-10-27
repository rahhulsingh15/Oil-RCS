<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

/**
 * Reads config/permissions.php and keeps the permissions table in step with it.
 */
class Permissions
{
    /** @var array<string, true>|null */
    private static ?array $keys = null;

    /** All permission keys defined in config, e.g. ["dashboard.view", ...]. */
    public static function keys(): array
    {
        return array_keys(self::keyMap());
    }

    public static function exists(string $key): bool
    {
        return isset(self::keyMap()[$key]);
    }

    /**
     * Modules for the permission matrix.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return config('permissions.modules', []);
    }

    /** @return list<string> */
    public static function standardActions(): array
    {
        return config('permissions.standard_actions', ['view', 'create', 'edit', 'delete']);
    }

    /**
     * Create missing permissions, update labels, remove ones no longer in config,
     * and make sure the built-in roles exist.
     *
     * @return array{created: int, removed: int}
     */
    public static function sync(): array
    {
        $order = 0;
        $newKeys = [];

        foreach (self::modules() as $module => $config) {
            foreach ($config['permissions'] as $action => $label) {
                $permission = Permission::updateOrCreate(
                    ['key' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'label' => $label, 'sort_order' => $order++]
                );
                if ($permission->wasRecentlyCreated) {
                    $newKeys[] = $permission->key;
                }
            }
        }
        $created = count($newKeys);

        $removed = Permission::whereNotIn('key', self::keys())->delete();

        Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], [
            'name' => 'Super Admin',
            'is_system' => true,
            'description' => 'Full access to everything. Cannot be edited or deleted.',
        ]);

        // Built-in roles get their default permissions only when first created,
        // so later changes made from the portal are not overwritten.
        foreach (config('permissions.defaults', []) as $slug => $keys) {
            $role = Role::firstOrCreate(['slug' => $slug], [
                'name' => str($slug)->replace('-', ' ')->title(),
            ]);

            if ($role->wasRecentlyCreated || $role->permissions()->doesntExist()) {
                $role->syncPermissionKeys($keys);
            } elseif ($added = array_intersect($keys, $newKeys)) {
                // A new feature's permission reaches the built-in role; other edits stay as they are
                $role->permissions()->syncWithoutDetaching(Permission::whereIn('key', $added)->pluck('id'));
            }
        }

        Role::pluck('id')->each(fn ($id) => Role::forgetCache($id));
        self::$keys = null;

        return ['created' => $created, 'removed' => $removed];
    }

    /** @return array<string, true> */
    private static function keyMap(): array
    {
        if (self::$keys === null) {
            self::$keys = [];
            foreach (self::modules() as $module => $config) {
                foreach (array_keys($config['permissions']) as $action) {
                    self::$keys["{$module}.{$action}"] = true;
                }
            }
        }

        return self::$keys;
    }
}
