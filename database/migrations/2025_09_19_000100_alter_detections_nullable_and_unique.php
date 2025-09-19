<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make sample_no nullable via raw SQL to avoid requiring doctrine/dbal
        // Preserve length and uniqueness. Works for MySQL.
        try {
            DB::statement('ALTER TABLE detections MODIFY COLUMN sample_no VARCHAR(64) NULL');
        } catch (\Throwable $e) {
            // Ignore if already nullable or on unsupported platform; keep migration idempotent
        }

        Schema::table('detections', function (Blueprint $table) {
            // Ensure unique index on detection_code_id to avoid duplicates for the same code
            try {
                $table->unique('detection_code_id', 'detections_detection_code_id_unique');
            } catch (\Throwable $e) {
                // Index may already exist; ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            // Drop unique index if exists
            try {
                $table->dropUnique('detections_detection_code_id_unique');
            } catch (\Throwable $e) {
                // Ignore if not exists
            }
        });

        // Revert sample_no to NOT NULL if possible
        try {
            DB::statement('ALTER TABLE detections MODIFY COLUMN sample_no VARCHAR(64) NOT NULL');
        } catch (\Throwable $e) {
            // Ignore
        }
    }
};

