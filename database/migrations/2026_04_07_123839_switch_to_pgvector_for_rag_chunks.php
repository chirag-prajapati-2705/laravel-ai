<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        Schema::table('rag_chunks', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
                $table->vector('embedding', 768)->nullable();
            } else {
                $table->text('embedding')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->json('embedding')->nullable();
        });
    }
};
