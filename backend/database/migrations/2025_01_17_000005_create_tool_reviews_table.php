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
        if (!Schema::hasTable('tool_reviews')) {
            Schema::create('tool_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_tool_id')->constrained('ai_tools')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->tinyInteger('rating')->unsigned()->comment('Rating from 1 to 5');
                $table->text('comment')->nullable()->comment('Review comment');
                $table->timestamps();
                $table->softDeletes();

                // Ensure a user can only review a tool once
                $table->unique(['ai_tool_id', 'user_id']);
                
                // Indexes for performance
                $table->index('ai_tool_id');
                $table->index('user_id');
                $table->index('rating');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_reviews');
    }
};

