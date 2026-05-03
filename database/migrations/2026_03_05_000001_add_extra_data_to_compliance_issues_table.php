<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_issues', function (Blueprint $table) {
            $table->json('extra_data')->nullable()->after('detail_message');
        });
    }

    public function down(): void
    {
        Schema::table('missing_translations', function (Blueprint $table) {
            $table->dropColumn('extra_data');
        });
    }
};
