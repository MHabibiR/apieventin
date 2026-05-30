<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminWebController extends Controller
{
    public function getDashboardStats()
    {
        $total_events = Event::count();
        $total_organizers = Organizer::where('status', 'verified')->count();
        $total_participants = Transaction::where('status_pembayaran', 'settlement')->count();
        $pending_proposals = Proposal::where('status', 'pending')->count();
        
        $recent_events_data = Event::with('organizer.user')->latest()->take(5)->get();
        $recent_events = $recent_events_data->map(function ($event) {
            return [
                'title' => $event->nama_event,
                'status' => $event->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_events' => $total_events,
                'total_organizers' => $total_organizers,
                'total_participants' => $total_participants,
                'pending_proposals' => $pending_proposals,
                'recent_events' => $recent_events
            ]
        ]);
    }

    public function getAllOrganizers()
    {
        $organizers = Organizer::with('user')->withCount('events')->latest()->get();

        $mappedOrganizers = $organizers->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->nama_eo,
                'type' => 'Organizer',
                'email' => $org->user ? $org->user->email : '-',
                'phone' => $org->user ? $org->user->nomor_handphone : '-',
                'total_events' => $org->events_count, 
                'status' => $org->status,
            ];
        });

        $stats = [
            'pending' => $organizers->where('status', 'pending')->count(),
            'total' => $organizers->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $mappedOrganizers,
            'stats' => $stats
        ]);
    }

    public function updateOrganizerStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:verified,rejected,pending,blocked']);
        
        $organizer = Organizer::find($id);
        if (!$organizer) {
            return response()->json(['status' => 'error', 'message' => 'Organizer not found'], 404);
        }

        $organizer->update(['status' => $request->status]);

        return response()->json([
            'status' => 'success',
            'message' => 'Organizer status updated successfully',
            'data' => $organizer
        ]);
    }

    public function submitProposal(Request $request)
    {
        // Untuk mobile app mensubmit form proposal
        $validated = $request->validate([
            'nama_pengaju' => 'required|string',
            'email_pengaju' => 'required|email',
            'password_pengaju' => 'required|string|min:6',
            'no_hp_pengaju' => 'nullable|string',
            'nama_eo' => 'required|string',
            'file_proposal' => 'required|string', // Atur sebagai string path atau handle file upload
            'nama_event' => 'required|string',
            'kategori' => 'required|in:musik,pameran,seminar,workshop',
            'deskripsi' => 'required|string',
            'tgl_event' => 'required|date',
            'waktu' => 'required|string',
            'lokasi' => 'required|string',
            'harga_reg' => 'nullable|integer',
            'harga_vip' => 'nullable|integer',
            'kapasitas_reg' => 'nullable|integer',
            'kapasitas_vip' => 'nullable|integer',
            'thumbnail' => 'nullable|string',
        ]);

        $validated['password_pengaju'] = Hash::make($validated['password_pengaju']);
        $validated['status'] = 'pending';

        $proposal = Proposal::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal submitted successfully',
            'data' => $proposal
        ]);
    }

    public function getAllProposals()
    {
        $proposals = Proposal::latest()->get();

        $mappedProposals = $proposals->map(function ($prop) {
            return [
                'id' => $prop->id,
                'title' => $prop->nama_event,
                'category' => $prop->kategori,
                'ticket_type' => ($prop->harga_reg > 0) ? 'Paid' : 'Free',
                'price' => $prop->harga_reg ?? 0,
                'organizer_name' => $prop->nama_eo,
                'created_at' => $prop->created_at,
                'status' => $prop->status,
                'total_capacity' => ($prop->kapasitas_reg ?? 0) + ($prop->kapasitas_vip ?? 0),
                'date_start' => $prop->tgl_event,
                'venue_name' => $prop->lokasi,
                'description' => $prop->deskripsi,
            ];
        });

        $stats = [
            'pending' => $proposals->where('status', 'pending')->count(),
            'approved' => $proposals->where('status', 'approved')->count(),
            'rejected' => $proposals->where('status', 'rejected')->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $mappedProposals,
            'stats' => $stats
        ]);
    }

    public function updateProposalStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:approved,rejected']);
        
        $proposal = Proposal::find($id);
        if (!$proposal) {
            return response()->json(['status' => 'error', 'message' => 'Proposal not found'], 404);
        }

        if ($request->status === 'approved' && $proposal->status === 'pending') {
            DB::beginTransaction();
            try {
                // 1. Buat atau update User
                $user = User::firstOrCreate(
                    ['email' => $proposal->email_pengaju],
                    [
                        'nama' => $proposal->nama_pengaju,
                        'password' => $proposal->password_pengaju, // Sudah di-hash saat submit
                        'nomor_handphone' => $proposal->no_hp_pengaju,
                        'role' => 'organizer'
                    ]
                );

                if ($user->role !== 'organizer') {
                    $user->update(['role' => 'organizer']);
                }

                // 2. Buat Organizer
                $organizer = Organizer::create([
                    'user_id' => $user->id,
                    'nama_eo' => $proposal->nama_eo,
                    'file_proposal' => $proposal->file_proposal,
                    'status' => 'verified'
                ]);

                // 3. Buat Event
                Event::create([
                    'organizer_id' => $organizer->id,
                    'nama_event' => $proposal->nama_event,
                    'deskripsi' => $proposal->deskripsi,
                    'waktu' => $proposal->waktu,
                    'tgl_event' => $proposal->tgl_event,
                    'harga_vip' => $proposal->harga_vip,
                    'harga_reg' => $proposal->harga_reg,
                    'lokasi' => $proposal->lokasi,
                    'seats' => 0, // Default seats
                    'thumbnail' => $proposal->thumbnail ?? 'default.jpg',
                    'kapasitas_vip' => $proposal->kapasitas_vip,
                    'kapasitas_reg' => $proposal->kapasitas_reg,
                    'kategori' => $proposal->kategori,
                    'status' => 'open' // Buka pendaftaran
                ]);

                // 4. Update Proposal Status
                $proposal->update(['status' => 'approved']);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['status' => 'error', 'message' => 'Gagal memproses approval: ' . $e->getMessage()], 500);
            }
        } else {
            $proposal->update(['status' => $request->status]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal status updated successfully',
            'data' => $proposal
        ]);
    }

    public function getAllEvents()
    {
        $events = Event::with('organizer.user')->latest()->get();
        
        $mappedEvents = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->nama_event,
                'venue_name' => $event->lokasi,
                'date_start' => $event->tgl_event,
                'total_capacity' => ($event->kapasitas_reg ?? 0) + ($event->kapasitas_vip ?? 0),
                'status' => $event->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $mappedEvents
        ]);
    }

    public function storeEvent(Request $request)
    {
        // (Tetap sama seperti sebelumnya)
        $validated = $request->validate([
            'nama_event' => 'required|string',
            'tgl_event' => 'required|date',
            'organizer_id' => 'required|exists:organizer,id',
            'waktu' => 'required|string',
            'harga_vip' => 'nullable|numeric',
            'harga_reg' => 'required|numeric',
            'lokasi' => 'required|string',
            'seats' => 'nullable|integer',
            'kapasitas_vip' => 'nullable|integer',
            'kapasitas_reg' => 'required|integer',
            'kategori' => 'required|string',
        ]);

        $event = Event::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Event created successfully',
            'data' => $event
        ]);
    }

    public function deleteEvent($id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        $event->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Event deleted successfully'
        ]);
    }
}
