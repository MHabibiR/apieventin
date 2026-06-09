<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class OrganizerWebController extends Controller
{
    private function getOrganizerId(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if ($user && $user->role === 'organizer') {
            $organizer = \App\Models\Organizer::where('user_id', $user->id)->first();
            return $organizer ? $organizer->id : null;
        }
        return null;
    }

    public function getDashboardStats(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);

        $total_events = Event::where('organizer_id', $organizerId)->count();
        $total_participants = Transaction::whereHas('event', function ($query) use ($organizerId) {
            $query->where('organizer_id', $organizerId);
        })->count();

        $revenue = Transaction::whereHas('event', function ($query) use ($organizerId) {
            $query->where('organizer_id', $organizerId);
        })->whereIn('status_pembayaran', ['success', 'settlement'])->sum('total_harga');

        $recent = Transaction::with(['event', 'user'])
            ->whereHas('event', function ($query) use ($organizerId) {
                $query->where('organizer_id', $organizerId);
            })->latest()->take(5)->get();

        $recent_registrations = $recent->map(function($trx) {
            return [
                'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
                'email' => $trx->user ? $trx->user->email : '-',
                'status' => $trx->status_pembayaran,
                'event_title' => $trx->event ? $trx->event->nama_event : '-'
            ];
        });

        $earliest_event = Event::where('organizer_id', $organizerId)->where('status', 'open')->min('created_at');
        $days_to_show = 11;
        if ($earliest_event) {
            $days_since = \Carbon\Carbon::parse($earliest_event)->diffInDays(\Carbon\Carbon::today());
            $days_to_show = min(11, $days_since);
        } else {
            $days_to_show = 0;
        }

        $daily_chart = [];
        $max_daily = 0;
        for ($i = $days_to_show; $i >= 0; $i--) {
            $date = \Carbon\Carbon::today()->subDays($i);
            $count = Transaction::whereHas('event', function ($query) use ($organizerId) {
                $query->where('organizer_id', $organizerId);
            })->whereDate('created_at', $date)->count();
            
            $max_daily = max($max_daily, $count);
            
            $daily_chart[] = [
                'date' => $date->format('d M'),
                'count' => $count
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_events' => $total_events,
                'total_participants' => $total_participants,
                'total_revenue' => $revenue,
                'recent_registrations' => $recent_registrations,
                'daily_chart' => $daily_chart,
                'max_daily' => $max_daily > 0 ? $max_daily : 1
            ]
        ]);
    }

    public function getMyEvents(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);
        $events = Event::where('organizer_id', $organizerId)->get();

        return response()->json([
            'status' => 'success',
            'data' => $events
        ]);
    }

    public function storeMyEvent(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);

        $validated = $request->validate([
            'nama_event' => 'required|string',
            'tgl_event' => 'required|date',
            'waktu' => 'required|string',
            'harga_vip' => 'nullable|numeric',
            'harga_reg' => 'required|numeric',
            'lokasi' => 'required|string',
            'seats' => 'nullable|boolean',
            'kapasitas_vip' => 'nullable|integer',
            'kapasitas_reg' => 'required|integer',
            'kategori' => 'required|string',
            'deskripsi' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'file_proposal' => 'nullable|file|mimes:pdf|max:5120'
        ]);

        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $path = $file->store('thumbnails', 'public');
            $validated['thumbnail'] = $path;
        } else {
            $validated['thumbnail'] = 'default.jpg'; // Default thumbnail
        }
        
        if ($request->hasFile('file_proposal')) {
            $file = $request->file('file_proposal');
            $path = $file->store('proposals', 'public');
            $validated['file_proposal'] = $path;
        }
        
        if (!isset($validated['deskripsi'])) {
            $validated['deskripsi'] = ''; // Set empty string if null
        }

        $validated['organizer_id'] = $organizerId;
        $validated['status'] = 'pending'; // Ditetapkan ke pending untuk ditinjau admin
        
        // Convert string representations of boolean if necessary
        if (isset($validated['seats'])) {
            $validated['seats'] = filter_var($validated['seats'], FILTER_VALIDATE_BOOLEAN);
        } else {
            // Checkbox might not be sent if unchecked
            $validated['seats'] = false;
        }

        $event = Event::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Event created successfully',
            'data' => $event
        ]);
    }

    public function getParticipants(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);

        $query = Transaction::with(['event', 'user'])
            ->whereHas('event', function ($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            });

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('kode_transaksi', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->status != '' && $request->status != 'Semua Status') {
            if (strtolower($request->status) == 'lunas') {
                $query->whereIn('status_pembayaran', ['success', 'settlement']);
            } else if (strtolower($request->status) == 'pending') {
                $query->where('status_pembayaran', 'pending');
            }
        }

        $transactions = $query->get();

        $participants = $transactions->map(function ($trx) {
            return [
                'id' => $trx->id,
                'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
                'email' => $trx->user ? $trx->user->email : '-',
                'ticket_type' => $trx->jenis_tiket ?? 'Regular',
                'price' => $trx->total_harga,
                'status' => $trx->status_pembayaran,
                'booking_code' => $trx->kode_transaksi,
                'checkin_status' => $trx->status_kehadiran,
                'event_title' => $trx->event ? $trx->event->nama_event : '-'
            ];
        });

        // Stats hitung dari semua tanpa filter search agar informatif, atau pakai base query
        $baseQuery = Transaction::whereHas('event', function ($q) use ($organizerId) {
            $q->where('organizer_id', $organizerId);
        })->get();

        $stats = [
            'total_participants' => $baseQuery->count(),
            'paid_participants' => $baseQuery->whereIn('status_pembayaran', ['success', 'settlement'])->count(),
            'pending_participants' => $baseQuery->where('status_pembayaran', 'pending')->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $participants,
            'stats' => $stats
        ]);
    }

    public function exportCsv(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);

        $transactions = Transaction::with(['event', 'user'])
            ->whereHas('event', function ($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            })->get();

        $csvFileName = 'peserta_event_' . date('Ymd_His') . '.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['ID Transaksi', 'Nama Peserta', 'Email', 'Event', 'Kode Booking', 'Kategori Tiket', 'Status Pembayaran', 'Tanggal Daftar'];

        $callback = function() use($transactions, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($transactions as $trx) {
                $row['ID Transaksi']  = $trx->id;
                $row['Nama Peserta']  = $trx->user ? $trx->user->nama : 'Unknown';
                $row['Email']         = $trx->user ? $trx->user->email : '-';
                $row['Event']         = $trx->event ? $trx->event->nama_event : '-';
                $row['Kode Booking']  = $trx->kode_transaksi;
                $row['Kategori Tiket']= $trx->jenis_tiket;
                $row['Status Pembayaran'] = $trx->status_pembayaran;
                $row['Tanggal Daftar']= $trx->created_at ? $trx->created_at->format('Y-m-d H:i:s') : '';

                fputcsv($file, array($row['ID Transaksi'], $row['Nama Peserta'], $row['Email'], $row['Event'], $row['Kode Booking'], $row['Kategori Tiket'], $row['Status Pembayaran'], $row['Tanggal Daftar']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function storeManualParticipant(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);
        
        $request->validate([
            'nama' => 'required|string',
            'email' => 'required|email',
            'nomor_handphone' => 'required|string',
            'event_id' => 'required|integer',
            'jenis_tiket' => 'required|string'
        ]);

        // Verifikasi event milik organizer
        $event = Event::where('id', $request->event_id)->where('organizer_id', $organizerId)->first();
        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            // Cek atau buat user
            $user = \App\Models\User::firstOrCreate(
                ['email' => $request->email],
                [
                    'nama' => $request->nama,
                    'nomor_handphone' => $request->nomor_handphone,
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'role' => 'user'
                ]
            );

            $harga = strtolower($request->jenis_tiket) == 'vip' ? $event->harga_vip : $event->harga_reg;

            $transaction = Transaction::create([
                'kode_transaksi' => 'EVT-MANUAL-' . strtoupper(substr(uniqid(), -6)),
                'user_id' => $user->id,
                'event_id' => $event->id,
                'jenis_tiket' => $request->jenis_tiket,
                'jumlah_tiket' => 1,
                'total_harga' => $harga ?? 0,
                'status_pembayaran' => 'success',
                'status_kehadiran' => 'belum_hadir',
                'nomor_kursi' => $request->nomor_kursi,
                'nama_sertifikat' => $user->nama
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Peserta manual berhasil ditambahkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function markAsPaid($id)
    {
        $trx = Transaction::find($id);
        if (!$trx) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        
        $trx->update(['status_pembayaran' => 'success']);
        return response()->json(['status' => 'success']);
    }

    public function deleteParticipant($id)
    {
        $trx = Transaction::find($id);
        if (!$trx) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        
        $trx->delete();
        return response()->json(['status' => 'success']);
    }

    public function getCheckinHistory(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);

        $transactions = Transaction::with(['event', 'user'])
            ->whereHas('event', function ($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            })->orderBy('updated_at', 'desc')->get();

        $history = $transactions->where('status_kehadiran', 'checked_in')->map(function ($trx) {
            return [
                'id' => $trx->id,
                'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
                'email' => $trx->user ? $trx->user->email : '-',
                'booking_code' => $trx->kode_transaksi,
                'event_title' => $trx->event ? $trx->event->nama_event : '-',
                'checkin_time' => $trx->updated_at
            ];
        })->values();

        $stats = [
            'total_checked_in' => $transactions->where('status_kehadiran', 'checked_in')->count(),
            'remaining' => $transactions->where('status_kehadiran', 'belum_hadir')->whereIn('status_pembayaran', ['success', 'settlement'])->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $history,
            'stats' => $stats
        ]);
    }

    public function verifyCheckin(Request $request)
    {
        $request->validate(['kode_transaksi' => 'required|string']);
        
        $transaction = Transaction::with('user')->where('kode_transaksi', $request->kode_transaksi)->first();
        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        $transaction->update(['status_kehadiran' => 'checked_in']);

        $data = $transaction->toArray();
        $data['participant_name'] = $transaction->user ? $transaction->user->nama : 'Unknown';

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in verified',
            'data' => $data
        ]);
    }

    public function getSeating($id)
    {
        $event = \App\Models\Event::find($id);
        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        $transactions = Transaction::with('user')->where('event_id', $id)
            ->whereNotNull('nomor_kursi')
            ->get();
            
        $transactionsBySeat = $transactions->keyBy('nomor_kursi');

        $seats = [];
        $occupiedSeats = 0;

        // Generate VIP Seats
        for ($i = 1; $i <= $event->kapasitas_vip; $i++) {
            $seatNumber = 'VIP-' . $i;
            $trx = $transactionsBySeat->get($seatNumber);
            
            if ($trx) $occupiedSeats++;
            
            $seats[] = [
                'seat_number' => $seatNumber,
                'category_name' => 'VIP',
                'status' => $trx ? 'occupied' : 'available',
                'participant_name' => $trx && $trx->user ? $trx->user->nama : null,
                'booking_code' => $trx ? $trx->kode_transaksi : null
            ];
        }

        // Generate Regular Seats
        for ($i = 1; $i <= $event->kapasitas_reg; $i++) {
            $seatNumber = 'REG-' . $i;
            $trx = $transactionsBySeat->get($seatNumber);
            
            if ($trx) $occupiedSeats++;
            
            $seats[] = [
                'seat_number' => $seatNumber,
                'category_name' => 'Reguler',
                'status' => $trx ? 'occupied' : 'available',
                'participant_name' => $trx && $trx->user ? $trx->user->nama : null,
                'booking_code' => $trx ? $trx->kode_transaksi : null
            ];
        }
        
        $totalSeats = $event->kapasitas_vip + $event->kapasitas_reg;

        return response()->json([
            'status' => 'success',
            'data' => $seats,
            'stats' => [
                'total_seats' => $totalSeats,
                'occupied_seats' => $occupiedSeats,
                'available_seats' => $totalSeats - $occupiedSeats
            ]
        ]);
    }

    public function updateSeating(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
            'nomor_kursi' => 'required|string'
        ]);

        $transaction = Transaction::find($request->transaction_id);
        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        $transaction->update(['nomor_kursi' => $request->nomor_kursi]);

        return response()->json([
            'status' => 'success',
            'message' => 'Seating updated',
            'data' => $transaction
        ]);
    }

    public function getLuckyDrawData($id)
    {
        $eligible = Transaction::with('user')
            ->where('event_id', $id)
            ->whereIn('status_pembayaran', ['success', 'settlement'])
            ->where('status_kehadiran', 'checked_in')
            ->where('is_lucky_draw_winner', false)
            ->get()->map(function($trx) {
                return [
                    'id' => $trx->id,
                    'name' => $trx->user ? $trx->user->nama : 'Unknown',
                    'booking_code' => $trx->kode_transaksi,
                ];
            });
            
        $winners = Transaction::with('user')
            ->where('event_id', $id)
            ->where('is_lucky_draw_winner', true)
            ->orderBy('updated_at', 'desc')
            ->get()->map(function($trx) {
                return [
                    'id' => $trx->id,
                    'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
                    'booking_code' => $trx->kode_transaksi,
                ];
            });

        return response()->json([
            'status' => 'success',
            'eligible_participants' => $eligible,
            'winners' => $winners
        ]);
    }

    public function storeLuckyDrawWinner(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer',
            'participant_id' => 'required|integer',
        ]);
        
        $transaction = Transaction::where('id', $request->participant_id)
            ->where('event_id', $request->event_id)
            ->first();
            
        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }
        
        $transaction->update(['is_lucky_draw_winner' => true]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Winner stored successfully'
        ]);
    }

    public function getCertificates($id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        $transactions = Transaction::with('user')
            ->where('event_id', $id)
            ->where('status_kehadiran', 'checked_in')
            ->get();

        $statusStr = $event->is_certificate_published ? 'sent' : 'pending';

        $participants = $transactions->map(function($trx) use ($statusStr) {
            return [
                'name' => $trx->user ? $trx->user->nama : 'Unknown',
                'email' => $trx->user ? $trx->user->email : '-',
                'checkin_time' => $trx->updated_at,
                'status' => $statusStr
            ];
        });

        $eligibleCount = $participants->count();

        return response()->json([
            'status' => 'success',
            'data' => $participants,
            'stats' => [
                'eligible' => $eligibleCount,
                'sent' => $event->is_certificate_published ? $eligibleCount : 0, 
                'pending' => $event->is_certificate_published ? 0 : $eligibleCount
            ]
        ]);
    }

    public function publishCertificate(Request $request)
    {
        $request->validate(['event_id' => 'required|integer']);
        
        $event = Event::find($request->event_id);
        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        $event->update(['is_certificate_published' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Certificates published'
        ]);
    }
    public function getPendingTransactions(Request $request)
    {
        $organizerId = $this->getOrganizerId($request);
        if (!$organizerId) {
            return response()->json(['success' => false, 'message' => 'Organizer tidak ditemukan.'], 403);
        }

        $transactions = Transaction::with(['user', 'event'])
            ->whereHas('event', function ($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            })
            ->where('status_pembayaran', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $transactions->map(function ($trx) {
            return [
                'id' => $trx->id,
                'kode_transaksi' => $trx->kode_transaksi,
                'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
                'event_title' => $trx->event ? $trx->event->nama_event : 'Unknown Event',
                'ticket_type' => $trx->jenis_tiket ?? 'Regular',
                'total_harga' => $trx->total_harga,
                'payment_url' => $trx->payment_url ? url('storage/' . $trx->payment_url) : null,
                'created_at' => $trx->created_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function validateTransaction(Request $request, $id)
    {
        $organizerId = $this->getOrganizerId($request);
        if (!$organizerId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $transaction = Transaction::where('id', $id)
            ->whereHas('event', function ($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            })
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $action = $request->input('action'); // 'approve' or 'reject'
        
        if ($action === 'approve') {
            if ($transaction->status_pembayaran !== 'success') {
                $transaction->status_pembayaran = 'success';
                $transaction->save();
                
                $event = Event::find($transaction->event_id);
                if ($event) {
                    if ($transaction->jenis_tiket === 'vip') {
                        $event->decrement('kapasitas_vip', 1);
                    } else {
                        $event->decrement('kapasitas_reg', 1);
                    }
                }
            }
            return response()->json(['success' => true, 'message' => 'Transaksi berhasil disetujui.']);
        } elseif ($action === 'reject') {
            $transaction->status_pembayaran = 'failed';
            $transaction->save();
            return response()->json(['success' => true, 'message' => 'Transaksi telah ditolak.']);
        }

        return response()->json(['success' => false, 'message' => 'Aksi tidak valid.'], 400);
    }
}
