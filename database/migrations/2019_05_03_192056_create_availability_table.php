<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAvailabilityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('availability', function (Blueprint $table) {
            $table->primary(['station_id', 'time_interval']);
            $table->integer('station_id');
            $table->string('station_name');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->char('zip', 5)->nullable();
            $table->string('borough')->nullable();
            $table->string('hood')->nullable();
            $table->integer('available_bikes')->nullable();
            $table->integer('available_docks')->nullable();
            $table->dateTime('time_interval');
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
        Schema::dropIfExists('availability');
    }
}
