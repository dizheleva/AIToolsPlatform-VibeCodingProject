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
        if (!Schema::hasTable('ai_tools')) {
            Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('url', 500);
            $table->string('logo_url', 500)->nullable();
            $table->enum('pricing_model', ['free', 'freemium', 'paid', 'enterprise'])->default('free');
            $table->enum('status', ['active', 'inactive', 'pending_review'])->default('pending_review');
            $table->boolean('featured')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->string('documentation_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('created_by');
            $table->index('featured');
            $table->index('deleted_at');
            // Note: FullText index can be added manually if needed for search optimization
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tools');
    }
};

