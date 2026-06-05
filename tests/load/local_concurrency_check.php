<?php

/**
 * Local concurrency CORRECTNESS check (bukan uji throughput produksi).
 *
 * Membuktikan di bawah koneksi paralel NYATA:
 *  - submit duplikat (idempotency_key & doc_ref sama) → TIDAK membuat baris ganda,
 *    TIDAK ada HTTP 500 (1062 ditangani idempoten), hanya 1 approval_request_id.
 *  - tidak ada deadlock yang lolos sebagai 500 pada jalur submit konkuren.
 *
 * Prasyarat: `php artisan serve` jalan + `php artisan loadtest:seed` sudah dijalankan
 * pada DB yang sama. Jalankan:
 *   BASE_URL=http://127.0.0.1:8000 CLIENT_KEY=... SECRET=... DOC_TYPE=1 N=25 \
 *   php tests/load/local_concurrency_check.php
 *
 * Exit 0 = lulus, 1 = gagal.
 */

$base   = getenv('BASE_URL')   ?: 'http://127.0.0.1:8000';
$key    = getenv('CLIENT_KEY') ?: 'LOADTEST_KEY';
$secret = getenv('SECRET')     ?: 'loadtest-secret-12345678';
$docType = (int) (getenv('DOC_TYPE') ?: 1);
$n      = (int) (getenv('N') ?: 25);

$docRef  = 'CONC-' . time();
$idemKey = 'IDEMP-' . $docRef;

// NO_KEY=1 → uji jalur dedup berbasis doc_ref (uq_tbl_request_source_doc), tanpa idempotency_key.
$noKey = (bool) getenv('NO_KEY');
$payloadArr = [
    'doc_ref'            => $docRef,
    'idtbldocument_type' => $docType,
    'context_json'       => ['amount' => 1000, '_loadtest' => true],
];
if (! $noKey) {
    $payloadArr['idempotency_key'] = $idemKey;
}
$payload = json_encode($payloadArr);

function headersFor(string $secret, string $key, string $body): array
{
    $ts    = (string) time();
    $nonce = bin2hex(random_bytes(8));
    $sig   = hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . $body, $secret);
    return [
        "X-Client-Key: {$key}",
        "X-Timestamp: {$ts}",
        "X-Nonce: {$nonce}",
        "X-Signature: {$sig}",
        'Content-Type: application/json',
        'Accept: application/json',
    ];
}

$mh = curl_multi_init();
$handles = [];
for ($i = 0; $i < $n; $i++) {
    $ch = curl_init("{$base}/api/v1/approval/submit");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => headersFor($secret, $key, $payload), // nonce unik per request
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

// Jalankan semua bersamaan.
do {
    $status = curl_multi_exec($mh, $running);
    if ($running) {
        curl_multi_select($mh, 1.0);
    }
} while ($running && $status === CURLM_OK);

$codes = [];
$ids   = [];
$errors = 0;
foreach ($handles as $ch) {
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $body = curl_multi_getcontent($ch);
    $codes[$code] = ($codes[$code] ?? 0) + 1;
    if ($code === 0) {
        $errors++;
    }
    // Dev `artisan serve` (PHP 8.5) bisa menempel HTML deprecation sebelum JSON.
    // Ambil dari kurung kurawal pertama agar parsing tetap andal (prod PHP 8.2 tidak begini).
    $body = (string) $body;
    $pos  = strpos($body, '{');
    $j = $pos !== false ? json_decode(substr($body, $pos), true) : null;
    if (isset($j['approval_request_id'])) {
        $ids[$j['approval_request_id']] = true;
    }
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);

echo "Concurrency check: {$n} identical submits (doc_ref={$docRef})\n";
echo 'HTTP status distribution: ' . json_encode($codes) . "\n";
echo 'Distinct approval_request_id returned: ' . count($ids) . ' -> ' . implode(',', array_keys($ids)) . "\n";

$http500 = $codes[500] ?? 0;
$ok = ($http500 === 0) && (count($ids) === 1) && ($errors === 0);

if ($ok) {
    echo "PASS: tidak ada 500, tidak ada koneksi gagal, tepat 1 request id (no duplicate, no deadlock-as-500).\n";
    echo 'ASSERT_REQUEST_ID=' . array_key_first($ids) . "\n";
    echo "ASSERT_DOC_REF={$docRef}\n";
    exit(0);
}

echo "FAIL: http500={$http500}, distinctIds=" . count($ids) . ", connErrors={$errors}\n";
exit(1);
