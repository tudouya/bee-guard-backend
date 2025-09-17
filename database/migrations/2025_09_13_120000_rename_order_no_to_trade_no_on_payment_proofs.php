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
            }
        }
    }
};
