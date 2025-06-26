<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop existing fields that need to be renamed/restructured
            $table->dropColumn(['name']);
            
            // Add new fields according to schema
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('password_hash')->after('email');
            $table->dropColumn(['password']);
            $table->string('phone_number', 50)->nullable()->after('password_hash');
            $table->enum('user_role', ['passenger', 'driver', 'admin'])->default('passenger')->after('phone_number');
            $table->string('avatar_url')->nullable()->after('user_role');
            $table->timestamp('member_since')->default(now())->after('avatar_url');
            $table->string('account_status', 50)->default('Active')->after('member_since');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->dropColumn([
                'first_name',
                'last_name',
                'password_hash',
                'phone_number',
                'user_role',
                'avatar_url',
                'member_since',
                'account_status'
            ]);
            $table->string('password')->after('email');
        });
    }
}; 