<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rides', function (Blueprint $table) {
            // Add missing fields
            $table->foreignId('vehicle_id')->nullable()->after('driver_id')->constrained('vehicles');
            $table->datetime('departure_datetime')->after('dropoff_location');
            $table->dropColumn(['departure_time']);
            $table->enum('ride_type', ['shared', 'exclusive'])->default('shared')->after('departure_datetime');
            $table->decimal('price_per_seat', 10, 2)->after('ride_type');
            $table->dropColumn(['price']);
            $table->string('ride_status', 50)->default('Scheduled')->after('available_seats');
            $table->dropColumn(['status']);
        });
    }

    public function down()
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn(['vehicle_id', 'departure_datetime', 'ride_type', 'price_per_seat', 'ride_status']);
            $table->datetime('departure_time')->after('dropoff_location');
            $table->decimal('price', 10, 2)->after('departure_time');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->after('available_seats');
        });
    }
}; 