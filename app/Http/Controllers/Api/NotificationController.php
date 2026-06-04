<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Proposal;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!$user) {
            return response()->json([], 200);
        }

        $notifications = [];

        if ($user->role === 'main_admin') {
            // Notifications for Admin
            $pendingProposals = Proposal::where('status', 'pending')->count();
            if ($pendingProposals > 0) {
                $notifications[] = [
                    'type' => 'Pendaftaran EO',
                    'message' => "Ada {$pendingProposals} pendaftaran Organizer baru menunggu persetujuan."
                ];
            }

            $pendingEvents = Event::where('status', 'pending')->count();
            if ($pendingEvents > 0) {
                $notifications[] = [
                    'type' => 'Proposal Event',
                    'message' => "Ada {$pendingEvents} proposal event baru menunggu persetujuan."
                ];
            }
        } else if ($user->role === 'organizer') {
            // Notifications for Organizer
            $organizer = Organizer::where('user_id', $user->id)->first();
            
            if ($organizer) {
                $pendingEvents = Event::where('organizer_id', $organizer->id)->where('status', 'pending')->count();
                if ($pendingEvents > 0) {
                    $notifications[] = [
                        'type' => 'Status Event',
                        'message' => "Anda memiliki {$pendingEvents} event yang masih dalam tahap peninjauan admin (Pending)."
                    ];
                }

                $activeEvents = Event::where('organizer_id', $organizer->id)->where('status', 'open')->count();
                if ($activeEvents > 0) {
                    $notifications[] = [
                        'type' => 'Status Event',
                        'message' => "Ada {$activeEvents} event Anda yang sedang aktif dan terbuka untuk publik."
                    ];
                }
                
                if ($organizer->status === 'pending') {
                    $notifications[] = [
                        'type' => 'Status Akun',
                        'message' => "Akun Organizer Anda saat ini sedang ditinjau oleh Admin."
                    ];
                }
            }
        }

        return response()->json($notifications, 200);
    }
}
