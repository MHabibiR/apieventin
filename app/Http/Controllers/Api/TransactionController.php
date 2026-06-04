<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Midtrans\Config;
use Midtrans\Snap;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TransactionController extends Controller
{
    public function __construct()
    {
    }

    // FUNGSI CHECKOUT MANUAL
    public function checkoutManual(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid, silakan login ulang.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'event_id'    => 'required|integer',
            'jenis_tiket' => 'required|in:vip,reguler',
            'nomor_kursi' => 'nullable|string|max:50',
            'bukti_pembayaran' => 'nullable|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        $event = Event::find($request->event_id);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event tidak ditemukan.'], 404);
        }

        $hargaSatuan = ($request->jenis_tiket === 'vip') ? $event->harga_vip : $event->harga_reg;
        
        if ($hargaSatuan > 0 && !$request->hasFile('bukti_pembayaran')) {
            return response()->json(['success' => false, 'message' => 'Bukti pembayaran wajib diunggah untuk event berbayar.'], 422);
        }

        if ($event->seats && !empty($request->nomor_kursi)) {
            $isSeatTaken = Transaction::where('event_id', $request->event_id)
                ->where('nomor_kursi', $request->nomor_kursi)
                ->whereIn('status_pembayaran', ['pending', 'success'])
                ->exists();

            if ($isSeatTaken) {
                return response()->json(['success' => false, 'message' => 'Maaf, kursi ini baru saja dipesan orang lain.'], 400);
            }
        } else if ($event->seats && empty($request->nomor_kursi)) {
            return response()->json(['success' => false, 'message' => 'Nomor kursi wajib dipilih untuk event ini.'], 400);
        }

        $sisaKuota = ($request->jenis_tiket === 'vip') ? $event->kapasitas_vip : $event->kapasitas_reg;
        if ($sisaKuota < 1) {
            return response()->json(['success' => false, 'message' => 'Maaf, kuota tiket untuk kelas ini sudah habis.'], 400);
        }

        $jumlahTiket = 1;
        $totalHarga  = $hargaSatuan * $jumlahTiket;
        $kodeTransaksi = 'EVT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $paymentUrl = null;
        $statusPembayaran = 'pending';

        if ($hargaSatuan > 0) {
            if ($request->hasFile('bukti_pembayaran')) {
                $path = $request->file('bukti_pembayaran')->store('bukti_bayar', 'public');
                $paymentUrl = $path;
            }
        } else {
            $statusPembayaran = 'success';
            if ($request->jenis_tiket === 'vip') {
                $event->decrement('kapasitas_vip', 1);
            } else {
                $event->decrement('kapasitas_reg', 1);
            }
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'kode_transaksi'    => $kodeTransaksi,
                'user_id'           => $authUser->id,
                'event_id'          => $event->id,
                'jenis_tiket'       => $request->jenis_tiket,
                'nomor_kursi'       => $request->nomor_kursi,
                'jumlah_tiket'      => 1,
                'total_harga'       => $totalHarga,
                'status_pembayaran' => $statusPembayaran,
                'status_kehadiran'  => 'belum_hadir',
                'payment_url'       => $paymentUrl,
            ]);

            DB::commit();

            if ($hargaSatuan > 0) {
                $this->kirimNotifTelegram($transaction, $event, $authUser);
            }

            return response()->json([
                'success' => true,
                'message' => $hargaSatuan > 0 ? 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi dari penyelenggara.' : 'Berhasil mendaftar event gratis.',
                'data'    => [
                    'kode_transaksi' => $transaction->kode_transaksi,
                    'status'         => $statusPembayaran
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses transaksi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getMyTickets(Request $request)
    {
        // 1. Ambil data user dari JWT yang dilewatin middleware
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
        }

        // 2. Ambil transaksi milik user tersebut yang statusnya SUDAH BERHASIL (Lunas)
        // Kita sertakan data 'event' menggunakan eager loading (with)
        $tickets = \App\Models\Transaction::with('event')
            ->where('user_id', $authUser->id)
            ->whereIn('status_pembayaran', ['success', 'settlement'])
            ->orderBy('created_at', 'desc')
            ->get();

        $tickets->map(function ($ticket) {
            $ticket->qr_url = url('/api/ticket-qr/' . $ticket->kode_transaksi);
            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar tiket berhasil dimuat.',
            'data' => $tickets
        ], 200);
    }

    public function showDetailTicket(Request $request, $kode_transaksi)
    {
        $authUser = $request->get('auth_user');
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Sesi Kadaluarsa.'], 401);
        }

        $ticket = \App\Models\Transaction::with('event')->where('kode_transaksi', $kode_transaksi)->where('user_id', $authUser->id)->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        if ($ticket->status_pembayaran === 'success' || $ticket->status_pembayaran === 'settlement') {
            $ticket->qr_url = url('/api/ticket-qr/' . $ticket->kode_transaksi);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail tiket berhasil ditemukan.',
            'data' => $ticket
        ], 200);
    }

    public function getTicketQr($kode_transaksi)
    {
        $transaction = Transaction::where('kode_transaksi', $kode_transaksi)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Tiket tidak ditemukan'], 404);
        }

        if ($transaction->status_pembayaran !== 'success' && $transaction->status_pembayaran !== 'settlement') {
            return response('Tiket belum lunas', 403);
        }

        $image = QrCode::format('svg')->size(300)->margin(1)->generate($kode_transaksi);
        
        return response($image)->header('Content-Type', 'image/svg+xml');
    }

    public function getMyCertificates(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
        }

        $certificates = Transaction::with('event')
            ->where('user_id', $authUser->id)
            ->where('status_kehadiran', 'checked_in')
            ->whereHas('event', function($q) {
                $q->where('is_certificate_published', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $certificates->map(function ($cert) {
            $cert->download_url = url('/api/download-certificate/' . $cert->kode_transaksi);
            return $cert;
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar sertifikat berhasil dimuat.',
            'data' => $certificates
        ], 200);
    }

    public function downloadCertificate(Request $request, $kode_transaksi)
    {
        $authUser = $request->attributes->get('auth_user');
        
        $transaction = Transaction::with(['event', 'user'])
            ->where('kode_transaksi', $kode_transaksi)
            ->where('user_id', $authUser->id)
            ->where('status_kehadiran', 'checked_in')
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Sertifikat tidak ditemukan atau Anda belum check-in.'], 404);
        }

        if (!$transaction->event->is_certificate_published) {
            return response()->json(['message' => 'Sertifikat untuk event ini belum dipublikasikan.'], 403);
        }

        $kategori = $transaction->event->kategori ?? 'seminar';
        $viewName = "certificates.{$kategori}";
        if (!view()->exists($viewName)) {
            $viewName = "certificates.seminar"; // fallback
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewName, [
            'user' => $transaction->user,
            'event' => $transaction->event,
            'transaction' => $transaction
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Sertifikat-' . $transaction->event->nama_event . '-' . $transaction->user->nama . '.pdf');
    }

    public function verifyCertificate($cert_id)
    {
        $kode_transaksi = str_replace('CERT-', '', $cert_id);

        $transaction = Transaction::with(['event', 'user'])->where('kode_transaksi', $kode_transaksi)->where('status_kehadiran', 'checked_in')->first();

        if (!$transaction || !$transaction->event->is_certificate_published) {
            return response()->json(['success' => false, 'message' => 'Sertifikat tidak valid atau tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sertifikat Valid!',
            'data' => [
                'nama_peserta' => $transaction->user->nama,
                'nama_event' => $transaction->event->nama_event,
                'kategori' => $transaction->event->kategori,
                'tgl_event' => $transaction->event->tgl_event,
                'status_kehadiran' => $transaction->status_kehadiran
            ]
        ], 200);
    }

    private function kirimNotifTelegram($transaction, $event, $user)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID');

        if (!$token || !$chatId) return;

        // Susun teks rapi berformat Markdown untuk layar HP Admin
        $pesan = "*ADA PEMBAYARAN BARU (EventIn)* 🚨\n\n"
               . "• *Kode Transaksi:* `{$transaction->kode_transaksi}`\n"
               . "• *Nama Pembeli:* {$user->nama}\n"
               . "• *Nama Event:* {$event->nama_event}\n"
               . "• *Kategori Tiket:* " . strtoupper($transaction->jenis_tiket) . "\n"
               . "• *Total Tagihan:* Rp " . number_format($transaction->total_harga, 0, ',', '.') . "\n\n"
               . "*DATA REKENING PENGIRIM:*\n"
               . "*Status saat ini:* `Pending`\n"
               . "Silakan buka Dashboard Organizer EventIn untuk memeriksa keaslian bukti gambar dan mengubah status konfirmasi.";

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $pesan,
            'parse_mode' => 'Markdown'
        ]);
    }
}

