<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Transaction;

class EventController extends Controller
{
    
    public function index(Request $request)
    {
        try {
            $kategori = $request->query('kategori');
            $search = $request->query('search');

            $eventQuery = \App\Models\Event::with('organizer:id,nama_eo') 
                ->select('id', 'nama_event', 'tgl_event', 'harga_reg', 'organizer_id', 'thumbnail', 'kategori', 'status')
                ->where('status', 'open'); 

            if ($request->filled('kategori')) {
                $eventQuery->where('kategori', $kategori);
            }
            
            if ($request->filled('search')) {
                $searchKey = strtolower($request->query('search'));
                $eventQuery->whereRaw('LOWER(nama_event) LIKE ?', ["%{$searchKey}%"]);
            }

            $events = $eventQuery->orderBy('tgl_event', 'asc')->get();

            $events->map(function ($event) {
                if ($event->thumbnail) {
                    $event->poster_url = asset('storage/' . $event->thumbnail);
                } else {
                    $event->poster_url = 'https://via.placeholder.com/150';
                }
                return $event;
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar event aktif berhasil diambil.',
                'data'    => $events
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id) 
    {
        $event = Event::with('organizer:id,user_id,nama_eo,status')->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event tidak ditemukan.'
            ], 404);
        }

        if ($event->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Event telah ditutup.'
            ], 404);
        }

        if ($event->thumbnail) {
            $event->poster_url = asset('storage/' . $event->thumbnail);
        } else {
            $event->poster_url = 'https://via.placeholder.com/600x400';
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail event berhasil diambil.',
            'data'    => $event
        ], 200);
    }

    public function getSeats($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event tidak ditemukan.'
            ], 404);
        }

        if (!$event->seats) {
            return response()->json([
                'success' => false,
                'message' => 'Event ini tidak memiliki layout kursi.'
            ], 400);
        }

        // Ambil semua transaksi sukses dan pending untuk event ini
        $transactions = Transaction::where('event_id', $id)
            ->whereIn('status_pembayaran', ['success', 'pending'])
            ->get();

        $takenSeats = $transactions->pluck('nomor_kursi')->toArray();

        $seats = [];

        // Generate VIP Seats
        $vipCount = $event->kapasitas_vip ?? 0;
        for ($i = 1; $i <= $vipCount; $i++) {
            $seatNumber = 'VIP-' . $i;
            $seats[] = [
                'nomor_kursi' => $seatNumber,
                'tipe_kursi' => 'vip',
                'status' => in_array($seatNumber, $takenSeats) ? 'booked' : 'available'
            ];
        }

        // Generate Reguler Seats
        $regCount = $event->kapasitas_reg ?? 0;
        for ($i = 1; $i <= $regCount; $i++) {
            $seatNumber = 'REG-' . $i;
            $seats[] = [
                'nomor_kursi' => $seatNumber,
                'tipe_kursi' => 'reguler',
                'status' => in_array($seatNumber, $takenSeats) ? 'booked' : 'available'
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data layout kursi berhasil diambil.',
            'data' => $seats
        ], 200);
    }
}