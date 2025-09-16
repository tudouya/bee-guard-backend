<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64); // 如 SBV/IAPV 等
            $table->string('name', 191);
            // 详细介绍
            $table->text('description')->nullable();
            // 列表展示的简短简介
            $table->string('brief', 191)->nullable();
            // 典型症状
            $table->text('symptom')->nullable();
            // 传播方式
            $table->text('transmit')->nullable();
            // 防控要点
            $table->text('prevention')->nullable();
            // 控制可见性：active|hidden
            $table->string('status', 32)->default('active');
            // 排序（升序）
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique('code');
            $table->index('status');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diseases');
    }
};
