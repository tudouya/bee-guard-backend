<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 为兼容已运行过的环境，按列存在性进行条件添加
        if (!Schema::hasColumn('diseases', 'brief') ||
            !Schema::hasColumn('diseases', 'symptom') ||
            !Schema::hasColumn('diseases', 'transmit') ||
            !Schema::hasColumn('diseases', 'prevention') ||
            !Schema::hasColumn('diseases', 'status') ||
            !Schema::hasColumn('diseases', 'sort')) {
            Schema::table('diseases', function (Blueprint $table) {
                if (!Schema::hasColumn('diseases', 'brief')) {
                    $table->string('brief', 191)->nullable()->after('description');
                }
                if (!Schema::hasColumn('diseases', 'symptom')) {
                    $table->text('symptom')->nullable()->after('brief');
                }
                if (!Schema::hasColumn('diseases', 'transmit')) {
                    $table->text('transmit')->nullable()->after('symptom');
                }
                if (!Schema::hasColumn('diseases', 'prevention')) {
                    $table->text('prevention')->nullable()->after('transmit');
                }
                if (!Schema::hasColumn('diseases', 'status')) {
                    $table->string('status', 32)->default('active')->after('prevention');
                    $table->index('status');
                }
                if (!Schema::hasColumn('diseases', 'sort')) {
                    $table->unsignedInteger('sort')->default(0)->after('status');
                    $table->index('sort');
                }
            });
        }
    }

    public function down(): void
    {
        // 为避免生产数据丢失，down 不做删除列操作
    }
};

