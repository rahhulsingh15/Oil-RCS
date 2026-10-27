<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance rules, trackable milestones, alerts and the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Master_RSC_Compliance_Register_Template.xlsx: the 28 RSC obligations
        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();               // CA-01, EP-01 ...
            $table->string('head', 100);                        // Broad Compliance Head
            $table->string('obligation');
            $table->string('article', 50)->nullable();
            $table->string('trigger_event', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('compliance_type', 50)->nullable();  // One-time / Event / Recurring ...
            $table->string('responsible_department', 100)->nullable();
            $table->string('nodal_officer', 100)->nullable();
            $table->string('nodal_officer_email')->nullable();
            $table->string('approving_authority', 100)->nullable();
            $table->unsignedSmallInteger('alert_days_before')->nullable();
            $table->string('status', 50)->nullable();
            $table->text('supporting_documents')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // A dated obligation for one block. The alert scheduler reads this table.
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 191)->unique();        // stable key so re-imports update, not duplicate
            $table->string('category', 50);                     // Licence / Exploration Phase / Bank Guarantee ...
            $table->string('title');
            $table->string('location_name', 100)->nullable();
            $table->date('reference_date')->nullable();         // the date the due date was derived from
            $table->date('due_date');
            $table->unsignedSmallInteger('alert_days_before')->default(30);
            $table->string('status', 20)->default('pending');   // pending / in_progress / completed / not_applicable
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                         // upcoming / overdue
            $table->date('triggered_on');
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->string('email_status', 20)->nullable();     // pending / sent / failed / skipped
            $table->string('email_recipient')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->unique(['milestone_id', 'type']);           // one alert per milestone per type
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 50);                        // created / updated / deleted / imported / login
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('compliance_items');
    }
};
