<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('contact_name');
            $table->string('relationship', 50);
            $table->string('phone_number', 50);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('emergency_contacts');
    }
}; 