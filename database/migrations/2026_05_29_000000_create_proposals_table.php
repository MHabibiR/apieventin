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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            // Data Akun
            $table->string('nama_pengaju');
            $table->string('email_pengaju');
            $table->string('password_pengaju');
            $table->string('no_hp_pengaju')->nullable();
            
            // Data Organizer
            $table->string('nama_eo');
            $table->string('file_proposal');
            
            // Data Event
            $table->string('nama_event');
            $table->enum('kategori', ['musik', 'pameran', 'seminar', 'workshop']);
            $table->text('deskripsi');
            $table->date('tgl_event');
            $table->time('waktu');
            $table->string('lokasi');
            $table->integer('harga_reg')->nullable();
            $table->integer('harga_vip')->nullable();
            $table->integer('kapasitas_reg')->nullable();
            $table->integer('kapasitas_vip')->nullable();
            $table->string('thumbnail')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
