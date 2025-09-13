<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $table->string('channel', 20)->default('manual')->index(); // manual | wxpay | alipay
            $table->string('trade_no', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('detection_code_id')->nullable()->constrained('detection_codes')->nullOnDelete();
            // package reference (optional)
            $table->string('package_id', 64)->nullable();
            $table->string('package_name', 191)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

