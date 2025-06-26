<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Basic user fields
            $table->string('phone')->nullable();
            $table->enum('role', ['passenger', 'driver'])->default('passenger');
            $table->string('location')->nullable();
            $table->string('profile_photo')->nullable();
            $table->text('bio')->nullable();
            $table->json('languages')->nullable();
            $table->json('notification_preferences')->nullable();
            
            // Driver specific fields
            $table->string('license_number')->nullable();
            $table->json('vehicle_details')->nullable();
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->decimal('current_heading', 5, 2)->nullable();
            $table->decimal('current_speed', 5, 2)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->enum('availability_status', ['available', 'busy', 'offline'])->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('total_rides')->default(0);
            $table->integer('completed_rides')->default(0);
            $table->integer('cancelled_rides')->default(0);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'role',
                'location',
                'profile_photo',
                'bio',
                'languages',
                'notification_preferences',
                'license_number',
                'vehicle_details',
                'current_latitude',
                'current_longitude',
                'current_heading',
                'current_speed',
                'last_location_update',
                'availability_status',
                'rating',
                'total_rides',
                'completed_rides',
                'cancelled_rides'
            ]);
        });
    }
}; 