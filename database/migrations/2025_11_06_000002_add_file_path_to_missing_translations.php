<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missing_translations', function (Blueprint $table) {
            $table->string('package')->nullable();
            $table->string('file_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('missing_translations', function (Blueprint $table) {
            $table->dropColumn('file_path');
            $table->dropColumn('package');
        });
    }
};
