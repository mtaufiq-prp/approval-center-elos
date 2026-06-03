<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER TABLE tbluser — menambahkan kolom yang dibutuhkan untuk
 * autentikasi web Laravel.
 *
 * Alasan:
 * - Schema utama (approval_center_schema_tbl.sql) tidak menyediakan kolom
 *   password karena fokus FSD adalah workflow & approval.
 * - Login Laravel butuh kolom password (hash) + remember_token.
 * - Kolom audit kecil (last_login_at, password_changed_at, must_change_password)
 *   ditambah untuk mendukung kebijakan keamanan dasar.
 *
 * Catatan:
 * - Semua kolom NULLable agar tidak merusak data user existing.
 * - User tanpa password tidak akan bisa login (Auth::attempt akan fail).
 * - Tidak ada perubahan pada PK / FK / nama tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbluser', function (Blueprint $table) {
            // Password bcrypt — NULL agar tidak memaksa user existing isi.
            $table->string('password', 255)
                  ->nullable()
                  ->after('phone')
                  ->comment('Bcrypt hash password untuk login web. NULL = belum di-set.');

            // Standar Laravel "remember me".
            $table->string('remember_token', 100)
                  ->nullable()
                  ->after('password');

            // Paksa user ganti password saat login pertama / setelah reset.
            $table->tinyInteger('must_change_password')
                  ->default(1)
                  ->after('remember_token')
                  ->comment('1 = user harus ganti password saat login berikutnya.');

            // Audit kapan terakhir berhasil login.
            $table->dateTime('last_login_at', 3)
                  ->nullable()
                  ->after('must_change_password');

            // Audit kapan terakhir password diganti (untuk policy expiry nanti).
            $table->dateTime('password_changed_at', 3)
                  ->nullable()
                  ->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'remember_token',
                'must_change_password',
                'last_login_at',
                'password_changed_at',
            ]);
        });
    }
};
