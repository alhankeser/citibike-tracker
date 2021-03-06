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
            $table->char('zip', 5)->nullable();
            $table->string('hood_1')->nullable();
            $table->string('hood_2')->nullable();
            $table->string('borough')->nullable();
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
