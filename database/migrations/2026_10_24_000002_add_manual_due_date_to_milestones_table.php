<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            // true = due date was changed by a user in the portal; Excel re-import will not overwrite it
            $table->boolean('manual_due_date')->default(false)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('manual_due_date');
        });
    }
};
