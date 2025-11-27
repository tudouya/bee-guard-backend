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
            if (! Schema::hasColumn('diseases', 'category')) {
                $table->string('category', 32)
                    ->nullable()
                    ->comment('病种分类: rna/dna/pest/other')
                    ->after('code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('diseases')) {
            return;
        }

        Schema::table('diseases', function (Blueprint $table) {
            if (Schema::hasColumn('diseases', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
