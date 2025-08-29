<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Rename table from compliance_validations to compliance_issues
        Schema::rename('compliance_validations', 'compliance_issues');
        
        // Update column names
        Schema::table('compliance_issues', function (Blueprint $table) {
            $table->renameColumn('failed_at', 'detected_at');
            $table->renameColumn('back_to_valid_at', 'resolved_at');
        });
    }

    public function down()
    {
        // Revert column names
        Schema::table('compliance_issues', function (Blueprint $table) {
            $table->renameColumn('detected_at', 'failed_at');
            $table->renameColumn('resolved_at', 'back_to_valid_at');
        });
        
        // Rename table back
        Schema::rename('compliance_issues', 'compliance_validations');
    }
};