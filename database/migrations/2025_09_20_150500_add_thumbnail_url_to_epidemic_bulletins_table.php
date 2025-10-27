<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epidemic_bulletins', function (Blueprint $table) {
            if (!Schema::hasColumn('epidemic_bulletins', 'thumbnail_url')) {
                $table->string('thumbnail_url', 255)
                    ->nullable()
                    ->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('epidemic_bulletins', function (Blueprint $table) {
            if (Schema::hasColumn('epidemic_bulletins', 'thumbnail_url')) {
                $table->dropColumn('thumbnail_url');
            }
        });
    }
};

