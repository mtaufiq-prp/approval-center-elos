# Approval Center Propan — Panduan Lengkap (Apa, Untuk Apa, Cara Pakai)

> Hub persetujuan terpusat untuk seluruh aplikasi internal PT Propan Raya ICC
> (PR Online, Retur/SFA, BSKB, RPD/Propan Journey, PIS, dll).
> Dokumen ini menjelaskan **apa** sistem ini, **untuk apa**, dan **cara memakainya** —
> baik dari sisi aplikasi pengirim (API) maupun pengguna manusia (web UI).
>
> Dokumen pendamping: `README.md` (setup dev), `DEPLOYMENT_GUIDE.md` (deploy),
> `SECURITY.md` (model keamanan), `performance/PERFORMANCE_DESIGN.md` (skalabilitas),
> `tests/load/README.md` (load test). `CLAUDE.md` = konteks teknis internal.

---

## 1. Singkatnya: ini program apa?

**Approval Center = "mesin persetujuan terpusat".** Semua logika approval (siapa
menyetujui, berapa level, urutan, syaratnya) ditarik keluar dari masing-masing aplikasi
ke **satu tempat**. Aplikasi pengirim cukup *menitipkan* request approval dan menerima
hasilnya — tidak perlu tahu detail alurnya.

**Pola Hub-and-Spoke:**

```
Aplikasi (SFA / PR / BSKB / ...)  ──push request──►   APPROVAL CENTER (hub)
            ▲                                          - tentukan flow & approver
            └───────────── callback hasil ────────────  - kelola inbox & keputusan
                                                        - kirim balik hasil + audit
```

Analogi: seperti **payment gateway, tapi untuk approval**. Aplikasi tinggal "titip
approval", hub yang mengurus prosesnya end-to-end.

**Kenapa dibuat?**
- Hindari tiap aplikasi menulis ulang logika approval (berulang, tidak konsisten).
- Ubah alur approval **tanpa koding & tanpa deploy ulang** aplikasi pengirim.
- Satu tempat untuk audit, monitoring, eskalasi, dan integrasi.

---

## 2. Konsep inti (mental model)

| Istilah | Arti | Tabel |
|---|---|---|
| **Source App** | Aplikasi pengirim (SFA, PR, …) | `tblsource_app` |
| **API Client** | Kredensial HMAC agar source app boleh submit | `tblapi_client` |
| **Document Type** | Jenis dokumen yang diapprove + skema tampilannya (`form_schema`) | `tbldocument_type` |
| **Flow Definition** | "Nama" alur approval (mis. `FLOW_SFA_RETUR`) | `tblflow_definition` |
| **Flow Version** | Versi konkret flow; status `DRAFT`→`ACTIVE`→`INACTIVE`. Hanya 1 ACTIVE per definition | `tblflow_version` |
| **Node / Step** | Kotak di diagram: `START`, `APPROVAL`, `DECISION`, `END` (juga REVIEW/NOTIFICATION/SYSTEM) | `tblflow_step` |
| **Edge / Transition** | Panah antar node + kondisi/aksi | `tblflow_transition` |
| **Assignee Rule** | "Siapa approver di node ini" | `tblstep_assignee_rule` |
| **Routing Rule** | "Request seperti apa → pakai flow version yang mana" | `tblrouting_rule` |
| **Approval Request** | 1 pengajuan approval nyata dari source app | `tblapproval_request` |
| **Process Instance** | 1 eksekusi flow untuk sebuah request (token berjalan di graph) | `tblprocess_instance` |
| **Task** | Pekerjaan approval di inbox seseorang | `tbltask` |
| **Callback Outbox** | Antrian hasil yang dikirim balik ke source app | `tblcallback_outbox` |

> **Prinsip kunci:** alur approval **tidak di-hardcode**. Semua adalah konfigurasi data:
> `Definition → Version → Node + Edge + Assignee Rule → Routing Rule`. Mengubah alur =
> mengubah konfigurasi, bukan koding.

---

## 3. Status mesin (state machine)

