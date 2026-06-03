# Deployment Guide — Approval Center Propan

> Versi dokumen: 2.0 | Dibuat: Mei 2026  
> Stack: PHP 8.2+, Laravel 11, MySQL 8.x / MariaDB 10.6+, Queue driver: database

---

## Daftar Isi

1. [Prasyarat Server](#1-prasyarat-server)
2. [Struktur File Proyek](#2-struktur-file-proyek)
3. [Setup Lokal (Development)](#3-setup-lokal-development)
4. [Deploy ke Server Production](#4-deploy-ke-server-production)
5. [Konfigurasi .env](#5-konfigurasi-env)
6. [Setup Database](#6-setup-database)
7. [Queue Worker & Supervisor](#7-queue-worker--supervisor)
8. [Scheduler Cron](#8-scheduler-cron)
9. [Web Server — Nginx](#9-web-server--nginx)
10. [Web Server — Apache](#10-web-server--apache)
11. [Mermaid.js Offline (Production Internal)](#11-mermaidjs-offline-production-internal)
12. [First Login & Konfigurasi Awal](#12-first-login--konfigurasi-awal)
13. [Checklist Pre-Launch](#13-checklist-pre-launch)
14. [Monitoring & Troubleshooting](#14-monitoring--troubleshooting)
15. [Upgrade Prosedur](#15-upgrade-prosedur)
16. [Keamanan](#16-keamanan)

---

## 1. Prasyarat Server

### Software

| Komponen | Versi Minimum | Catatan |
|---|---|---|
| PHP | 8.2+ | Disarankan 8.3 |
| Composer | 2.x | Untuk install dependency |
| MySQL | 8.0+ | Atau MariaDB 10.6+ |
| Nginx / Apache | — | Pilih salah satu |
| Supervisor | — | Untuk queue worker |
| Git | — | Untuk deploy |
| Node.js | 18+ | Hanya untuk build assets jika ada (opsional) |

### PHP Extensions Wajib

```
php-fpm php-mysql php-mbstring php-xml php-bcmath
php-curl php-zip php-intl php-redis (opsional) php-gd
```

Install di Ubuntu/Debian:
```bash
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-intl php8.3-gd
```

### Spesifikasi Server Minimum

| | Development | Production |
|---|---|---|
| CPU | 2 core | 4 core |
| RAM | 2 GB | 8 GB |
| Disk | 20 GB | 100 GB |
| OS | Ubuntu 22.04+ | Ubuntu 22.04 LTS |

---

## 2. Struktur File Proyek

```
approval-center/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/          # ApprovalSubmit, Status, Cancel
│   │   │   └── Web/             # Dashboard, Inbox, Monitoring, Audit
│   │   │       ├── Master/      # SourceApp, ApiClient, User, Role, dll
│   │   │       └── Workflow/    # FlowDefinition, FlowVersion, Node, Edge
│   │   ├── Middleware/          # Auth, HMAC, Role, ForcePasswordChange
│   │   └── Requests/            # FormRequests per domain
│   ├── Jobs/                    # SendCallback, ProcessOutbox, SlaEscalation
│   ├── Models/                  # 31 Eloquent models (tblxxx)
│   ├── Services/                # 8 service classes
│   └── Support/                 # ConditionJsonValidator, FlowValidationResult
├── database/
│   ├── migrations/              # 7 ALTER/CREATE migrations BPMN-lite
│   └── seeders/                 # DatabaseSeeder, AdminSeeder
├── resources/views/             # 77 blade views
├── routes/
│   ├── api.php                  # /api/v1/approval/*
│   ├── web.php                  # Web routes
│   └── console.php              # Scheduler (cron jobs)
├── approval_center_schema_tbl.sql  # Schema utama (29 tabel)
└── .env.example
```

---

## 3. Setup Lokal (Development)

### 3.1 Clone Repository

```bash
# Sesuaikan dengan lokasi repo atau upload manual ke server
cd approval-center
```

### 3.2 Install Dependencies

```bash
composer install
```

### 3.3 Buat .env

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` — minimal isi:
```env
DB_HOST=127.0.0.1
DB_DATABASE=approval_center
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3.4 Setup Database

```bash
# 1. Buat database
mysql -u root -p -e "CREATE DATABASE approval_center CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import schema utama (29 tabel)
mysql -u root -p approval_center < approval_center_schema_tbl.sql

# 3. Jalankan migrations BPMN-lite (7 migrations)
php artisan migrate

# 4. Seed data awal (admin user + role)
php artisan db:seed
```

### 3.5 Setup Storage & Cache

```bash
php artisan storage:link
php artisan config:clear
php artisan cache:clear
```

### 3.6 Jalankan Development Server

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work database --queue=default,callbacks --tries=3

# Terminal 3 (opsional): Scheduler testing
php artisan schedule:work
```

Buka `http://localhost:8000` — login dengan:
- **Username:** `ADMIN_DEV`
- **Password:** `admin123`
- Sistem akan meminta ganti password saat login pertama.

---

## 4. Deploy ke Server Production

### 4.1 Persiapan di Server

```bash
# Buat user aplikasi (jangan pakai root)
sudo useradd -m -s /bin/bash appuser
sudo usermod -aG www-data appuser

# Buat direktori
sudo mkdir -p /var/www/approval-center
sudo chown appuser:www-data /var/www/approval-center
```

### 4.2 Clone & Setup

```bash
cd /var/www
sudo -u appuser # Sesuaikan dengan lokasi repo atau upload manual ke server
cd approval-center

# Install dependencies tanpa dev packages
sudo -u appuser composer install --no-dev --optimize-autoloader

# Setup .env
sudo -u appuser cp .env.example .env
sudo -u appuser php artisan key:generate
```

### 4.3 Konfigurasi .env Production

```bash
sudo -u appuser nano .env
```

Isi sesuai [Seksi 5](#5-konfigurasi-env).

### 4.4 Permissions

```bash
sudo chown -R appuser:www-data /var/www/approval-center
sudo chmod -R 755 /var/www/approval-center
sudo chmod -R 775 /var/www/approval-center/storage
sudo chmod -R 775 /var/www/approval-center/bootstrap/cache
```

### 4.5 Setup Database di Production

```bash
# Import schema
mysql -h DB_HOST -u DB_USER -p DB_NAME < approval_center_schema_tbl.sql

# Jalankan migrations
sudo -u appuser php artisan migrate --force

# Seed (admin user pertama)
sudo -u appuser php artisan db:seed --force
```

### 4.6 Optimize untuk Production

```bash
sudo -u appuser php artisan config:cache
sudo -u appuser php artisan route:cache
sudo -u appuser php artisan view:cache
sudo -u appuser php artisan event:cache
sudo -u appuser php artisan storage:link
```

---

## 5. Konfigurasi .env

### Konfigurasi Lengkap

```dotenv
# =====================================================
# APLIKASI
# =====================================================
APP_NAME="Approval Center Propan"
APP_ENV=production          # local | production
APP_KEY=                    # Di-generate oleh: php artisan key:generate
APP_DEBUG=false             # WAJIB false di production
APP_URL=https://approval.propan.internal

# =====================================================
# DATABASE
# =====================================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=approval_center
DB_USERNAME=approval_user
DB_PASSWORD=SECRET_PASSWORD
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# =====================================================
# QUEUE (wajib: database — tidak perlu Redis)
# =====================================================
QUEUE_CONNECTION=database

# =====================================================
# CACHE & SESSION
# =====================================================
CACHE_STORE=database        # Atau file jika database queue sudah berat
SESSION_DRIVER=database
SESSION_LIFETIME=480        # 8 jam (menit)
SESSION_SECURE_COOKIE=true  # Wajib jika pakai HTTPS

# =====================================================
# MAIL (untuk notifikasi, opsional di fase awal)
# =====================================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.propan.internal
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@propan.co.id
MAIL_FROM_NAME="Approval Center"

# =====================================================
# LOGGING
# =====================================================
LOG_CHANNEL=daily           # Rotasi harian
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning           # debug | info | warning | error

# =====================================================
# APPROVAL CENTER — Config Kustom
# =====================================================
# Default max retry callback
APPROVAL_CALLBACK_MAX_RETRY=10

# HMAC timestamp tolerance (detik). Default 300 = 5 menit
APPROVAL_HMAC_TOLERANCE=300

# Timezone
APP_TIMEZONE=Asia/Jakarta
```

### Catatan Penting .env

> **JANGAN** commit file `.env` ke repository.  
> Gunakan `.env.example` (tanpa nilai rahasia) sebagai template.  
> Untuk production, gunakan secret manager atau set via CI/CD.

---

## 6. Setup Database

### 6.1 Buat User Database Dedicated

```sql
-- Di MySQL sebagai root
CREATE USER 'approval_user'@'127.0.0.1'
    IDENTIFIED BY 'SECRET_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
    ON approval_center.*
    TO 'approval_user'@'127.0.0.1';

FLUSH PRIVILEGES;
```

### 6.2 Urutan Import & Migration

```bash
# Step 1: Import 29 tabel utama (dari file asli)
mysql -u approval_user -p approval_center < approval_center_schema_tbl.sql

# Step 2: Cek status migrations sebelum jalankan
php artisan migrate:status

# Step 3: Jalankan 7 migrations BPMN-lite
php artisan migrate --force

# Step 4: Verifikasi tabel baru ada
mysql -u approval_user -p approval_center -e "SHOW TABLES LIKE 'tblprocess%';"
```

### 6.3 Database Backup Sebelum Deploy

```bash
# Backup sebelum setiap deploy
mysqldump -u approval_user -p approval_center \
    --single-transaction \
    --routines \
    --triggers \
    > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 6.4 Tabel Queue (Wajib)

Tabel `jobs` dan `failed_jobs` harus sudah ada. Jika belum:
```bash
php artisan queue:table
php artisan migrate
```

Cek:
```sql
SHOW TABLES LIKE 'jobs';
SHOW TABLES LIKE 'failed_jobs';
```

---

## 7. Queue Worker & Supervisor

Queue worker **wajib berjalan** untuk:
- Mengirim callback ke source app (SendCallbackJob)
- Scan callback outbox (ProcessCallbackOutboxJob)
- Eskalasi SLA (SlaEscalationJob)

### 7.1 Install Supervisor

```bash
sudo apt install -y supervisor
```

### 7.2 Konfigurasi Supervisor

Buat file `/etc/supervisor/conf.d/approval-center.conf`:

```ini
[program:approval-center-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/approval-center/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=default,callbacks
directory=/var/www/approval-center
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=appuser
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/approval-center-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:approval-center-scheduler]
process_name=%(program_name)s
command=php /var/www/approval-center/artisan schedule:work
directory=/var/www/approval-center
autostart=true
autorestart=true
user=appuser
redirect_stderr=true
stdout_logfile=/var/log/approval-center-scheduler.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
```

### 7.3 Aktifkan Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start approval-center-worker:*
sudo supervisorctl start approval-center-scheduler

# Cek status
sudo supervisorctl status
```

### 7.4 Monitor Queue Worker

```bash
# Lihat antrian
php artisan queue:monitor default,callbacks

# Lihat failed jobs
php artisan queue:failed

# Retry semua failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush
```

---

## 8. Scheduler Cron

Jika tidak menggunakan `schedule:work` via Supervisor (alternatif):

```bash
sudo crontab -e -u appuser
```

Tambahkan:
```cron
* * * * * cd /var/www/approval-center && php artisan schedule:run >> /dev/null 2>&1
```

### Jadwal yang Berjalan

| Job | Frekuensi | Fungsi |
|---|---|---|
| ProcessCallbackOutboxJob | Setiap 1 menit | Kirim callback pending ke source app |
| SlaEscalationJob | Setiap 30 menit | Catat task yang overdue |

---

## 9. Web Server — Nginx

### 9.1 Konfigurasi Nginx

Buat `/etc/nginx/sites-available/approval-center`:

```nginx
server {
    listen 80;
    server_name approval.propan.internal;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name approval.propan.internal;

    # SSL
    ssl_certificate     /etc/ssl/certs/approval.propan.crt;
    ssl_certificate_key /etc/ssl/private/approval.propan.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/approval-center/public;
    index index.php;

    # Ukuran upload (untuk attachment dokumen)
    client_max_body_size 20M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
    gzip_min_length 1000;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Cache static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Log
    access_log /var/log/nginx/approval-center-access.log;
    error_log  /var/log/nginx/approval-center-error.log;
}
```

### 9.2 Aktifkan Site

```bash
sudo ln -s /etc/nginx/sites-available/approval-center /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 10. Web Server — Apache

Jika menggunakan Apache (alternatif Nginx):

Buat `/etc/apache2/sites-available/approval-center.conf`:

```apache
<VirtualHost *:443>
    ServerName approval.propan.internal
    DocumentRoot /var/www/approval-center/public

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/approval.propan.crt
    SSLCertificateKeyFile /etc/ssl/private/approval.propan.key

    <Directory /var/www/approval-center/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/approval-center-error.log
    CustomLog ${APACHE_LOG_DIR}/approval-center-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite ssl headers
sudo a2ensite approval-center
sudo systemctl reload apache2
```

Pastikan `.htaccess` di folder `public/` ada (sudah ada di Laravel default).

---

## 11. Mermaid.js Offline (Production Internal)

Karena jaringan internal perusahaan mungkin tidak punya akses internet, siapkan Mermaid.js secara lokal:

### 11.1 Download Mermaid.js

```bash
# Di server dengan akses internet, atau download manual
mkdir -p /var/www/approval-center/public/vendor/mermaid

# Download mermaid.min.js
curl -L https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js \
    -o /var/www/approval-center/public/vendor/mermaid/mermaid.min.js

# Verifikasi
ls -lh /var/www/approval-center/public/vendor/mermaid/
```

### 11.2 Verifikasi di Flow Preview

Buka halaman **Workflow → Flow Version → Preview**. Sistem otomatis:
1. Coba load dari `/vendor/mermaid/mermaid.min.js` (lokal)
2. Jika gagal, coba CDN jsDelivr
3. Jika keduanya gagal, tampilkan tabel node-edge fallback

---

## 12. First Login & Konfigurasi Awal

### 12.1 Login Pertama

```
URL      : https://approval.propan.internal
Username : ADMIN_DEV
Password : admin123
```

> **Sistem akan memaksa ganti password saat login pertama.**  
> Set password yang kuat (min 8 karakter, ada angka & huruf).

### 12.2 Langkah Konfigurasi Awal

Ikuti urutan ini:

**1. Master Data — Source App**
```
Menu: Master → Source App → Tambah
Isi: app_code (mis. SFA), app_name, base_url source app
```

**2. Master Data — API Client**
```
Menu: Master → API Client → Tambah
Pilih Source App yang baru dibuat
Simpan → Sistem menampilkan client_key & client_secret SEKALI
Salin dan simpan ke konfigurasi source app
```

**3. Master Data — User**
```
Menu: Master → User → Tambah
Buat user untuk approver (user_ref = NPK karyawan)
Assign role APPROVER
Gunakan Reset Password → berikan password sementara ke user
```

**4. Master Data — Approval Group (opsional)**
```
Menu: Master → Approval Group → Tambah
Buat group jika ada multi-approver untuk satu step
Tambahkan member
```

**5. Workflow — Flow Definition**
```
Menu: Workflow → Tambah Flow Definition
flow_code  : FLOW_SFA_RETUR (contoh)
Source App : SFA
Doc Type   : Retur Barang
```

**6. Workflow — Flow Version**
```
Dari halaman Flow Definition → Tambah Version
version_no : 1
Status     : DRAFT (otomatis)
```

**7. Workflow — Tambah Node**
```
Dari halaman Flow Version → Node → Tambah
START node      : step_type=START,  node_code=START
Approval nodes  : step_type=APPROVAL, node_code=BMH (mis.)
Decision node   : step_type=DECISION, gateway_type=EXCLUSIVE
END node        : step_type=END,    node_code=END

Untuk setiap APPROVAL node → wajib tambah Assignee Rule
```

**8. Workflow — Tambah Edge**
```
Dari halaman Flow Version → Edge → Tambah
Contoh: START → BMH (action_code=SUBMIT)
        BMH → END (action_code=APPROVE)
        BMH → END (action_code=REJECT, final_status=REJECTED)
```

**9. Workflow — Validate & Deploy**
```
Dari halaman Flow Version → klik Validate
Jika hasil VALID → klik Deploy
```

**10. Routing Rule**
```
Menu: Workflow → Routing Rule → Tambah
Isi rule agar request dari SFA mengarah ke FLOW_SFA_RETUR
condition_json: {"op":"=","field":"doc_type","value":"RETUR"}
```

---

## 13. Checklist Pre-Launch

### Wajib

- [ ] `.env` sudah dikonfigurasi (`APP_DEBUG=false`, `APP_ENV=production`)
- [ ] `APP_KEY` di-generate dan tersimpan aman
- [ ] Database schema diimport + migrations dijalankan
- [ ] Queue worker berjalan (cek: `supervisorctl status`)
- [ ] Cron scheduler aktif
- [ ] Nginx/Apache menggunakan HTTPS
- [ ] Password admin ADMIN_DEV sudah diganti dari `admin123`
- [ ] Minimal 1 Source App + 1 API Client sudah dikonfigurasi
- [ ] Minimal 1 Flow Definition + Flow Version sudah ACTIVE
- [ ] Minimal 1 Routing Rule aktif untuk setiap Source App
- [ ] Test submit request dari source app → callback diterima

### Direkomendasikan

- [ ] Backup database otomatis terjadwal (mis. mysqldump tiap hari)
- [ ] Log rotation dikonfigurasi (`/etc/logrotate.d/approval-center`)
- [ ] Monitoring server (uptime, CPU, memory) aktif
- [ ] Mermaid.js lokal tersedia di `public/vendor/mermaid/`
- [ ] Firewall: hanya port 80, 443 yang dibuka ke publik
- [ ] DB port 3306 tidak dibuka ke publik

### Test Integrasi API

```bash
# Test HMAC Signature (ganti nilai sesuai API Client Anda)
CLIENT_KEY="AC-XXXX"
CLIENT_SECRET="yyyyy"
TIMESTAMP=$(date +%s)
BODY='{"source_request_id":"TEST-001","source_request_no":"REQ-TEST-001","title":"Test Request","requester_ref":"EMP001","requester_name":"Test User","doc_type":"RETUR","context_json":{}}'
SIGNATURE=$(echo -n "${TIMESTAMP}\n${BODY}" | openssl dgst -sha256 -hmac "${CLIENT_SECRET}" | sed 's/.*= //')

curl -s -X POST https://approval.propan.internal/api/v1/approval/submit \
  -H "Content-Type: application/json" \
  -H "X-Client-Key: ${CLIENT_KEY}" \
  -H "X-Timestamp: ${TIMESTAMP}" \
  -H "X-Signature: ${SIGNATURE}" \
  -d "${BODY}" | python3 -m json.tool
```

---

## 14. Monitoring & Troubleshooting

### Log Files

| Log | Lokasi | Isi |
|---|---|---|
| Laravel App | `storage/logs/laravel-YYYY-MM-DD.log` | Error, warning, info aplikasi |
| Queue Worker | `/var/log/approval-center-worker.log` | Output worker |
| Nginx | `/var/log/nginx/approval-center-*.log` | HTTP access/error |

```bash
# Monitor log real-time
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Filter error saja
grep -E "ERROR|CRITICAL|ALERT" storage/logs/laravel-$(date +%Y-%m-%d).log | tail -50
```

### Perintah Diagnostik Berguna

```bash
# Cek antrian yang tertunda
php artisan queue:monitor default,callbacks

# Lihat semua failed jobs
php artisan queue:failed

# Retry satu failed job berdasarkan ID
php artisan queue:retry {id}

# Retry semua failed job
php artisan queue:retry all

# Hapus semua failed job
php artisan queue:flush

# Cek scheduler apa yang akan jalan berikutnya
php artisan schedule:list

# Test scheduler manual
php artisan schedule:run --verbose

# Clear semua cache
php artisan optimize:clear

# Rebuild cache production
php artisan optimize

# Cek status database connections
php artisan db:show
```

### Masalah Umum

**Queue tidak berjalan (callback tidak terkirim)**
```bash
sudo supervisorctl status                # Cek apakah worker jalan
sudo supervisorctl restart approval-center-worker:*  # Restart
php artisan queue:failed | head -10     # Cek ada failed job?
```

**Callback terus FAILED**
```bash
# Lihat error di outbox
# Menu: Monitor → Callback Outbox → filter status FAILED
# Klik Retry untuk item tertentu
# Atau via artisan:
php artisan queue:retry all
```

**Login gagal / session expired cepat**
```bash
# Cek SESSION_LIFETIME di .env (default 480 menit)
# Cek SESSION_SECURE_COOKIE=true hanya jika memang HTTPS
php artisan session:table   # Jika driver session=database, pastikan tabel ada
php artisan migrate
```

**Flow tidak bisa di-deploy (validasi gagal)**
```
Buka: Workflow → Flow Version → klik Validate
Baca pesan error (R1-R12)
Perbaiki node/edge yang salah
Validate ulang → Deploy
```

---

## 15. Upgrade Prosedur

### Untuk setiap rilis baru:

```bash
# 1. Backup database DULU
mysqldump -u approval_user -p approval_center --single-transaction \
    > backup_before_upgrade_$(date +%Y%m%d_%H%M%S).sql

# 2. Masuk maintenance mode
php artisan down --retry=60

# 3. Pull kode baru
git pull origin main

# 4. Update dependencies
composer install --no-dev --optimize-autoloader

# 5. Jalankan migrations baru (jika ada)
php artisan migrate --force

# 6. Clear & rebuild cache
php artisan optimize:clear
php artisan optimize

# 7. Restart queue worker
sudo supervisorctl restart approval-center-worker:*

# 8. Keluar maintenance mode
php artisan up
```

---

## 16. Keamanan

### Checklist Keamanan

- **APP_DEBUG=false** di production — WAJIB. Jika `true`, stack trace PHP tampil ke browser.
- **APP_KEY** harus 32 karakter random. Jangan gunakan key yang sama di environment berbeda.
- **Client Secret API** disimpan AES-encrypted di database. Plaintext hanya ditampilkan sekali saat create/rotate.
- **Rotate API Secret** secara berkala (minimal 6 bulan sekali), terutama setelah ada pergantian tim IT.
- **Allowed IP** di API Client diisi IP server source app — bukan `0.0.0.0`.
- **HTTPS Only** — `SESSION_SECURE_COOKIE=true` dan paksa redirect HTTP → HTTPS di Nginx.
- **Database user** hanya dapat hak: SELECT, INSERT, UPDATE, DELETE (tidak GRANT, SUPER, FILE).
- **Log file** di `storage/logs/` jangan bisa diakses publik via web.
- **File .env** jangan pernah di-commit ke Git. Tambahkan ke `.gitignore`.

### Rotation Prosedur API Secret

Jika ada indikasi secret bocor:
```
1. Login ke Approval Center sebagai ADMIN_APPROVAL
2. Menu: Master → API Client → pilih client terdampak
3. Klik Rotate Secret
4. Simpan secret baru (ditampilkan sekali)
5. Update konfigurasi di source app dengan secret baru
6. Test API dengan secret baru
7. Konfirmasi ke tim source app bahwa secret lama sudah tidak valid
```

---

## Ringkasan Perintah Penting

```bash
# Development
php artisan serve                          # Jalankan dev server
php artisan queue:work database           # Queue worker lokal
php artisan schedule:work                  # Scheduler lokal

# Database
php artisan migrate                        # Jalankan migrations
php artisan migrate:status                 # Cek status migrations
php artisan migrate:rollback               # Rollback 1 batch

# Cache
php artisan optimize:clear                 # Clear semua cache
php artisan optimize                       # Build cache production

# Queue
php artisan queue:monitor default,callbacks
php artisan queue:failed
php artisan queue:retry all

# Maintenance
php artisan down                           # Maintenance mode ON
php artisan up                             # Maintenance mode OFF
php artisan about                          # Info environment
```

---

*Dokumen ini dibuat otomatis bersama dengan source code Approval Center Propan.*  
*Update terakhir: Mei 2026.*
