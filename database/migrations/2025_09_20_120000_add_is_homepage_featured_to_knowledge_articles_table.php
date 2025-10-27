<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('knowledge_articles', 'is_homepage_featured')) {
                $table->boolean('is_homepage_featured')
                    ->default(false)
                    ->after('sort');
                $table->index('is_homepage_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            if (Schema::hasColumn('knowledge_articles', 'is_homepage_featured')) {
                $table->dropIndex('knowledge_articles_is_homepage_featured_index');
                $table->dropColumn('is_homepage_featured');
            }
        });
    }
};
