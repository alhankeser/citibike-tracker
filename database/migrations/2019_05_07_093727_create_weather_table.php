<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWeatherTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('weather', function (Blueprint $table) {
            $table->primary(['zip', 'timestamp_est']);
            $table->char('zip', 5);
            $table->timestamp('timestamp');
            $table->string('summary')->nullable();
            $table->string('icon')->nullable();
            $table->float('precip_intensity', 5, 2);
            $table->float('temperature', 5, 2);
            $table->float('apparent_temperature', 5, 2);
            $table->float('dew_point', 5, 2);
            $table->float('humidity', 5, 2);
            $table->float('wind_speed', 5, 2);
            $table->float('wind_gust', 5, 2);
            $table->float('cloud_cover', 5, 2);
            $table->float('uv_index', 5, 2);
            $table->float('visibility', 5, 2);
            $table->float('ozone', 5, 2);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->string('status')->nullable;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('weather');
    }
}
