// k6 load test — Approval Center @ 1000 create-request/menit
//
// Membuktikan target produksi: ≥1000 create approval request/menit (~16.7/s)
// tanpa lag signifikan, tanpa duplicate, dengan idempotency benar.
//
// Menandatangani request sama persis seperti ApiClientAuthenticate:
//   signature = HMAC_SHA256( timestamp + "\n" + nonce + "\n" + rawBody , plainSecret )  (hex)
//
// Jalankan:
//   k6 run -e BASE_URL=http://staging/approval_center/public \
//          -e CLIENT_KEY=xxx -e CLIENT_SECRET=yyy -e DOC_TYPE=1 \
//          tests/load/k6-approval-load.js
//
// Catatan: approve/reject adalah aksi WEB (session, di luar HMAC API), sehingga
// "concurrent approve pada request yang sama" diuji di PHPUnit (lock task+instance),
// bukan di k6. k6 fokus pada endpoint API: submit (create), status, cancel + idempotency.

import http from 'k6/http';
import crypto from 'k6/crypto';
import { check } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const BASE_URL      = __ENV.BASE_URL      || 'http://localhost:8000';
const CLIENT_KEY    = __ENV.CLIENT_KEY    || 'LOADTEST';
const CLIENT_SECRET = __ENV.CLIENT_SECRET || 'secret';
const DOC_TYPE      = parseInt(__ENV.DOC_TYPE || '1', 10);
const RATE_PER_MIN  = parseInt(__ENV.RATE || '1000', 10);
const DURATION      = __ENV.DURATION || '5m';

const dupHits      = new Counter('idempotent_hits');
const dupFailures  = new Counter('idempotency_duplicate_created'); // HARUS 0
const submitErrors = new Rate('submit_errors');

export const options = {
  scenarios: {
    // 70%+ beban: create 1000/menit selama DURATION.
    create_load: {
      executor: 'constant-arrival-rate',
      rate: RATE_PER_MIN,
      timeUnit: '1m',
      duration: DURATION,
      preAllocatedVUs: 50,
      maxVUs: 300,
      exec: 'createRequest',
    },
    // Idempotency: doc_ref tetap ditembak berulang → harus selalu balas idempotent/sukses, TANPA duplikat.
    idempotency: {
      executor: 'constant-arrival-rate',
      rate: Math.max(1, Math.round(RATE_PER_MIN * 0.1)),
      timeUnit: '1m',
      duration: DURATION,
      preAllocatedVUs: 5,
      maxVUs: 30,
      exec: 'idempotentRequest',
      startTime: '2s',
    },
    // Status checks (read path) ~20%.
    status_checks: {
      executor: 'constant-arrival-rate',
      rate: Math.max(1, Math.round(RATE_PER_MIN * 0.2)),
      timeUnit: '1m',
      duration: DURATION,
      preAllocatedVUs: 10,
      maxVUs: 60,
      exec: 'statusCheck',
      startTime: '3s',
    },
  },
  thresholds: {
    'http_req_duration{scenario:create_load}': ['p(95)<300', 'p(99)<800'],
    'http_req_duration{scenario:status_checks}': ['p(95)<500'],
    submit_errors: ['rate<0.01'],
    idempotency_duplicate_created: ['count==0'],
  },
};

function sign(body) {
  const ts = Math.floor(Date.now() / 1000).toString();
  const nonce = `${__VU}-${__ITER}-${Math.random().toString(36).slice(2)}`;
  const sig = crypto.hmac('sha256', CLIENT_SECRET, `${ts}\n${nonce}\n${body}`, 'hex');
  return {
    'X-Client-Key': CLIENT_KEY,
    'X-Timestamp': ts,
    'X-Nonce': nonce,
    'X-Signature': sig,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}

function submit(docRef) {
  const payload = {
    doc_ref: docRef,
    idtbldocument_type: DOC_TYPE,
    context_json: { amount: 1000000, _loadtest: true },
    payload_json: { header: [{ idtblbranch: 'TST' }], detail: [] },
  };
  const body = JSON.stringify(payload);
  return http.post(`${BASE_URL}/api/v1/approval/submit`, body, { headers: sign(body), tags: { name: 'submit' } });
}

export function createRequest() {
  const docRef = `LT-${__VU}-${__ITER}-${Date.now()}`;
  const res = submit(docRef);
  const ok = check(res, { 'submit 2xx': (r) => r.status === 201 || r.status === 200 });
  submitErrors.add(!ok);
}

let firstId = null;
export function idempotentRequest() {
  // doc_ref tetap → pertama 201, sisanya 200 idempotent; TIDAK boleh membuat baris baru.
  const docRef = `LT-IDEMP-FIXED`;
  const res = submit(docRef);
  const ok = check(res, { 'idemp 2xx': (r) => r.status === 201 || r.status === 200 });
  submitErrors.add(!ok);
  try {
    const j = res.json();
    if (j && j.idempotent === true) dupHits.add(1);
    if (j && j.approval_request_id) {
      if (firstId === null) firstId = j.approval_request_id;
      // Jika muncul ID berbeda untuk doc_ref yang sama → duplikat (HARUS 0).
      else if (j.approval_request_id !== firstId) dupFailures.add(1);
    }
  } catch (e) { /* ignore parse */ }
}

export function statusCheck() {
  const res = http.get(
    `${BASE_URL}/api/v1/approval/status?doc_ref=LT-IDEMP-FIXED&idtbldocument_type=${DOC_TYPE}`,
    { headers: sign(''), tags: { name: 'status' } }
  );
  check(res, { 'status 200/404': (r) => r.status === 200 || r.status === 404 });
}
