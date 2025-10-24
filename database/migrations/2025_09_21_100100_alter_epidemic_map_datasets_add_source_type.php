<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epidemic_map_datasets', function (Blueprint $table) {
            if (!Schema::hasColumn('epidemic_map_datasets', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('district_code');
            }

            if (!Schema::hasColumn('epidemic_map_datasets', 'locked')) {
                $table->boolean('locked')->default(false)->after('source_type');
            }
        });

        // 调整唯一索引，纳入 source_type
        Schema::table('epidemic_map_datasets', function (Blueprint $table) {
            try {
                $table->dropUnique('epidemic_map_datasets_year_region_unique');
            } catch (\Throwable $e) {
                // 索引不存在时忽略
            }

            $table->unique(['year', 'province_code', 'district_code', 'source_type'], 'epidemic_map_datasets_year_region_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('epidemic_map_datasets', function (Blueprint $table) {
            if (Schema::hasColumn('epidemic_map_datasets', 'locked')) {
                $table->dropColumn('locked');
            }
            if (Schema::hasColumn('epidemic_map_datasets', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });

        Schema::table('epidemic_map_datasets', function (Blueprint $table) {
            try {
                $table->dropUnique('epidemic_map_datasets_year_region_source_unique');
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unique(['year', 'province_code', 'district_code'], 'epidemic_map_datasets_year_region_unique');
        });
    }
};
