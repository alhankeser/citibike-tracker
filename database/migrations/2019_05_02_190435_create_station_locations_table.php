<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStationLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('station_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('station_id');
            $table->integer('zip');
            $table->string('hood_1');
            $table->string('hood_2');
            $table->string('borough');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('station_locations');
    }
}