`tblapproval_request.request_status`:

```
DRAFT → SUBMITTED → IN_PROGRESS → ┬─ APPROVED   (final)
                                  ├─ REJECTED   (final)
                                  ├─ RETURNED   (dikembalikan ke pemohon; bisa di-resubmit)
                                  ├─ CANCELLED  (final)
                                  └─ ERROR      (kesalahan config/approver; recovery via admin)
```

- `APPROVED/REJECTED/CANCELLED` = **final**, tidak bisa berubah lagi (dijaga lock + re-cek).
- `RETURNED` = dikembalikan untuk perbaikan; resubmit `doc_ref` yang sama **men-drive ulang** prosesnya.
- Status callback **terpisah** dari status approval: `tblcallback_outbox.status ∈ {PENDING, SENT, FAILED, DEAD}`. Approval tetap final walau callback masih PENDING.

---

## 4. Dua sisi pemakaian

### Sisi A — Aplikasi pengirim (API, autentikasi HMAC)
Endpoint `/api/v1/*`, header wajib: `X-Client-Key`, `X-Timestamp`, `X-Nonce`, `X-Signature`.

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/v1/approval/submit` | Ajukan approval baru |
| GET | `/api/v1/approval/{id}/status` | Cek status (juga `?doc_ref=&idtbldocument_type=`) |
| POST | `/api/v1/approval/{id}/cancel` | Batalkan (jika belum final) |
| POST | `/api/v1/callback/test` | Receiver dummy untuk uji callback |

### Sisi B — Manusia (Web UI, login session)

| Peran | Bisa apa | Menu |
|---|---|---|
| **ADMIN_APPROVAL** | Setup semua (master data, workflow builder) + lihat semua | Master, Workflow, Inbox, Monitoring, Audit |
| **APPROVER** | Memproses task yang ditugaskan padanya | Inbox |
| **AUDITOR** | Lihat-saja: monitoring + log audit (lintas program) | Monitoring, Audit |
| *(REQUESTER)* | Konstanta peran tersedia; alur requester via API source app | — |

> Catatan: `ADMIN_APPROVAL` & `AUDITOR` bersifat **global lintas program** (by design — lihat `SECURITY.md`).

---

## 5. Cara pakai — langkah demi langkah

### 👤 A. ADMIN — daftarkan program baru dari nol (sekali setup)

Urutan menu **Master** lalu **Workflow** (semua butuh role `ADMIN_APPROVAL`):

1. **Master → Source App** — daftarkan aplikasi (mis. `SFA`), isi `default_callback_url` (URL untuk menerima hasil).
2. **Master → API Client** — buat kredensial → sistem menampilkan **Client Key + Secret** (secret muncul **sekali**, simpan baik-baik; bisa di-*rotate*). Set `allowed_ip` & `token_expired_at` bila perlu.
3. **Master → Document Type** — buat jenis dokumen (mis. "Retur SFA") + isi `form_schema` (JSON: field apa yang ditampilkan ke approver).
4. **Master → Org Unit / Position / User / Role / Approval Group / Delegation** — data approver (orang, jabatan, grup, delegasi cuti).
5. **Workflow → Flow Definition** — buat definisi flow (mis. `FLOW_SFA_RETUR`).
6. **Flow Definition → Version → Builder** (kanvas visual ReactFlow):
   - Drag node: `START` → `DECISION`/`APPROVAL` → `END`.
   - Tarik panah (edge) antar node; pada edge dari DECISION isi **kondisi** (mis. `SUM_GT total > 25000000`).
   - Pada tiap node `APPROVAL`, set **Assignee Rule** (siapa approver) + mode (`ANY`) + SLA jam + reject behavior.
   - Tombol **Validate** untuk cek konsistensi graph.
7. **Workflow → Routing Rule** — petakan "request (source_app + document_type + kondisi) → flow version".
8. **Deploy** — versi jadi **ACTIVE**. Mulai sekarang request masuk memakai versi ini.

**Mengubah flow yang sudah dipakai:** jangan edit versi ACTIVE — **Clone → edit → Deploy**.
Versi lama yang masih punya request berjalan **terkunci** (`isLocked()`) sehingga request
in-flight tidak rusak.

### 🔌 B. APLIKASI pengirim — integrasi API

Alur: **submit → tunggu callback (atau poll status)**.

Tanda tangan request (sama persis dengan middleware `ApiClientAuthenticate`):

```
signature = HMAC_SHA256( timestamp + "\n" + nonce + "\n" + raw_body , client_secret )
```

Headers: `X-Client-Key`, `X-Timestamp` (unix detik, toleransi ±300s), `X-Nonce` (acak,
anti-replay), `X-Signature` (hex).

**Contoh PHP:**
```php
$base   = 'http://10.50.0.4/approval_center/public';
$key    = 'CLIENT_KEY_ANDA';
$secret = 'CLIENT_SECRET_ANDA';

