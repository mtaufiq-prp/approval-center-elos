<?php

namespace App\Support;

/**
 * ConditionJsonValidator — validator STRUKTURAL (bukan evaluator runtime).
 *
 * Format yang didukung:
 *
 * 1) ALWAYS-TRUE (kosong): null | {} | []
 *
 * 2) LEAF (single condition):
 *    { "op": "=", "field": "kondisi_produk", "value": "RUSAK" }
 *
 * 3) GROUP (AND/OR):
 *    { "logic": "AND", "conditions": [ <leaf|group>, ... ] }
 *
 * Operator yang didukung:
 *   Komparasi    : = != > >= < <=
 *   Set          : IN NOT_IN
 *   Range        : BETWEEN
 *   String       : CONTAINS
 *   Null check   : IS_NULL IS_NOT_NULL
 *   Sum array    : SUM_GT SUM_GTE SUM_LT SUM_LTE SUM_EQ
 *   Array member : ANY_IN NONE_IN
 */
class ConditionJsonValidator
{
    public const MAX_DEPTH = 8;

    public const OPERATORS = [
        // Komparasi scalar
        '=', '!=', '>', '>=', '<', '<=',
        // Set
        'IN', 'NOT_IN',
        // Range
        'BETWEEN',
        // String
        'CONTAINS',
        // Null check
        'IS_NULL', 'IS_NOT_NULL',
        // Sum dari array field (mis. sum detail[].value_retur_ori)
        'SUM_GT', 'SUM_GTE', 'SUM_LT', 'SUM_LTE', 'SUM_EQ',
        // Cek apakah ada/tidak ada elemen array yang cocok
        'ANY_IN', 'NONE_IN',
    ];

    public const LOGICS = ['AND', 'OR'];

    /** @var string[] */
    private array $errors = [];

    public function errors(): array { return $this->errors; }

    public function validateRaw(?string $rawJson): bool
    {
        $this->errors = [];
        if ($rawJson === null || trim($rawJson) === '') return true;

        $decoded = json_decode($rawJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'condition_json bukan JSON valid: ' . json_last_error_msg();
            return false;
        }
        return $this->validateDecoded($decoded);
    }

    public function validateDecoded($decoded): bool
    {
        $this->errors = [];
        if ($decoded === null || $decoded === [] || $decoded === (object) []) return true;
        if (! is_array($decoded)) {
            $this->errors[] = 'condition_json harus berupa object/array.';
            return false;
        }
        return $this->walk($decoded, 0, '$');
    }

    private function walk(array $node, int $depth, string $path): bool
    {
        if ($depth > self::MAX_DEPTH) {
            $this->errors[] = "Kedalaman condition_json melebihi batas (" . self::MAX_DEPTH . ") pada {$path}";
            return false;
        }

        if (array_key_exists('logic', $node) || array_key_exists('conditions', $node)) {
            return $this->walkGroup($node, $depth, $path);
        }
        return $this->walkLeaf($node, $path);
    }

    private function walkGroup(array $node, int $depth, string $path): bool
    {
        $ok = true;
        if (! isset($node['logic']) || ! in_array(strtoupper((string) $node['logic']), self::LOGICS, true)) {
            $this->errors[] = "logic harus AND atau OR pada {$path}";
            $ok = false;
        }
        if (! isset($node['conditions']) || ! is_array($node['conditions']) || empty($node['conditions'])) {
            $this->errors[] = "conditions harus array non-empty pada {$path}";
            return false;
        }
        foreach ($node['conditions'] as $i => $child) {
            if (! is_array($child)) {
                $this->errors[] = "conditions[{$i}] bukan object pada {$path}";
                $ok = false;
                continue;
            }
            $ok = $this->walk($child, $depth + 1, "{$path}.conditions[{$i}]") && $ok;
        }
        return $ok;
    }

    private function walkLeaf(array $node, string $path): bool
    {
        $ok  = true;
        $op  = $node['op'] ?? null;

        if (! is_string($op) || ! in_array(strtoupper($op), self::OPERATORS, true)) {
            $this->errors[] = "Operator tidak valid pada {$path}. Allowed: " . implode(', ', self::OPERATORS);
            $ok = false;
            $op = '';
        }
        $op = strtoupper($op);

        // Operator SUM_* dan ANY_IN/NONE_IN boleh field berisi titik dan kurung siku
        $allowDotBracket = in_array($op, ['SUM_GT','SUM_GTE','SUM_LT','SUM_LTE','SUM_EQ','ANY_IN','NONE_IN'], true);

        $field = $node['field'] ?? null;
        if (! is_string($field) || trim($field) === '') {
            $this->errors[] = "field wajib diisi sebagai string pada {$path}";
            $ok = false;
        } elseif (! $allowDotBracket && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field)) {
            $this->errors[] = "field '{$field}' format tidak valid pada {$path}.";
            $ok = false;
        } elseif ($allowDotBracket && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.\[\]]*$/', $field)) {
            $this->errors[] = "field '{$field}' format tidak valid pada {$path}.";
            $ok = false;
        }

        $hasValue   = array_key_exists('value', $node);
        $valueNeeded = ! in_array($op, ['IS_NULL', 'IS_NOT_NULL'], true);

        if ($valueNeeded && ! $hasValue) {
            $this->errors[] = "value wajib diisi pada {$path} untuk operator {$op}";
            $ok = false;
        }

        if ($hasValue) {
            $value = $node['value'];

            if (in_array($op, ['IN', 'NOT_IN', 'ANY_IN', 'NONE_IN'], true)) {
                if (! is_array($value) || empty($value)) {
                    $this->errors[] = "value untuk {$op} pada {$path} harus array non-empty.";
                    $ok = false;
                }
            } elseif ($op === 'BETWEEN') {
                if (! is_array($value) || count($value) !== 2) {
                    $this->errors[] = "value untuk BETWEEN pada {$path} harus array 2 elemen [min, max].";
                    $ok = false;
                }
            } elseif (in_array($op, ['>', '>=', '<', '<=', 'SUM_GT', 'SUM_GTE', 'SUM_LT', 'SUM_LTE', 'SUM_EQ'], true)) {
                if (! is_numeric($value)) {
                    $this->errors[] = "value untuk {$op} pada {$path} harus numerik.";
                    $ok = false;
                }
            } elseif (in_array($op, ['=', '!=', 'CONTAINS'], true)) {
                if (! is_scalar($value) && ! is_null($value)) {
                    $this->errors[] = "value untuk {$op} pada {$path} harus scalar.";
                    $ok = false;
                }
            }
        }

        // Key asing — SUM_* dan ANY_IN/NONE_IN menggunakan key yang sama (op, field, value)
        $allowedKeys = ['op', 'field', 'value'];
        $unknown = array_diff(array_keys($node), $allowedKeys);
        if (! empty($unknown)) {
            $this->errors[] = "Key tidak dikenal pada {$path}: " . implode(', ', $unknown);
            $ok = false;
        }

        return $ok;
    }
}
