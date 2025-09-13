<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('uploads') && Schema::hasColumn('uploads', 'url')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->dropColumn('url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('uploads') && ! Schema::hasColumn('uploads', 'url')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->string('url', 512)->nullable();
            });
        }
    }
};

