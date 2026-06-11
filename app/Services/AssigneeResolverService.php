<?php

namespace App\Services;

use App\Models\TblApprovalGroup;
use App\Models\TblFlowStep;
use App\Models\TblPosition;
use App\Models\TblRole;
use App\Models\TblUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AssigneeResolverService
 *
 * Menentukan kandidat approver berdasarkan tblstep_assignee_rule.
 *
 * assignee_type:
 *   USER         → assignee_value = user_ref
 *   ROLE         → assignee_value = role_code; ambil semua user aktif dengan role tsb
 *   GROUP        → assignee_value = group_code
 *   POSITION     → assignee_value = position_code
 *   SUPERIOR     → dari idtbluser_superior di tbluser (submitter punya atasan)
 *   FIELD_USER   → assignee_value = nama field di context_json yang berisi user_ref
 *   FIELD_POSITION → assignee_value = field di context_json yang berisi position_code
 *   JOBTITLE     → assignee_value = jobtitleid dari db_master.ms_jobtitle;
 *                   lookup employeeno dari db_master.tbemployeeit lalu cocokkan ke tbluser
 *   ORG_HEAD     → assignee_value = tier (dept_head|div_head|atasan); telusuri hierarki
 *                   organisasi di db_master.tbemployeeit dari NPK pemohon (fakta di
 *                   context_json). Generik untuk semua source app — approver ditentukan
 *                   hub, bukan dikirim app. Atasan TETAP (mis. direksi) cukup pakai GROUP.
 *   API_RESOLVER → assignee_value = URL endpoint; GET dengan context param
 *
 * Return: Collection<TblUser>
 */
class AssigneeResolverService
{
    // In-memory cache per request untuk JOBTITLE yang sama (#44)
    private array $jobtitleCache = [];
    // In-memory cache per request untuk ORG_HEAD (tier:npk) yang sama
    private array $orgHeadCache = [];
    public function __construct(
        private ConditionEvaluatorService $condEval,
    ) {}

    /**
     * Resolve kandidat approver untuk step + context tertentu.
     *
     * @param TblFlowStep $step        APPROVAL node
     * @param array       $context     context_json dari approval request
     * @param int|null    $submitterId idtbluser submitter (untuk SUPERIOR)
     * @return Collection<TblUser>
     */
    public function resolve(TblFlowStep $step, array $context, ?int $submitterId = null): Collection
    {
        $candidates = collect();
        $rules = $step->activeAssigneeRules()->orderBy('priority_no')->get();

        foreach ($rules as $rule) {
            // Evaluasi condition rule sendiri (kalau ada)
            if ($rule->condition_json && ! $this->condEval->evaluate($rule->condition_json, $context)) {
                continue;
            }

            $users = $this->resolveRule($rule->assignee_type, $rule->assignee_value, $context, $submitterId);
            // Tandai sumber resolusi per kandidat untuk audit/provenance (#113).
            // Map ke ENUM tbltask_candidate.candidate_source yang valid.
            $source = $this->candidateSourceFor($rule->assignee_type);
            foreach ($users as $u) {
                if (! $u->getAttribute('_candidate_source')) {
                    $u->setAttribute('_candidate_source', $source);
                }
            }
            $candidates = $candidates->merge($users);
        }

        $candidates = $candidates->unique('idtbluser')->values();

        // Substitusi delegasi aktif: tambahkan delegate sebagai kandidat (#96).
        // Satu hop saja untuk mencegah loop A->B->A.
        return $this->applyDelegations($candidates);
    }

    /**
     * Untuk tiap kandidat yang sedang mendelegasikan (delegator), tambahkan
     * delegate aktif sebagai kandidat. Delegate ditandai source DELEGATION.
     *
     * @param Collection<int,TblUser> $candidates
     * @return Collection<int,TblUser>
     */
    private function applyDelegations(Collection $candidates): Collection
    {
        $delegatorIds = $candidates->pluck('idtbluser')->all();
        if (empty($delegatorIds)) {
            return $candidates;
        }

        $delegations = \App\Models\TblDelegation::activeAt()
            ->whereIn('idtbluser_delegator', $delegatorIds)
            ->get();

        foreach ($delegations as $d) {
            // Hindari loop: jangan tambahkan delegate yang juga sudah jadi kandidat
            if ($candidates->firstWhere('idtbluser', $d->idtbluser_delegate)) {
                continue;
            }
            $delegate = TblUser::where('idtbluser', $d->idtbluser_delegate)
                ->where('is_active', 1)->first();
            if ($delegate) {
                $delegate->setAttribute('_candidate_source', 'DELEGATION');
                $candidates->push($delegate);
            }
        }

        return $candidates->unique('idtbluser')->values();
    }

