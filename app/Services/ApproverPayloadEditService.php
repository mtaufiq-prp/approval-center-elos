<?php

namespace App\Services;

use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Models\TblFlowStep;
use App\Models\TblProcessInstance;
use App\Models\TblTask;
use App\Models\TblUser;

/**
 * ApproverPayloadEditService
 *
 * Memungkinkan approver mengedit SEBAGIAN field payload saat memproses task —
 * sesuai keputusan desain:
 *   • Per-field per-node : whitelist field per node disimpan di
 *     tblflow_step.node_config_json['editable_fields'] (array path bergaya
 *     form_schema, mis. "header.keterangan").
 *   • Hanya field non-routing : edit hanya mengubah payload_json; context_json /
 *     _computed (dasar routing) TIDAK disentuh & enrichment TIDAK dijalankan ulang,
 *     sehingga jalur approval yang sudah ditentukan tetap stabil.
 *   • Sinkron ke source app : data hasil edit ikut di callback final (payload_json
 *     disertakan di body callback oleh FlowEngineService::enqueueCallback).
 *
 * Keamanan: hanya path yang ADA di whitelist node yang boleh diubah (validasi
 * server-side, abaikan path lain). Tiap perubahan dicatat append-only di tblaction_log.
 */
class ApproverPayloadEditService
{
    /** Daftar path field yang boleh diedit pada node ini (dari node_config_json). */
    public function editablePaths(TblFlowStep $node): array
    {
        $cfg = $node->node_config_json ?? [];
        $paths = $cfg['editable_fields'] ?? [];
        if (! is_array($paths)) {
            return [];
        }
        // Normalisasi: string non-kosong, unik.
        return array_values(array_unique(array_filter(array_map(
            fn ($p) => is_string($p) ? trim($p) : null,
            $paths
        ))));
    }

    public function nodeAllowsEdit(TblFlowStep $node): bool
    {
        return ! empty($this->editablePaths($node));
    }

    /**
     * Terapkan edit ke payload_json request (di-lock), divalidasi terhadap whitelist node.
     * Mengembalikan daftar perubahan [{path, before, after}]. Tidak mengubah apa pun bila kosong.
     *
     * Dipanggil DI DALAM transaksi act() SEBELUM engine->completeTask agar atomik &
     * agar callback final memuat payload yang sudah diedit.
     *
     * @param array<string,mixed> $edits  map path => nilai-baru (mentah dari form)
     * @return array<int,array{path:string,before:mixed,after:mixed}>
     */
    public function apply(TblTask $task, array $edits, TblUser $actor): array
    {
        $node = $task->flowStep ?: TblFlowStep::find($task->idtblflow_step);
        if (! $node) {
            return [];
        }

        $whitelist = $this->editablePaths($node);
        if (empty($whitelist) || empty($edits)) {
            return [];
        }

        // #lock-order: KUNCI instance DULU, lalu request — SAMA dengan completeCurrentTask
        // & ApprovalCancelController (instance → request). apply() dipanggil di act()
        // SEBELUM completeTask; tanpa ini edit mengunci request lebih dulu → ABBA deadlock
        // vs cancel. completeTask nanti me-lock instance lagi (re-entrant di transaksi sama).
        TblProcessInstance::where('idtblprocess_instance', $task->idtblprocess_instance)
            ->lockForUpdate()->first();

        // Lock request agar edit + keputusan atomik & tidak balapan.
        $request = TblApprovalRequest::where('idtblapproval_request', $task->idtblapproval_request)
            ->lockForUpdate()->firstOrFail();

        $payload = is_array($request->payload_json) ? $request->payload_json : [];
        $changes = [];

        foreach ($edits as $path => $newRaw) {
            $path = (string) $path;
            // HANYA path yang ada di whitelist (cegah edit field terlarang).
            if (! in_array($path, $whitelist, true)) {
                continue;
            }
            // #scalar-guard: tolak nilai non-scalar. Tanpa ini, approver bisa mengirim
            // edits[path][]=x (array) sehingga field scalar (mis. keterangan/nilai) berubah
            // menjadi struktur sewenang-wenang → korupsi data & risiko injeksi di source app.
            if (! is_scalar($newRaw) && $newRaw !== null) {
                continue;
            }
            // #ambiguous-guard: tolak path yang turun ke array MULTI-baris (mis. detail[] >1
            // baris tanpa indeks) — descent ke index 0 akan mengubah hanya baris pertama secara
            // diam-diam. Gunakan indeks eksplisit (mis. "detail.2.qty") untuk edit per-baris.
            if (self::ambiguousDescent($payload, $path)) {
                continue;
            }
            $before = self::getValue($payload, $path);
            // Hanya boleh mengubah field yang SUDAH ADA (hindari membuat struktur baru).
            if ($before === null && ! self::pathExists($payload, $path)) {
                continue;
            }
            $after = self::coerceType($before, $newRaw);
            if ($after === $before) {
                continue; // tidak berubah
            }
            $payload = self::setValue($payload, $path, $after);
            $changes[] = ['path' => $path, 'before' => $before, 'after' => $after];
        }

        if (empty($changes)) {
            return [];
        }

        $request->payload_json = $payload;
        $request->save();

        // Audit append-only (tampil di Riwayat & terlindungi trigger DB).
        TblActionLog::create([
            'idtblapproval_request' => $request->idtblapproval_request,
            'idtblprocess_instance' => $task->idtblprocess_instance,
            'task_id'               => $task->idtbltask,
            'idtbluser_actor'       => $actor->idtbluser,
            'actor_ref'             => $actor->user_ref,
            'action_code'           => 'EDIT_PAYLOAD',
            'action_note'           => $this->summarize($changes),
            'before_status'         => $request->request_status,
            'after_status'          => $request->request_status,
            'idtblflow_step_before' => $node->idtblflow_step,
            'idtblflow_step_after'  => $node->idtblflow_step,
            'client_ip'             => request()?->ip(),
            'user_agent'            => substr((string) request()?->userAgent(), 0, 255),
        ]);

        return $changes;
    }

