<?php

namespace App\Providers;

use App\Models\Block;
use App\Models\User;
use App\Notifications\DeadlineAlert;
use App\Services\ComplianceSummary;
use App\Support\Permissions;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, so pages and the header share the same query
        $this->app->scoped(ComplianceSummary::class);
    }

    public function boot(): void
    {
        // Every key in config/permissions.php becomes a Gate ability,
        // so @can('users.edit') and ->can('users.edit') work everywhere.
        // Other abilities (e.g. "view" on a Block) fall through to the policies.
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            if (Permissions::exists($ability)) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        Paginator::defaultView('admin.layouts.pagination');

        // Bell icon: the user's own deadline notifications (in the portal only, no email)
        View::composer('admin.layouts.header', function ($view) {
            $user = auth()->user();
            $can = $user?->can('milestones.view');
            $view->with([
                'bellUnread' => $can ? $user->unreadNotifications()->where('type', DeadlineAlert::class)->count() : 0,
                'bellItems' => $can ? $user->notifications()->where('type', DeadlineAlert::class)
                    ->orderByRaw('read_at IS NOT NULL')->latest()->take(8)->get() : collect(),
            ]);
        });

        // Dropdowns of the top filter bar, limited to the user's blocks
        View::composer('admin.layouts.filters', function ($view) {
            $blocks = Block::visibleTo(auth()->user())->with('timeline:id,block_id,current_phase')->orderBy('code')->get();

            $phases = $blocks->map(fn (Block $b) => $b->isRelinquished() ? 'Relinquished' : $b->timeline?->current_phase)
                ->filter()->unique()->sort()->values()->all();

            $view->with([
                'filterBasins' => $blocks->pluck('basin')->filter()->unique()->sort()->values(),
                'filterBlocks' => $blocks,
                'filterPhases' => $phases,
            ]);
        });
    }
}
