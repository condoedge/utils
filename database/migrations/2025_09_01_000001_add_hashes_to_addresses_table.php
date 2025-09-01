<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // 1) dedupe_key (generated, STORED)
            $table->string('dedupe_key', 600)->storedAs(
                "CONCAT_WS('|', " .
                "COALESCE(LOWER(TRIM(`country`)) COLLATE utf8mb4_0900_ai_ci, ''), " .
                "COALESCE(LOWER(TRIM(`state`))   COLLATE utf8mb4_0900_ai_ci, ''), " .
                "COALESCE(LOWER(TRIM(`city`))    COLLATE utf8mb4_0900_ai_ci, ''), " .
                "COALESCE(LOWER(TRIM(`postal_code`)) COLLATE utf8mb4_0900_ai_ci, ''), " .
                // collapse multiple spaces in address1
                "LOWER(REGEXP_REPLACE(COALESCE(TRIM(`address1`), ''), '\\\\s+', ' ')) COLLATE utf8mb4_0900_ai_ci" .
                ")"
            )->stored(); // explicit for clarity

            // 2) index for dedupe_key
            $table->index('dedupe_key', 'idx_dedupe_key');

            // 3) dedupe_hash (SHA-256 hex) as CHAR(64), generated STORED
            //    If you prefer a UNIQUE constraint, add ->unique('ux_dedupe_hash') instead of index().
            $table->char('dedupe_hash', 64)
                  ->storedAs("SHA2(`dedupe_key`, 256)")
                  ->stored();

            $table->index('dedupe_hash', 'idx_dedupe_hash');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('idx_dedupe_hash');
            $table->dropColumn('dedupe_hash');

            $table->dropIndex('idx_dedupe_key');
            $table->dropColumn('dedupe_key');
        });
    }
};
