# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Konteks proyek untuk Claude Code. File ini menggantikan "ingatan" sesi sebelumnya.

---

## 1. Identitas Proyek

| Item | Detail |
|---|---|
| **Nama** | Approval Center Propan |
| **Tujuan** | Hub persetujuan terpusat untuk semua aplikasi internal PT Propan Raya ICC (PR Online, Retur Barang/SFA, BSKB, RPD, PIS, dll) |
| **Stack** | PHP `^8.2`, Laravel `^11.0`, MySQL 8.x/MariaDB, Blade + Bootstrap 5 (CDN), ReactFlow v11 (UMD), Guzzle 7 |
| **Queue** | Laravel Queue, driver `database` |
| **Server (prod)** | `http://10.50.0.4/approval_center/public/` — Apache, Ubuntu, user `elos`, path `/var/www/html/approval_center/` |
| **Dev lokal** | Windows, path `C:\2026\Program\Development\ai\approval_center` (bukan git repo) |

> Versi stack di atas mengikuti `composer.json` (`php ^8.2`, `laravel/framework ^11.0`). Jangan klaim versi minor spesifik kecuali sudah dicek.

---

## 1a. Development Commands

```powershell
# Setup
composer install
php artisan key:generate          # jika .env belum ada APP_KEY

# Import schema utama (sumber kebenaran), lalu ALTER migrations
# mysql -uroot -p approval_center < approval_center_schema_tbl.sql
php artisan migrate               # HANYA ALTER tambahan — jangan drop/recreate

# Seeder
php artisan db:seed                                   # role + admin dev
php artisan db:seed --class=SfaReturFlowV2Seeder      # seeder spesifik

# Run app
php artisan serve                 # http://localhost:8000 — login user ADMIN_DEV

# Worker & scheduler (terminal terpisah)
php artisan queue:work --tries=3
php artisan schedule:work         # ProcessCallbackOutboxJob tiap 1m, SlaEscalationJob tiap 30m

# Test (PHPUnit 11; DB test = approval_center_test, perlu MySQL)
php artisan test                              # semua test
php artisan test --testsuite=Feature
php artisan test tests/Feature/Auth/LoginTest.php          # satu file
php artisan test --filter=ForcePasswordChangeTest          # satu test/method

# Lint / format
./vendor/bin/pint                 # Laravel Pint (code style)
./vendor/bin/pint --test          # cek tanpa mengubah

# Dev utility command (DEV ONLY)
php artisan approval:reset {request_id} {node_code?} [--list] [--force]

# Selalu clear cache setelah perubahan view/config/route
php artisan view:clear; php artisan config:clear; php artisan route:clear
```

---

## 2. Konvensi WAJIB (Jangan Dilanggar)

```
Nama tabel   : tblxxx          (contoh: tblapproval_request)
Primary key  : idtblxxx        (contoh: idtblapproval_request)
Schema SQL   : sumber kebenaran — hanya buat ALTER migration, bukan drop/recreate
step_code    : selalu = node_code (NOT NULL di DB)
approval_mode: default 'ANY' jika null dari form 
level_no     : kolom yang benar di tblposition (bukan position_level)
```

---

## 3. Arsitektur Hub-and-Spoke

```
Aplikasi SFA ──┐
Aplikasi PR  ──┤──► ApprovalSubmitController (HMAC Auth)
Aplikasi BSKB──┘         │
                          ▼
                  PayloadEnrichmentService   ← inject _computed ke context_json
                          │
                          ▼
                  RoutingRuleService         ← tentukan flow version
                          │
                          ▼
                  FlowEngineService          ← BPMN-lite runtime engine
                          │
                     ┌────┴────┐
                     ▼         ▼
               tbltask    tblprocess_instance
                     │
                     ▼
              Inbox Approver
                     │
                     ▼
              CallbackOutbox  ──► callback ke source app
```

---

## 4. Flow Engine — Cara Kerja

### Node Types
| Type | Behavior |
|---|---|
| `START` | Auto-forward ke next node, tidak buat task |
| `DECISION` | Evaluasi condition_json tiap edge, pilih priority terkecil yang match |
| `APPROVAL` | Buat `tbltask` + `tbltask_candidate`, tunggu keputusan approver |
| `END` | Panggil `completeProcess()` |

### Assignee Types di `tblstep_assignee_rule`
| Type | Value | Cara Resolve |
|---|---|---|
| `USER` | NPK/user_ref | Langsung lookup `tbluser.user_ref` |
| `ROLE` | role_code | Semua user aktif dengan role tsb |
| `FIELD_USER` | nama field di context_json | `context_json._computed.bmh_user_ref` |
| `JOBTITLE` | jobtitleid | Query `db_master.tbemployeeit WHERE jobtitleid=? AND activestatus=1` → `employeeno` → `tbluser.user_ref` |
| `API_RESOLVER` | URL endpoint | POST ke URL dengan context |

