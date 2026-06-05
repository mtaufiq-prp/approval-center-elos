<?php

/*
|--------------------------------------------------------------------------
| Approval Center - Application Configuration
|--------------------------------------------------------------------------
|
| Konfigurasi khusus modul Approval Center. Tidak boleh hardcode di
| service/controller. Semua nilai yang bisa berubah per environment
| WAJIB diambil dari sini.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | API Security (HMAC SHA256)
    |--------------------------------------------------------------------------
    | API antar aplikasi internal Propan menggunakan HMAC SHA256.
    | client_secret di tblapi_client disimpan ENCRYPTED (bukan hash one-way)
    | agar server dapat decrypt untuk re-compute signature.
    */
    'api_security' => [
        // Header wajib untuk inbound API
        'header_client_key' => 'X-Client-Key',
        'header_timestamp'  => 'X-Timestamp',
        'header_signature'  => 'X-Signature',

        // Toleransi waktu (detik) antara X-Timestamp dan server time.
        // Mencegah replay attack dari timestamp lama.
        'time_tolerance_seconds' => (int) env('APPROVAL_HMAC_TIME_TOLERANCE', 300),

        // Signature dihitung sebagai:
        //   HMAC_SHA256( timestamp + "\n" + raw_request_body , decrypted_secret )
        // Konstanta separator agar konsisten antar aplikasi.
        'signature_separator' => "\n",

        // Rate limit per API client (bukan per IP) untuk endpoint /api/v1/*.
        // Default tinggi agar target 1000 req/menit (~16.7/s) tidak ter-throttle
        // (review #3 — throttle:60,1 per-IP membuat target mustahil). Limiter
        // 'api_client' didefinisikan di AppServiceProvider, keyed by idtblapi_client.
        'rate_limit_per_minute' => (int) env('APPROVAL_API_RATE_LIMIT', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback Outbox
    |--------------------------------------------------------------------------
    | Pengiriman hasil approval ke aplikasi asal WAJIB lewat outbox.
    | Tidak boleh callback langsung dari controller.
    */
    'callback' => [
        'max_retry'              => (int) env('APPROVAL_CALLBACK_MAX_RETRY', 10),
        'http_timeout_seconds'   => (int) env('APPROVAL_CALLBACK_HTTP_TIMEOUT', 15),
        'backoff_cap_minutes'    => (int) env('APPROVAL_CALLBACK_BACKOFF_CAP_MINUTES', 1440),
        // #6/#9/#10: jumlah baris outbox yang di-dispatch per tick scheduler.
        // Pada 1000 req/menit, ~1000 callback/menit diproduksi; batch 50 (lama)
        // membuat backlog tumbuh ~950/menit dan tidak pernah terkuras. Default
        // dinaikkan ke 1000 dan worker queue diparalelkan (lihat DEPLOYMENT_GUIDE).
        'batch_size'             => (int) env('APPROVAL_CALLBACK_BATCH_SIZE', 1000),

        /*
        | SSRF allowlist (review #8/#109/#11).
        |
        | Sistem ini INTERNAL-only: aplikasi sumber (SFA/PR/BSKB) hidup di jaringan
        | privat (10.x). Guard lama menolak SEMUA rentang privat → seluruh callback
        | internal ditandai DEAD dan hub-and-spoke putus. Kita ganti dengan ALLOWLIST:
        | resolved IP target HARUS masuk salah satu CIDR ini. Loopback & metadata/
        | link-local (169.254.0.0/16) SELALU diblokir, bahkan jika di dalam allowlist.
        |
        | Default = rentang privat RFC1918 (deployment internal). Set
        | APPROVAL_CALLBACK_ALLOWED_CIDRS untuk membatasi lebih ketat (mis. hanya
        | subnet server aplikasi). String kosong = izinkan semua host non-loopback/
        | non-metadata (TIDAK disarankan; hanya untuk dev).
        */
        'allowed_cidrs' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'APPROVAL_CALLBACK_ALLOWED_CIDRS',
            '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
        ))))),

        // Status yang menghasilkan event callback ke aplikasi asal
        'event_types' => [
            'APPROVED'  => true,
            'REJECTED'  => true,
            'RETURNED'  => true,
            'CANCELLED' => true,
            'ERROR'     => true,
            // TASK_CREATED dipakai opsional, default OFF
            'TASK_CREATED' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Start (skalabilitas 1000 req/menit — review #12)
    |--------------------------------------------------------------------------
    | Bila TRUE, endpoint submit hanya menyimpan request (transaksional, ringan)
    | lalu meng-enqueue StartProcessJob untuk menjalankan engine (enrichment sudah
    | dilakukan sinkron untuk routing). Memindahkan traversal engine + resolusi
    | assignee + pembuatan task keluar dari request HTTP → latency p95 turun & lebih
    | tahan beban. Response mengembalikan status SUBMITTED; source app polling status.
    |
    | Default FALSE (kompatibel: engine jalan sinkron, response langsung IN_PROGRESS
    | dengan task siap). Aktifkan di produksi beban tinggi + jalankan queue worker.
    */
    'async_start' => (bool) env('APPROVAL_ASYNC_START', false),

    /*
    |--------------------------------------------------------------------------
    | Enrichment (PayloadEnrichmentService)
    |--------------------------------------------------------------------------
    | Lookup data master (db_master.ms_product_group cross-DB & branch→approver map)
    | dijalankan tiap submit. Data ini jarang berubah → cache pendek mengurangi beban
    | query (terutama cross-DB) pada 1000 req/menit. 0 = nonaktifkan cache.
    | Trade-off: perubahan mapping approver baru berlaku setelah TTL kedaluwarsa.
    */
    'enrichment' => [
        'cache_ttl_seconds' => (int) env('APPROVAL_ENRICHMENT_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification
    |--------------------------------------------------------------------------
    */
    'notification' => [
        'batch_size'       => (int) env('APPROVAL_NOTIFICATION_BATCH_SIZE', 100),
        'default_channels' => array_filter(array_map(
            'trim',
            explode(',', env('APPROVAL_NOTIFICATION_DEFAULT_CHANNELS', 'IN_APP,EMAIL'))
        )),

        // Channel yang sudah terimplementasi di codebase.
        // Channel lain (TELEGRAM, WHATSAPP, WEB_PUSH) tetap bisa di-queue
        // tetapi worker akan menandai SKIPPED jika belum ada handler.
        'enabled_channels' => ['IN_APP', 'EMAIL'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Engine (Condition Evaluator)
    |--------------------------------------------------------------------------
    */
    'rule_engine' => [
        // Batas kedalaman nesting AND/OR untuk mencegah DoS via condition_json
        // yang sangat dalam.
        'max_depth' => (int) env('APPROVAL_RULE_MAX_DEPTH', 8),

        // Perilaku ketika field di condition tidak ditemukan di context_json:
        //   fail-closed → condition dianggap FALSE → rule TIDAK match
        //   fail-open   → condition dianggap TRUE
        // Untuk approval yang sensitif, DEFAULT fail-closed.
        'missing_field_behavior' => env('APPROVAL_RULE_MISSING_FIELD_BEHAVIOR', 'fail-closed'),

        // Daftar operator yang didukung (whitelist).
        'allowed_operators' => [
            '=', '!=', '>', '>=', '<', '<=',
            'IN', 'NOT_IN', 'BETWEEN', 'CONTAINS',
            'AND', 'OR',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval Mode
    |--------------------------------------------------------------------------
    | Versi awal hanya ANY yang aktif penuh. ALL & SEQUENTIAL dibuat
    | scaffold dengan TODO supaya mudah dikembangkan kemudian.
    */
    'approval_mode' => [
        'enabled' => [
            'ANY'        => true,
            'ALL'        => false,   // TODO Tahap berikutnya
            'SEQUENTIAL' => false,   // TODO Tahap berikutnya
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Codes (master role yang harus ada)
    |--------------------------------------------------------------------------
    | Konstanta role agar tidak menulis literal di banyak tempat.
    */
    'roles' => [
        'admin'     => 'ADMIN_APPROVAL',
        'approver'  => 'APPROVER',
        'requester' => 'REQUESTER',
        'auditor'   => 'AUDITOR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit & Integration Log
    |--------------------------------------------------------------------------
    */
    'audit' => [
        // Field di request body yang harus di-redact saat menyimpan log
        // (hindari menyimpan token / password / signature).
        'redact_headers' => [
            'authorization', 'x-signature', 'cookie', 'set-cookie',
        ],
        'redact_body_paths' => [
            'password', 'secret', 'token',
        ],
    ],

];
