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
            $table->string('station_status')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->char('zip', 5)->nullable();
            $table->string('borough')->nullable();
            $table->string('hood')->nullable();
            $table->integer('available_bikes')->nullable();
            $table->integer('available_docks')->nullable();
            $table->timestampTz('time_interval');
            $table->timestampTz('created_at')->useCurrent();
            $table->string('weather_summary')->nullable();
            $table->float('precip_intensity', 5, 2)->nullable();
            $table->float('temperature', 5, 2)->nullable();
            $table->float('humidity', 5, 2)->nullable();
            $table->float('wind_speed', 5, 2)->nullable();
            $table->float('wind_gust', 5, 2)->nullable();
            $table->float('cloud_cover', 5, 2)->nullable();
            $table->string('weather_status')->nullable();
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
