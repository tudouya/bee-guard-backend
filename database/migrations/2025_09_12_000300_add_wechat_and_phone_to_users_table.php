<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('unionid', 64)->nullable()->after('openid');
            $table->string('session_key', 128)->nullable()->after('unionid');
            $table->string('phone', 32)->nullable()->after('avatar');
            $table->unique(['unionid']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['unionid']);
            $table->dropColumn(['unionid', 'session_key', 'phone']);
        });
    }
};

