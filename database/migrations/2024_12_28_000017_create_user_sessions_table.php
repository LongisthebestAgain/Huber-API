<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_token');
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->timestamp('login_timestamp')->default(now());
            $table->timestamp('expiry_timestamp');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_sessions');
    }
}; 