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
        Schema::create('missing_translations', function (Blueprint $table) {
            addMetaData($table);

            $table->string('translation_key')->unique();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_translations');
    }
};
