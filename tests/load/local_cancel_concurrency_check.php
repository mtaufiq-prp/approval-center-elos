<?php

/**
 * Local concurrency check untuk CANCEL: M cancel paralel pada SATU request berjalan.
 * Membuktikan jalur lock instance→request: tepat satu yang berhasil (200 CANCELLED),
 * sisanya konflik (409) — TIDAK ada 500/deadlock, dan state akhir konsisten CANCELLED.
 *
 * Prasyarat: server jalan + loadtest:seed. Buat dulu satu request berjalan lalu kirim id.
 *   BASE_URL=... CLIENT_KEY=... SECRET=... REQUEST_ID=123 M=10 php tests/load/local_cancel_concurrency_check.php
 */

$base   = getenv('BASE_URL')   ?: 'http://127.0.0.1:8123';
$key    = getenv('CLIENT_KEY') ?: 'LOADTEST_KEY';
$secret = getenv('SECRET')     ?: 'loadtest-secret-12345678';
$id     = (int) getenv('REQUEST_ID');
$m      = (int) (getenv('M') ?: 10);

if ($id <= 0) { fwrite(STDERR, "REQUEST_ID wajib.\n"); exit(2); }

$body = json_encode(['reason' => 'concurrent cancel test']);

function hdr(string $secret, string $key, string $body): array
{
    $ts = (string) time();
    $nonce = bin2hex(random_bytes(8));
    $sig = hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . $body, $secret);
    return ["X-Client-Key: {$key}", "X-Timestamp: {$ts}", "X-Nonce: {$nonce}", "X-Signature: {$sig}",
        'Content-Type: application/json', 'Accept: application/json'];
}

$mh = curl_multi_init();
$handles = [];
for ($i = 0; $i < $m; $i++) {
    $ch = curl_init("{$base}/api/v1/approval/{$id}/cancel");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => hdr($secret, $key, $body),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
do { $s = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $s === CURLM_OK);

$codes = [];
foreach ($handles as $ch) {
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $codes[$code] = ($codes[$code] ?? 0) + 1;
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);

echo "Cancel concurrency: {$m} parallel cancels on request #{$id}\n";
echo 'HTTP status distribution: ' . json_encode($codes) . "\n";

$ok = (($codes[500] ?? 0) === 0) && (($codes[200] ?? 0) === 1) && (($codes[0] ?? 0) === 0);
echo $ok
    ? "PASS: tepat 1×200 (CANCELLED), sisanya 409, 0×500/conn-error (instance→request lock OK).\n"
    : "FAIL: distribution tidak sesuai.\n";
exit($ok ? 0 : 1);
