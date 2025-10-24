<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epidemic_bulletins', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->text('content')->nullable();
            $table->enum('risk_level', ['high', 'medium', 'low'])->default('low');
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('source', 150)->nullable();
            $table->json('attachments')->nullable();
            $table->string('province_code', 12)->nullable()->index();
            $table->string('city_code', 12)->nullable()->index();
            $table->string('district_code', 12)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epidemic_bulletins');
    }
};
