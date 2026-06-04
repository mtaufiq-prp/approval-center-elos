<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PayloadEnrichmentService
 *
 * Memperkaya context_json dengan field _computed sebelum flow engine berjalan.
 * Dipanggil dari ApprovalSubmitController tepat sebelum startProcess().
 *
 * Field yang di-inject ke context_json._computed:
 *
 *   total_nilai_retur  : sum(payload.detail[].value_retur_ori) — untuk routing berdasarkan nilai
 *   idmsalasan_list    : array unik idmsalasan dari detail[] — untuk routing berdasarkan alasan
 *   bmh_user_ref       : NPK BMH berdasarkan header.idtblbranch (bisa multiple, diambil semua)
 *   rrm_user_ref       : NPK RRM berdasarkan mapping BMH → RRM
 *   pmm_user_ref       : NPK PMM dari db_master.ms_product_group.produk_manager (by detail.ph)
 *   pd_user_ref        : NPK PD dari db_master.ms_product_group.pd_manager (by detail.ph)
 *   nrm_user_ref       : NPK NRM (Julius Kurata — single user, hardcoded dari tbluser)
 *   pkg_user_ref       : NPK Packaging Manager (Hendri Gunawan — single user)
 *   ceo_user_ref       : NPK CEO (Kris Rianto Adidarma — single user)
 *
 * Logic ini di-inject oleh Approval Center, bukan oleh source app.
 * Source app tidak perlu diubah — cukup kirim payload biasa.
 *
 * Kalau lookup gagal (user tidak ditemukan, tabel tidak ada, dll),
 * field tidak di-inject dan tidak melempar exception
 * agar submit tetap bisa berjalan.
 */
class PayloadEnrichmentService
{
    // User ref yang fixed (single user) — dapat di-override via env SFA_NRM_REF, SFA_CEO_REF, SFA_PKG_REF
    private string $nrmRef;
    private string $ceoRef;
    private string $pkgRef;

    public function __construct()
    {
        $this->nrmRef = env('SFA_NRM_REF', '11990056'); // default: JULIUS KURATA
        $this->ceoRef = env('SFA_CEO_REF', '1030018');  // default: KRIS RIANTO ADIDARMA
        $this->pkgRef = env('SFA_PKG_REF', '11130476'); // default: HENDRI GUNAWAN
    }

