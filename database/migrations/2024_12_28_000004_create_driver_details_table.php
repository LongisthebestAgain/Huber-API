<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_details', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->onDelete('cascade');
            $table->string('profile_title')->nullable();
            $table->text('about_text')->nullable();
            $table->json('languages')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->decimal('completion_rate', 5, 2)->default(0.00);
            $table->decimal('average_rating', 2, 1)->default(0.0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_details');
    }
}; 