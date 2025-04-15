<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emails', function (Blueprint $table) {

<<<<<<< HEAD
            // addMetaData($table);
            
            // $table->foreignId('team_id')->constrained();
            // $table->nullableMorphs('emailable');

            // $table->integer('type_em')->nullable();
            // $table->string('address_em');
=======
            addMetaData($table);
            
            $table->foreignId('team_id')->constrained();
            $table->nullableMorphs('emailable');

            $table->integer('type_em')->nullable();
            $table->string('address_em');
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
        Schema::dropIfExists('emails');
    }
};
