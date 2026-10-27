<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\User;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Milestone $mine;
    private Milestone $other;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::sync();

        $this->mine = $this->milestone('MN-ONHP-2018/1');
        $this->other = $this->milestone('KG-UDWHP-2023/1');
    }

    private function milestone(string $code): Milestone
    {
        $block = Block::create(['code' => $code, 'basin' => 'Mahanadi', 'status' => 'Active']);

        return Milestone::create([
            'block_id' => $block->id, 'source_key' => "test:{$code}", 'category' => 'Bank Guarantee',
            'title' => "BG renewal {$code}", 'due_date' => today()->addDays(10), 'alert_days_before' => 30,
        ]);
    }

    private function representative(): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('slug', 'company-representative')->value('id'), 'is_active' => true,
        ]);
        $user->blocks()->sync([$this->mine->block_id]);

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'), 'is_active' => true]);
    }

    public function test_representative_updates_status_of_own_block_and_it_is_logged(): void
    {
        $rep = $this->representative();

        $this->actingAs($rep)
            ->put(route('milestones.update', $this->mine), ['status' => 'completed', 'remarks' => 'BG renewed with SBI', 'due_date' => $this->mine->due_date->format('Y-m-d')])
            ->assertRedirect()
            ->assertSessionHas('success');

        $m = $this->mine->fresh();
        $this->assertSame('completed', $m->status);
        $this->assertSame('BG renewed with SBI', $m->remarks);
        $this->assertNotNull($m->completed_at);
        $this->assertSame($rep->id, $m->updated_by);

        $log = AuditLog::where('auditable_id', $m->id)->where('event', 'updated')->firstOrFail();
        $this->assertSame($rep->id, $log->user_id);
        $this->assertSame(['status' => 'Pending', 'remarks' => null], $log->old_values);
        $this->assertSame(['status' => 'Completed', 'remarks' => 'BG renewed with SBI'], $log->new_values);
    }

    public function test_completed_milestone_clears_its_notification_and_shows_in_latest_updates(): void
    {
        $rep = $this->representative();
        app(\App\Services\AlertService::class)->run(); // due in 10 days, alert window 30 -> "Due Soon"
        $this->assertSame(1, $rep->unreadNotifications()->count());

        $this->actingAs($rep)->put(route("milestones.update", $this->mine), ["status" => "completed"]);

        $this->assertSame(0, $rep->fresh()->unreadNotifications()->count());
        $this->get(route("dashboard"))
            ->assertSee("Latest Milestone Updates")
            ->assertSee("Completed");
    }

    public function test_representative_cannot_update_another_blocks_milestone(): void
    {
        $this->actingAs($this->representative())
            ->put(route('milestones.update', $this->other), ['status' => 'completed'])
            ->assertForbidden();

        $this->assertSame('pending', $this->other->fresh()->status);
    }

    public function test_due_date_needs_its_own_permission(): void
    {
        $this->actingAs($this->representative())
            ->put(route('milestones.update', $this->mine), ['status' => 'pending', 'due_date' => today()->addYear()->format('Y-m-d')])
            ->assertForbidden();

        $this->assertTrue($this->mine->fresh()->due_date->isSameDay(today()->addDays(10)));
    }

    public function test_user_without_edit_permission_is_forbidden(): void
    {
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $role->syncPermissionKeys(['milestones.view', 'blocks.view', 'blocks.view_all']);
        $viewer = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($viewer)->put(route('milestones.update', $this->mine), ['status' => 'completed'])->assertForbidden();
        $this->get(route('compliance.index'))->assertOk()->assertDontSee('Update RSC Milestone Record');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put(route('milestones.update', $this->mine), ['status' => 'done-ish'])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_changed_due_date_survives_excel_reimport(): void
    {
        $newDate = today()->addMonths(6)->startOfDay();

        $this->actingAs($this->admin())
            ->put(route('milestones.update', $this->mine), ['status' => 'in_progress', 'due_date' => $newDate->format('Y-m-d')])
            ->assertSessionHas('success');

        $m = $this->mine->fresh();
        $this->assertTrue($m->manual_due_date);
        $this->assertTrue($m->due_date->isSameDay($newDate));

        // Same source key as an Excel-built BG milestone, then re-run the generator
        $m->block->update(['code' => 'MN-ONHP-2018/1']);
        $m->block->bankGuarantee()->create(['validity_date' => today()->addDays(40), 'renewal_days_before_expiry' => 10]);
        $m->update(['source_key' => 'bg:MN-ONHP-2018/1']);
        (new MilestoneGenerator(new ImportReport))->generate();

        $m = $m->fresh();
        $this->assertTrue($m->due_date->isSameDay($newDate));
        $this->assertSame('in_progress', $m->status);
    }
}
