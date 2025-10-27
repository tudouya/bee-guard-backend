<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('knowledge_articles', 'sort')) {
                $table->unsignedInteger('sort')->default(0)->after('published_at');
                $table->index('sort');
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            if (Schema::hasColumn('knowledge_articles', 'sort')) {
                $table->dropColumn('sort');
            }
        });
    }
};
