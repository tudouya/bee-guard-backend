<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disease_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->string('note', 191)->nullable();
            $table->timestamps();

            $table->unique(['disease_id', 'product_id']);
            $table->index('disease_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_product');
    }
};

