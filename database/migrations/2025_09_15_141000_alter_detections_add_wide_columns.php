<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            // 基础元数据
            if (!Schema::hasColumn('detections', 'contact_name')) {
                $table->string('contact_name', 191)->nullable()->after('sample_no');
            }

            // RNA 病毒
            foreach (['iapv','bqcv','sbv','abpv','cbpv','dwv'] as $code) {
                $col = 'rna_' . $code . '_level';
                if (!Schema::hasColumn('detections', $col)) {
                    $table->enum($col, ['weak','medium','strong'])->nullable()->after('lab_notes');
                }
            }
            // DNA/细菌/真菌
            foreach (['afb','efb','ncer','napi','cb'] as $code) {
                $col = 'dna_' . $code . '_level';
                if (!Schema::hasColumn('detections', $col)) {
                    $table->enum($col, ['weak','medium','strong'])->nullable()->after('lab_notes');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            if (Schema::hasColumn('detections', 'contact_name')) {
                $table->dropColumn('contact_name');
            }
            $drop = [];
            foreach (['iapv','bqcv','sbv','abpv','cbpv','dwv'] as $code) {
                $drop[] = 'rna_' . $code . '_level';
            }
            foreach (['afb','efb','ncer','napi','cb'] as $code) {
                $drop[] = 'dna_' . $code . '_level';
            }
            foreach ($drop as $col) {
                if (Schema::hasColumn('detections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

