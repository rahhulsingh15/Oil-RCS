<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin = full access, representative = company representative (limited access)
            $table->string('role', 20)->default('representative')->after('password');
            $table->string('designation')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('designation');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'designation', 'is_active', 'last_login_at']);
        });
    }
};
