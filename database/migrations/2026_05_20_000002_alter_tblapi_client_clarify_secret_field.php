<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER TABLE tblapi_client — klarifikasi semantik kolom client_secret_hash
 * dan tambahan kolom audit untuk rotasi secret.
 *
 * Alasan (sesuai keputusan R-03 di Tahap 1):
 * - API antar aplikasi internal Propan tetap menggunakan HMAC SHA256.
 * - Untuk verifikasi signature, server WAJIB bisa decrypt secret asli.
 * - Maka secret disimpan AES-encrypted (via APP_KEY Laravel), BUKAN
 *   hash one-way murni.
 * - Untuk meminimalkan perubahan schema, nama kolom client_secret_hash
 *   tetap dipertahankan, namun:
 *     * tipe diperbesar VARCHAR(255) → TEXT karena ciphertext + IV + tag
 *       (base64) bisa lebih panjang dari 255 karakter.
 *     * comment kolom diperbarui agar tidak menyesatkan developer baru.
 * - Ditambah kolom secret_rotated_at untuk audit rotasi secret.
 *
 * Catatan:
 * - Migration ini IDEMPOTEN — aman dijalankan ulang.
 * - Jika ke depan tim memutuskan memisahkan jadi kolom client_secret_encrypted
 *   yang baru, lakukan migration tambahan; jangan ubah file ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Perbesar tipe kolom client_secret_hash → TEXT
        //    MySQL/MariaDB: gunakan raw SQL agar bisa update COMMENT sekaligus.
        DB::statement(
            "ALTER TABLE tblapi_client
             MODIFY COLUMN client_secret_hash TEXT NOT NULL
             COMMENT 'AES-encrypted client secret (via APP_KEY). BUKAN hash one-way. Decrypted server-side untuk verifikasi HMAC SHA256.'"
        );

        // 2) Tambah kolom audit rotasi secret (jika belum ada)
        if (! Schema::hasColumn('tblapi_client', 'secret_rotated_at')) {
            Schema::table('tblapi_client', function ($table) {
                $table->dateTime('secret_rotated_at', 3)
                      ->nullable()
                      ->after('client_secret_hash')
                      ->comment('Timestamp terakhir client_secret dirotasi.');
            });
        }
    }

    public function down(): void
    {
        // Rollback: kembalikan ke VARCHAR(255) (data ciphertext bisa truncated,
        // sehingga rollback ini hanya disarankan di environment dev).
        if (Schema::hasColumn('tblapi_client', 'secret_rotated_at')) {
            Schema::table('tblapi_client', function ($table) {
                $table->dropColumn('secret_rotated_at');
            });
        }

        DB::statement(
            "ALTER TABLE tblapi_client
             MODIFY COLUMN client_secret_hash VARCHAR(255) NOT NULL"
        );
    }
};
