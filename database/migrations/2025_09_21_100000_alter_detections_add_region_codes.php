<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            if (!Schema::hasColumn('detections', 'province_code')) {
                $table->string('province_code', 12)->nullable()->after('address_text')->index();
            }
            if (!Schema::hasColumn('detections', 'city_code')) {
                $table->string('city_code', 12)->nullable()->after('province_code')->index();
            }
            if (!Schema::hasColumn('detections', 'district_code')) {
                $table->string('district_code', 12)->nullable()->after('city_code')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            if (Schema::hasColumn('detections', 'district_code')) {
                $table->dropColumn('district_code');
            }
            if (Schema::hasColumn('detections', 'city_code')) {
                $table->dropColumn('city_code');
            }
            if (Schema::hasColumn('detections', 'province_code')) {
                $table->dropColumn('province_code');
            }
        });
    }
};
