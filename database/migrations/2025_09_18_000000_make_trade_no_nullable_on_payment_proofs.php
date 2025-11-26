<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_proofs')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `payment_proofs` CHANGE `trade_no` `trade_no` VARCHAR(128) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_proofs')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `payment_proofs` CHANGE `trade_no` `trade_no` VARCHAR(128) NOT NULL');
        }
    }
};
