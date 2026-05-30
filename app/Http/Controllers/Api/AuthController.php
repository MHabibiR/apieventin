<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Firebase\JWT\JWT;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validasi input
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal bertipe 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.'
        ]);

        // response validasi gagal
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        // response validasi berhasil
        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil! Silakan login.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 1. Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // 2. Cek apakah user ada dan password cocok menggunakan Hash::check
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
            ], 401);
        }

        // 3. Generate Payload untuk JWT Token
        $payload = [
            'iss' => "laravel-jwt",
            'sub' => $user->id,
            'id' => $user->id,
            'role' => $user->role, // Membawa role user ('main_admin' / 'organizer')
            'iat' => time(),
            'exp' => time() + 60 * 60 * 24
        ];

        $token = JWT::encode($payload, env('JWT_SECRET_KEY'), 'HS256');

        // 4. Kembalikan Response
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ], 200);
    }

    public function loginOrganizer(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah.'
            ], 401);
        }

        if (!in_array($user->role, ['organizer', 'main_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {

            $payload = [
                'iss' => "laravel-jwt",
                'sub' => $user->id,
                'id' => $user->id,
                'role' => $user->role,
                'iat' => time(),
                'exp' => time() + 60 * 60 * 24
            ];

            $token = JWT::encode($payload, env('JWT_SECRET_KEY'), 'HS256');

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'token' => $token,
                'data' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function registerOrganizer(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nama_eo' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'file_proposal' => 'required|file|mimes:pdf|max:5120',
        ], [
            'nama.required' => 'Email wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'nama_eo.required' => 'Nama EO wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal bertipe 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'file_proposal.required' => 'File proposal wajib diunggah.',
            'file_proposal.mimes' => 'File proposal harus berformat PDF.',
            'file_proposal.max' => 'Ukuran file proposal maksimal adalah 5MB.',
        ]);

        // Response validasi gagal
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Insert data ke tabel 'users'
            $user = User::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'organizer',
            ]);

            // Proses upload file proposal ke folder storage/app/public/proposals
            if ($request->hasFile('file_proposal')) {
                $file = $request->file('file_proposal');
                $filePath = $file->store('proposals', 'public'); 
            }

            // Insert data ke tabel 'organizer'
            Organizer::create([
                'user_id' => $user->id,
                'nama_eo' => $request->nama_eo,
                'file_proposal' => $filePath,
                'status' => 'accept',
            ]);

            DB::commit();

            // Response registrasi berhasil
            return response()->json([
                'success' => true,
                'message' => 'Registrasi Organizer berhasil! Berkas pendaftaran Anda akan ditinjau oleh Admin.',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nama_eo' => $request->nama_eo,
                    'role' => $user->role
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mendaftar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        // 1. Ambil data user dari JWT Middleware
        $authUser = $request->attributes->get('auth_user');
    
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau pengguna tidak ditemukan.'
            ], 401);
        }
    
        // 2. Cari data lengkap user
        $user = User::find($authUser->id);
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan di database.'
            ], 404);
        }
    
        // 3. Validasi Input
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal bertipe 6 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'new_password.regex' => 'Password baru hanya boleh berisi huruf dan angka.',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
    
        // 4. Cek password lama dengan di database
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama yang Anda masukkan salah.'
            ], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
    
        // 5. Enkripsi password baru
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);
    
        // 6. response sukses
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.'
        ], 200);
    }
    
    public function logout(Request $request)
    {
        // Ambil data user dari JWT Middleware (untuk memastikan user memang sudah login)
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi sudah berakhir atau token tidak valid.'
            ], 401);
        }
        // 2. guard bawaan Laravel untuk membersihkan state session/auth lokal
        Auth::guard('web')->logout(); 
        // 3. Kembalikan response sukses ke Ionic
        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari akun. Sesi telah dihapus.'
        ], 200);
    }

        public function refreshToken(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak sah, gagal memperbarui token.'
            ], 401);
        }

        // Cari user di database 
        $user = User::find($authUser->id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.'
            ], 404);
        }

        // Buat Payload Baru
        $payload = [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(30)->timestamp // Reset otomatis masa berlaku baru
        ];

        $newToken = JWT::encode($payload, env('JWT_SECRET_KEY'), 'HS256');

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil diperbarui otomatis.',
            'token' => 'Bearer ' . $newToken
        ], 200);
    }
}