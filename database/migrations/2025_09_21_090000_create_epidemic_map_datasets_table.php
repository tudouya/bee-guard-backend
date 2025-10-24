<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epidemic_map_datasets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('province_code', 12)->index();
            $table->string('city_code', 12)->nullable()->index();
            $table->string('district_code', 12)->index();
            $table->string('source', 191)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('data_updated_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'province_code', 'district_code'], 'epidemic_map_datasets_year_region_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epidemic_map_datasets');
    }
};
