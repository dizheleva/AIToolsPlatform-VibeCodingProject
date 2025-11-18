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
        Schema::table('ai_tools', function (Blueprint $table) {
            // Add index for pricing_model (used for filtering)
            if (Schema::hasColumn('ai_tools', 'pricing_model')) {
                try {
                    $table->index('pricing_model', 'ai_tools_pricing_model_index');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
            
            // Add index for created_at (used for sorting)
            if (Schema::hasColumn('ai_tools', 'created_at')) {
                try {
                    $table->index('created_at', 'ai_tools_created_at_index');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }

            // Add composite index for common queries (status + featured)
            if (Schema::hasColumn('ai_tools', 'status') && Schema::hasColumn('ai_tools', 'featured')) {
                try {
                    $table->index(['status', 'featured'], 'ai_tools_status_featured_index');
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
        Schema::table('ai_tools', function (Blueprint $table) {
            // Drop indexes if they exist
            try {
                $table->dropIndex('ai_tools_pricing_model_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('ai_tools_created_at_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('ai_tools_status_featured_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });
    }
};
