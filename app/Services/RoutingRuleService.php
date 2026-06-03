<?php

namespace App\Services;

use App\Models\TblFlowVersion;
use App\Models\TblRoutingRule;

/**
 * RoutingRuleService
 *
 * Menentukan flow_version mana yang dipakai untuk approval request baru.
 *
 * Algoritma:
 *  1. Filter routing rule berdasarkan (source_app, document_type, is_active=1).
 *  2. Urutkan berdasarkan priority_no (kecil = prioritas tinggi).
 *  3. Untuk setiap rule, evaluasi condition_json terhadap context request.
 *  4. Rule pertama yang match → kembalikan flow_version-nya.
 *     Jika rule tidak punya idtblflow_version → ambil ACTIVE version terbaru dari flow_definition.
 *  5. Jika tidak ada match → throw RuntimeException (request tidak bisa diproses).
 *
 * @throws \RuntimeException jika tidak ada routing rule yang match.
 */
class RoutingRuleService
{
    public function __construct(private ConditionEvaluatorService $condEval) {}

    public function determineFlowVersion(
        int $idtblsource_app,
        int $idtbldocument_type,
        array $context
    ): TblFlowVersion {
        $rules = TblRoutingRule::where('idtblsource_app', $idtblsource_app)
            ->where('idtbldocument_type', $idtbldocument_type)
            ->where('is_active', 1)
            ->orderBy('priority_no')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->condEval->evaluate($rule->condition_json ?: [], $context)) {
                continue;
            }

            // Rule match — tentukan version
            if ($rule->idtblflow_version) {
                $version = TblFlowVersion::find($rule->idtblflow_version);
                if ($version && $version->status === TblFlowVersion::STATUS_ACTIVE) {
                    return $version;
                }
                // Version di-pin tapi sudah tidak ACTIVE → skip (next rule)
                continue;
            }

            // Ambil ACTIVE version terbaru dari flow_definition
            $version = TblFlowVersion::where('idtblflow_definition', $rule->idtblflow_definition)
                ->where('status', TblFlowVersion::STATUS_ACTIVE)
                ->orderByDesc('version_no')
                ->first();

            if ($version) return $version;
        }

        throw new \RuntimeException(
            "Tidak ada routing rule yang cocok untuk source_app #{$idtblsource_app} document_type #{$idtbldocument_type}."
        );
    }
}