$payload = [
    'doc_ref'            => 'R-2026-00123',          // ID dokumen di aplikasi asal
    'doc_no'             => 'RETUR/2026/00123',
    'idtbldocument_type' => 5,                        // doc type milik source app Anda
    'callback_url'       => 'http://sfa.internal/api/approval-callback',
    'idempotency_key'    => 'R-2026-00123',           // cegah duplikat saat retry
    'submitter_user_ref' => '11110247',               // NPK pengaju (opsional)
    'amount'             => 30000000,
    'context_json'       => [                          // dipakai untuk routing & kondisi
        'header' => [['idtblbranch' => 'TNG']],
        'detail' => [['value_retur_ori' => 30000000, 'idmsalasan' => 61, 'ph' => 'PH4X']],
    ],
    'payload_json'       => [/* data lengkap utk ditampilkan ke approver */],
];

$body = json_encode($payload);
$ts   = (string) time();
$nonce= bin2hex(random_bytes(8));
$sig  = hash_hmac('sha256', $ts."\n".$nonce."\n".$body, $secret);

$ch = curl_init("$base/api/v1/approval/submit");
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-Client-Key: $key", "X-Timestamp: $ts", "X-Nonce: $nonce", "X-Signature: $sig",
        'Content-Type: application/json', 'Accept: application/json',
    ],
]);
$resp = json_decode(curl_exec($ch), true);
// → { success, approval_request_id, status: "IN_PROGRESS", ... }
```

**Cek status:**
```
GET /api/v1/approval/{approval_request_id}/status     (HMAC-signed; body kosong → tanda tangani "ts\nnonce\n")
→ { status, pending_tasks: [{ node_code, assignee_ref, due_at }] }
```

**Menerima callback** (hub → aplikasi Anda): hub POST ke `callback_url` dengan header
`X-Callback-Ts`, `X-Callback-Nonce`, `X-Callback-Sig` =
`HMAC_SHA256(ts + "\n" + nonce + "\n" + body, secret_callback)`. Verifikasi tanda tangan,
lalu proses `payload_json` (`event_type`, `approval_request_id`, `source_request_id`,
`request_status`, `decided_at`). Balas HTTP 2xx supaya tidak di-retry.

**Idempotency:** kirim `idempotency_key`; submit ulang `doc_ref` yang sama → balasan
*idempotent* (tidak membuat request dobel), bahkan pada race konkuren (errno 1062 ditangani).

### ✅ C. APPROVER — memproses approval

1. Login → **Inbox** → daftar task yang ditugaskan padamu (assignee langsung / kandidat grup aktif).
2. Klik task → lihat detail dokumen (dirender dari `form_schema`) + **alur persetujuan** (done/current/future + siapa).
3. Pilih keputusan + catatan:
   - **Approve** → lanjut ke level berikutnya / selesai.
   - **Reject** → request `REJECTED` (final).
   - **Return** → `RETURNED` (dikembalikan ke pemohon untuk perbaikan).
   - **Cancel** → `CANCELLED`.
4. Engine otomatis meneruskan token & (saat final) menulis callback ke aplikasi asal.
5. **Inbox → History** = task yang sudah kamu proses.

> Pengamanan: kamu hanya bisa mengaksi task milikmu (assignee/kandidat aktif/admin), task
> harus `OPEN/CLAIMED`, dan **tidak bisa menyetujui request yang kamu ajukan sendiri**
> (segregation of duties).

### 🔍 D. AUDITOR / ADMIN — pemantauan

- **Monitoring** → daftar + detail semua request (status, jalur, payload, route log).
- **Audit** →
  - `action-log`: keputusan approver (append-only).
  - `audit-event`: perubahan konfigurasi/master (append-only).
  - `integration-log`: request/response API inbound.
  - `callback-outbox`: status pengiriman callback + tombol **Retry** (DEAD/FAILED).

---

## 6. Cara kerja internal — jejak 1 request (end-to-end)

```
SFA submit (HMAC)
  → ApiClientAuthenticate  : verifikasi HMAC + nonce(anti-replay) + timestamp + client aktif/expired/IP
  → ApprovalSubmitController:
       • idempotency check (anti-dobel)
       • PayloadEnrichmentService : hitung _computed (total nilai, BMH/RRM/PMM dari db_master)
       • RoutingRuleService       : tentukan flow version (cache 10 menit)
       • buat tblapproval_request (SUBMITTED) + audit SUBMIT
       • FlowEngineService.startProcess():
            START → DECISION (evaluasi condition_json) → node APPROVAL
            → AssigneeResolverService : resolve approver → buat tbltask + enqueue notifikasi
  → Approver buka Inbox → Approve
  → FlowEngineService.completeCurrentTask() (lock instance→task→request, anti-race):
       teruskan token → node berikutnya … hingga END
  → completeProcess(): set status final + tulis tblcallback_outbox (transactional outbox)
  → (cron tiap menit) ProcessCallbackOutboxJob → SendCallbackJob (queue worker):
       POST hasil ke callback_url (HMAC-signed) → status SENT (retry/backoff → DEAD bila gagal terus)
