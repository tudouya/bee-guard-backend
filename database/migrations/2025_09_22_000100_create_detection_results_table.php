<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detection_results')) {
            return;
        }

        Schema::create('detection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detection_id')->constrained('detections')->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();
            $table->enum('level', ['negative', 'weak', 'medium', 'strong', 'present', 'absent'])
                ->nullable()
                ->comment('negative/null = negative or not tested; weak/medium/strong = RNA/DNA level; present/absent = boolean true/false');
            $table->string('source', 32)->nullable()->comment('結果來源: manual/import/lab_sync');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['detection_id', 'disease_id']);
            $table->index('disease_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_results');
    }
};