    /** Map assignee_type → ENUM tbltask_candidate.candidate_source. */
    private function candidateSourceFor(string $assigneeType): string
    {
        return match ($assigneeType) {
            'ROLE'                       => 'ROLE',
            'GROUP'                      => 'GROUP',
            'POSITION', 'FIELD_POSITION', 'JOBTITLE' => 'POSITION',
            'SUPERIOR', 'ORG_HEAD'       => 'SUPERIOR',
            'API_RESOLVER'               => 'API_RESOLVER',
            default                      => 'DIRECT', // USER / FIELD_USER
        };
    }

    private function resolveRule(string $type, ?string $value, array $context, ?int $submitterId): Collection
    {
        return match ($type) {
            'USER'           => $this->resolveUser($value),
            'ROLE'           => $this->resolveRole($value),
            'GROUP'          => $this->resolveGroup($value),
            'POSITION'       => $this->resolvePosition($value),
            'SUPERIOR'       => $this->resolveSuperior($submitterId),
            'FIELD_USER'     => $this->resolveFieldUser($value, $context),
            'FIELD_POSITION' => $this->resolveFieldPosition($value, $context),
            'JOBTITLE'       => $this->resolveJobTitle($value),
            'ORG_HEAD'       => $this->resolveOrgHead($value, $context),
            'API_RESOLVER'   => $this->resolveApi($value, $context),
            default          => collect(),
        };
    }

    private function resolveUser(?string $userRef): Collection
    {
        if (! $userRef) return collect();
        $user = TblUser::where('user_ref', $userRef)->where('is_active', 1)->first();
        return $user ? collect([$user]) : collect();
    }

    private function resolveRole(?string $roleCode): Collection
    {
        if (! $roleCode) return collect();
        return TblUser::where('is_active', 1)
            ->whereHas('roles', fn($q) => $q->where('role_code', $roleCode)->where('is_active', 1))
            ->get();
    }

    private function resolveGroup(?string $groupCode): Collection
    {
        if (! $groupCode) return collect();
        $group = TblApprovalGroup::where('group_code', $groupCode)->where('is_active', 1)->first();
        if (! $group) return collect();

        return TblUser::where('is_active', 1)
            ->whereIn('idtbluser',
                $group->members()->where('is_active', 1)->pluck('idtbluser')
            )->get();
    }

    private function resolvePosition(?string $posCode): Collection
    {
        if (! $posCode) return collect();
        return TblUser::where('is_active', 1)
            ->whereHas('position', fn($q) => $q->where('position_code', $posCode))
            ->get();
    }

    private function resolveSuperior(?int $submitterId): Collection
    {
        if (! $submitterId) return collect();
        $submitter = TblUser::find($submitterId);
        if (! $submitter || ! $submitter->idtbluser_superior) return collect();
        $superior = TblUser::where('idtbluser', $submitter->idtbluser_superior)->where('is_active', 1)->first();
        return $superior ? collect([$superior]) : collect();
    }

    private function resolveFieldUser(?string $field, array $context): Collection
    {
        if (! $field) return collect();
        $userRef = data_get($context, $field);
        if (! $userRef) return collect();
        return $this->resolveUser($userRef);
    }

    private function resolveFieldPosition(?string $field, array $context): Collection
    {
        if (! $field) return collect();
        $posCode = data_get($context, $field);
        if (! $posCode) return collect();
        return $this->resolvePosition($posCode);
    }

