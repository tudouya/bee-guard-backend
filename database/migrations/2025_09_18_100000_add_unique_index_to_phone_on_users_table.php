<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure column exists
        if (!Schema::hasColumn('users', 'phone')) {
            // Column must exist before adding a unique index according to our schema plan.
            return; // No-op for environments not yet on expected schema.
        }

        // Guard: prevent adding unique index if duplicates exist (excluding NULLs)
        $duplicate = DB::table('users')
            ->select('phone', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->having('cnt', '>', 1)
            ->limit(1)
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                sprintf(
                    'Cannot add unique index on users.phone; duplicate found for phone=%s',
                    (string) ($duplicate->phone ?? '')
                )
            );
        }

        $driver = Schema::getConnection()->getDriverName();
        $hasUnique = false;

        if ($driver === 'mysql') {
            // Check existing unique index on phone to keep migration idempotent
            $database = DB::getDatabaseName();
            $existing = DB::select(
                'SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?;',
                [$database, 'users', 'phone']
            );
            foreach ($existing as $row) {
                // NON_UNIQUE = 0 means unique index
                if (isset($row->NON_UNIQUE) && (int) $row->NON_UNIQUE === 0) {
                    $hasUnique = true;
                    break;
                }
            }
        }

        if (!$hasUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('phone');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'phone')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $hasUnique = true; // Assume exists; best-effort drop below

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $existing = DB::select(
                'SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?;',
                [$database, 'users', 'phone']
            );
            $hasUnique = false;
            foreach ($existing as $row) {
                if (isset($row->NON_UNIQUE) && (int) $row->NON_UNIQUE === 0) {
                    $hasUnique = true;
                    break;
                }
            }
        }

        if ($hasUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['phone']); // drops users_phone_unique by convention
            });
        }
    }
};

