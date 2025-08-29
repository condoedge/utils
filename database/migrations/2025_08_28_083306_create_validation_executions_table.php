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
        Schema::create('validation_executions', function (Blueprint $table) {
            addMetaData($table);

            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('execution_ended_at')->nullable();
            $table->string('rule_code');
            $table->unsignedBigInteger('records_checked');
            $table->unsignedBigInteger('records_failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validation_executions');
    }
};
