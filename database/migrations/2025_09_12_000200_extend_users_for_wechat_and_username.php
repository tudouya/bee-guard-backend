<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // New optional identity fields
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('openid', 64)->nullable()->unique()->after('remember_token');
            $table->string('nickname', 191)->nullable()->after('openid');
            $table->string('avatar', 512)->nullable()->after('nickname');
        });

        // Make email/password nullable for farmer (WeChat-only) users.
        // Use raw SQL to avoid requiring doctrine/dbal.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL");
            DB::statement("ALTER TABLE `users` MODIFY `password` VARCHAR(255) NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite alters require table rebuild; skip here (tests often run in memory).
            // For SQLite testing, keep original NOT NULL constraints.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NOT NULL");
            DB::statement("ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['openid']);
            $table->dropColumn(['username', 'openid', 'nickname', 'avatar']);
        });
    }
};

