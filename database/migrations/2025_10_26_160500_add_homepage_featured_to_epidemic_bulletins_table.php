<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epidemic_bulletins', function (Blueprint $table) {
            if (!Schema::hasColumn('epidemic_bulletins', 'homepage_featured')) {
                $table->boolean('homepage_featured')
                    ->default(false)
                    ->after('thumbnail_url')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('epidemic_bulletins', function (Blueprint $table) {
            if (Schema::hasColumn('epidemic_bulletins', 'homepage_featured')) {
                $table->dropIndex('epidemic_bulletins_homepage_featured_index');
                $table->dropColumn('homepage_featured');
            }
        });
    }
};
