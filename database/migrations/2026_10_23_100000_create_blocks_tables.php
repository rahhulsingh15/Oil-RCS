<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Block (contract) master data, one table per sheet of Blocks_database_for_portal_v2.xlsx.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sheet "Backup Database": master list of basins
        Schema::create('basins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Sheet "Block Details"
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();            // e.g. MN-ONHP-2018/1
            $table->string('regime', 30)->nullable();          // OALP
            $table->string('bid_round', 20)->nullable();       // II, III, IX
            $table->string('category', 100)->nullable();       // Onland / Ultra Deep Water ...
            $table->string('basin', 100)->nullable();
            $table->string('basin_category', 20)->nullable();
            $table->string('state', 100)->nullable();
            $table->text('districts')->nullable();
            $table->decimal('area_original', 12, 2)->nullable();   // sq km
            $table->decimal('area_revised', 12, 2)->nullable();
            $table->date('area_revision_date')->nullable();
            $table->date('rsc_signing_date')->nullable();
            $table->string('status', 30)->default('Active');   // Active / Relinquished
            $table->string('current_stage', 50)->nullable();   // Exploration / Relinquished
            $table->date('relinquishment_date')->nullable();
            $table->timestamps();
        });

        // Sheet "PEL _PML_PL validity"
        Schema::create('licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('granting_authority', 100)->nullable();
            $table->string('initial_grant_date_text', 100)->nullable(); // can hold two dates
            $table->date('initial_grant_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('pel_to_pl_converted', 10)->nullable();
            $table->date('validity_date')->nullable();
            $table->string('current_year', 20)->nullable();
            $table->decimal('fee_rate', 14, 2)->nullable();
            $table->decimal('fee_amount', 18, 2)->nullable();
            $table->timestamps();
        });

        // Sheet "Timeline details": contract phases (IEP/SEP or Phase-I/II or single EP)
        Schema::create('block_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('contractual_structure', 50)->nullable();
            $table->string('phase1_duration', 30)->nullable();
            $table->string('phase1_extension_allowed', 30)->nullable();
            $table->string('phase2_duration', 30)->nullable();
            $table->string('phase2_extension_allowed', 30)->nullable();
            $table->string('current_phase', 30)->nullable();
            $table->date('phase1_start_date')->nullable();
            $table->date('phase1_validity_contractual')->nullable();
            $table->text('phase1_extensions_granted')->nullable();
            $table->date('phase1_validity_current')->nullable();
            $table->string('policy_2025_availed', 100)->nullable(); // "Whether policy dated 11.11.25 availed"
            $table->date('phase2_start_date')->nullable();
            $table->date('phase2_validity_contractual')->nullable();
            $table->text('phase2_extensions_granted')->nullable();
            $table->date('phase2_validity_revised')->nullable();
            $table->timestamps();
        });

        // Sheet "CWP details": committed work programme vs achievement
        Schema::create('work_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('committed_2d_lkm', 12, 2)->nullable();
            $table->decimal('committed_3d_sqkm', 12, 2)->nullable();
            $table->unsignedInteger('committed_wells')->nullable();
            $table->decimal('achieved_2d_lkm', 12, 2)->nullable();
            $table->decimal('achieved_3d_sqkm', 12, 2)->nullable();
            $table->unsignedInteger('achieved_wells')->nullable();
            $table->decimal('additional_2d_lkm', 12, 2)->nullable();
            $table->decimal('additional_3d_sqkm', 12, 2)->nullable();
            $table->unsignedInteger('additional_wells')->nullable();
            $table->unsignedInteger('wells_under_drilling')->nullable();
            $table->string('location_under_drilling', 100)->nullable();
            $table->decimal('current_drilled_depth_m', 10, 2)->nullable();
            $table->decimal('target_depth_m', 10, 2)->nullable();
            $table->decimal('drilling_completion_pct', 6, 2)->nullable(); // 0-100
            $table->timestamps();
        });

        // Sheet "BG details"
        Schema::create('bank_guarantees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phase', 30)->nullable();
            $table->decimal('amount_usd', 18, 2)->nullable();
            $table->decimal('amount_inr', 18, 2)->nullable();
            $table->date('validity_date')->nullable();
            $table->date('claim_date')->nullable();
            $table->string('renewal_timeline', 100)->nullable();     // "10 days prior to expiry"
            $table->unsignedSmallInteger('renewal_days_before_expiry')->nullable();
            $table->timestamps();
        });

        // Sheet "Investment details"
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('invested_seismic_inr', 18, 2)->nullable();
            $table->decimal('invested_wells_inr', 18, 2)->nullable();
            $table->decimal('invested_others_inr', 18, 2)->nullable();
            $table->decimal('invested_total_inr', 18, 2)->nullable();
            $table->decimal('ld_seismic_musd', 12, 4)->nullable();
            $table->decimal('ld_wells_musd', 12, 4)->nullable();
            $table->decimal('ld_others_musd', 12, 4)->nullable();
            $table->decimal('ld_total_musd', 12, 4)->nullable();
            $table->decimal('ld_total_cr_inr', 14, 4)->nullable();
            $table->unsignedSmallInteger('paid_extension_quarters')->nullable();
            $table->decimal('paid_extension_usd', 18, 2)->nullable();
            $table->decimal('paid_extension_inr', 18, 2)->nullable();
            $table->decimal('total_outgo_inr', 18, 2)->nullable();
            $table->timestamps();
        });

        // Sheet "MC meeting details": one row per meeting
        Schema::create('mc_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('meeting_no');
            $table->date('meeting_date')->nullable();
            $table->timestamps();
            $table->unique(['block_id', 'meeting_no']);
        });

        // Sheet "Location inventory": one row per drilling location
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);                         // OBRA, OPUB ...
            $table->decimal('target_depth_m', 10, 2)->nullable();
            $table->text('targets')->nullable();                // primary/secondary targets
            $table->string('edpmb_no', 20)->nullable();
            $table->date('edpmb_date')->nullable();
            $table->string('edpmb_date_text', 50)->nullable();   // kept when the date is invalid in Excel
            $table->string('drilling_status', 30)->nullable();  // Drilled / Not drilled / Under drilling
            $table->string('actual_drilled_depth', 50)->nullable();
            $table->text('key_findings')->nullable();
            $table->text('studies_carried_out')->nullable();
            $table->string('expected_drilling_start', 50)->nullable(); // "June 2027"
            $table->string('planned_completion', 50)->nullable();
            $table->text('current_status')->nullable();
            $table->timestamps();
            $table->unique(['block_id', 'name']);
        });

        // Sheet "Statutory clearances": one row per location + clearance type (EC / CTE / CTO)
        Schema::create('clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project', 100)->nullable();
            $table->string('location_name', 100);               // as written in Excel, e.g. "OPUB & OPUH"
            $table->string('type', 10);                         // EC / CTE / CTO
            $table->string('status', 30);                       // received / applied / not_applied / not_applicable
            $table->date('applied_on')->nullable();
            $table->date('received_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('note')->nullable();                 // free text found in the date cells
            $table->text('remarks')->nullable();                // Excel "Remarks" (Abandoned, ...)
            $table->timestamps();
            $table->unique(['block_id', 'location_name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearances');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('mc_meetings');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('bank_guarantees');
        Schema::dropIfExists('work_programmes');
        Schema::dropIfExists('block_timelines');
        Schema::dropIfExists('licences');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('basins');
    }
};
