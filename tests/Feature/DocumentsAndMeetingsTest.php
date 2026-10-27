<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\ComplianceItem;
use App\Models\Document;
use App\Models\Location;
use App\Models\McMeeting;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentsAndMeetingsTest extends TestCase
{
    use RefreshDatabase;

    private Block $mine;
    private Block $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Permissions::sync();

        $this->mine = Block::create(['code' => 'MN-ONHP-2018/1', 'basin' => 'Mahanadi', 'status' => 'Active', 'category' => 'Combination (Onland + Offshore)', 'bid_round' => 'II']);
        $this->other = Block::create(['code' => 'KG-UDWHP-2023/1', 'basin' => 'Krishna Godavari', 'status' => 'Active', 'category' => 'Ultra Deep Water', 'bid_round' => 'IX']);
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

    private function upload(Block $block, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('documents.store'), $extra + [
            'block_id' => $block->id, 'type' => 'WCR', 'title' => 'WCR of OBRA',
            'file' => UploadedFile::fake()->create('obra-wcr.pdf', 120, 'application/pdf'),
        ]);
    }

    // ------------------------------------------------------------ documents

    public function test_representative_uploads_views_and_downloads_own_block_document(): void
    {
        $this->actingAs($this->rep());

        $this->upload($this->mine)->assertSessionHas('success');

        $doc = Document::firstOrFail();
        $this->assertSame('WCR of OBRA', $doc->title);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'uploaded', 'auditable_id' => $doc->id]);

        $this->get(route('documents.index'))->assertOk()->assertSee('WCR of OBRA');
        $this->get(route('blocks.show', $this->mine))->assertOk()->assertSee('WCR of OBRA');
        $this->get(route('documents.download', $doc))->assertOk()->assertDownload('obra-wcr.pdf');
    }

    public function test_representative_cannot_upload_or_download_for_another_block(): void
    {
        $adminDoc = null;
        $this->actingAs($this->admin());
        $this->upload($this->other, ['title' => 'KG letter']);
        $adminDoc = Document::firstOrFail();

        $this->actingAs($this->rep());
        $this->upload($this->other, ['title' => 'Sneaky'])->assertForbidden();
        $this->get(route('documents.download', $adminDoc))->assertForbidden();
        $this->get(route('documents.index'))->assertDontSee('KG letter');
        $this->assertSame(1, Document::count());
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        $this->actingAs($this->rep());
        $this->upload($this->mine, ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->assertSame(0, Document::count());
    }

    public function test_only_users_with_delete_permission_can_delete(): void
    {
        $this->actingAs($this->rep());
        $this->upload($this->mine);
        $doc = Document::firstOrFail();

        $this->delete(route('documents.destroy', $doc))->assertForbidden();

        $this->actingAs($this->admin())->delete(route('documents.destroy', $doc))->assertSessionHas('success');
        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertMissing($doc->file_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $doc->id]);
    }

    // ------------------------------------------------------------ MC meetings

    public function test_adding_a_meeting_with_minutes_moves_the_next_mc_due_date(): void
    {
        ComplianceItem::create(['code' => 'MC-01', 'head' => 'Management Committee', 'obligation' => 'MC Meeting', 'alert_days_before' => 15]);
        McMeeting::create(['block_id' => $this->mine->id, 'meeting_no' => 1, 'meeting_date' => '2025-01-10']);

        $this->actingAs($this->rep())->post(route('mc-meetings.store'), [
            'block_id' => $this->mine->id,
            'meeting_date' => today()->subDays(5)->format('Y-m-d'),
            'file' => UploadedFile::fake()->create('mc-2.pdf', 80, 'application/pdf'),
        ])->assertSessionHas('success');

        $meeting = McMeeting::where('block_id', $this->mine->id)->where('meeting_no', 2)->firstOrFail();
        $this->assertSame(1, $meeting->minutes()->count());
        $this->assertSame('MC Minutes', $meeting->minutes()->first()->type);

        $milestone = Milestone::where('source_key', 'mc:MN-ONHP-2018/1')->firstOrFail();
        $this->assertTrue($milestone->due_date->isSameDay(today()->subDays(5)->addMonthsNoOverflow(6)));

        $this->get(route('mc-meetings.index'))->assertOk()->assertSee('MC Meeting #2')->assertSee('mc-2.pdf');
    }

    public function test_minutes_upload_for_an_existing_meeting_and_block_check(): void
    {
        $mine = McMeeting::create(['block_id' => $this->mine->id, 'meeting_no' => 1, 'meeting_date' => '2025-01-10']);
        $other = McMeeting::create(['block_id' => $this->other->id, 'meeting_no' => 1, 'meeting_date' => '2025-01-10']);
        $this->actingAs($this->rep());

        $this->post(route('mc-meetings.minutes', $mine), ['file' => UploadedFile::fake()->create('m.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $this->post(route('mc-meetings.minutes', $other), ['file' => UploadedFile::fake()->create('m.pdf', 50, 'application/pdf')])->assertForbidden();

        $this->assertSame(1, Document::count());
    }

    public function test_future_meeting_date_is_rejected(): void
    {
        $this->actingAs($this->rep())->post(route('mc-meetings.store'), [
            'block_id' => $this->mine->id, 'meeting_date' => today()->addWeek()->format('Y-m-d'),
        ])->assertSessionHasErrors('meeting_date');
    }

    // ------------------------------------------------------------ dashboard

    public function test_dashboard_shows_block_types_wells_and_oalp_rounds(): void
    {
        Location::create(['block_id' => $this->mine->id, 'name' => 'OBRA', 'drilling_status' => 'Drilled']);
        Location::create(['block_id' => $this->mine->id, 'name' => 'OBRD', 'drilling_status' => 'Not drilled']);

        $response = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        $response->assertSee('Blocks: Onland vs Offshore')
            ->assertSee('Overall')->assertSee('Block-wise')
            ->assertSee('Drilled (1)')
            ->assertSee('Planned (1)')
            ->assertSee('Blocks by OALP Round')
            ->assertDontSee('Blocks by Current Phase');

        $rounds = $response->viewData('rounds');
        $this->assertSame(0, $rounds['OALP-I']);
        $this->assertSame(1, $rounds['OALP-II']);
        $this->assertSame(1, $rounds['OALP-IX']);
        $this->assertSame(['MN-ONHP-2018/1'], $response->viewData('blockTypes')['Onland']); // Combination counts as Onland
        $this->assertSame(['KG-UDWHP-2023/1'], $response->viewData('blockTypes')['Offshore']);
    }

    public function test_relinquished_block_is_left_out_of_onland_offshore_chart(): void
    {
        Block::create(['code' => 'MN-ONHP-2018/4', 'basin' => 'Mahanadi', 'status' => 'Relinquished', 'category' => 'Onland', 'bid_round' => 'III']);

        $types = $this->actingAs($this->admin())->get(route('dashboard'))->viewData('blockTypes');
        $this->assertNotContains('MN-ONHP-2018/4', $types['Onland']);
        $this->assertSame(['Onland', 'Offshore'], array_keys($types));
    }

    public function test_powerpoint_files_are_not_allowed(): void
    {
        $this->actingAs($this->rep());
        $this->upload($this->mine, ['file' => UploadedFile::fake()->create('deck.pptx', 10)])->assertSessionHasErrors('file');
    }

    public function test_new_feature_permissions_reach_existing_representative_role(): void
    {
        $role = Role::where('slug', 'company-representative')->first();
        $this->assertTrue(in_array('documents.create', $role->permissionKeys(), true));
        $this->assertFalse(in_array('documents.delete', $role->permissionKeys(), true));
    }
}
