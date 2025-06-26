<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('booked_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('ride_id')->constrained('rides')->onDelete('cascade');
            $table->string('seat_number', 10);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booked_seats');
    }
}; 