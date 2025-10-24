<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epidemic_map_dataset_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('epidemic_map_datasets')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->string('disease_code', 64);
            $table->unsignedInteger('positive_cases')->default(0);
            $table->unsignedInteger('sample_total')->default(0);
            $table->decimal('rate', 8, 5)->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'month', 'disease_code'], 'epidemic_map_entries_unique');
            $table->index('disease_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epidemic_map_dataset_entries');
    }
};
