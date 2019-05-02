<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->integer('status_key')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('st_address_1')->nullable();
            $table->string('st_address_2')->nullable();
            $table->integer('total_docks')->nullable();
            $table->string('status')->nullable();
            $table->string('altitude')->nullable();
            $table->string('location')->nullable();
            $table->string('land_mark')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_test_station')->nullable();
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
        Schema::dropIfExists('stations');
    }
}
