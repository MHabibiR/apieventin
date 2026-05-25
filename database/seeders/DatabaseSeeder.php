<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Bersihkan data lama terlebih dahulu agar tidak terjadi duplikasi email
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->truncate();
        DB::table('organizer')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. BUAT AKUN MAIN ADMIN
        DB::table('users')->insert([
            'nama' => 'Muhammad Habibi (Admin)',
            'email' => 'admin@eventin.com',
            'password' => Hash::make('password123'), // Ini password untuk login nanti
            'role' => 'main_admin', // Mengunci role sebagai administrator pusat
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. BUAT AKUN UTAMA UNTUK ORGANIZER
        $organizerUserId = DB::table('users')->insertGetId([
            'nama' => 'Habibi Rahman (Organizer)',
            'email' => 'organizer@eventin.com',
            'password' => Hash::make('password123'), // Ini password untuk login nanti
            'role' => 'organizer', // Mengunci role sebagai penyelenggara
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. DAFTARKAN PROFIL LEMBAGA EO UNTUK ORGANIZER DI ATAS
        DB::table('organizer')->insert([
            'user_id' => $organizerUserId,
            'nama_eo' => 'Habibi Creative Studio',
            'file_proposal' => 'proposal_habibi_branding.pdf',
            'status' => 'verified', // Langsung verified agar akun ini bisa langsung membuat event
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. PANGGIL EVENT SEEDER SETELAH ORGANIZER BERHASIL DIBUAT
        $this->call([
            EventSeeder::class,
        ]);
    }
}