# Approval Center Propan

Website approval terpusat untuk menangani approval lintas aplikasi internal Propan: PR Online, Retur Barang, BSKB, RPD / Propan Journey, PIS, dan aplikasi lainnya.

Tech stack:
- **Backend** : PHP 8.2+, Laravel 11
- **Database**: MySQL 8.x / MariaDB 10.6+
- **Frontend**: Blade + Bootstrap 5
- **Queue**   : Laravel Queue (driver `database`)
- **Auth**    : Session login

> **PENTING**: Naming convention table mengikuti file `approval_center_schema_tbl.sql` (format `tblxxx`, primary key `idtblxxx`). Tidak boleh diubah ke konvensi default Laravel.

---

## 1. Setup Development

### 1.1 Prasyarat
- PHP 8.2+ dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `json`, `tokenizer`, `xml`, `ctype`, `bcmath`
- Composer 2.x
- MySQL 8.x atau MariaDB 10.6+
- Node.js (opsional untuk aset; di tahap awal kita pakai CDN Bootstrap)

### 1.2 Langkah Setup

```bash
# 1. Clone & masuk folder
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env: isi DB_DATABASE / DB_USERNAME / DB_PASSWORD

# 4. Buat database & import schema utama (sumber kebenaran)
mysql -uroot -p < path/to/approval_center_schema_tbl.sql

# 5. Jalankan migration ALTER tambahan (auth fields & secret cipher)
php artisan migrate

# 6. Seeder role + admin development
php artisan db:seed

# 7. Jalankan server
php artisan serve
```

Login pertama:
- URL       : `http://localhost:8000/login`
- User Ref  : `ADMIN_DEV`
- Password  : `admin123`
- Anda akan dipaksa ganti password (must_change_password = 1).

### 1.3 Menjalankan Worker & Scheduler

```bash
# Terminal 1 — queue worker (callback, notification, sla)
php artisan queue:work --tries=3

# Terminal 2 — scheduler (di dev pakai schedule:work; di prod pakai cron)
php artisan schedule:work
```

---

## 2. Struktur Folder Singkat

```
app/
├── Http/Controllers/Api/V1     ← API endpoint untuk aplikasi asal
├── Http/Controllers/Web        ← UI admin / approver / auditor
├── Http/Middleware             ← role check, HMAC auth, etc.
├── Models                      ← 29 Eloquent model (tblxxx)
├── Services                    ← 12 service class (workflow engine)
├── Support/RuleEngine          ← Condition evaluator (=, !=, >, IN, AND, OR, ...)
├── Support/AssigneeResolvers   ← USER, ROLE, GROUP, POSITION, SUPERIOR, dst
├── Support/ApprovalMode        ← ANY (aktif), ALL/SEQUENTIAL (TODO)
├── Support/Security            ← AES cipher untuk secret, HMAC verifier
└── Jobs                        ← SendCallback, SendNotification, SlaEscalation

database/migrations             ← HANYA ALTER tambahan
database/seeders                ← Role + admin dev
routes/web.php                  ← UI route (dengan role middleware)
routes/api.php                  ← /api/v1/* dengan HMAC auth
routes/console.php              ← Scheduler
config/approval_center.php      ← Konfigurasi modul (HMAC, callback, rule engine)
```

---

## 3. Endpoint API Inbound

| Method | Endpoint                                                           | Fungsi                                        |
|--------|--------------------------------------------------------------------|-----------------------------------------------|
| POST   | `/api/v1/approval/submit`                                          | Submit approval request dari aplikasi asal    |
| GET    | `/api/v1/approval/status/{source_app}/{document_type}/{source_request_id}` | Cek status approval                  |
| POST   | `/api/v1/approval/cancel`                                          | Cancel approval (jika belum final)            |
| POST   | `/api/v1/callback/test`                                            | Dummy receiver untuk testing callback         |

Header wajib (kecuali `/callback/test`):
```
X-Client-Key:  <public key dari tblapi_client>
X-Timestamp:   <unix epoch detik>
X-Signature:   HMAC_SHA256( X-Timestamp + "\n" + raw_body , decrypted_client_secret )
```

---

## 4. Prinsip Implementasi (jangan dilanggar)

- Flow approval **tidak boleh hardcode** per aplikasi. Selalu lewat `tblflow_definition` + `tblflow_version` + `tblflow_step` + `tblstep_assignee_rule` + `tblflow_transition` + `tblrouting_rule`.
- Submit approval **wajib pakai `idempotency_key`**.
- Callback **wajib via `tblcallback_outbox`**, tidak boleh langsung dari controller.
- Semua **inbound/outbound API** dicatat di `tblintegration_message_log`.
- Semua **aksi approval** dicatat di `tblaction_log`.
- Perubahan master data dicatat di `tblaudit_event`.
- Submit approval & action approval **wajib dalam `DB::transaction()`**.
- **Naming convention table & PK** dari file SQL utama TIDAK boleh diubah.

---

## 5. Roadmap Implementasi

| Tahap | Status | Output |
|-------|--------|--------|
| 1 | ✅ Selesai | Analisa FSD & schema |
| 2 | ✅ Selesai | Project skeleton + migration + seeder |
| 3 | ⏳ Berikutnya | 29 Eloquent model + relationship |
| 4 | ⏳ | Authentication + role middleware |
| 5 | ⏳ | Master Data CRUD |
| 6 | ⏳ | Core Workflow Engine (12 service class) |
| 7 | ⏳ | API Integration (HMAC + idempotency) |
| 8 | ⏳ | UI Operational + Queue Jobs + Scheduler |
