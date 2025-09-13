<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->enum('source_type', ['gift', 'self_paid'])->index();
            $table->string('prefix', 16)->index();
            $table->enum('status', ['available', 'assigned', 'used', 'expired'])->default('available')->index();
            $table->foreignId('enterprise_id')->nullable()->constrained('enterprises')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['enterprise_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_codes');
    }
};

