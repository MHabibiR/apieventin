$id = 100;
$eligible = App\Models\Transaction::with('user')
    ->where('event_id', $id)
    ->whereIn('status_pembayaran', ['success', 'settlement'])
    ->where('status_kehadiran', 'checked_in')
    ->where('is_lucky_draw_winner', false)
    ->get()->map(function($trx) {
        return [
            'id' => $trx->id,
            'name' => $trx->user ? $trx->user->nama : 'Unknown',
            'booking_code' => $trx->kode_transaksi,
        ];
    });
    
$winners = App\Models\Transaction::with('user')
    ->where('event_id', $id)
    ->where('is_lucky_draw_winner', true)
    ->orderBy('updated_at', 'desc')
    ->get()->map(function($trx) {
        return [
            'id' => $trx->id,
            'participant_name' => $trx->user ? $trx->user->nama : 'Unknown',
            'booking_code' => $trx->kode_transaksi,
        ];
    });

echo "Eligible count: " . count($eligible) . "\n";
echo "Winners count: " . count($winners) . "\n";
echo json_encode(['eligible' => $eligible, 'winners' => $winners], JSON_PRETTY_PRINT);
