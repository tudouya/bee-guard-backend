<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 兼容已运行过的环境：安全删除子表
        Schema::dropIfExists('detection_results');
    }

    public function down(): void
    {
        // 不恢复该表
    }
};

