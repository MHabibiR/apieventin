<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
// use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. Ambil token dari Header
        $header = $request->header('Authorization');
        
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak disediakan atau format salah.'
            ], 401);
        }

        // Memotong teks 'Bearer ' (7 karakter) untuk mengambil string token murninya
        $token = substr($header, 7);

        try {
            // 2. Decode token menggunakan Secret Key dari .env
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET_KEY'), 'HS256'));
            
            // 3. Validasi Hak Akses (Role)
            if (!in_array($decoded->role, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak! Anda tidak memiliki hak akses untuk halaman ini.'
                ], 403);
            }

            // Fallback for old tokens that used 'sub' instead of 'id'
            if (!isset($decoded->id) && isset($decoded->sub)) {
                $decoded->id = $decoded->sub;
            }

            // Menyisipkan data user yang ter-decode ke dalam request agar bisa dipakai di Controller lain
            $request->attributes->add(['auth_user' => $decoded]);

            return $next($request);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau telah kedaluwarsa.',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}
