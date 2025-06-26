<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->decimal('price', 10, 2);
            $table->integer('available_seats');
            $table->dateTime('departure_time');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled']);
            $table->json('route_info')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rides');
    }
}; 