```

Pekerjaan berat (callback, notifikasi, eskalasi SLA) dijalankan **async** lewat queue, jadi
endpoint submit tetap ringan & cepat.

---

## 7. Referensi konfigurasi

### Tipe Node (`step_type`)
| Type | Perilaku |
|---|---|
| `START` | Auto-forward ke node berikut, tidak membuat task |
| `DECISION` | Evaluasi `condition_json` tiap edge, pilih `priority_no` terkecil yang match (gateway EXCLUSIVE) |
| `APPROVAL` | Buat task + tunggu keputusan approver |
| `END` | Selesaikan proses (status diturunkan dari aksi terakhir) |
| `REVIEW`/`NOTIFICATION`/`SYSTEM` | Auto-forward (tahap lanjutan) |

### Tipe Assignee Rule (`assignee_type`)
| Type | Value | Cara resolve |
|---|---|---|
| `USER` | NPK/user_ref | Lookup `tbluser.user_ref` |
| `ROLE` | role_code | Semua user aktif dengan role tsb |
| `GROUP` | group_code | Anggota `tblapproval_group` aktif |
| `POSITION` | position_code | User pada posisi tsb |
| `SUPERIOR` | — | Atasan submitter (`idtbluser_superior`) |
| `FIELD_USER` | path di context | mis. `_computed.bmh_user_ref` |
| `FIELD_POSITION` | path di context | position_code dari context |
| `JOBTITLE` | jobtitleid | `db_master.tbemployeeit` → employeeno → `tbluser` |
| `API_RESOLVER` | URL | POST context, balas `{user_refs:[...]}` |

> Plus **delegasi**: jika approver sedang mendelegasikan (`tbldelegation` aktif), delegate
> ditambahkan otomatis sebagai kandidat (1 hop, anti-loop).

### Operator kondisi (`condition_json`)
```
Skalar    : = != > >= < <= IN NOT_IN BETWEEN CONTAINS IS_NULL IS_NOT_NULL
Sum array : SUM_GT SUM_GTE SUM_LT SUM_LTE SUM_EQ   (mis. field "detail[].value_retur_ori")
Array     : ANY_IN NONE_IN                          (mis. field "_computed.idmsalasan_list")
Logika    : AND OR (nested, batas kedalaman via config)
```
Field tidak ditemukan → default **fail-closed** (rule tidak match) demi keamanan.

---

### Edit field oleh approver (opsional)

Approver dapat mengubah SEBAGIAN field saat memproses task, jika admin mengizinkan:

- **Konfigurasi (admin)**: di form Node → isi **"Field yang boleh diedit"** (1 path per baris,
  gaya form_schema, mis. `header.keterangan`). Tersimpan di `tblflow_step.node_config_json.editable_fields`.
- **Saat approval**: di inbox, field tsb muncul sebagai input "Edit Data" di dalam form keputusan.
- **Aturan keamanan (di-enforce server-side)**:
  - Hanya path yang ada di whitelist node yang boleh diubah (selain itu diabaikan).
  - Hanya nilai **scalar** (teks/angka) — nilai array/objek ditolak (cegah korupsi/injeksi).
  - Path melalui array multi-baris (mis. `detail` >1 baris tanpa indeks) ditolak; pakai indeks
    eksplisit `detail.2.qty` untuk edit baris tertentu.
  - Edit hanya menyentuh `payload_json`; **routing membaca `context_json`** → jalur approval
    yang sudah ditentukan TIDAK berubah (non-routing).
  - Tiap perubahan tercatat append-only di `tblaction_log` (`EDIT_PAYLOAD`, before→after).
- **Ke source app**: data hasil edit ikut di **callback final** (`payload` di body callback).

### Callback per-node (step reached) — opsional

Selain callback final, tiap node bisa mengirim callback **saat flow MASUK node itu** — berguna bila source
app harus "melakukan sesuatu" di step tertentu (mis. generate dokumen, kirim WA, trigger proses).

- **Konfigurasi (admin)**: di form Node → centang **"Kirim callback saat flow MASUK node ini"** + (opsional)
  isi `event_code`. Tersimpan di `node_config_json.callback_on_enter`.
- **Perilaku**:
  - Dikirim ke **`callback_url` source app** yang sama (bedakan event via `event_code`); event_type = `TASK_CREATED`.
  - Body: `event_code`, `node_code`, `step_name`, `request_status`, `reached_at`, dan `payload` (data terkini).
  - **START/END dikecualikan** (state akhir sudah dicakup callback final → tidak ada callback ganda).
  - **Re-entry** (RETURN/loop/resubmit) → dikirim ulang tiap node dimasuki; pakai `reached_at` untuk dedup di source app.
  - Transactional outbox: bila gagal enqueue, keputusan/submit di-rollback (fail-safe, tidak hilang diam-diam).
- **Skala**: mengaktifkan di banyak node menambah volume callback — sesuaikan `APPROVAL_CALLBACK_BATCH_SIZE` & worker.

## 8. Contoh nyata: Flow SFA Retur V2 (sudah di-seed)

Routing otomatis berdasarkan `context_json._computed` (nilai & alasan retur):

| Prioritas | Kondisi | Jalur approval |
|---|---|---|
| 100 | Alasan produk rusak (`idmsalasan ∈ {61,68}`) | BMH→RRM→NRM→PMM→PD→CEO |
| 110 | Alasan kemasan/label (`{11,33,34,35,36}`) | BMH→RRM→NRM→PKG→CEO |
| 120 | Nilai retur > 25.000.000 | BMH→RRM→NRM→CEO |
| 130 | 15.000.001 – 25.000.000 | BMH→RRM→NRM |
| 140 | Alasan khusus (`{56,62,63,64,66}`) | BMH→RRM→NRM |
| 150 | 5.000.001 – 15.000.000 | BMH→RRM |
| 200 | Default (≤ 5.000.000) | BMH saja |

Approver ditentukan dinamis: BMH dari cabang (`_computed.bmh_user_ref`), CEO dari jabatan
(`JOBTITLE JT0526`), PMM/PD dari product group. Ganti pejabat cukup di HR — flow tak berubah.

---

## 9. Operasional (ringkas)

Agar subsistem async berjalan, butuh **queue worker** + **scheduler cron**:

```bash
# Worker (callback, notifikasi, SLA, async-start). Produksi: pakai systemd/supervisor.
php artisan queue:work database --queue=default --tries=3 --max-time=3600

