<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            // Check if columns exist before adding indexes
            if (Schema::hasColumn('users', 'status')) {
                // Check if index already exists by trying to add it (will fail silently if exists in some DBs)
                try {
                    $table->index('status', 'users_status_index');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
            
            if (Schema::hasColumn('users', 'role')) {
                try {
                    $table->index('role', 'users_role_index');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }

            // Add composite index for common queries (role + status)
            if (Schema::hasColumn('users', 'role') && Schema::hasColumn('users', 'status')) {
                try {
                    $table->index(['role', 'status'], 'users_role_status_index');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes if they exist
            try {
                $table->dropIndex('users_status_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('users_role_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('users_role_status_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });
    }
};

