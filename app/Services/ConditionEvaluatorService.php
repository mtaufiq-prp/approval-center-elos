<?php

namespace App\Services;

use App\Support\ConditionJsonValidator;
use RuntimeException;

/**
 * ConditionEvaluatorService
 *
 * Mengevaluasi condition_json terhadap context array (payload dari request).
 *
 * Format condition_json:
 *   null / []              → always true
 *   LEAF  { op, field, value }
 *   GROUP { logic:AND|OR, conditions:[] }
 *
 * Field access: dot notation. Mis. "item.nilai" akses $context['item']['nilai'].
 *
 * Operator: = != > >= < <= IN NOT_IN BETWEEN CONTAINS IS_NULL IS_NOT_NULL
 *
 * Prioritas evaluasi untuk EXCLUSIVE gateway ditangani FlowEngineService
 * bukan di sini; service ini cukup return bool per condition.
 */
class ConditionEvaluatorService
{
    /** @param array $context  Biasanya berasal dari approval_request.context_json */
    public function evaluate(?array $condition, array $context): bool
    {
        if (empty($condition)) return true;
        if (! is_array($condition)) return true;

        // GROUP
        if (isset($condition['logic'])) {
            return $this->evaluateGroup($condition, $context);
        }

        // LEAF
        if (isset($condition['op'])) {
            return $this->evaluateLeaf($condition, $context);
        }

        // Unrecognised structure — treat as true (permissive for forward compat)
        return true;
    }

    private function evaluateGroup(array $node, array $context): bool
    {
        $logic = strtoupper($node['logic'] ?? 'AND');
        $conditions = $node['conditions'] ?? [];

        if (empty($conditions)) return true;

        if ($logic === 'AND') {
            foreach ($conditions as $c) {
                if (! $this->evaluate($c, $context)) return false;
            }
            return true;
        }

        // OR
        foreach ($conditions as $c) {
            if ($this->evaluate($c, $context)) return true;
        }
        return false;
    }

    private function evaluateLeaf(array $node, array $context): bool
    {
        $op    = strtoupper($node['op'] ?? '=');
        $field = $node['field'] ?? '';
        $value = $node['value'] ?? null;

        // ── Operator yang perlu array source (SUM_*, ARRAY_ANY_IN, dll) ──
        // Format field: "detail[].value_retur_ori" → sum semua elemen
        if (in_array($op, ['SUM_GT','SUM_GTE','SUM_LT','SUM_LTE','SUM_EQ'])) {
            return $this->evaluateSumOp($op, $field, $value, $context);
        }

        // ANY_IN: apakah ada elemen di array field yang nilainya IN value[]
        // Contoh: {"op":"ANY_IN","field":"_computed.idmsalasan_list","value":[61,68]}
        if ($op === 'ANY_IN') {
            $actual = $this->resolveField($field, $context);
            if (! is_array($actual) || ! is_array($value)) return false;
            foreach ($actual as $item) {
                if ($this->inArray($item, $value)) return true;
            }
            return false;
        }

        // NONE_IN: kebalikan ANY_IN
        if ($op === 'NONE_IN') {
            $actual = $this->resolveField($field, $context);
            if (! is_array($actual)) return true;
            if (! is_array($value))  return true;
            foreach ($actual as $item) {
                if ($this->inArray($item, $value)) return false;
            }
            return true;
        }

        $actual = $this->resolveField($field, $context);

        return match ($op) {
            '='        => $this->eq($actual, $value),
            '!='       => ! $this->eq($actual, $value),
            '>'        => is_numeric($actual) && (float) $actual >  (float) $value,
            '>='       => is_numeric($actual) && (float) $actual >= (float) $value,
            '<'        => is_numeric($actual) && (float) $actual <  (float) $value,
            '<='       => is_numeric($actual) && (float) $actual <= (float) $value,
            'IN'       => is_array($value) && $this->inArray($actual, $value),
            'NOT_IN'   => is_array($value) && ! $this->inArray($actual, $value),
            'BETWEEN'  => is_array($value) && count($value) === 2
                          && is_numeric($actual)
                          && (float) $actual >= (float) $value[0]
                          && (float) $actual <= (float) $value[1],
            'CONTAINS' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $value)),
            'IS_NULL'     => $actual === null,
            'IS_NOT_NULL' => $actual !== null,
            default    => false,
        };
    }

    /**
     * Evaluasi SUM operator.
     * Field format: "detail[].value_retur_ori" ATAU "_computed.total_nilai_retur"
     * Jika field mengandung "[]", sum dari array. Jika tidak, langsung resolve.
     */
    private function evaluateSumOp(string $op, string $field, mixed $value, array $context): bool
    {
        // Jika pakai _computed.total_nilai_retur, langsung resolveField
        if (! str_contains($field, '[]')) {
            $actual = $this->resolveField($field, $context);
            if (! is_numeric($actual)) return false;
            $sum = (float) $actual;
        } else {
            // Mis. "detail[].value_retur_ori" → pisahkan
            [$arrayPath, $subField] = explode('[]', $field, 2);
            $arrayPath = trim($arrayPath, '.');
            $subField  = trim($subField,  '.');
            $arr = $this->resolveField($arrayPath, $context);
            if (! is_array($arr)) return false;
            $sum = 0.0;
            foreach ($arr as $row) {
                $v = is_array($row) ? ($row[$subField] ?? null) : null;
                if (is_numeric($v)) $sum += (float) $v;
            }
        }

        $cmp = (float) $value;
        return match ($op) {
            'SUM_GT'  => $sum >  $cmp,
            'SUM_GTE' => $sum >= $cmp,
            'SUM_LT'  => $sum <  $cmp,
            'SUM_LTE' => $sum <= $cmp,
            'SUM_EQ'  => abs($sum - $cmp) < 0.001,
            default   => false,
        };
    }

    /**
     * Resolve dot-notation field dari context array.
     * Mis. "item.nilai" → $context['item']['nilai']
     */
    private function resolveField(string $field, array $context): mixed
    {
        $parts = explode('.', $field);
        $val   = $context;
        foreach ($parts as $part) {
            if (! is_array($val) || ! array_key_exists($part, $val)) {
                return null;
            }
            $val = $val[$part];
        }
        return $val;
    }

    private function eq(mixed $a, mixed $b): bool
    {
        // String comparison: case-insensitive
        if (is_string($a) && is_string($b)) {
            return strtolower($a) === strtolower($b);
        }
        // Numeric: cast
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        return $a === $b;
    }

    private function inArray(mixed $actual, array $arr): bool
    {
        foreach ($arr as $item) {
            if ($this->eq($actual, $item)) return true;
        }
        return false;
    }
}
