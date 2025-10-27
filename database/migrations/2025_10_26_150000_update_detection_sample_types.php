<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            if (!Schema::hasColumn('detections', 'sample_types')) {
                $table->json('sample_types')->nullable()->after('sample_type');
            }
        });

        if (Schema::hasColumn('detections', 'sample_type')) {
            DB::table('detections')
                ->select(['id', 'sample_type'])
                ->whereNotNull('sample_type')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('detections')
                            ->where('id', $row->id)
                            ->update([
                                'sample_types' => json_encode([$row->sample_type]),
                            ]);
                    }
                });
        }

        Schema::table('detections', function (Blueprint $table) {
            if (Schema::hasColumn('detections', 'sample_type')) {
                $table->dropColumn('sample_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            if (!Schema::hasColumn('detections', 'sample_type')) {
                $table->enum('sample_type', ['adult_bee', 'capped_brood', 'uncapped_brood', 'other'])
                    ->nullable()
                    ->after('sample_no');
            }
        });

        if (Schema::hasColumn('detections', 'sample_types')) {
            DB::table('detections')
                ->select(['id', 'sample_types'])
                ->whereNotNull('sample_types')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $types = $row->sample_types;
                        if (!is_array($types)) {
                            $types = json_decode($types ?? '[]', true) ?: [];
                        }

                        DB::table('detections')
                            ->where('id', $row->id)
                            ->update([
                                'sample_type' => $types[0] ?? null,
                            ]);
                    }
                });
        }

        Schema::table('detections', function (Blueprint $table) {
            if (Schema::hasColumn('detections', 'sample_types')) {
                $table->dropColumn('sample_types');
            }
        });
    }
};
