<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasTable('payment_proofs')) {
            if ($driver === 'mysql') {
                $columns = collect(DB::select('SHOW COLUMNS FROM payment_proofs'))
                    ->pluck('Field')->all();
                if (in_array('order_no', $columns, true) && ! in_array('trade_no', $columns, true)) {
                    DB::statement('ALTER TABLE `payment_proofs` CHANGE `order_no` `trade_no` VARCHAR(128) NOT NULL');
                }
            } elseif ($driver === 'pgsql') {
                $hasOrder = DB::table('information_schema.columns')
                    ->where('table_name', 'payment_proofs')
                    ->where('column_name', 'order_no')->exists();
                $hasTrade = DB::table('information_schema.columns')
                    ->where('table_name', 'payment_proofs')
                    ->where('column_name', 'trade_no')->exists();
                if ($hasOrder && ! $hasTrade) {
                    DB::statement('ALTER TABLE payment_proofs RENAME COLUMN order_no TO trade_no');
                }
            } else {
                // SQLite or others: fallback add-copy-drop strategy
                if (Schema::hasColumn('payment_proofs', 'order_no') && ! Schema::hasColumn('payment_proofs', 'trade_no')) {
                    Schema::table('payment_proofs', function ($table) {
                        $table->string('trade_no', 128)->nullable();
                    });
                    DB::statement('UPDATE payment_proofs SET trade_no = order_no WHERE trade_no IS NULL');
                    Schema::table('payment_proofs', function ($table) {
                        $table->string('trade_no', 128)->nullable(false)->change();
                    });
                    // Drop old column if supported; if not, leave as-is for dev
                    try {
                        Schema::table('payment_proofs', function ($table) {
                            $table->dropColumn('order_no');
                        });
                    } catch (\Throwable $e) {
                        // ignore for SQLite
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasTable('payment_proofs')) {
            if ($driver === 'mysql') {
                $columns = collect(DB::select('SHOW COLUMNS FROM payment_proofs'))
                    ->pluck('Field')->all();
                if (in_array('trade_no', $columns, true) && ! in_array('order_no', $columns, true)) {
                    DB::statement('ALTER TABLE `payment_proofs` CHANGE `trade_no` `order_no` VARCHAR(128) NOT NULL');
                }
            } elseif ($driver === 'pgsql') {
                $hasTrade = DB::table('information_schema.columns')
                    ->where('table_name', 'payment_proofs')
                    ->where('column_name', 'trade_no')->exists();
                $hasOrder = DB::table('information_schema.columns')
                    ->where('table_name', 'payment_proofs')
                    ->where('column_name', 'order_no')->exists();
                if ($hasTrade && ! $hasOrder) {
                    DB::statement('ALTER TABLE payment_proofs RENAME COLUMN trade_no TO order_no');
                }
            }
        }
    }
};

