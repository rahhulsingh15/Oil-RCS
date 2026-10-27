<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'designation',
        'phone',
        'is_nodal_officer',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Pages tried, in order, to find where a user lands after login. */
    private const HOME_PAGES = [
        'dashboard.view' => 'dashboard',
        'milestones.view' => 'compliance.index',
        'blocks.view' => 'blocks.index',
        'clearances.view' => 'clearances.index',
        'reports.view' => 'reports.index',
        'users.view' => 'admin.users.index',
        'roles.view' => 'admin.roles.index',
    ];

    /** @var list<int>|null */
    private ?array $blockIdCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_nodal_officer' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Blocks assigned to this user (used when the role cannot see all blocks). */
    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class)->orderBy('code');
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->isSuperAdmin();
    }

    public function hasPermission(string $key): bool
    {
        if (! $this->is_active || ! $this->role) {
            return false;
        }

        return $this->isSuperAdmin() || in_array($key, $this->role->permissionKeys(), true);
    }

    public function canSeeAllBlocks(): bool
    {
        return $this->hasPermission('blocks.view_all');
    }

    /** IDs of blocks this user may see, or null when they may see every block. */
    public function visibleBlockIds(): ?array
    {
        if ($this->canSeeAllBlocks()) {
            return null;
        }

        return $this->blockIdCache ??= $this->blocks()->pluck('blocks.id')->all();
    }

    public function canAccessBlock(Block|int $block): bool
    {
        $ids = $this->visibleBlockIds();
        $id = $block instanceof Block ? $block->id : $block;

        return $ids === null || in_array($id, $ids, true);
    }

    public function roleLabel(): string
    {
        return $this->role?->name ?? 'No role';
    }

    /** First page this user is allowed to open. */
    public function homeRoute(): ?string
    {
        foreach (self::HOME_PAGES as $permission => $route) {
            if ($this->hasPermission($permission)) {
                return $route;
            }
        }

        return null;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
