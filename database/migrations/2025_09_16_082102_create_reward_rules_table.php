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
        Schema::create('reward_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('metric', 32);
            $table->string('comparator', 16)->default('gte');
            $table->unsignedInteger('threshold');
            $table->string('fulfillment_mode', 32);
            $table->foreignUuid('coupon_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('badge_type', 64)->nullable();
            $table->boolean('lecturer_program')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['metric', 'is_active']);
            $table->index(['fulfillment_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_rules');
    }
};
