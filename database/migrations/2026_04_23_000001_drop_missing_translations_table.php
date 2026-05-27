<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The missing-translation tracking moved to a JSON-backed store
 * (storage/app/missing_translations.json) managed by
 * Condoedge\Utils\Services\Translation\MissingTranslationsStore.
 * Historical DB rows are not migrated — fresh JSON replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('missing_translations');
    }

    public function down(): void
    {
        Schema::create('missing_translations', function (Blueprint $table) {
            if (function_exists('addMetaData')) {
                addMetaData($table);
            } else {
                $table->id();
                $table->timestamps();
            }
            $table->string('translation_key');
            $table->string('locale', 8)->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->string('package')->nullable();
            $table->string('file_path')->nullable();
            $table->unique(['translation_key', 'locale']);
        });
    }
};
