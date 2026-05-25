<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Support\Facades\DB;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizer = Organizer::first();

        if (!$organizer) {
            $this->command->error('Gagal. daftarkan diri anda terlebih dahulu.');
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Event::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Event::create([
            'nama_event'    => 'Konser Musik JKT48 Karawang',
            'deskripsi'     => 'Sebuah acara festival musik persembahan dari kami. Menghadirkan musisi ternama dari Indonesia',
            'waktu'         => '19:00',
            'tgl_event'     => '2026-08-25',
            'lokasi'        => 'Gedung Singaperbangsa Karawang',
            'kategori'      => 'musik',
            'seats'         => true, // Menggunakan nomor kursi
            'harga_vip'     => 350000,
            'kapasitas_vip' => 50,
            'harga_reg'     => 150000,
            'kapasitas_reg' => 200,
            'thumbnail'     => 'events/dummy-jkt48.png', // Path simulasi di storage
            'status'        => 'open',
            'organizer_id'  => $organizer->id
        ]);

        // 4. Masukkan data dummy Event Tanpa Kursi (Hanya Reguler)
        Event::create([
            'nama_event'    => 'Workshop Pemrograman Laravel 11 Modern',
            'deskripsi'     => 'Sebuah acara workshop untuk mempelajari pemrograman php dengan framework laravel 11.',
            'waktu'         => '09:00',
            'tgl_event'     => '2026-06-12',
            'lokasi'        => 'Aula Universitas Buana Perjuangan',
            'kategori'      => 'workshop',
            'seats'         => false, 
            'harga_vip'     => null,
            'kapasitas_vip' => null,
            'harga_reg'     => 50000,
            'kapasitas_reg' => 100,
            'thumbnail'     => 'events/dummy-laravel.png',
            'status'        => 'open',
            'organizer_id'  => $organizer->id
        ]);

        Event::create([
            'nama_event'    => 'AI Engineer for future',
            'deskripsi'     => 'Sebuah acara workshop AI bagi para engineer muda sebagai bekal menghadapi era digital di masa depan.',
            'waktu'         => '07:00',
            'tgl_event'     => '2026-06-06',
            'lokasi'        => 'Auditorium UBP Karawang',
            'kategori'      => 'workshop',
            'seats'         => false,
            'harga_vip'     => null,
            'kapasitas_vip' => null,
            'harga_reg'     => 5000,
            'kapasitas_reg' => 150,
            'thumbnail'     => 'events/dummy-laravel.png',
            'status'        => 'open',
            'organizer_id'  => $organizer->id
        ]);
        

        $this->command->info('Data Event berhasil dimasukkan.');
    }
}
