<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Basin;
use App\Models\Block;
use App\Models\Clearance;
use App\Models\ComplianceItem;
use App\Models\Location;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\User;
use App\Services\Import\BlocksDatabaseImporter;
use App\Services\Import\ImportReport;
use App\Support\Excel\XlsxReader;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualDataEntryTest extends TestCase
{
    use RefreshDatabase;

    private Block $mine;
    private Block $other;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::sync();
        Basin::create(['name' => 'Mahanadi']);
        foreach ([['PL-01', 90], ['CA-01', 30], ['ENV-01', 30], ['EP-02', 90], ['MC-01', 15]] as [$code, $days]) {
            ComplianceItem::create(['code' => $code, 'head' => 'Test', 'obligation' => $code, 'alert_days_before' => $days]);
        }

        $this->mine = Block::create(['code' => 'MN-ONHP-2018/1', 'basin' => 'Mahanadi', 'status' => 'Active']);
        $this->other = Block::create(['code' => 'KG-UDWHP-2023/1', 'basin' => 'Mahanadi', 'status' => 'Active']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'), 'is_active' => true]);
    }

    /** A user whose role has the given permissions, assigned to $this->mine only. */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Editor '.uniqid(), 'slug' => 'editor-'.uniqid()]);
        $role->syncPermissionKeys($permissions);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->blocks()->sync([$this->mine->id]);

        return $user;
    }

    // ------------------------------------------------------------ blocks

    public function test_admin_adds_a_block_and_its_deadlines_are_created(): void
    {
        $this->actingAs($this->admin())->post(route('blocks.store'), [
            'code' => 'mn-onhp-2030/9', 'basin' => 'Mahanadi', 'status' => 'Active', 'category' => 'Onland', 'bid_round' => 'X',
            'licence_validity_date' => today()->addYear()->format('Y-m-d'),
            'bankGuarantee_validity_date' => today()->addYears(3)->format('Y-m-d'), 'bankGuarantee_renewal_days_before_expiry' => 10,
        ])->assertRedirect();

        $block = Block::where('code', 'MN-ONHP-2030/9')->firstOrFail();
        $this->assertNotNull($block->updated_by);
        $this->assertTrue($block->licence->validity_date->isSameDay(today()->addYear()));
        $this->assertTrue(Milestone::where('source_key', 'pel:MN-ONHP-2030/9')->exists());
        // same field name in two sections must not mix up
        $this->assertTrue($block->bankGuarantee->validity_date->isSameDay(today()->addYears(3)));
        $this->assertTrue(Milestone::where('source_key', 'bg:MN-ONHP-2030/9')->first()->due_date->isSameDay(today()->addYears(3)->subDays(10)));
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $block->id]);
    }

    public function test_block_buttons_and_pages_follow_permissions(): void
    {
        $viewer = $this->userWith(['blocks.view']);
        $this->actingAs($viewer)->get(route('blocks.index'))->assertOk()->assertDontSee('Add Block');
        $this->get(route('blocks.show', $this->mine))->assertDontSee('Edit Block');
        $this->get(route('blocks.create'))->assertForbidden();
        $this->get(route('blocks.edit', $this->mine))->assertForbidden();

        $editor = $this->userWith(['blocks.view', 'blocks.edit']);
        $this->actingAs($editor)->get(route('blocks.show', $this->mine))->assertSee('Edit Block');
        $this->get(route('blocks.edit', $this->mine))->assertOk();
        $this->get(route('blocks.edit', $this->other))->assertForbidden(); // not assigned
    }

    public function test_editing_licence_validity_moves_the_deadline(): void
    {
        $this->mine->licence()->create(['validity_date' => today()->addMonths(3)]);
        $editor = $this->userWith(['blocks.view', 'blocks.edit']);

        $this->actingAs($editor)->put(route('blocks.update', $this->mine), [
            'code' => 'MN-ONHP-2018/1', 'basin' => 'Mahanadi', 'status' => 'Active',
            'licence_validity_date' => today()->addYears(2)->format('Y-m-d'),
        ])->assertRedirect(route('blocks.show', $this->mine));

        $this->assertTrue(Milestone::where('source_key', 'pel:MN-ONHP-2018/1')->first()->due_date->isSameDay(today()->addYears(2)));
        $this->assertNotNull($this->mine->licence()->first()->updated_by);
        $log = AuditLog::where('auditable_type', (new \App\Models\Licence)->getMorphClass())->firstOrFail();
        $this->assertArrayHasKey('validity_date', $log->new_values);
    }

    public function test_block_creator_without_all_blocks_gets_it_assigned(): void
    {
        $creator = $this->userWith(['blocks.view', 'blocks.create']);
        $this->actingAs($creator)->post(route('blocks.store'), ['code' => 'NEW-1', 'basin' => 'Mahanadi', 'status' => 'Active'])->assertRedirect();

        $this->assertTrue($creator->fresh()->blocks()->where('code', 'NEW-1')->exists());
    }

    public function test_admin_deletes_a_block(): void
    {
        $this->actingAs($this->admin())->delete(route('blocks.destroy', $this->other))->assertRedirect(route('blocks.index'));
        $this->assertDatabaseMissing('blocks', ['id' => $this->other->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'description' => 'Block KG-UDWHP-2023/1 deleted with all its data']);
    }

    // ------------------------------------------------------------ clearances

    private function clearancePayload(array $extra = []): array
    {
        return $extra + [
            'block_id' => $this->mine->id, 'location_name' => 'OBRD', 'remarks' => null,
            'ec_status' => 'received', 'ec_valid_until' => today()->addYears(3)->format('Y-m-d'),
            'cte_status' => 'received', 'cte_valid_until' => today()->addYear()->format('Y-m-d'),
            'cto_status' => 'not_applied',
        ];
    }

    public function test_clearances_add_edit_delete_with_deadlines(): void
    {
        $user = $this->userWith(['clearances.view', 'clearances.create', 'clearances.edit', 'clearances.delete']);
        $this->actingAs($user);

        $this->post(route('clearances.store'), $this->clearancePayload())->assertRedirect(route('clearances.index'));
        $this->assertSame(3, Clearance::where('location_name', 'OBRD')->count());
        $this->assertTrue(Milestone::where('source_key', 'cte:MN-ONHP-2018/1:OBRD')->exists());

        // duplicate location is refused
        $this->post(route('clearances.store'), $this->clearancePayload())->assertSessionHasErrors('location_name');

        // edit: CTE validity changes, location renamed
        $this->put(route('clearances.update'), $this->clearancePayload([
            'original_block' => $this->mine->id, 'original_location' => 'OBRD', 'location_name' => 'OBRD-1',
            'cte_valid_until' => today()->addYears(2)->format('Y-m-d'),
        ]))->assertRedirect();
        $this->assertSame(0, Clearance::where('location_name', 'OBRD')->count());
        $this->assertTrue(Milestone::where('source_key', 'cte:MN-ONHP-2018/1:OBRD-1')->first()->due_date->isSameDay(today()->addYears(2)));
        $this->assertFalse(Milestone::where('source_key', 'cte:MN-ONHP-2018/1:OBRD')->exists());

        // delete removes the rows and their deadline
        $this->delete(route('clearances.destroy'), ['block' => $this->mine->id, 'location' => 'OBRD-1'])->assertRedirect();
        $this->assertSame(0, Clearance::count());
        $this->assertFalse(Milestone::where('source_key', 'like', 'cte:%')->exists());
    }

    public function test_clearances_need_permission_and_own_block(): void
    {
        $this->actingAs($this->userWith(['clearances.view']))
            ->post(route('clearances.store'), $this->clearancePayload())->assertForbidden();

        $this->actingAs($this->userWith(['clearances.view', 'clearances.create']))
            ->post(route('clearances.store'), $this->clearancePayload(['block_id' => $this->other->id]))->assertForbidden();
        $this->assertSame(0, Clearance::count());
    }

    // ------------------------------------------------------------ locations & investment

    public function test_locations_add_edit_delete(): void
    {
        $this->actingAs($this->userWith(['locations.view', 'locations.create', 'locations.edit', 'locations.delete']));

        $this->post(route('locations.store'), ['block_id' => $this->mine->id, 'name' => 'obrx', 'drilling_status' => 'Not drilled'])
            ->assertRedirect(route('locations.index'));
        $location = Location::where('name', 'OBRX')->firstOrFail();

        $this->post(route('locations.store'), ['block_id' => $this->mine->id, 'name' => 'OBRX', 'drilling_status' => 'Drilled'])
            ->assertSessionHasErrors('name');

        $this->put(route('locations.update', $location), ['block_id' => $this->mine->id, 'name' => 'OBRX', 'drilling_status' => 'Under drilling'])
            ->assertRedirect();
        $this->assertSame('Under drilling', $location->fresh()->drilling_status);
        $this->get(route('locations.index'))->assertSee('OBRX');

        $this->delete(route('locations.destroy', $location))->assertRedirect();
        $this->assertSame(0, Location::count());
    }

    public function test_investment_edit_needs_permission(): void
    {
        $this->actingAs($this->userWith(['investment.view']))->get(route('investment.edit', $this->mine))->assertForbidden();

        $this->actingAs($this->userWith(['investment.view', 'investment.edit']))
            ->put(route('investment.update', $this->mine), ['ld_total_musd' => 6.25, 'paid_extension_quarters' => 2])
            ->assertRedirect(route('investment.index'));

        $this->assertEquals(6.25, $this->mine->investment()->first()->ld_total_musd);
        $this->get(route('investment.index'))->assertSee('6.25');
    }

    // ------------------------------------------------------------ Excel re-import

    public function test_excel_reimport_keeps_records_edited_in_the_portal(): void
    {
        $admin = $this->admin();
        // Portal edit of the BG of MN-ONHP-2018/1 (Excel says 28.02.2027)
        $this->mine->bankGuarantee()->create(['validity_date' => '2030-12-31', 'updated_by' => $admin->id]);

        $report = new ImportReport;
        (new BlocksDatabaseImporter($report))->import(new XlsxReader(database_path('data/Blocks_database_for_portal_v2.xlsx')));

        $this->assertSame('2030-12-31', $this->mine->bankGuarantee()->first()->validity_date->format('Y-m-d'));
        $this->assertGreaterThan(0, $report->counts['kept (edited in portal)'] ?? 0);
        // a record not edited in the portal is updated from the Excel as before
        $this->assertSame('2027-02-28', Block::where('code', 'MN-ONHP-2018/2')->first()->bankGuarantee->validity_date->format('Y-m-d'));
    }
    public function test_well_under_drilling_is_entered_on_the_cwp_page(): void
    {
        $this->actingAs($this->userWith(["blocks.view"]))->get(route("cwp.edit", $this->mine))->assertForbidden();

        $editor = $this->userWith(["blocks.view", "blocks.edit"]);
        $this->actingAs($editor)->get(route("cwp.index"))->assertOk()->assertSee(route("cwp.edit", $this->mine));

        $this->put(route("cwp.update", $this->mine), [
            "committed_wells" => 3, "achieved_wells" => 0,
            "wells_under_drilling" => 1, "location_under_drilling" => "OBKB-R",
            "current_drilled_depth_m" => 1945, "target_depth_m" => 3393,
        ])->assertRedirect(route("cwp.index"));

        $cwp = $this->mine->workProgramme()->first();
        $this->assertSame("OBKB-R", $cwp->location_under_drilling);
        $this->assertEqualsWithDelta(57.32, $cwp->drilling_completion_pct, 0.01); // auto: 1945 / 3393

        // a typed % is ignored, it always follows the depths
        $this->put(route("cwp.update", $this->mine), ["wells_under_drilling" => 1, "location_under_drilling" => "OBKB-R", "current_drilled_depth_m" => 3393, "target_depth_m" => 3393, "drilling_completion_pct" => 12]);
        $this->assertEquals(100, $this->mine->workProgramme()->first()->drilling_completion_pct);
        $this->get(route("cwp.index"))->assertSee("OBKB-R");

        // drilling finished: details are cleared
        $this->put(route("cwp.update", $this->mine), ["committed_wells" => 3, "achieved_wells" => 1, "wells_under_drilling" => 0, "location_under_drilling" => "OBKB-R"]);
        $this->assertNull($this->mine->workProgramme()->first()->location_under_drilling);

        $this->put(route("cwp.update", $this->other), ["wells_under_drilling" => 1])->assertForbidden(); // not assigned
    }
    public function test_add_investment_with_bank_guarantee(): void
    {
        $this->actingAs($this->userWith(["investment.view", "investment.edit"]))->get(route("investment.create"))->assertForbidden();

        $user = $this->userWith(["investment.view", "investment.create"]);
        $this->actingAs($user)->get(route("investment.index"))->assertSee("Add Investment");
        $this->get(route("investment.create", ["block" => $this->mine->id]))->assertOk();

        $this->post(route("investment.store"), [
            "block_id" => $this->mine->id, "ld_total_musd" => 5, "paid_extension_quarters" => 2,
            "bg_amount_usd" => 8500000, "bg_validity_date" => today()->addMonths(5)->format("Y-m-d"),
        ])->assertRedirect(route("investment.index"));

        $this->assertEquals(5, $this->mine->investment()->first()->ld_total_musd);
        $this->assertTrue($this->mine->bankGuarantee()->first()->validity_date->isSameDay(today()->addMonths(5)));
        $this->assertTrue(Milestone::where("source_key", "bg:MN-ONHP-2018/1")->exists());

        $this->post(route("investment.store"), ["block_id" => $this->other->id, "ld_total_musd" => 1])->assertForbidden(); // not assigned
    }
}