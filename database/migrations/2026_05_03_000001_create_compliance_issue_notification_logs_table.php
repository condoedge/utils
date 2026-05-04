<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_issue_notification_logs', function (Blueprint $table) {
            addMetaData($table);

            $table->foreignId('compliance_issue_id')->constrained('compliance_issues', indexName: 'compliance_issue_logs_compliance_issue_id_foreign')->cascadeOnDelete();
            $table->nullableMorphs('notifiable', 'coinl_notifiable_index');
            $table->string('channel')->nullable();
            $table->string('recipient_label')->nullable();
            $table->string('status')->nullable();
            $table->string('status_color')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['compliance_issue_id', 'sent_at'], 'coinl_compliance_issue_id_sent_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_issue_notification_logs');
    }
};
