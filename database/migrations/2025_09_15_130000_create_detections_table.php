<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('detection_code_id')->nullable()->constrained('detection_codes')->nullOnDelete();

            // 样品信息
            $table->string('sample_no', 64); // 业务上唯一且必填
            $table->enum('sample_type', ['adult_bee', 'capped_brood', 'uncapped_brood', 'other'])->nullable();
            $table->string('address_text', 255)->nullable();

            // 流程与时间
            $table->timestamp('sampled_at')->nullable(); // 新增：取样时间
            $table->timestamp('submitted_at')->nullable(); // 小程序提交问卷时间（可空）
            $table->enum('status', ['pending', 'received', 'processing', 'completed'])->default('pending')->index();

            // 实验/报告元数据
            $table->timestamp('tested_at')->nullable()->index();
            $table->string('tested_by', 64)->nullable();
            $table->string('report_no', 64)->nullable();
            $table->text('lab_notes')->nullable();
            $table->timestamp('reported_at')->nullable()->index();

            // 兼容字段（问卷/联系方式）
            $table->json('questionnaire')->nullable();
            $table->string('contact_phone', 32)->nullable();

            $table->timestamps();

            $table->unique('sample_no');
            $table->index(['user_id', 'detection_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detections');
    }
};
