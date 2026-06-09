<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Organizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ProfileController extends Controller
{
    public function getProfile(Request $request) 
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.'
            ], 401);
        }

        $user = User::with('organizer')->find($authUser->id);

        if(!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pengguna tidak dapat ditemukan.'
            ], 404);
        }

        $responseData = [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'nomor_handphone' => $user->nomor_handphone,
            'jenis_kelamin' => $user->jenis_kelamin,
            'role' => $user->role,
            'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            'profile_photo_path' => $user->profile_photo
        ];

        if ($user->role === 'organizer' && $user->organizer) {
            $responseData['nama_eo'] = $user->organizer->nama_eo;
            $responseData['no_rekening'] = $user->organizer->no_rekening;
            $responseData['atas_nama_rekening'] = $user->organizer->atas_nama_rekening;
            $responseData['nama_bank'] = $user->organizer->nama_bank;
            $responseData['file_proposal'] = $user->organizer->file_proposal ? asset('storage/' . $user->organizer->file_proposal) : null;
        }

        return response()->json([
            'success' => true,
            'message' => 'data profile berhasil diambil.',
            'data' => $responseData
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau pengguna tidak ditemukan.'
            ], 401);
        }

        // Cari data user asli di database agar bisa memanggil ->update()
        $user = User::find($authUser->id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan di database.'
            ], 404);
        }
        
        $rules = [
            'nama' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'nomor_handphone' => 'nullable|string|max:20',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ];
        
        if ($user->role === 'organizer') {
            $rules['nama_eo'] = 'required|string|max:255';
            $rules['no_rekening'] = 'nullable|string|max:50';
            $rules['atas_nama_rekening'] = 'nullable|string|max:255';
            $rules['nama_bank'] = 'nullable|string|max:100';
        }

        $validator = Validator::make($request->all(), $rules, [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar pada akun lain.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib diisi.',
            'jenis_kelamin.in' => 'Pilihan jenis kelamin tidak valid.',
            'nama_eo.required' => 'Nama Organizer wajib diisi.',
            'profile_photo.image' => 'File harus berupa gambar.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $file = $request->file('profile_photo');
            $path = $file->store('avatars', 'public');
            $user->profile_photo = $path;
        }

        // Update seluruh field yang dikirimkan dari aplikasi mobile
        $user->update($request->only('nama', 'email', 'nomor_handphone', 'jenis_kelamin'));

        $responseData = [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'nomor_handphone' => $user->nomor_handphone,
            'jenis_kelamin' => $user->jenis_kelamin,
            'role' => $user->role,
            'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            'profile_photo_path' => $user->profile_photo
        ];

        if ($user->role === 'organizer') {
            $organizer = Organizer::where('user_id', $user->id)->first();
            if ($organizer) {
                $organizer->update([
                    'nama_eo' => $request->nama_eo,
                    'no_rekening' => $request->no_rekening,
                    'atas_nama_rekening' => $request->atas_nama_rekening,
                    'nama_bank' => $request->nama_bank
                ]);
                $responseData['nama_eo'] = $organizer->nama_eo;
                $responseData['no_rekening'] = $organizer->no_rekening;
                $responseData['atas_nama_rekening'] = $organizer->atas_nama_rekening;
                $responseData['nama_bank'] = $organizer->nama_bank;
            }
        }

        // Return data yang sudah di-update
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $responseData
        ], 200);
    }

public function deleteAccount(Request $request)
{
    $authUser = $request->attributes->get('auth_user');

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Sesi tidak valid atau pengguna tidak ditemukan.'
        ], 401);
    }

    $user = User::find($authUser->id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Pengguna tidak ditemukan di database.'
        ], 404);
    }

    DB::beginTransaction();


try {
    if ($user->role === 'organizer') {
        
        // Ambil data organizer berdasarkan user_id sebelum dihapus
        $organizer = Organizer::where('user_id', $user->id)->first();

        if ($organizer) {
            if ($organizer->file_proposal && Storage::disk('public')->exists($organizer->file_proposal)) {
                
                Storage::disk('public')->delete($organizer->file_proposal);
            }

            // Hapus baris data dari tabel 'organizers'
            $organizer->delete();
        }
    }

    $user->delete();

    DB::commit();

    return response()->json([
        'success' => true,
        'message' => 'Akun Anda beserta berkas proposal berhasil dihapus secara permanen.'
    ], 200);

} catch (\Exception $e) {
    DB::rollback();

    return response()->json([
        'success' => false,
        'message' => 'Gagal menghapus akun. Terjadi kesalahan pada server.',
        'error' => $e->getMessage()
    ], 500);
}
}
}