    /**
     * JOBTITLE: Lookup user berdasarkan jobtitleid dari db_master.tbemployeeit.
     * employeeno di tbemployeeit = user_ref di tbluser approval center.
     * Keuntungan: saat ganti pejabat, cukup update di HR system saja.
     */
    private function resolveJobTitle(?string $jobTitleId): Collection
    {
        if (! $jobTitleId) return collect();
        if (isset($this->jobtitleCache[$jobTitleId])) {
            return $this->jobtitleCache[$jobTitleId];
        }
        try {
            $rows = \Illuminate\Support\Facades\DB::select(
                "SELECT employeeno FROM db_master.tbemployeeit WHERE jobtitleid = ? AND activestatus = 1",
                [$jobTitleId]
            );
            if (empty($rows)) {
                Log::warning("AssigneeResolver JOBTITLE: tidak ada employee aktif untuk jobtitleid={$jobTitleId}");
                return collect();
            }
            $npks   = array_map(fn($r) => $r->employeeno, $rows);
            $result = TblUser::where("is_active", 1)->whereIn("user_ref", $npks)->get();
            return $this->jobtitleCache[$jobTitleId] = $result;
        } catch (\Throwable $e) {
            // Re-throw agar FlowEngine bisa set instance ERROR dan job bisa di-retry
            Log::error("AssigneeResolver JOBTITLE({$jobTitleId}) DB error: {$e->getMessage()}");
            throw new \RuntimeException("Gagal resolve JOBTITLE {$jobTitleId}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * ORG_HEAD: resolve atasan PEMOHON pada tier tertentu dengan menelusuri hierarki
     * organisasi di db_master.tbemployeeit (tiap kolom groupno..corno menyimpan NPK
     * atasan pada tier itu). NPK pemohon dibaca dari context_json (FAKTA dari app),
     * field-nya dapat dikonfigurasi. Generik — dipakai semua source app tanpa kode
     * per-app; approver ditentukan hub, bukan dikirim app.
     *
     * assignee_value = tier: dept_head | div_head | atasan
     * (Atasan TETAP seperti direksi cukup pakai GROUP, tidak lewat sini.)
     */
    private function resolveOrgHead(?string $tier, array $context): Collection
    {
        $tier = strtolower(trim((string) $tier));
        $expr = $this->orgTierExpr($tier);
        if (! $expr) {
            Log::warning("AssigneeResolver ORG_HEAD: tier tidak dikenal '{$tier}' (pakai dept_head|div_head|atasan).");
            return collect();
        }

        // NPK pemohon = fakta dokumen dari payload (BUKAN approver). Field dapat
        // dikonfigurasi; coba beberapa lokasi umum (header objek / array / top-level).
        $fields = (array) config('approval_center.org_resolver.requester_npk_fields', [
            'header.npk_pembuat', 'header.0.npk_pembuat', 'npk_pembuat',
            'header.create_by', 'header.0.create_by',
        ]);
        $npk = null;
        foreach ($fields as $f) {
            $v = data_get($context, $f);
            if ($v !== null && $v !== '') { $npk = trim((string) $v); break; }
        }
        if (! $npk) {
            Log::warning('AssigneeResolver ORG_HEAD: NPK pemohon tidak ditemukan di context_json.');
            return collect();
        }

        $cacheKey = "{$tier}:{$npk}";
        if (isset($this->orgHeadCache[$cacheKey])) {
            return $this->orgHeadCache[$cacheKey];
        }

        try {
            // Filter anti-diri-sendiri (replikasi PSTB get_atasan_head, + divno yang
            // di PSTB kelewat): kalau pemohon ADALAH kepala di suatu tier (kolomnya =
            // NPK dia sendiri), baris di-skip → tier tak resolve ke dirinya sendiri
            // (cegah self-approval di level org). COALESCE agar aman thd NULL.
            $selfExcl = "COALESCE(groupno,'')<>employeeno AND COALESCE(subunitno,'')<>employeeno"
                . " AND COALESCE(unitno,'')<>employeeno AND COALESCE(subdeptno_deputy,'')<>employeeno"
                . " AND COALESCE(subdeptno,'')<>employeeno AND COALESCE(deptno_deputy,'')<>employeeno"
                . " AND COALESCE(deptno,'')<>employeeno AND COALESCE(divno_deputy,'')<>employeeno"
                . " AND COALESCE(divno,'')<>employeeno AND COALESCE(dirno_deputy,'')<>employeeno"
                . " AND COALESCE(dirno,'')<>employeeno AND COALESCE(corno,'')<>employeeno";
            $rows = \Illuminate\Support\Facades\DB::select(
                "SELECT ({$expr}) AS head FROM db_master.tbemployeeit
                 WHERE employeeno = ? AND ({$selfExcl})
                 ORDER BY careerstatus DESC LIMIT 1",
                [$npk]
            );
            $head = $rows[0]->head ?? null;
            if ($head === null || $head === '' || $head === '0') {
                Log::warning("AssigneeResolver ORG_HEAD: tier '{$tier}' kosong untuk NPK {$npk}.");
                return $this->orgHeadCache[$cacheKey] = collect();
            }
            // Auto-provision (opsi A): kalau NPK atasan belum ada di tbluser, buat
            // otomatis dari db_master.tbemployeeit → resolver tak pernah buntu.
            $user = $this->ensureUserByNpk((string) $head);
            $result = $user ? collect([$user]) : collect();
            if ($result->isEmpty()) {
                Log::warning("AssigneeResolver ORG_HEAD: gagal resolve/provision user NPK {$head} (tier {$tier}).");
            }
            return $this->orgHeadCache[$cacheKey] = $result;
        } catch (\Throwable $e) {
            // Re-throw (seperti JOBTITLE) agar FlowEngine set instance ERROR & bisa retry.
            Log::error("AssigneeResolver ORG_HEAD({$tier},{$npk}) DB error: {$e->getMessage()}");
            throw new \RuntimeException("Gagal resolve ORG_HEAD {$tier}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Ekspresi SQL CASE untuk mengambil NPK atasan pada tier tertentu, mengambil
     * tier non-kosong PERTAMA dari titik mulai peran lalu naik ke atas. Replikasi
     * PSTB get_atasan_head().
     */
    private function orgTierExpr(string $tier): ?string
    {
        return match ($tier) {
            'atasan'    => "CASE WHEN groupno<>'' THEN groupno WHEN subunitno<>'' THEN subunitno WHEN unitno<>'' THEN unitno WHEN subdeptno_deputy<>'' THEN subdeptno_deputy WHEN subdeptno<>'' THEN subdeptno WHEN deptno_deputy<>'' THEN deptno_deputy WHEN deptno<>'' THEN deptno WHEN divno_deputy<>'' THEN divno_deputy WHEN divno<>'' THEN divno WHEN dirno_deputy<>'' THEN dirno_deputy WHEN dirno<>'' THEN dirno WHEN corno<>'' THEN corno END",
            'dept_head' => "CASE WHEN deptno_deputy<>'' THEN deptno_deputy WHEN deptno<>'' THEN deptno WHEN divno_deputy<>'' THEN divno_deputy WHEN divno<>'' THEN divno WHEN dirno_deputy<>'' THEN dirno_deputy WHEN dirno<>'' THEN dirno WHEN corno<>'' THEN corno END",
            'div_head'  => "CASE WHEN divno_deputy<>'' THEN divno_deputy WHEN divno<>'' THEN divno WHEN dirno_deputy<>'' THEN dirno_deputy WHEN dirno<>'' THEN dirno WHEN corno<>'' THEN corno END",
            default     => null,
        };
    }

    /**
     * Auto-provision (opsi A): pastikan user dengan user_ref=$npk ADA & aktif di
     * tbluser. Kalau belum ada, buat otomatis dari db_master.tbemployeeit
     * (full_name dari master, role APPROVER, password default '123456' +
     * must_change_password=1 → user bisa login lalu dipaksa ganti). Membuat
     * ORG_HEAD/JOBTITLE tidak pernah buntu karena approver
     * belum ter-sync, dan tbluser selalu ikut HR master. Idempoten.
     */
    private function ensureUserByNpk(string $npk): ?TblUser
    {
        $existing = TblUser::where('user_ref', $npk)->first();
        if ($existing) {
            return $existing->is_active ? $existing : null; // hormati user nonaktif
        }

        try {
            $emp = \Illuminate\Support\Facades\DB::select(
                "SELECT employeename FROM db_master.tbemployeeit WHERE employeeno = ? ORDER BY careerstatus DESC LIMIT 1",
                [$npk]
            );
            if (empty($emp)) {
                Log::warning("AssigneeResolver provision: NPK {$npk} tidak ada di db_master.tbemployeeit.");
                return null;
            }
            $name = trim((string) ($emp[0]->employeename ?? '')) ?: $npk;

            // Buat user minimal. Password default '123456' (cast 'hashed' auto-bcrypt)
            // + must_change_password=1: user BISA login lalu DIPAKSA ganti password saat
            // login pertama (bukan NULL yang bikin tak bisa login sama sekali).
            $user = new TblUser();
            $user->user_ref             = $npk;
            $user->full_name            = $name;
            $user->is_active            = 1;
            $user->password             = '123456';
            $user->must_change_password = 1;
            $user->save();

            // Role APPROVER (lookup by code, hindari hardcode id).
            $roleId = TblRole::where('role_code', 'APPROVER')->value('idtblrole');
            if ($roleId) {
                \Illuminate\Support\Facades\DB::table('tbluser_role')->insert([
                    'idtbluser'  => $user->idtbluser,
                    'idtblrole'  => $roleId,
                    'created_at' => now(),
                ]);
            }
            Log::info("AssigneeResolver provision: user {$npk} ({$name}) dibuat + role APPROVER.");
            return $user;

        } catch (\Throwable $e) {
            Log::error("AssigneeResolver provision({$npk}) gagal: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * API_RESOLVER: POST ke endpoint dengan context_json, expect
     * {"user_refs": ["USER1","USER2"]}
     */
    private function resolveApi(?string $endpoint, array $context): Collection
    {
        if (! $endpoint) return collect();
        try {
            $response = Http::timeout(5)->post($endpoint, ['context' => $context]);
            if ($response->ok()) {
                $refs = $response->json('user_refs', []);
                return TblUser::where('is_active', 1)->whereIn('user_ref', $refs)->get();
            }
        } catch (\Throwable $e) {
            Log::warning("AssigneeResolverService API_RESOLVER failed: {$e->getMessage()}");
        }
        return collect();
    }
}
