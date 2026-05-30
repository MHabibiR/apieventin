-- 1. Insert Users (Menggunakan email baru agar tidak bentrok)
-- (Password untuk semua user adalah: password)
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `nomor_handphone`, `jenis_kelamin`, `role`, `created_at`, `updated_at`) VALUES
(100, 'Super Admin', 'admin_baru@eventin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08111111111', 'Laki-laki', 'main_admin', NOW(), NOW()),
(101, 'Organizer Musik', 'organizer_baru@eventin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08222222222', 'Perempuan', 'organizer', NOW(), NOW()),
(102, 'John Doe (Hadir)', 'john_baru@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08333333333', 'Laki-laki', 'user', NOW(), NOW()),
(103, 'Jane Smith (Belum Hadir)', 'jane_baru@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08444444444', 'Perempuan', 'user', NOW(), NOW()),
(104, 'Budi (Pending Bayar)', 'budi_baru@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08555555555', 'Laki-laki', 'user', NOW(), NOW());

-- 2. Insert Organizer (Kaitkan dengan user_id = 101)
INSERT INTO `organizer` (`id`, `user_id`, `nama_eo`, `file_proposal`, `status`, `created_at`, `updated_at`) VALUES
(100, 101, 'Melody Maker EO', 'proposal_melody.pdf', 'approved', NOW(), NOW());

-- 3. Insert Events (Kategori Musik, ada kursi VIP & Reguler)
INSERT INTO `events` (`id`, `organizer_id`, `nama_event`, `deskripsi`, `waktu`, `tgl_event`, `harga_vip`, `harga_reg`, `lokasi`, `seats`, `thumbnail`, `kapasitas_vip`, `kapasitas_reg`, `kategori`, `status`, `is_certificate_published`, `created_at`, `updated_at`) VALUES
(100, 100, 'Konser Harmoni Alam 2026', 'Konser megah menampilkan musisi ternama.', '19:00:00', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 500000, 200000, 'Gelora Bung Karno, Jakarta', 1, 'harmoni.jpg', 50, 200, 'musik', 'open', 0, NOW(), NOW());

-- 4. Insert Transactions (Simulasi berbagai status peserta)
-- TRX 1: John (Lunas & Checked-in -> Bisa ikut Lucky Draw & Download Sertifikat)
INSERT INTO `transactions` (`id`, `kode_transaksi`, `user_id`, `event_id`, `jenis_tiket`, `nomor_kursi`, `jumlah_tiket`, `total_harga`, `status_pembayaran`, `status_kehadiran`, `payment_url`, `is_lucky_draw_winner`, `created_at`, `updated_at`) VALUES
(100, 'EVT-LUNAS-HADIR', 102, 100, 'vip', 'A1', 1, 500000, 'success', 'checked_in', NULL, 0, NOW(), NOW());

-- TRX 2: Jane (Lunas, Tapi Belum Hadir -> Terdata di Seat Layout, tapi TIDAK bisa di-Lucky Draw)
INSERT INTO `transactions` (`id`, `kode_transaksi`, `user_id`, `event_id`, `jenis_tiket`, `nomor_kursi`, `jumlah_tiket`, `total_harga`, `status_pembayaran`, `status_kehadiran`, `payment_url`, `is_lucky_draw_winner`, `created_at`, `updated_at`) VALUES
(101, 'EVT-LUNAS-BELUM', 103, 100, 'reguler', 'B1', 1, 200000, 'success', 'belum_hadir', NULL, 0, NOW(), NOW());

-- TRX 3: Budi (Pending/Belum Bayar)
INSERT INTO `transactions` (`id`, `kode_transaksi`, `user_id`, `event_id`, `jenis_tiket`, `nomor_kursi`, `jumlah_tiket`, `total_harga`, `status_pembayaran`, `status_kehadiran`, `payment_url`, `is_lucky_draw_winner`, `created_at`, `updated_at`) VALUES
(102, 'EVT-PENDING', 104, 100, 'reguler', 'B2', 1, 200000, 'pending', 'belum_hadir', NULL, 0, NOW(), NOW());
