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
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->vector('embedding', 768)->nullable();
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
