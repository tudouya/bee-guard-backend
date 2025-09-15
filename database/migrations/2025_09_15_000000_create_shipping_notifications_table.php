<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('detection_code_id')->constrained('detection_codes')->cascadeOnDelete();
            $table->string('courier_company', 80);
            $table->string('tracking_no', 64);
            $table->date('shipped_at')->nullable();
            $table->timestamps();

            $table->unique(['detection_code_id', 'tracking_no']);
            $table->index(['user_id', 'detection_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_notifications');
    }
};

