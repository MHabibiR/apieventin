$transactions = App\Models\Transaction::whereNotNull('nomor_kursi')->get();
$vipCount = 1;
$regCount = 1;

foreach ($transactions as $trx) {
    if (strtolower($trx->jenis_tiket) == 'vip') {
        $trx->nomor_kursi = 'VIP-' . $vipCount;
        $vipCount++;
    } else {
        $trx->nomor_kursi = 'REG-' . $regCount;
        $regCount++;
    }
    $trx->save();
}

echo "Updated " . $transactions->count() . " dummy transactions to new format.\n";
