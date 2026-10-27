<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Block $blockA;
    private Block $blockB;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::sync();

        $this->blockA = $this->makeBlock('MN-ONHP-2018/1');
        $this->blockB = $this->makeBlock('KG-UDWHP-2023/1');
    }

    // ---------------------------------------------------------------- helpers

    private function makeBlock(string $code): Block
    {
        $block = Block::create(['code' => $code, 'basin' => 'Mahanadi', 'status' => 'Active', 'current_stage' => 'Exploration']);

        Milestone::create([
            'block_id' => $block->id,
            'source_key' => "test:{$code}",
            'category' => 'Bank Guarantee',
            'title' => "BG renewal for {$code}",
            'due_date' => today()->addDays(10),
            'alert_days_before' => 30,
        ]);

        return $block;
    }

    private function role(string $name, array $permissions): Role
    {
        $role = Role::create(['name' => $name, 'slug' => str($name)->slug()]);
        $role->syncPermissionKeys($permissions);

        return $role;
    }

    private function user(Role|string $role, array $blocks = [], array $attributes = []): User
    {
        $role = is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true] + $attributes);
        $user->blocks()->sync(array_map(fn (Block $b) => $b->id, $blocks));

        return $user;
    }

    private function superAdmin(): User
    {
        return $this->user(Role::SUPER_ADMIN);
    }

    private function representative(array $blocks = []): User
    {
        return $this->user('company-representative', $blocks);
    }

    // ---------------------------------------------------------------- login & pages

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_login_lands_on_the_first_allowed_page(): void
    {
        $user = $this->representative([$this->blockA]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_user_whose_role_has_no_permissions_is_signed_out(): void
    {
        $user = $this->user($this->role('Nothing', []));

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_can_open_every_page(): void
    {
        $admin = $this->superAdmin();
        $other = $this->representative();
        $role = Role::where('slug', 'company-representative')->first();
        $item = \App\Models\ComplianceItem::create(['code' => 'CA-01', 'head' => 'Contract', 'obligation' => 'Bank Guarantees']);

        $this->actingAs($admin);
        foreach ([
            route('dashboard'), route('blocks.index'), route('blocks.show', $this->blockB),
            route('compliance.index'), route('clearances.index'), route('reports.index'), route('reports.export'),
            route('cwp.index'), route('investment.index'), route('locations.index'),
            route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $other),
            route('admin.roles.index'), route('admin.roles.create'), route('admin.roles.edit', $role),
            route('admin.compliance-items.index'), route('admin.compliance-items.edit', $item),
            route('admin.audit-logs.index'), route('profile.password'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_representative_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->representative([$this->blockA]));

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Roles &amp; Permissions', false);
        foreach (['admin.users.index', 'admin.users.create', 'admin.roles.index', 'admin.roles.create',
            'admin.audit-logs.index', 'admin.compliance-items.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('admin.roles.store'), ['name' => 'Hacker', 'permissions' => Permissions::keys()])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Hacker']);
    }

    // ---------------------------------------------------------------- block-level data access

    public function test_representative_only_sees_assigned_blocks(): void
    {
        $this->actingAs($this->representative([$this->blockA]));

        $this->get(route('blocks.index'))->assertOk()->assertSee('MN-ONHP-2018/1')->assertDontSee('KG-UDWHP-2023/1');
        $this->get(route('blocks.show', $this->blockA))->assertOk();
        $this->get(route('blocks.show', $this->blockB))->assertForbidden();

        $this->get(route('compliance.index'))->assertSee('BG renewal for MN-ONHP-2018/1')->assertDontSee('BG renewal for KG-UDWHP-2023/1');
        $this->get(route('compliance.index', ['block' => $this->blockB->id]))->assertDontSee('BG renewal for KG-UDWHP-2023/1');

        $csv = $this->get(route('reports.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('MN-ONHP-2018/1', $csv);
        $this->assertStringNotContainsString('KG-UDWHP-2023/1', $csv);
    }

    public function test_view_all_blocks_permission_shows_every_block(): void
    {
        $role = $this->role('Auditor', ['blocks.view', 'blocks.view_all', 'milestones.view']);
        $this->actingAs($this->user($role));

        $this->get(route('blocks.index'))->assertSee('MN-ONHP-2018/1')->assertSee('KG-UDWHP-2023/1');
        $this->get(route('blocks.show', $this->blockB))->assertOk();
    }

    public function test_page_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->user($this->role('Blocks only', ['blocks.view']), [$this->blockA]));

        $this->get(route('blocks.index'))->assertOk();
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('compliance.index'))->assertForbidden();
        $this->get(route('reports.export'))->assertForbidden();
    }

    // ---------------------------------------------------------------- role management

    public function test_admin_creates_role_and_permissions_take_effect_immediately(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.roles.store'), ['name' => 'Finance Team', 'permissions' => ['dashboard.view', 'reports.view']])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Finance Team')->firstOrFail();
        $member = $this->user($role, [$this->blockA]);

        $this->actingAs($member)->get(route('reports.index'))->assertOk();

        $this->actingAs($this->superAdmin())
            ->put(route('admin.roles.update', $role), ['name' => 'Finance Team', 'permissions' => ['dashboard.view']])
            ->assertRedirect();

        $this->actingAs($member->fresh())->get(route('reports.index'))->assertForbidden();
        $this->assertTrue(AuditLog::where('auditable_type', $role->getMorphClass())->where('event', 'updated')->exists());
    }

    public function test_unknown_permission_keys_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.roles.store'), ['name' => 'Bad', 'permissions' => ['everything.forever']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_super_admin_role_is_locked(): void
    {
        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        $this->actingAs($this->superAdmin());

        $this->put(route('admin.roles.update', $role), ['name' => 'Renamed'])->assertForbidden();
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['slug' => Role::SUPER_ADMIN, 'name' => 'Super Admin']);
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $role = $this->role('Busy', ['dashboard.view']);
        $this->user($role);

        $this->actingAs($this->superAdmin())->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    // ---------------------------------------------------------------- user management

    public function test_admin_creates_user_with_role_and_blocks(): void
    {
        $role = Role::where('slug', 'company-representative')->first();

        $this->actingAs($this->superAdmin())->post(route('admin.users.store'), [
            'name' => 'New Rep', 'email' => 'new.rep@example.com', 'role_id' => $role->id,
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'is_active' => '1', 'blocks' => [$this->blockB->id],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new.rep@example.com')->firstOrFail();
        $this->assertSame($role->id, $user->role_id);
        $this->assertSame([$this->blockB->id], $user->blocks()->pluck('blocks.id')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $user->id]);
    }

    public function test_user_manager_cannot_create_or_edit_super_admins(): void
    {
        $manager = $this->user($this->role('User Manager', ['users.view', 'users.create', 'users.edit']));
        $superRole = Role::where('slug', Role::SUPER_ADMIN)->first();
        $admin = $this->superAdmin();

        $this->actingAs($manager);
        $this->post(route('admin.users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role_id' => $superRole->id,
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'is_active' => '1',
        ])->assertForbidden();
        $this->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $manager), [
            'name' => $manager->name, 'email' => $manager->email, 'role_id' => $superRole->id, 'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
        $this->assertFalse($manager->fresh()->isSuperAdmin());
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->superAdmin();
        $rep = Role::where('slug', 'company-representative')->first();
        $this->actingAs($admin);

        $this->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => $rep->id, 'is_active' => '1',
        ])->assertSessionHas('error');
        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => $admin->role_id, 'is_active' => '0',
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->representative([$this->blockA]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
