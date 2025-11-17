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
        // Only create if both parent tables exist
        if (!Schema::hasTable('ai_tool_category') && Schema::hasTable('ai_tools') && Schema::hasTable('categories')) {
            Schema::create('ai_tool_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_tool_id')->constrained('ai_tools')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->timestamps();

                // Composite primary key
                $table->unique(['ai_tool_id', 'category_id']);
                $table->index('category_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_category');
    }
};

