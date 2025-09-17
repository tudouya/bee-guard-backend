<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            if (!Schema::hasColumn('enterprises', 'code_prefix')) {
                $table->string('code_prefix', 16)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            if (Schema::hasColumn('enterprises', 'code_prefix')) {
                $table->dropColumn('code_prefix');
            }
        });
    }
};