### Condition Operators (ConditionEvaluatorService + ConditionJsonValidator)
```
Scalar   : = != > >= < <= IN NOT_IN BETWEEN CONTAINS IS_NULL IS_NOT_NULL
Sum array: SUM_GT SUM_GTE SUM_LT SUM_LTE SUM_EQ
           → field: "_computed.total_nilai_retur" ATAU "detail[].value_retur_ori"
Array    : ANY_IN NONE_IN
           → field: "_computed.idmsalasan_list", value: [61, 68]
```

---

## 5. PayloadEnrichmentService — _computed Fields

Dipanggil di `ApprovalSubmitController` sebelum engine jalan.
Hanya aktif untuk `source_app_code = 'SFA'` (bisa diperluas).

```php
context_json._computed = [
    'total_nilai_retur'  // sum(payload.detail[].value_retur_ori)
    'idmsalasan_list'    // unique list payload.detail[].idmsalasan
    'bmh_user_ref'       // NPK BMH dari tblapprover_branch_map by header.idtblbranch
    'bmh_user_refs'      // array semua BMH di cabang (mode ANY)
    'rrm_user_ref'       // NPK RRM dari bmh→rrm mapping
    'pmm_user_ref'       // NPK PMM dari db_master.ms_product_group by detail.ph (ph4)
    'pd_user_ref'        // NPK PD dari db_master.ms_product_group by detail.ph (ph4)
    'nrm_user_ref'       // '11990056' (JULIUS KURATA) - hardcoded
    'ceo_user_ref'       // '1030018'  (KRIS RIANTO ADIDARMA) - via JT0526
    'pkg_user_ref'       // '11130476' (HENDRI GUNAWAN) - via JT0286
]
```

---

## 6. Flow SFA Retur V2 — FLOW_SFA_RETUR

### 7 Jalur (prioritas evaluasi dari atas ke bawah)

| Prioritas | Kondisi | Jalur Approval |
|---|---|---|
| 100 | `ANY_IN idmsalasan_list [61,68]` (Produk Rusak/Bermasalah) | BMH→RRM→NRM→PMM→PD→CEO |
| 110 | `ANY_IN idmsalasan_list [11,33,34,35,36]` (Kemasan/Label) | BMH→RRM→NRM→PKG→CEO |
| 120 | `SUM_GT total_nilai_retur 25000000` | BMH→RRM→NRM→CEO |
| 130 | SUM 15.000.001 – 25.000.000 | BMH→RRM→NRM |
| 140 | `ANY_IN idmsalasan_list [56,62,63,64,66]` (Alasan Khusus) | BMH→RRM→NRM |
| 150 | SUM 5.000.001 – 15.000.000 | BMH→RRM |
| 200 | DEFAULT (≤ 5.000.000) | BMH saja |

### Assignee Rules V2
| Node | Type | Value |
|---|---|---|
| BMH | `FIELD_USER` | `_computed.bmh_user_ref` |
| RRM | `FIELD_USER` | `_computed.rrm_user_ref` |
| NRM | `USER` | `11990056` (Julius Kurata) |
| PMM | `FIELD_USER` | `_computed.pmm_user_ref` |
| PD | `FIELD_USER` | `_computed.pd_user_ref` |
| PKG | `JOBTITLE` | `JT0286` (Packaging Management Sub Dept Head) |
| CEO | `JOBTITLE` | `JT0526` (Chief Executive Officer) |

---

## 7. Database — Tabel Kritis & Kolom yang Sering Salah

### `tblprocess_instance`
```sql
instance_status  ENUM('RUNNING','COMPLETED','REJECTED','CANCELLED','ERROR')
idtblflow_step_current  BIGINT NULL
started_at       DATETIME(3)
ended_at         DATETIME(3) NULL     ← BUKAN completed_at!
```

### `tblapproval_request`
```sql
request_status   ENUM('DRAFT','SUBMITTED','IN_PROGRESS','APPROVED','REJECTED','RETURNED','CANCELLED','ERROR')
completed_at     DATETIME(3) NULL
idtblflow_step_current  BIGINT NULL
```

### `tbltask`
```sql
task_status      ENUM('OPEN','CLAIMED','APPROVED','REJECTED','RETURNED','CANCELLED','SKIPPED','EXPIRED')
task_no          VARCHAR(120) NOT NULL UNIQUE   ← WAJIB diisi saat create!
idtbluser_assigned      BIGINT NULL
idtbluser_claimed_by    BIGINT NULL
idtbluser_completed_by  BIGINT NULL
decision_code    ENUM('APPROVE','REJECT','RETURN','CANCEL','SKIP','AUTO_APPROVE')
decision_note    TEXT NULL
completed_at     DATETIME(3) NULL
```

