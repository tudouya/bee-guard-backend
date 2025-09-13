<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['global', 'enterprise'])->index();
            $table->enum('applies_to', ['self_paid', 'gift', 'any'])->default('any')->index();
            $table->foreignId('enterprise_id')->nullable()->constrained('enterprises')->nullOnDelete();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('priority')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->timestamps();

            $table->index(['enterprise_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_rules');
    }
};

