<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Block;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DeadlineAlert;
use App\Services\AlertService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Block $mine;
    private Block $other;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::sync();
        $this->mine = Block::create(['code' => 'MN-ONHP-2018/1', 'basin' => 'Mahanadi', 'status' => 'Active']);
        $this->other = Block::create(['code' => 'KG-UDWHP-2023/1', 'basin' => 'Krishna Godavari', 'status' => 'Active']);
    }

    private function milestone(Block $block, int $daysLeft, string $key, int $alertDays = 30): Milestone
    {
        return Milestone::create([
            'block_id' => $block->id, 'source_key' => $key, 'category' => 'Bank Guarantee',
            'title' => "Test {$key}", 'due_date' => today()->addDays($daysLeft), 'alert_days_before' => $alertDays,
        ]);
    }

    private function rep(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('slug', 'company-representative')->value('id'), 'is_active' => true]);
        $user->blocks()->sync([$this->mine->id]);

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'), 'is_active' => true]);
    }

    private function check(): array
    {
        return app(AlertService::class)->run();
    }

    public function test_upcoming_and_overdue_are_notified_once_and_only_in_the_portal(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->milestone($this->mine, 25, 'bg:soon');       // inside 30-day window -> upcoming (stage 1)
        $this->milestone($this->mine, -5, 'bg:late');       // missed -> overdue
        $this->milestone($this->mine, 200, 'bg:far');       // far away -> nothing

        $first = $this->check();
        $this->assertSame(['upcoming' => 1, 'reminder' => 0, 'overdue' => 1, 'notified' => 2], $first);
        $this->assertSame(2, $admin->notifications()->count());

        // running again (next morning) does not repeat them
        $this->assertSame(['upcoming' => 0, 'reminder' => 0, 'overdue' => 0, 'notified' => 0], $this->check());
        $this->assertSame(2, $admin->notifications()->count());

        Mail::assertNothingSent(); // notifications only, no email
        $this->assertSame(['database'], (new DeadlineAlert(Alert::first(), Milestone::first()))->via($admin));
    }

    public function test_two_stage_alerts_fire_at_full_alert_days_and_reminder_at_half_days(): void
    {
        Mail::fake();
        $admin = $this->admin();
        // 30-day alert window: 1st stage at 30 days, 2nd stage at 15 days
        $m = $this->milestone($this->mine, 25, 'bg:twostage', 30);

        // Day 25: 1st stage alert (upcoming)
        $first = $this->check();
        $this->assertSame(1, $first['upcoming']);
        $this->assertSame(0, $first['reminder']);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('upcoming', $admin->notifications()->first()->data['type']);

        // Running next day: no duplicate
        $second = $this->check();
        $this->assertSame(0, $second['upcoming']);
        $this->assertSame(0, $second['reminder']);
        $this->assertSame(1, $admin->notifications()->count());

        // 10 days pass: now 15 days left (half of 30) -> 2nd stage alert (reminder)
        \Illuminate\Support\Carbon::setTestNow(today()->addDays(10));
        $third = $this->check();
        $this->assertSame(0, $third['upcoming']);
        $this->assertSame(1, $third['reminder']); // 2nd stage reminder alert!
        $this->assertSame(2, $admin->notifications()->count());
        $this->assertSame('reminder', $admin->notifications()->first()->data['type']);

        Mail::assertNothingSent(); // only in-portal notifications, zero email
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_representative_only_gets_notifications_of_assigned_blocks(): void
    {
        $rep = $this->rep();
        $admin = $this->admin();
        $this->milestone($this->mine, 5, 'bg:mine');
        $this->milestone($this->other, 5, 'bg:other');

        $this->check();

        $this->assertSame(['MN-ONHP-2018/1'], $rep->notifications->pluck('data.block_code')->all());
        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_relinquished_and_closed_items_are_not_notified(): void
    {
        $admin = $this->admin();
        $relinquished = Block::create(['code' => 'MN-ONHP-2018/4', 'basin' => 'Mahanadi', 'status' => 'Relinquished']);
        $this->milestone($relinquished, -5, 'x:relinquished');
        $done = $this->milestone($this->mine, -5, 'x:done');
        $done->update(['status' => Milestone::STATUS_COMPLETED]);

        $this->check();
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_new_due_date_resets_alerts_so_they_come_again(): void
    {
        $admin = $this->admin();
        $m = $this->milestone($this->mine, 10, 'bg:moving');
        $this->check();
        $this->assertSame(1, $admin->notifications()->count());

        $m->update(['due_date' => today()->addYear()]); // renewed: no longer due
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, Alert::count());

        $m->update(['due_date' => today()->addDays(3)]); // due soon again
        $this->check();
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_bell_page_read_unread_and_mark_all(): void
    {
        $rep = $this->rep();
        $this->milestone($this->mine, 10, 'bg:a');
        $this->milestone($this->mine, -3, 'bg:b');
        $this->check();

        $this->actingAs($rep)->get(route('dashboard'))->assertOk()->assertSee('2 unread');
        $this->get(route('notifications.index'))->assertOk()->assertSee('MN-ONHP-2018/1');

        $n = $rep->notifications()->first();
        $this->get(route('notifications.open', $n->id))->assertRedirect(route('compliance.index', ['block' => $this->mine->id]));
        $this->assertNotNull($n->fresh()->read_at);

        $this->post(route('notifications.toggle', $n->id));
        $this->assertNull($n->fresh()->read_at);

        $this->post(route('notifications.read-all'))->assertSessionHas('success');
        $this->assertSame(0, $rep->fresh()->unreadNotifications()->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $admin = $this->admin();
        $this->milestone($this->other, 5, 'bg:other');
        $this->check();
        $id = $admin->notifications()->first()->id;

        $this->actingAs($this->rep())->get(route('notifications.open', $id))->assertNotFound();
    }

    public function test_run_check_now_needs_permission_and_command_works(): void
    {
        $this->milestone($this->mine, 5, 'bg:a');

        $this->actingAs($this->rep())->post(route('notifications.run'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('notifications.run'))->assertSessionHas('success');
        $this->assertSame(1, $admin->notifications()->count());

        $this->artisan('alerts:check')->assertSuccessful();
        $this->assertDatabaseHas('audit_logs', ['event' => 'alerts-check']);
    }
    public function test_user_assigned_later_gets_current_alerts_on_next_check_once(): void
    {
        $this->milestone($this->mine, -5, "bg:late");
        $this->check(); // no representative yet

        $rep = $this->rep(); // assigned to the block afterwards
        $this->assertSame(0, $rep->notifications()->count());

        $result = $this->check();
        $this->assertSame(0, $result["overdue"]); // not a new alert...
        $this->assertSame(1, $rep->notifications()->count()); // ...but the new user is told

        $this->check();
        $this->assertSame(1, $rep->notifications()->count()); // and only once
    }

    public function test_nodal_officer_seeder_assigns_onhp_and_udwhp_blocks(): void
    {
        // an earlier placeholder account is updated, not duplicated
        $placeholder = User::factory()->create(["email" => "nodal.onhp@oil.local", "role_id" => Role::where("slug", "company-representative")->value("id")]);
        Block::create(["code" => "MN-UDWHP-2023/2", "basin" => "Mahanadi", "status" => "Active"]);

        $this->seed(\Database\Seeders\NodalOfficerSeeder::class);

        $onhp = User::where("email", "subhayan_dasgupta@oilindia.in")->firstOrFail();
        $udwhp = User::where("email", "bhabagrahi.sahu@oilindia.in")->firstOrFail();
        $this->assertTrue($onhp->is_nodal_officer);
        $this->assertSame("7002907403", $onhp->phone);
        $this->assertSame(["MN-ONHP-2018/1"], $onhp->blocks()->pluck("code")->all());
        $this->assertEqualsCanonicalizing(["KG-UDWHP-2023/1", "MN-UDWHP-2023/2"], $udwhp->blocks()->pluck("code")->all());
        $this->assertSame("Company Representative", $onhp->roleLabel());

        // running it again keeps one account each
        $this->seed(\Database\Seeders\NodalOfficerSeeder::class);
        $this->assertSame(1, User::where("email", "subhayan_dasgupta@oilindia.in")->count());
        $this->assertSame($placeholder->id, $onhp->id);
        $this->assertSame(0, User::where("email", "nodal.onhp@oil.local")->count());
    }
    public function test_admin_adds_another_nodal_officer_shown_on_block_detail(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route("admin.users.index"))->assertSee("Add Nodal Officer");
        $this->get(route("admin.users.create", ["nodal" => 1]))->assertOk();

        $this->post(route("admin.users.store"), [
            "name" => "Third Officer", "email" => "third@oilindia.in", "phone" => "9876543210",
            "role_id" => Role::where("slug", "company-representative")->value("id"),
            "password" => "Secret123", "password_confirmation" => "Secret123",
            "is_active" => "1", "is_nodal_officer" => "1", "blocks" => [$this->mine->id],
        ])->assertRedirect(route("admin.users.index"));

        $this->get(route("blocks.show", $this->mine))->assertSee("Third Officer")->assertSee("9876543210");
        $this->get(route("admin.users.index", ["status" => "nodal"]))->assertSee("Third Officer");

        // an assigned user who is not a nodal officer is not listed as one
        $this->rep();
        $this->get(route("blocks.show", $this->mine))->assertSee("Third Officer");
        $this->post(route("admin.users.store"), [
            "name" => "Bad Phone", "email" => "bad@oilindia.in", "phone" => "call me",
            "role_id" => Role::where("slug", "company-representative")->value("id"),
            "password" => "Secret123", "password_confirmation" => "Secret123", "is_active" => "1",
        ])->assertSessionHasErrors("phone");
    }
}