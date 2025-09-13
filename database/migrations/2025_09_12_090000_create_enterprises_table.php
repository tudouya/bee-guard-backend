<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 191);
            $table->string('contact_name', 191)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprises');
    }
};

