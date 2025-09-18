<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_notifications', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('shipped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_notifications', 'contact_phone')) {
                $table->dropColumn('contact_phone');
            }
        });
    }
};

