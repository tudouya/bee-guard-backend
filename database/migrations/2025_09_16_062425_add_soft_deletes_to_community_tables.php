<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('community_posts', 'deleted_at')) {
                $table->softDeletes();
                $table->index(['status', 'deleted_at']);
            }
        });

        Schema::table('community_post_replies', function (Blueprint $table) {
            if (!Schema::hasColumn('community_post_replies', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (Schema::hasColumn('community_posts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('community_post_replies', function (Blueprint $table) {
            if (Schema::hasColumn('community_post_replies', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
