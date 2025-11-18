<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if status column exists before trying to modify it
        // This migration might run before the status column is created
        if (!Schema::hasColumn('users', 'status')) {
            return; // Column doesn't exist yet, skip this migration
        }

        // For MySQL, use raw SQL to modify enum
        // For SQLite (used in tests), the column is already VARCHAR/TEXT
        // and will accept any value, so we don't need to modify it
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL-specific: modify enum column to include 'rejected'
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
        }
        // For SQLite and other databases, the column is already flexible enough
        // Validation happens at application level, not database level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if status column exists
        if (!Schema::hasColumn('users', 'status')) {
            return; // Column doesn't exist, nothing to revert
        }

        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL-specific: revert enum column to original values
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'approved') DEFAULT 'pending'");
        }
        // For SQLite, no action needed
    }
};

