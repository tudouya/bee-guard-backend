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
        Schema::create('reward_issuances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reward_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('coupon_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farmer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending_review');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('store_platform', 50)->nullable();
            $table->string('store_name')->nullable();
            $table->string('store_url')->nullable();
            $table->decimal('face_value', 10, 2)->nullable();
            $table->text('usage_instructions')->nullable();
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->unique(['reward_rule_id', 'community_post_id', 'farmer_user_id'], 'reward_issuances_unique_reward');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_issuances');
    }
};
