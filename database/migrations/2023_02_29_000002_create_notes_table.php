<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notes', function (Blueprint $table)
        {
<<<<<<< HEAD
            // addMetaData($table);
            
            // $table->foreignId('team_id')->constrained();
            // $table->morphs('notable');

            // $table->string('content_nt');
            // $table->timestamp('date_nt')->default(now());

            // $table->tinyInteger('note_subtype')->nullable();
=======
            addMetaData($table);
            
            $table->foreignId('team_id')->constrained();
            $table->morphs('notable');

            $table->string('content_nt');
            $table->timestamp('date_nt')->default(now());

            $table->tinyInteger('note_subtype')->nullable();
>>>>>>> f188e08 (All the helpers migrated, database migrations, and minor improvements)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notes');
    }
}
