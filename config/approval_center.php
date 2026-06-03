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
        'batch_size'             => (int) env('APPROVAL_CALLBACK_BATCH_SIZE', 50),

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
