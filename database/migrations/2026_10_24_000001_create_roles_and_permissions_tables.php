<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role & permission module.
 * Replaces the fixed users.role column with roles that the admin manages from the portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // Super Admin: cannot be edited or deleted
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();         // e.g. milestones.update_status
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // Which blocks a user may see, when their role does not allow "all blocks"
        Schema::create('block_user', function (Blueprint $table) {
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['block_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
        });

        // Move existing users from the old text role to the new roles table
        $now = now();
        $superAdminId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin', 'slug' => 'super-admin', 'is_system' => true,
            'description' => 'Full access to everything. Cannot be edited or deleted.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $representativeId = DB::table('roles')->insertGetId([
            'name' => 'Company Representative', 'slug' => 'company-representative', 'is_system' => false,
            'description' => 'Sees assigned blocks and updates milestone status.',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('users')->where('role', 'admin')->update(['role_id' => $superAdminId]);
        DB::table('users')->where('role', '!=', 'admin')->update(['role_id' => $representativeId]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('representative')->after('password');
        });

        $superAdminId = DB::table('roles')->where('slug', 'super-admin')->value('id');
        DB::table('users')->update(['role' => 'representative']);
        DB::table('users')->where('role_id', $superAdminId)->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('block_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
