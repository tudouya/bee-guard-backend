<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('enterprise_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('platform', 50);
            $table->string('store_name');
            $table->string('store_url');
            $table->decimal('face_value', 10, 2);
            $table->unsignedInteger('total_quantity')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->text('usage_instructions');
            $table->string('status', 32)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['enterprise_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_templates');
    }
};
