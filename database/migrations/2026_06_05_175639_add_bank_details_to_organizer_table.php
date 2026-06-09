<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizer', function (Blueprint $table) {
            $table->string('no_rekening')->nullable();
            $table->string('atas_nama_rekening')->nullable();
            $table->string('nama_bank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizer', function (Blueprint $table) {
            $table->dropColumn(['no_rekening', 'atas_nama_rekening', 'nama_bank']);
        });
    }
};
