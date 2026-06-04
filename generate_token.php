<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Firebase\JWT\JWT;

$user = User::where('email', 'organizer_baru@eventin.com')->first();
$payload = [
    'iss' => "laravel-jwt",
    'sub' => $user->id,
    'id' => $user->id,
    'role' => $user->role,
    'iat' => time(),
    'exp' => time() + 60 * 60 * 24
];
$token = JWT::encode($payload, env('JWT_SECRET_KEY'), 'HS256');
echo $token;
