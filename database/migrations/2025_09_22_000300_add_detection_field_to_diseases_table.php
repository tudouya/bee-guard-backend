<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diseases')) {
            return;
        }

        Schema::table('diseases', function (Blueprint $table) {
            if (! Schema::hasColumn('diseases', 'detection_field')) {
                $table->string('detection_field', 128)
                    ->nullable()
                    ->comment('绑定宽表/同步字段名，用于回填或双写映射')
                    ->after('category');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('diseases')) {
            return;
        }

        Schema::table('diseases', function (Blueprint $table) {
            if (Schema::hasColumn('diseases', 'detection_field')) {
                $table->dropColumn('detection_field');
            }
        });
    }
};
