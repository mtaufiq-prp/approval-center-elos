# Review Issues — Global Approval Center

Generated from deep repository review (security, approval logic, data consistency, API/perf/reliability).

## Summary

| No | Severity | Category | Priority | Title |
|---|---|---|---|---|
| 1 | Critical | Integration | P0 | [Critical][Integration] completeProcess() tidak pernah membuat tblcallback_outbox — source app tak pernah menerima hasil approval |
| 2 | Critical | Integration | P0 | [Critical][Integration] SendCallbackJob menulis/membaca kolom & status yang tidak ada di schema tblcallback_outbox |
| 3 | Critical | API | P0 | [Critical][API] ApprovalStatusController memuat relasi yang tidak ada (processInstances/pendingTasks/assignee) → HTTP 500 selalu |
| 4 | Critical | Approval Logic | P0 | [Critical][Approval Logic] START → edge SUBMIT tidak pernah match saat startProcess → request langsung APPROVED tanpa approval |
| 5 | Critical | Approval Logic | P0 | [Critical][Approval Logic] Keputusan RETURN/CANCEL menjadikan request APPROVED (fail-open) karena fallback completeProcess(COMPLETED) |
| 6 | Critical | Architecture | P0 | [Critical][Architecture] Instance berjalan tidak punya snapshot flow; versi lama bisa diedit setelah deploy → jalur approval request lama bisa berubah/rusak |
| 7 | High | Database | P1 | [High][Database] ApprovalSubmitController menulis kolom & status invalid ke tblintegration_message_log (payload inbound hilang / insert gagal) |
| 8 | High | Database | P1 | [High][Database] Kolom idtbluser_submitter tidak ada di schema/fillable → submitter selalu hilang & resolver SUPERIOR rusak |
| 9 | High | Security | P1 | [High][Security] Tidak ada isolasi data antar-program (source_app) di Monitoring & Audit — satu admin/auditor melihat semua program |
| 10 | High | Security | P1 | [High][Security] token_expired_at API Client tidak pernah diperiksa — kredensial kadaluarsa tetap diterima |
| 11 | High | Approval Logic | P1 | [High][Approval Logic] Fitur delegasi tidak operasional — TblDelegation & flag allow_delegate tidak pernah dipakai resolver |
| 12 | High | Reliability | P1 | [High][Reliability] Node APPROVAL tanpa kandidat valid → seluruh proses ERROR permanen tanpa pemulihan; keputusan approver ter-rollback |
| 13 | High | Approval Logic | P1 | [High][Approval Logic] Celah boundary threshold nilai retur (pecahan) tidak ter-cover & batas DECISION vs edge BMH/RRM tidak konsisten |
| 14 | High | Transaction | P1 | [High][Transaction] Idempotency submit longgar: tanpa idempotency_key double-submit lolos & duplikat dibalas 422 (bukan idempotent) |
| 15 | High | Reliability | P1 | [High][Reliability] retryCallback men-dispatch model ke SendCallbackJob yang menerima int → TypeError, retry manual gagal |
| 16 | Medium | Reliability | P2 | [Medium][Reliability] Callback outbox scanner: filter status 'RETRY' (bukan ENUM), abaikan next_retry_at, tanpa lock → double-send & item gagal tak diproses ulang |
| 17 | Medium | Security | P2 | [Medium][Security] inbox.show authorizeView tidak memfilter is_active → kandidat nonaktif masih bisa melihat payload penuh |
| 18 | Medium | Security | P2 | [Medium][Security] ApprovalSubmitController tidak memvalidasi idtbldocument_type milik source_app pemanggil |
| 19 | Medium | Approval Logic | P2 | [Medium][Approval Logic] Tidak ada pencegahan self-approval (segregation of duties) — submitter bisa menyetujui request-nya sendiri |
| 20 | Medium | Transaction | P2 | [Medium][Transaction] deploy() tanpa row-lock & tanpa constraint single-ACTIVE → dua deploy paralel bisa menghasilkan dua versi ACTIVE |
| 21 | Medium | Database | P2 | [Medium][Database] Tidak ada unique constraint pencegah duplikasi task per (instance, step, user) — loop/RETURN bisa menumpuk task ganda |
| 22 | Medium | Audit | P2 | [Medium][Audit] Audit trail tidak immutable secara teknis & tidak menyimpan snapshot payload awal request |
| 23 | Medium | Reliability | P2 | [Medium][Reliability] SLA escalation hanya mencatat log — tidak reassign/notifikasi & tidak ada target eskalasi |
| 24 | Medium | Performance | P2 | [Medium][Performance] SlaEscalationJob memuat semua task overdue ke memori + N+1 query exists per task (risiko OOM) |
| 25 | Medium | API | P2 | [Medium][API] Semua exception submit dibungkus jadi HTTP 422 generic → source app tidak bisa membedakan error validasi vs transien |
| 26 | Medium | API | P2 | [Medium][API] Tidak ada validasi struktur context_json/payload_json (hanya array) → salah-rute approval senyap |
| 27 | Medium | Reliability | P2 | [Medium][Reliability] Auto-forward node REVIEW/NOTIFICATION/SYSTEM tanpa loop-guard runtime & fail-open ke APPROVED |
| 28 | Medium | Performance | P2 | [Medium][Performance] Inbox/History: orWhereHas('candidates') memicu full scan tbltask + inboxCount dihitung ulang tiap halaman |
| 29 | Low | Security | P3 | [Low][Security] Pesan error builder & jobtitle-search membocorkan detail exception internal ke client |
| 30 | Low | Security | P3 | [Low][Security] completeCurrentTask tidak memverifikasi task berada di node aktif instance (defense-in-depth) |
| 31 | Low | Performance | P3 | [Low][Performance] Optimasi query list/audit: whereDate() mematikan index, index komposit monitoring hilang, distinct pluck dropdown per request |
| 32 | Low | Approval Logic | P3 | [Low][Approval Logic] Inkonsistensi guard status: web act() hanya izinkan OPEN, engine izinkan OPEN/CLAIMED |
