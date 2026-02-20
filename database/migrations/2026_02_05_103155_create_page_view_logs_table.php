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
        Schema::create('page_view_logs', function (Blueprint $table) {
            $table->id();

            // User identification
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_id_hashed', 64)->nullable()->index(); // SHA-256 hash for privacy

            // Team context (multi-tenancy)
            $table->unsignedBigInteger('team_id')->nullable()->index();
            if (Schema::hasTable('teams')) {
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            }
            $table->string('team_level', 20)->nullable()->index(); // MAIN, DISTRICT, GROUP, UNIT
            $table->string('user_role', 50)->nullable(); // Role name within team
            $table->string('user_type', 20)->nullable(); // SCOUT, LEADER, VOLUNTEER, PARENT

            // Page information
            $table->string('page_url', 500)->index();
            $table->string('page_title', 255)->nullable();
            $table->string('http_method', 10)->default('GET'); // GET, POST, PUT, DELETE
            $table->json('query_params')->nullable(); // URL query parameters

            // Request metadata
            $table->string('referer_url', 500)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable(); // Supports IPv6
            $table->string('session_id', 100)->nullable()->index();

            // User flags
            $table->boolean('is_authenticated')->default(false)->index();
            $table->boolean('is_super_admin')->default(false)->index();

            // Environment
            $table->string('environment', 20)->default('production'); // production, staging, local

            // Timestamp
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['user_id', 'viewed_at']);
            $table->index(['team_id', 'viewed_at']);
            $table->index(['team_level', 'viewed_at']);
            $table->index(['is_authenticated', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_view_logs');
    }
};