    private function summarize(array $changes): string
    {
        $parts = array_map(function ($c) {
            $b = is_scalar($c['before']) ? (string) $c['before'] : json_encode($c['before']);
            $a = is_scalar($c['after']) ? (string) $c['after'] : json_encode($c['after']);
            return "{$c['path']}: '{$b}' → '{$a}'";
        }, $changes);
        return mb_substr('Edit field: ' . implode('; ', $parts), 0, 2000);
    }

    /**
     * Pertahankan tipe asal field (numeric tetap numeric) untuk menjaga sanity payload.
     * $newRaw DIJAMIN scalar|null oleh pemanggil (non-scalar sudah ditolak di apply()).
     */
    private static function coerceType(mixed $before, mixed $newRaw): mixed
    {
        if ($newRaw === null)  return null;
        if (is_int($before))   return is_numeric($newRaw) ? (int) $newRaw : (string) $newRaw;
        if (is_float($before)) return is_numeric($newRaw) ? (float) $newRaw : (string) $newRaw;
        if (is_bool($before))  return filter_var($newRaw, FILTER_VALIDATE_BOOLEAN);
        return (string) $newRaw; // default: paksa string, jangan pernah teruskan struktur mentah
    }

    /**
     * True bila path turun ke array-of-objects MULTI-elemen via aturan index-0 (ambigu).
     * Path dengan indeks eksplisit (mis. "detail.2.qty") TIDAK ambigu karena menargetkan baris pasti.
     */
    private static function ambiguousDescent(array $payload, string $path): bool
    {
        $cur = $payload;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cur)) return false;
            if (array_key_exists(0, $cur) && is_array($cur[0]) && ! ctype_digit($seg)) {
                if (count($cur) > 1) return true; // multi-baris tanpa indeks → ambigu
                $cur = $cur[0];
            }
            if (! array_key_exists($seg, $cur)) return false;
            $cur = $cur[$seg];
        }
        return false;
    }

    // ── Resolver path bergaya form_schema (mirror _context_renderer::resolveField) ──
    // Aturan: bila current adalah array-of-objects (mis. header[]), turun ke index 0
    // lebih dulu sebelum mengambil key (kecuali segmen berupa indeks numerik).

    /** Ambil nilai pada path (null jika tidak ada). */
    public static function getValue(array $payload, string $path): mixed
    {
        if ($path === '') return null;
        $cur = $payload;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cur)) return null;
            if (array_key_exists(0, $cur) && is_array($cur[0]) && ! ctype_digit($seg)) {
                $cur = $cur[0];
            }
            if (! array_key_exists($seg, $cur)) return null;
            $cur = $cur[$seg];
        }
        return $cur;
    }

    /** Apakah path benar-benar ada (membedakan "tidak ada" vs "ada bernilai null"). */
    public static function pathExists(array $payload, string $path): bool
    {
        if ($path === '') return false;
        $cur = $payload;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cur)) return false;
            if (array_key_exists(0, $cur) && is_array($cur[0]) && ! ctype_digit($seg)) {
                $cur = $cur[0];
            }
            if (! array_key_exists($seg, $cur)) return false;
            $cur = $cur[$seg];
        }
        return true;
    }

    /** Set nilai pada path (hanya bila key leaf sudah ada); kembalikan payload baru (immutable). */
    public static function setValue(array $payload, string $path, mixed $value): array
    {
        return self::setRec($payload, explode('.', $path), $value);
    }

    private static function setRec(array $node, array $segs, mixed $value): array
    {
        $seg = $segs[0];
        // Turun ke index 0 bila array-of-objects & segmen bukan indeks numerik.
        if (array_key_exists(0, $node) && is_array($node[0]) && ! ctype_digit((string) $seg)) {
            $node[0] = self::setRec($node[0], $segs, $value);
            return $node;
        }
        if (count($segs) === 1) {
            if (array_key_exists($seg, $node)) {
                $node[$seg] = $value;
            }
            return $node;
        }
        if (isset($node[$seg]) && is_array($node[$seg])) {
            $node[$seg] = self::setRec($node[$seg], array_slice($segs, 1), $value);
        }
        return $node;
    }
}
