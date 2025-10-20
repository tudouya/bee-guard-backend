<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'homepage_featured')) {
                $table->boolean('homepage_featured')->default(false)->after('status')->index();
            }
            if (!Schema::hasColumn('products', 'homepage_sort_order')) {
                $table->integer('homepage_sort_order')->default(0)->after('homepage_featured')->index();
            }
            if (!Schema::hasColumn('products', 'homepage_registration_no')) {
                $table->string('homepage_registration_no', 191)->nullable()->after('homepage_sort_order');
            }
            if (!Schema::hasColumn('products', 'homepage_applicable_scene')) {
                $table->text('homepage_applicable_scene')->nullable()->after('homepage_registration_no');
            }
            if (!Schema::hasColumn('products', 'homepage_highlights')) {
                $table->text('homepage_highlights')->nullable()->after('homepage_applicable_scene');
            }
            if (!Schema::hasColumn('products', 'homepage_cautions')) {
                $table->text('homepage_cautions')->nullable()->after('homepage_highlights');
            }
            if (!Schema::hasColumn('products', 'homepage_price')) {
                $table->string('homepage_price', 128)->nullable()->after('homepage_cautions');
            }
            if (!Schema::hasColumn('products', 'homepage_contact_company')) {
                $table->string('homepage_contact_company', 191)->nullable()->after('homepage_price');
            }
            if (!Schema::hasColumn('products', 'homepage_contact_phone')) {
                $table->string('homepage_contact_phone', 64)->nullable()->after('homepage_contact_company');
            }
            if (!Schema::hasColumn('products', 'homepage_contact_wechat')) {
                $table->string('homepage_contact_wechat', 128)->nullable()->after('homepage_contact_phone');
            }
            if (!Schema::hasColumn('products', 'homepage_contact_website')) {
                $table->string('homepage_contact_website', 255)->nullable()->after('homepage_contact_wechat');
            }
        });

        if (!Schema::hasTable('product_homepage_images')) {
            Schema::create('product_homepage_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('path', 512);
                $table->integer('position')->default(0)->index();
                $table->timestamps();

                $table->index(['product_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_homepage_images')) {
            Schema::drop('product_homepage_images');
        }

        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'homepage_contact_website',
                'homepage_contact_wechat',
                'homepage_contact_phone',
                'homepage_contact_company',
                'homepage_price',
                'homepage_cautions',
                'homepage_highlights',
                'homepage_applicable_scene',
                'homepage_registration_no',
                'homepage_sort_order',
                'homepage_featured',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
