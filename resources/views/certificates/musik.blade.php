<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat - {{ $event->nama_event }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; text-align: center; color: #333; margin: 0; padding: 0; background-color: #FDF2E9; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 50px; border: 15px solid #E74C3C; background-color: #ffffff; box-sizing: border-box; }
        .header { font-size: 38px; font-weight: bold; margin-bottom: 20px; color: #C0392B; letter-spacing: 2px; }
        .sub-header { font-size: 18px; margin-bottom: 40px; color: #7f8c8d; }
        .name { font-size: 45px; font-weight: bold; margin-bottom: 30px; color: #2C3E50; text-transform: uppercase; border-bottom: 2px solid #E74C3C; display: inline-block; padding-bottom: 10px; }
        .event-info { font-size: 18px; margin-bottom: 40px; line-height: 1.6; }
        .theme { font-weight: bold; color: #E74C3C; }
        .qr-code { margin-top: 30px; }
        .footer { margin-top: 40px; font-size: 12px; color: #95a5a6; border-top: 1px solid #ecf0f1; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">SERTIFIKAT APRESIASI</div>
        <div class="sub-header">Dianugerahkan Kepada:</div>
        <div class="name">{{ $user->nama }}</div>
        <div class="event-info">
            Sebagai bentuk penghargaan atas kehadiran dan partisipasinya dalam acara <span class="theme">MUSIK</span> yang luar biasa:<br><br>
            <strong>{{ $event->nama_event }}</strong><br><br>
            Diselenggarakan pada {{ $event->tgl_event }}
        </div>
        <div class="qr-code">
            <img src="data:image/svg+xml;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->generate(url('/api/verify-certificate/CERT-' . $transaction->kode_transaksi))) }}" alt="QR Code Verifikasi">
        </div>
        <div class="footer">
            ID Verifikasi: <strong>CERT-{{ $transaction->kode_transaksi }}</strong><br>
            Scan QR Code di atas untuk memverifikasi keaslian sertifikat ini secara digital.
        </div>
    </div>
</body>
</html>
