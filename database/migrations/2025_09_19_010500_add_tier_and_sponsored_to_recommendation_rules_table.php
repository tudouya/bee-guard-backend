<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('recommendation_rules', 'tier')) {
                $table->integer('tier')->default(20)->after('priority')->index();
            }
            if (!Schema::hasColumn('recommendation_rules', 'sponsored')) {
                $table->boolean('sponsored')->default(false)->after('tier')->index();
            }
        });

        // Initialize sensible defaults: enterprise rules get tier=10, globals keep default 20
        try {
            DB::table('recommendation_rules')
                ->where('scope_type', 'enterprise')
                ->update(['tier' => 10]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        Schema::table('recommendation_rules', function (Blueprint $table) {
            if (Schema::hasColumn('recommendation_rules', 'sponsored')) {
                $table->dropColumn('sponsored');
            }
            if (Schema::hasColumn('recommendation_rules', 'tier')) {
                $table->dropColumn('tier');
            }
        });
    }
};

