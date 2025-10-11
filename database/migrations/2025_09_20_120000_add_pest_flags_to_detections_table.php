<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $columns = [
                'pest_large_mite',
                'pest_small_mite',
                'pest_wax_moth',
                'pest_small_hive_beetle',
                'pest_shield_mite',
                'pest_scoliidae_wasp',
                'pest_parasitic_bee_fly',
            ];

            $after = 'dna_cb_level';

            foreach ($columns as $column) {
                if (!Schema::hasColumn('detections', $column)) {
                    $table->boolean($column)
                        ->default(false)
                        ->after($after)
                        ->comment('1=有,0=无');
                }
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $columns = [
                'pest_large_mite',
                'pest_small_mite',
                'pest_wax_moth',
                'pest_small_hive_beetle',
                'pest_shield_mite',
                'pest_scoliidae_wasp',
                'pest_parasitic_bee_fly',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('detections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
