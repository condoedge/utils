<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShortUrlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('short_urls', function (Blueprint $table) {
<<<<<<< HEAD
            // $table->id();

            // $table->string('short_url_code');
            
            // $table->string('invitation_id')->nullable();

            // $table->timestamps();
=======
            $table->id();

            $table->string('short_url_code');
            
            $table->string('invitation_id')->nullable();

            $table->timestamps();
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
        Schema::dropIfExists('short_urls');
    }
}