### `tblstep_assignee_rule`
```sql
assignee_type    ENUM('USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','API_RESOLVER','JOBTITLE')
-- JOBTITLE ditambah via migration 2026_05_26_000001
```

### `tblapprover_branch_map` (tabel custom)
```sql
idtblbranch      VARCHAR(10)   ← kode cabang SFA dari header.idtblbranch
bmh_user_ref     VARCHAR(20)   ← NPK BMH
rrm_user_ref     VARCHAR(20)   ← NPK RRM atasan BMH
```

---

## 8. Cross-Database Queries

Server MySQL yang sama punya beberapa database:

| Database | Isi |
|---|---|
| `approval_center` (default) | Semua tabel sistem ini |
| `db_master` | `ms_jobtitle`, `tbemployeeit`, `ms_product_group` |
| `db_sfa` | Data SFA (read-only referensi) |

Contoh query cross-DB:
```php
DB::select("SELECT produk_manager, pd_manager FROM db_master.ms_product_group WHERE ph4 = ?", [$ph4]);
DB::select("SELECT employeeno FROM db_master.tbemployeeit WHERE jobtitleid = ? AND activestatus = 1", [$jobtitleId]);
```

---

## 9. Visual Builder (ReactFlow v11 UMD)

**PENTING:** Pakai `reactflow@11.11.4` UMD via jsDelivr CDN.
**JANGAN** pakai `@xyflow/react@12` — butuh React 19 yang belum didukung.

### Pattern kritis
```javascript
// G._nodes dan G._edges diupdate SETIAP React render DAN di applyNode()
// Jangan pakai G.getNodes() langsung — bisa stale
G._nodes = nodes;  // diset dalam React component render
G._edges = edges;

// applyNode() HARUS update G._nodes secara sinkron
G._nodes = G._nodes.map(n => n.id === id ? {...n, data: newData} : n);
G.selNode = {...G.selNode, data: newData};  // update selNode juga
```

### Route Builder API
```
GET  /workflow/api/jobtitle-search?q=keyword  → search ms_jobtitle
GET  /workflow/flow-version/{id}/builder-data
POST /workflow/flow-version/{id}/builder-save
POST /workflow/flow-version/{id}/builder-validate
POST /workflow/flow-version/{id}/builder-deploy
POST /workflow/flow-version/{id}/builder-clone
```

---

## 10. Form Schema Builder (Document Type)

`tbldocument_type.form_schema` = JSON array field definitions:
```json
[
  {"field": "header.customer_name", "label": "Nama Customer", "type": "text", "width": "third"},
  {"field": "detail",               "label": "Detail Barang", "type": "table", "width": "full",
   "columns": ["product_name","qty","value_retur"],
   "col_labels": ["Nama","Qty","Nilai"]}
]
```

Field `header.xxx` → resolve dari `payload_json.header[0].xxx` (array, ambil index 0).
Field `detail` → resolve sebagai tabel dari `payload_json.detail[]`.

Renderer: `resources/views/partials/_context_renderer.blade.php`

---

## 11. Users & Password Seeder

| Group | Contoh NPK | Password |
|---|---|---|
| BMH Tangerang | 11110247 (SLAMET SANTOSO) | `Propan@11110247` |
| RRM | 11030021 (MOH. CARNO ADINATA) | `Propan@11030021` |
| NRM | 11990056 (JULIUS KURATA) | `Propan@11990056` |
| CEO | 1030018 (KRIS RIANTO ADIDARMA) | `Propan@1030018` |
| PKG | 11130476 (HENDRI GUNAWAN) | `Propan@11130476` |
| Admin | ADMIN_DEV | (set manual saat deploy) |

---

## 12. Migrations yang Sudah Dijalankan

```
2026_05_20_000001  core tables (tblapproval_request, tblflow_*, dll)
2026_05_20_000002  tblprocess_instance
2026_05_20_000003  tbltask + tbltask_candidate
2026_05_20_000004  step_type ENUM + DECISION
2026_05_20_000005  tblprocess_route_log
2026_05_20_000006  tblprocess_route_log (lanjutan)
2026_05_20_000007  tblprocess_token
2026_05_21_000001  tbldocument_type.form_schema (JSON column)
2026_05_21_000002  tbldocument_type.sample_context_json
2026_05_25_000001  tblapprover_branch_map (branch→BMH/RRM mapping)
2026_05_26_000001  ALTER tblstep_assignee_rule: tambah JOBTITLE ke ENUM
```

