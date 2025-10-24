<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diseases', function (Blueprint $table) {
            if (!Schema::hasColumn('diseases', 'map_alias')) {
                $table->string('map_alias', 191)->nullable()->after('name');
            }

            if (!Schema::hasColumn('diseases', 'map_color')) {
                $table->string('map_color', 7)->nullable()->after('map_alias');
            }

            if (!Schema::hasColumn('diseases', 'map_order')) {
                $table->integer('map_order')->default(0)->after('map_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('diseases', function (Blueprint $table) {
            if (Schema::hasColumn('diseases', 'map_order')) {
                $table->dropColumn('map_order');
            }

            if (Schema::hasColumn('diseases', 'map_color')) {
                $table->dropColumn('map_color');
            }

            if (Schema::hasColumn('diseases', 'map_alias')) {
                $table->dropColumn('map_alias');
            }
        });
    }
};
