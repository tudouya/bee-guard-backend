<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            if (!Schema::hasColumn('enterprises', 'intro')) {
                $table->text('intro')->nullable()->after('status');
            }

            if (!Schema::hasColumn('enterprises', 'logo_url')) {
                $table->string('logo_url', 255)->nullable()->after('intro');
            }

            if (!Schema::hasColumn('enterprises', 'certifications')) {
                $table->string('certifications', 512)->nullable()->after('logo_url');
            }

            if (!Schema::hasColumn('enterprises', 'services')) {
                $table->string('services', 512)->nullable()->after('certifications');
            }

            if (!Schema::hasColumn('enterprises', 'promotions')) {
                $table->string('promotions', 512)->nullable()->after('services');
            }

            if (!Schema::hasColumn('enterprises', 'contact_wechat')) {
                $table->string('contact_wechat', 128)->nullable()->after('contact_phone');
            }

            if (!Schema::hasColumn('enterprises', 'contact_link')) {
                $table->string('contact_link', 255)->nullable()->after('contact_wechat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            $columns = [
                'intro',
                'logo_url',
                'certifications',
                'services',
                'promotions',
                'contact_wechat',
                'contact_link',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('enterprises', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