    /**
     * Enrich context_json dengan _computed fields.
     *
     * @param array $contextJson   context_json asli dari source app
     * @param array $payloadJson   payload_json lengkap (berisi header[], detail[])
     * @param string $sourceAppCode Kode source app (mis. 'SFA') — untuk filter kondisional
     * @return array context_json yang sudah diperkaya
     */
    public function enrich(array $contextJson, array $payloadJson, string $sourceAppCode = ''): array
    {
        // Hanya enrich untuk source app SFA
        // Bisa diperluas untuk source app lain dengan logic berbeda
        if (strtoupper($sourceAppCode) !== 'SFA') {
            return $contextJson;
        }

        $computed = [];

        try {
            $header  = $payloadJson['header'][0]  ?? $payloadJson['header'] ?? [];
            $details = $payloadJson['detail']      ?? [];

            // ── 1. Total nilai retur ────────────────────────────────────
            $computed['total_nilai_retur'] = $this->sumDetailField($details, 'value_retur_ori');

            // ── 2. List idmsalasan unik ─────────────────────────────────
            $computed['idmsalasan_list'] = $this->uniqueDetailField($details, 'idmsalasan');

            // ── 3. Routing users berdasarkan idtblbranch ────────────────
            $branchId = $header['idtblbranch'] ?? null;
            if ($branchId) {
                [$bmhRefs, $rrmRef] = $this->lookupBranchApprovers((string) $branchId);
                if (! empty($bmhRefs)) {
                    // Simpan sebagai array (bisa multiple BMH per branch)
                    $computed['bmh_user_refs'] = $bmhRefs;
                    // Untuk assignee rule FIELD_USER yang hanya support 1 value,
                    // gunakan bmh_user_ref (first) — engine akan resolve via GROUP
                    $computed['bmh_user_ref']  = $bmhRefs[0];
                }
                if ($rrmRef) {
                    $computed['rrm_user_ref'] = $rrmRef;
                }
            }

            // ── 4. PMM & PD berdasarkan detail.ph (semua PH unik, bukan hanya pertama) ─
            $phList = $this->extractUniquePhs($details);
            $computed['product_ph_list'] = $phList;
            foreach ($phList as $ph) {
                [$pmmRef, $pdRef] = $this->lookupProductManagerByPh($ph);
                // Ambil PMM/PD dari PH pertama yang berhasil resolve
                if ($pmmRef && empty($computed['pmm_user_ref'])) $computed['pmm_user_ref'] = $pmmRef;
                if ($pdRef  && empty($computed['pd_user_ref']))  $computed['pd_user_ref']  = $pdRef;
            }

            // ── 5. Single users (dari env, dengan fallback default) ──────
            $computed['nrm_user_ref'] = $this->nrmRef;
            $computed['ceo_user_ref'] = $this->ceoRef;
            $computed['pkg_user_ref'] = $this->pkgRef;

        } catch (\Throwable $e) {
            Log::warning("PayloadEnrichmentService: partial enrichment error: {$e->getMessage()}");
        }

        // Inject ke context_json
        if (! empty($computed)) {
            $contextJson['_computed'] = $computed;
        }

        return $contextJson;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Sum nilai field dari array detail (skip null/non-numeric)
     */
    private function sumDetailField(array $details, string $field): float
    {
        $sum = 0.0;
        foreach ($details as $row) {
            $val = $row[$field] ?? null;
            if (is_numeric($val)) {
                $sum += (float) $val;
            }
        }
        return $sum;
    }

    /**
     * Ambil nilai unik dari field di detail array
     */
    private function uniqueDetailField(array $details, string $field): array
    {
        $values = [];
        foreach ($details as $row) {
            $val = $row[$field] ?? null;
            if ($val !== null && $val !== '' && ! in_array($val, $values, true)) {
                $values[] = $val;
            }
        }
        return $values;
    }

    /**
     * Ambil semua PH unik yang tidak kosong dari detail
     */
    private function extractUniquePhs(array $details): array
    {
        $phs = [];
        foreach ($details as $row) {
            $ph = trim($row['ph'] ?? '');
            if ($ph !== '' && ! in_array($ph, $phs, true)) {
                $phs[] = $ph;
            }
        }
        return $phs;
    }

    /**
     * Lookup BMH (bisa multiple) dan RRM berdasarkan idtblbranch
     * dari tblapprover_branch_map di approval_center.
     *
     * @return array [bmh_refs_array, rrm_ref_string|null]
     */
    private function lookupBranchApprovers(string $branchId): array
    {
        try {
            $rows = DB::connection('mysql') // connection approval_center
                ->table('tblapprover_branch_map')
                ->where('idtblbranch', $branchId)
                ->where('is_active', 1)
                ->get(['bmh_user_ref', 'rrm_user_ref']);

            if ($rows->isEmpty()) return [[], null];

            $bmhRefs = $rows->pluck('bmh_user_ref')
                ->filter()->unique()->values()->toArray();

            // RRM: ambil dari baris pertama yang ada RRM-nya
            $rrmRef = $rows->firstWhere('rrm_user_ref', '!=' , null)?->rrm_user_ref
                ?? $rows->first()?->rrm_user_ref;

            return [$bmhRefs, $rrmRef ?: null];

        } catch (\Throwable $e) {
            Log::warning("lookupBranchApprovers({$branchId}): {$e->getMessage()}");
            return [[], null];
        }
    }

    /**
     * Lookup PMM (produk_manager) dan PD (pd_manager) dari
     * db_master.ms_product_group berdasarkan PH4.
     *
     * @return array [pmm_ref|null, pd_ref|null]
     */
    private function lookupProductManagerByPh(string $ph4): array
    {
        try {
            // Query ke database db_master — pastikan ada connection 'master' di config/database.php
            // atau gunakan DB::select dengan full database prefix
            $row = DB::select(
                "SELECT produk_manager, pd_manager
                 FROM db_master.ms_product_group
                 WHERE ph4 = ?
                 LIMIT 1",
                [$ph4]
            );

            if (empty($row)) return [null, null];

            $pmm = $row[0]->produk_manager ?? null;
            $pd  = $row[0]->pd_manager     ?? null;

            return [
                ($pmm && $pmm !== '0') ? (string) $pmm : null,
                ($pd  && $pd  !== '0') ? (string) $pd  : null,
            ];

        } catch (\Throwable $e) {
            Log::warning("lookupProductManagerByPh({$ph4}): {$e->getMessage()}");
            return [null, null];
        }
    }
}