# Scheduler (produksi via cron tiap menit):
* * * * * cd /path/approval_center && php artisan schedule:run >> storage/logs/schedule.log 2>&1
```

Command maintenance (terjadwal otomatis):
- `approval:reconcile-stuck` — re-drive request SUBMITTED yang menggantung (safety net async).
- `approval:prune-logs` — retensi log operasional (route_log/integration_log/callback SENT-DEAD); **audit tidak di-prune**.

Konfigurasi penting (`.env` / `config/approval_center.php`):
- `APPROVAL_ASYNC_START` — jalankan engine via queue (skalabilitas beban tinggi).
- `APPROVAL_CALLBACK_BATCH_SIZE`, `APPROVAL_API_RATE_LIMIT`, `APPROVAL_CALLBACK_ALLOWED_CIDRS`,
  `APPROVAL_ENRICHMENT_CACHE_TTL`, `APPROVAL_LOG_RETENTION_DAYS`.

Detail keamanan, performa & load test: lihat `SECURITY.md`, `performance/PERFORMANCE_DESIGN.md`,
`tests/load/README.md`.

---

## 10. Insight: kekuatan & hal yang perlu diperhatikan

**Kekuatan**
- ✅ **Config-driven** — tambah/ubah program approval tanpa deploy ulang aplikasi pengirim.
- ✅ **Visual builder** (ReactFlow) — alur digambar, bukan dikoding.
- ✅ **Multi-aplikasi & terisolasi** per `source_app`.
- ✅ **Production-grade**: idempotent, transaksional + row-lock anti-race, callback
  transactional-outbox + retry/DEAD, audit append-only (trigger DB), throughput teruji
  **≥1000 request/menit** (create p95 ~100ms, error 0%).

**Hal yang perlu diperhatikan (by design / keterbatasan saat ini)**
- ⚠️ Mode approval **ANY** aktif penuh; **ALL / SEQUENTIAL** masih fallback ke ANY (di-log, belum penuh).
- ⚠️ DECISION gateway baru **EXCLUSIVE** (pilih 1 jalur); INCLUSIVE/PARALLEL belum diimplementasi.
- ⚠️ Engine membaca **konfigurasi LIVE** (bukan snapshot per-instance) — dimitigasi dengan
  penguncian versi yang punya instance berjalan; snapshot penuh = rekomendasi jangka panjang.
- ⚠️ `ADMIN_APPROVAL`/`AUDITOR` **global lintas program** (belum ada scoping auditor per-program).

---

## 11. Peta halaman (menu) lengkap

| Area | Halaman | Akses |
|---|---|---|
| Auth | login, change-password (paksa ganti saat pertama) | semua |
| Dashboard | ringkasan | login |
| Master | source-app, api-client (+secret/rotate), user (+reset-pwd), role, org-unit, position, approval-group (+member), document-type, delegation | ADMIN |
| Workflow | flow-definition, flow-version (validate/deploy/clone/preview), node, edge, assignee-rule, routing-rule, **builder** (kanvas visual) | ADMIN |
| Inbox | index, history, detail task, aksi (approve/reject/return) | APPROVER, ADMIN |
| Monitoring | daftar + detail request | ADMIN, AUDITOR |
| Audit | action-log, audit-event, integration-log, callback-outbox (+retry) | ADMIN, AUDITOR |

---

*Panduan ini disusun dari hasil scan menyeluruh kode & skema. Untuk perubahan signifikan,
perbarui dokumen ini agar tetap menjadi rujukan tim.*
