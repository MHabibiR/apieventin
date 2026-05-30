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
        })->where('status_pembayaran', 'settlement')->sum('total_harga');

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

        return response()->json([
            'status' => 'success',
            'data' => [
                'active_events' => $total_events,
                'total_participants' => $total_participants,
                'total_revenue' => $revenue,
                'recent_registrations' => $recent_registrations
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
            'seats' => 'nullable|integer',
            'kapasitas_vip' => 'nullable|integer',
            'kapasitas_reg' => 'required|integer',
            'kategori' => 'required|string',
        ]);

        $validated['organizer_id'] = $organizerId;

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

        $transactions = Transaction::with(['event', 'user'])
            ->whereHas('event', function ($query) use ($organizerId) {
                $query->where('organizer_id', $organizerId);
            })->get();

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

        $stats = [
            'total_participants' => $transactions->count(),
            'paid_participants' => $transactions->where('status_pembayaran', 'settlement')->count(),
            'pending_participants' => $transactions->where('status_pembayaran', 'pending')->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $participants,
            'stats' => $stats
        ]);
    }

    public function getCheckinHistory(Request $request)
    {
        return $this->getParticipants($request);
    }

    public function verifyCheckin(Request $request)
    {
        $request->validate(['kode_transaksi' => 'required|string']);
        
        $transaction = Transaction::where('kode_transaksi', $request->kode_transaksi)->first();
        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        $transaction->update(['status_kehadiran' => 'checked_in']);

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in verified',
            'data' => $transaction
        ]);
    }

    public function getSeating($id)
    {
        $transactions = Transaction::where('event_id', $id)
            ->whereNotNull('nomor_kursi')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transactions
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

    public function drawLuckyDraw($id)
    {
        $winner = Transaction::with('user')
            ->where('event_id', $id)
            ->whereIn('status_pembayaran', ['success', 'settlement'])
            ->where('status_kehadiran', 'checked_in')
            ->where('is_lucky_draw_winner', false)
            ->inRandomOrder()
            ->first();

        if (!$winner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada peserta yang memenuhi syarat untuk undian ini.'
            ], 404);
        }

        $winner->update(['is_lucky_draw_winner' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pemenang berhasil diundi!',
            'data' => [
                'nama_peserta' => $winner->user ? $winner->user->nama : 'Unknown',
                'email' => $winner->user ? $winner->user->email : '-',
                'nomor_tiket' => $winner->kode_transaksi,
                'jenis_tiket' => $winner->jenis_tiket,
                'nomor_kursi' => $winner->nomor_kursi
            ]
        ]);
    }

    public function getLuckyDrawWinners($id)
    {
        $winners = Transaction::with('user')
            ->where('event_id', $id)
            ->where('is_lucky_draw_winner', true)
            ->get()
            ->map(function ($winner) {
                return [
                    'nama_peserta' => $winner->user ? $winner->user->nama : 'Unknown',
                    'email' => $winner->user ? $winner->user->email : '-',
                    'nomor_tiket' => $winner->kode_transaksi,
                    'jenis_tiket' => $winner->jenis_tiket,
                    'nomor_kursi' => $winner->nomor_kursi,
                    'waktu_menang' => $winner->updated_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $winners
        ]);
    }

    public function getCertificates($id)
    {
        $participants = Transaction::where('event_id', $id)
            ->where('status_kehadiran', 'checked_in')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $participants,
            'stats' => [
                'eligible' => $participants->count(),
                'sent' => 0, 
                'pending' => $participants->count()
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
}
