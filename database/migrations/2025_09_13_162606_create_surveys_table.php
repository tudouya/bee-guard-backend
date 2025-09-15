<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('detection_code_id')->constrained('detection_codes')->onDelete('cascade');

            // Q1. 填表时间
            $table->date('fill_date');
            $table->time('fill_time');

            // Q2. 场主姓名
            $table->string('owner_name', 100);

            // Q3. 蜂场当前地址
            $table->string('location_name', 500)->nullable();
            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 11, 8)->nullable();

            // Q4. 联系手机号
            $table->string('phone', 20);

            // Q5. 蜂群数量（群）
            $table->integer('bee_count');

            // Q6. 饲养方式
            $table->enum('raise_method', ['定地', '省内小转地', '跨省大转地']);

            // Q7. 蜂种
            $table->enum('bee_species', ['中华蜜蜂', '西方蜜蜂（意大利蜜蜂等）']);

            // Q8. 蜂场收入来源排序（1-4）
            $table->json('income_ranks');

            // Q9. 当前是否为生产期
            $table->enum('is_production_now', ['是', '否']);

            // Q10. 当前主要生产的蜂产品种类（条件字段）
            $table->enum('product_type', ['蜂蜜', '花粉', '蜂王浆', '其他'])->nullable();

            // Q11. 蜂蜜种类（条件字段）
            $table->string('honey_type', 100)->nullable();

            // Q12. 花粉种类（条件字段）
            $table->string('pollen_type', 100)->nullable();

            // Q13. 下一个生产期开始时间
            $table->string('next_month', 100);

            // Q14. 下一个生产期是否需要转地（条件字段）
            $table->enum('need_move', ['是', '否'])->nullable();

            // Q15. 下一个转地目的地（省/市/县）（条件字段）
            $table->string('move_province', 50)->nullable();
            $table->string('move_city', 50)->nullable();
            $table->string('move_district', 50)->nullable();

            // Q16. 下一个生产期主要蜜粉源（条件字段）
            $table->string('next_floral', 200)->nullable();

            // Q17. 近一个月内是否有蜂群异常
            $table->enum('has_abnormal', ['是', '否']);

            // Q18. 发病虫龄（多选）（条件字段）
            $table->json('sick_ages')->nullable();

            // Q19. 发病蜂群数（条件字段）
            $table->integer('sick_count')->nullable();

            // Q20. 主要症状（多选）+ 其他说明（条件字段）
            $table->json('symptoms')->nullable();
            $table->text('symptom_other')->nullable();

            // Q21. 近一月用过的药物（多项填空）（条件字段）
            $table->json('medications')->nullable();

            // Q22. 该病发生规律（条件字段）
            $table->string('occur_rule', 200)->nullable();

            // Q23. 您认为当前蜂群异常可能与什么有关（条件字段）
            $table->string('possible_reason', 200)->nullable();

            // Q24. 往年蜂群集中发病的时间段（可多选）
            $table->json('past_months');

            // 系统字段
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamps();

            // 索引
            $table->index('user_id');
            $table->index('detection_code_id');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};