---

## 13. Seeders yang Sudah Dijalankan

```
RoleSeeder                  → role: ADMIN_APPROVAL, APPROVER, AUDITOR, dll
DevAdminUserSeeder          → user ADMIN_DEV
SfaUsersAndBranchMapSeeder  → 72 user SFA + 47 rows tblapprover_branch_map
SfaReturFlowV2Seeder        → Flow V2 dengan 10 node + 27 edge (status: DEPLOYED/ACTIVE)
UpdateSfaR1SchemaSeeder     → form_schema 17 field untuk SFA_R1
SfaReturFlowV2TrialSeeder   → 4 approval request trial (V2-TRIAL-P4/P3/P6/P7)
```

---

## 14. File-file Kritis

```
app/Services/FlowEngineService.php          ← BPMN runtime engine
app/Services/PayloadEnrichmentService.php   ← inject _computed SFA
app/Services/AssigneeResolverService.php    ← resolve approver dari rules
app/Services/ConditionEvaluatorService.php  ← evaluasi condition_json (ANY_IN, SUM_GT, dll)
app/Support/ConditionJsonValidator.php      ← validasi struktural condition_json
app/Http/Controllers/Api/V1/ApprovalSubmitController.php
app/Http/Controllers/Web/InboxController.php
app/Http/Controllers/Web/Workflow/FlowBuilderController.php
resources/views/workflow/builder/index.blade.php   ← Visual builder (ReactFlow v11)
resources/views/partials/_context_renderer.blade.php
database/seeders/SfaReturFlowV2Seeder.php
```

---

## 15. Bug yang Sudah Diperbaiki (Riwayat)

| # | Bug | Fix |
|---|---|---|
| 1 | `step_code` NOT NULL | FlowNodeController set `step_code = node_code` |
| 2 | `approval_mode` NOT NULL | Default `'ANY'` |
| 3 | `match($x){...}` di Blade | Ganti dengan `@php $var = [...][x]` |
| 4 | ReactFlow `jsx` undefined | Pakai `reactflow@11` UMD, bukan `@xyflow/react@12` |
| 5 | Canvas tidak tersimpan | `G._nodes`/`G._edges` di-update sinkron di `applyNode()` |
| 6 | `completeTask()` tidak ada | Ditambahkan sebagai wrapper `completeCurrentTask()` |
| 7 | `task_status = 'PENDING'` | ENUM hanya punya `OPEN` — difix ke `'OPEN'` |
| 8 | `instance->completed_at` | Kolom sebenarnya `ended_at` di `tblprocess_instance` |
| 9 | `instance->status` | Kolom sebenarnya `instance_status` |
| 10 | `request->status` | Kolom sebenarnya `request_status` |
| 11 | `idtbluser_assignee` | Kolom sebenarnya `idtbluser_assigned` |
| 12 | Task tanpa `task_no` | `task_no` NOT NULL — generate `TSK-{NODE}-{ID}-{TS}` |
| 13 | JOBTITLE tidak ada di ENUM | Migration `2026_05_26_000001` tambah `JOBTITLE` |
| 14 | `ConditionJsonValidator` tidak kenal `ANY_IN`/`SUM_GT` | Ditambahkan ke `OPERATORS` |
| 15 | Builder stuck di "Memuat canvas" | Stray `</script>` menutup script utama terlalu awal |
| 16 | JOBTITLE value tidak tersimpan | `applyNode()` harus update `G._nodes` sinkron |

---

## 16. Cara Deploy Perubahan ke Server

```bash
# 1. Copy file
scp file.php elos@10.50.0.4:/var/www/html/approval_center/path/to/file.php

# 2. Migration baru
php artisan migrate

# 3. Seeder baru
php artisan db:seed --class=NamaSeeder

# 4. Clear cache (selalu setelah perubahan)
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

---

## 17. Tentang Claude Code di VSCode

**Cara terbaik menggunakan file ini:**

1. Letakkan `CLAUDE.md` di root proyek: `/var/www/html/approval_center/CLAUDE.md`
2. Claude Code otomatis membaca `CLAUDE.md` saat mulai sesi baru
3. File ini menggantikan "ingatan" dari sesi chat sebelumnya

**Tips tambahan:**
- Setiap kali ada perubahan signifikan, minta Claude Code untuk update bagian yang relevan di `CLAUDE.md`
- Buat juga `.claude/` folder di root untuk instruksi tambahan per-direktori
- Gunakan `@CLAUDE.md` di prompt Claude Code untuk eksplisit merujuk file ini

---

*Dibuat: 26 Mei 2026 | Terakhir diupdate: sesi ini*
