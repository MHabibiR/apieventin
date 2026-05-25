<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizers = [
            [
                'nama' => 'Federico Barba',
                'email' => 'barba93@gmail.com',
                'nama_eo' => 'YTTA Vendor',
                'file_proposal' => 'proposal_jazz_malam.pdf',
            ],
            [
                'nama' => 'Maulana Haye',
                'email' => 'haye33@gmail.com',
                'nama_eo' => 'Gass aja',
                'file_proposal' => 'proposal_seminar.pdf',
            ],
            [
                'nama' => 'Azizi Asadel',
                'email' => 'zee@gmail.com',
                'nama_eo' => 'Zeemotin Club',
                'file_proposal' => 'proposal_workshop.pdf',
            ]
        ];

        foreach ($organizers as $org) {
            // Buat data User terlebih dahulu untuk mendapatkan ID-nya
            $userId = DB::table('users')->insertGetId([
                'nama' => $org['nama'],
                'email' => $org['email'],
                'password' => Hash::make('password123'), // Set password default
                // 'role' => 'organizer', // Hapus tanda // di depan jika tabel users Anda memiliki kolom 'role'
                'created_at' => now(), 
                'updated_at' => now(),
            ]);

            // 2. Gunakan ID User tersebut untuk mengisi profil Organizer
            DB::table('organizer')->insert([
                'user_id' => $userId,
                'nama_eo' => $org['nama_eo'],
                'file_proposal' => $org['file_proposal'],
                'status' => 'pending', 
                'created_at' => now(), 
                'updated_at' => now(),
            ]);
        }
    }
}