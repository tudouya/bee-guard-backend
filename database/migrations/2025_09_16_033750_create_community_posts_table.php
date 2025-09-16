<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title', 160);
            $table->text('content');
            $table->string('content_format', 20)->default('plain');
            $table->json('images')->nullable();
            $table->foreignId('disease_id')->nullable()->constrained('diseases')->nullOnDelete();
            $table->string('category', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason', 255)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status', 'published_at']);
            $table->index(['status', 'published_at']);
            $table->index('user_id');
            $table->index('disease_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
