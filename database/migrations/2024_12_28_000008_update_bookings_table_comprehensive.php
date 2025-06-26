<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add booking reference field
            $table->string('booking_ref', 50)->unique()->after('id');
            $table->string('booking_status', 50)->default('Confirmed')->after('total_amount');
            $table->dropColumn(['status', 'number_of_seats', 'special_requests', 'rating', 'review']);
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['booking_ref', 'booking_status']);
            $table->integer('number_of_seats')->after('ride_id');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->after('total_amount');
            $table->text('special_requests')->nullable()->after('status');
            $table->integer('rating')->nullable()->after('special_requests');
            $table->text('review')->nullable()->after('rating');
        });
    }
}; 