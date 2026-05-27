<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missing_translations', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('translation_key');
            $table->unsignedInteger('hit_count')->default(1)->after('package');
            $table->timestamp('last_seen_at')->nullable()->after('hit_count');
        });

        // Replace the existing unique(translation_key) with a composite unique(translation_key, locale).
        Schema::table('missing_translations', function (Blueprint $table) {
            try {
                $table->dropUnique(['translation_key']);
            } catch (\Throwable $e) {
                // Index name may differ; ignore if already gone.
            }
            $table->unique(['translation_key', 'locale'], 'missing_translations_key_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('missing_translations', function (Blueprint $table) {
            $table->dropUnique('missing_translations_key_locale_unique');
            $table->unique('translation_key');
            $table->dropColumn(['locale', 'hit_count', 'last_seen_at']);
        });
    }
};
