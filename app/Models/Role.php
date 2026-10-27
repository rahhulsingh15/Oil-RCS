<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    public const SUPER_ADMIN = 'super-admin';

    protected $guarded = ['id'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }

    /** Permission keys of this role, cached until the role's permissions change. */
    public function permissionKeys(): array
    {
        return Cache::rememberForever(self::cacheKey($this->id), fn () => $this->permissions()->pluck('key')->all());
    }

    /**
     * Replace this role's permissions.
     *
     * @param  list<string>  $keys
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncPermissionKeys(array $keys): array
    {
        $before = $this->permissions()->pluck('key')->all();
        $ids = Permission::whereIn('key', $keys)->pluck('id', 'key');

        $this->permissions()->sync($ids->values()->all());
        self::forgetCache($this->id);

        $after = $ids->keys()->all();

        return [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ];
    }

    public static function cacheKey(int $roleId): string
    {
        return "role:{$roleId}:permissions";
    }

    public static function forgetCache(int $roleId): void
    {
        Cache::forget(self::cacheKey($roleId));
    }

    protected static function booted(): void
    {
        static::deleted(fn (Role $role) => self::forgetCache($role->id));
    }
}
