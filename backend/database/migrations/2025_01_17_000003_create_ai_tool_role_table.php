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
        // Only create if ai_tools table exists
        if (!Schema::hasTable('ai_tool_role') && Schema::hasTable('ai_tools')) {
            Schema::create('ai_tool_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_tool_id')->constrained('ai_tools')->onDelete('cascade');
                $table->string('role', 32)->comment('User role: backend, frontend, qa, pm, designer');
                $table->timestamps();

                // Composite primary key
                $table->unique(['ai_tool_id', 'role']);
                $table->index('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_role');
    }
};

