<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who last edited a record in the portal. A record with updated_by set was
 * changed by hand, so an Excel re-import leaves it as it is.
 */
return new class extends Migration
{
    private array $tables = ['blocks', 'licences', 'block_timelines', 'work_programmes', 'bank_guarantees', 'investments', 'locations', 'clearances'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
