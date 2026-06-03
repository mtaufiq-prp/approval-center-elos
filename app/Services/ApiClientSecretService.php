<?php

namespace App\Services;

use App\Models\TblApiClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ApiClientSecretService
 *
 * Mengelola siklus hidup client_secret untuk integrasi API antar aplikasi
 * Propan dengan Approval Center.
 *
 * Aturan keamanan:
 *  - Secret di-generate sebagai 32 byte cryptographically random,
 *    base64-url encoded → menghasilkan ~43 char alphanumeric+_-.
 *  - Secret di-encrypt pakai Laravel Crypt::encryptString() yang
 *    di belakangnya pakai AES-256-CBC + HMAC SHA256 via APP_KEY.
 *  - Plaintext HANYA dikembalikan SEKALI dalam return value method
 *    generate/rotate. Setelah itu tidak ada cara melihat ulang.
 *  - Plaintext TIDAK PERNAH:
 *      * masuk log (controller wajib pakai flash one-time, jangan log)
 *      * masuk tblaudit_event (caller wajib pakai $extraRedact)
 *      * masuk response JSON selain saat generate/rotate
 *      * masuk session lama (flash, bukan put)
 */
class ApiClientSecretService
{
    /**
     * Generate plaintext secret baru.
     * Tidak menyimpan apapun ke DB; caller bertanggung jawab simpan
     * via createWithSecret() atau rotate().
     */
    public function generatePlainSecret(): string
    {
        // 32 byte = 256 bit entropy
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Encrypt plaintext secret untuk disimpan di kolom client_secret_hash
     * (yang sebenarnya berisi ciphertext — lihat migration Tahap 2).
     */
    public function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    /**
     * Decrypt ciphertext yang tersimpan di kolom client_secret_hash.
     * Dipakai oleh ApiClientVerifyHmac middleware di Tahap 7.
     *
     * Tidak dipakai oleh UI manapun di Tahap 5.
     */
    public function decrypt(string $cipher): string
    {
        return Crypt::decryptString($cipher);
    }

    /**
     * Generate client_key acak untuk API client baru.
     * Format: AC- + 24 char alphanumeric (mudah dibaca admin).
     */
    public function generateClientKey(): string
    {
        return 'AC-' . Str::upper(Str::random(24));
    }

    /**
     * Buat API client baru dengan secret yang baru di-generate.
     * Return DTO array berisi model + plaintext secret.
     *
     * Caller WAJIB:
     *  1. Tampilkan plaintext secret SEKALI di view (sebagai flash).
     *  2. Tidak menyimpan plaintext di session beyond flash.
     *  3. Tidak log/audit plaintext.
     *
     * @param array $attrs  Field selain client_key dan secret. Wajib:
     *                      idtblsource_app. Opsional: allowed_ip,
     *                      token_expired_at, is_active.
     *
     * @return array{model: TblApiClient, plain_secret: string, client_key: string}
     */
    public function createWithSecret(array $attrs): array
    {
        return DB::transaction(function () use ($attrs) {
            $plain     = $this->generatePlainSecret();
            $clientKey = $attrs['client_key'] ?? $this->generateClientKey();

            $model = new TblApiClient();
            $model->idtblsource_app    = $attrs['idtblsource_app'];
            $model->client_key         = $clientKey;
            $model->allowed_ip         = $attrs['allowed_ip']        ?? null;
            $model->token_expired_at   = $attrs['token_expired_at']  ?? null;
            $model->is_active          = $attrs['is_active']         ?? 1;
            // Set langsung — field ini tidak ada di $fillable (sengaja).
            $model->client_secret_hash = $this->encrypt($plain);
            $model->secret_rotated_at  = now();
            $model->save();

            return [
                'model'        => $model->fresh(),
                'plain_secret' => $plain,
                'client_key'   => $clientKey,
            ];
        });
    }

    /**
     * Rotate secret untuk client existing.
     * Tidak mengubah client_key (mempertahankan identitas).
     *
     * @return array{model: TblApiClient, plain_secret: string}
     */
    public function rotateSecret(TblApiClient $client): array
    {
        return DB::transaction(function () use ($client) {
            $plain = $this->generatePlainSecret();

            $client->client_secret_hash = $this->encrypt($plain);
            $client->secret_rotated_at  = now();
            $client->save();

            return [
                'model'        => $client->fresh(),
                'plain_secret' => $plain,
            ];
        });
    }

    /**
     * Revoke API client (soft) — is_active = 0.
     * Tidak menghapus row, tidak menghapus secret (audit/forensik).
     */
    public function revoke(TblApiClient $client): TblApiClient
    {
        $client->is_active = false;
        $client->save();
        return $client->fresh();
    }

    /**
     * Re-activate API client yang sebelumnya di-revoke.
     */
    public function activate(TblApiClient $client): TblApiClient
    {
        $client->is_active = true;
        $client->save();
        return $client->fresh();
    }
}
