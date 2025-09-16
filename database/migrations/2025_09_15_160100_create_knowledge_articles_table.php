<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title', 191);
            $table->string('brief', 300)->nullable();
            $table->longText('body_html');
            $table->dateTime('published_at')->nullable()->index();
            $table->unsignedBigInteger('views')->default(0)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['disease_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};

