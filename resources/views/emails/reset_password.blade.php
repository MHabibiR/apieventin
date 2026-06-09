<!DOCTYPE html>
<html>
<head>
    <title>Reset Kata Sandi EventIn</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2>Halo,</h2>
    <p>Kami menerima permintaan untuk mereset kata sandi akun EventIn Anda.</p>
    <p>Gunakan kode OTP di bawah ini untuk melanjutkan proses reset pada aplikasi mobile:</p>
    
    <div style="background-color: #f4f5f8; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;">
        <h1 style="letter-spacing: 6px; color: #3880ff; margin: 0; font-size: 32px;">{{ $otp }}</h1>
    </div>
    
    <p>Kode OTP ini hanya berlaku selama 5 menit. Demi keamanan akun Anda, jangan bagikan kode ini kepada siapa pun.</p>
    
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
        <p>Atau jika Anda menggunakan versi Web, silakan klik tombol di bawah ini untuk mengatur ulang kata sandi Anda:</p>
        <p style="text-align: center; margin: 25px 0;">
            <a href="http://127.0.0.1:8000/reset_password?token={{ $otp }}&email={{ urlencode($email) }}" style="background-color: #00bcd4; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Reset Kata Sandi Web</a>
        </p>
    </div>
    
    <p>Jika Anda tidak merasa mengajukan permintaan ini, silakan abaikan email ini.</p>
    <br>
    <p>Salam hangat,<br><strong>Tim EventIn</strong></p>
</body>
</html>