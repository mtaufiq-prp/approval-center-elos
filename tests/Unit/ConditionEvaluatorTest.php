<?php

namespace Tests\Unit;

use App\Services\ConditionEvaluatorService;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluatorService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ConditionEvaluatorService();
    }

    // ── Null / empty condition → always true ────────────────────────────────

    public function test_null_condition_returns_true(): void
    {
        $this->assertTrue($this->svc->evaluate(null, []));
    }

    public function test_empty_array_condition_returns_true(): void
    {
        $this->assertTrue($this->svc->evaluate([], []));
    }

    // ── Scalar operators ────────────────────────────────────────────────────

    public function test_eq_string_case_insensitive(): void
    {
        $cond = ['op' => '=', 'field' => 'status', 'value' => 'approved'];
        $this->assertTrue($this->svc->evaluate($cond, ['status' => 'APPROVED']));
        $this->assertFalse($this->svc->evaluate($cond, ['status' => 'REJECTED']));
    }

    public function test_eq_numeric_epsilon(): void
    {
        // Nilai yang tidak identik secara bit tapi sama secara matematika
        $cond = ['op' => '=', 'field' => 'val', 'value' => 0.1 + 0.2];
        $this->assertTrue($this->svc->evaluate($cond, ['val' => 0.3]));
    }

    public function test_neq(): void
    {
        $cond = ['op' => '!=', 'field' => 'x', 'value' => 5];
        $this->assertTrue($this->svc->evaluate($cond, ['x' => 10]));
        $this->assertFalse($this->svc->evaluate($cond, ['x' => 5]));
    }

    public function test_gt_gte_lt_lte(): void
    {
        $ctx = ['v' => 10];
        $this->assertTrue($this->svc->evaluate(['op' => '>', 'field' => 'v', 'value' => 5], $ctx));
        $this->assertFalse($this->svc->evaluate(['op' => '>', 'field' => 'v', 'value' => 10], $ctx));
        $this->assertTrue($this->svc->evaluate(['op' => '>=', 'field' => 'v', 'value' => 10], $ctx));
        $this->assertTrue($this->svc->evaluate(['op' => '<', 'field' => 'v', 'value' => 20], $ctx));
        $this->assertTrue($this->svc->evaluate(['op' => '<=', 'field' => 'v', 'value' => 10], $ctx));
    }

    public function test_in_and_not_in(): void
    {
        $ctx = ['code' => 'B'];
        $this->assertTrue($this->svc->evaluate(['op' => 'IN', 'field' => 'code', 'value' => ['A', 'B', 'C']], $ctx));
        $this->assertFalse($this->svc->evaluate(['op' => 'NOT_IN', 'field' => 'code', 'value' => ['A', 'B', 'C']], $ctx));
    }

    public function test_between_valid(): void
    {
        $cond = ['op' => 'BETWEEN', 'field' => 'val', 'value' => [5, 15]];
        $this->assertTrue($this->svc->evaluate($cond, ['val' => 10]));
        $this->assertFalse($this->svc->evaluate($cond, ['val' => 20]));
    }

    public function test_between_non_numeric_bound_returns_false(): void
    {
        // #32: bound non-numerik tidak boleh di-coerce diam-diam
        $cond = ['op' => 'BETWEEN', 'field' => 'val', 'value' => ['low', 'high']];
        $this->assertFalse($this->svc->evaluate($cond, ['val' => 10]));
    }

    public function test_contains(): void
    {
        $cond = ['op' => 'CONTAINS', 'field' => 'name', 'value' => 'propan'];
        $this->assertTrue($this->svc->evaluate($cond, ['name' => 'PT Propan Raya ICC']));
        $this->assertFalse($this->svc->evaluate($cond, ['name' => 'Other Corp']));
    }

    public function test_contains_with_array_value_returns_false(): void
    {
        // #34: value array tidak boleh di-coerce
        $cond = ['op' => 'CONTAINS', 'field' => 'name', 'value' => ['propan']];
        $this->assertFalse($this->svc->evaluate($cond, ['name' => 'PT Propan']));
    }

    public function test_is_null_and_is_not_null(): void
    {
        $this->assertTrue($this->svc->evaluate(['op' => 'IS_NULL', 'field' => 'x', 'value' => null], ['x' => null]));
        $this->assertFalse($this->svc->evaluate(['op' => 'IS_NULL', 'field' => 'x', 'value' => null], ['x' => 0]));
        $this->assertTrue($this->svc->evaluate(['op' => 'IS_NOT_NULL', 'field' => 'x', 'value' => null], ['x' => 'val']));
    }

    // ── ANY_IN / NONE_IN ────────────────────────────────────────────────────

    public function test_any_in(): void
    {
        $cond = ['op' => 'ANY_IN', 'field' => '_computed.idmsalasan_list', 'value' => [61, 68]];
        $ctx  = ['_computed' => ['idmsalasan_list' => [10, 61, 90]]];
        $this->assertTrue($this->svc->evaluate($cond, $ctx));

        $ctx2 = ['_computed' => ['idmsalasan_list' => [10, 20]]];
        $this->assertFalse($this->svc->evaluate($cond, $ctx2));
    }

    public function test_none_in(): void
    {
        $cond = ['op' => 'NONE_IN', 'field' => 'list', 'value' => [1, 2, 3]];
        $this->assertTrue($this->svc->evaluate($cond, ['list' => [4, 5]]));
        $this->assertFalse($this->svc->evaluate($cond, ['list' => [1, 4]]));
    }

    // ── SUM operators ───────────────────────────────────────────────────────

    public function test_sum_gt_with_computed_field(): void
    {
        $cond = ['op' => 'SUM_GT', 'field' => '_computed.total_nilai_retur', 'value' => 25000000];
        $ctx  = ['_computed' => ['total_nilai_retur' => 30000000]];
        $this->assertTrue($this->svc->evaluate($cond, $ctx));

        $ctx2 = ['_computed' => ['total_nilai_retur' => 10000000]];
        $this->assertFalse($this->svc->evaluate($cond, $ctx2));
    }

    public function test_sum_gt_with_array_field(): void
    {
        $cond = ['op' => 'SUM_GT', 'field' => 'detail[].value_retur_ori', 'value' => 100];
        $ctx  = ['detail' => [['value_retur_ori' => 60], ['value_retur_ori' => 50]]];
        $this->assertTrue($this->svc->evaluate($cond, $ctx));
    }

    // ── GROUP / AND / OR ────────────────────────────────────────────────────

    public function test_and_group(): void
    {
        $cond = [
            'logic' => 'AND',
            'conditions' => [
                ['op' => '>', 'field' => 'a', 'value' => 0],
                ['op' => '<', 'field' => 'a', 'value' => 100],
            ],
        ];
        $this->assertTrue($this->svc->evaluate($cond, ['a' => 50]));
        $this->assertFalse($this->svc->evaluate($cond, ['a' => 0]));
    }

    public function test_or_group(): void
    {
        $cond = [
            'logic' => 'OR',
            'conditions' => [
                ['op' => '=', 'field' => 'status', 'value' => 'A'],
                ['op' => '=', 'field' => 'status', 'value' => 'B'],
            ],
        ];
        $this->assertTrue($this->svc->evaluate($cond, ['status' => 'B']));
        $this->assertFalse($this->svc->evaluate($cond, ['status' => 'C']));
    }

    // ── resolveField ────────────────────────────────────────────────────────

    public function test_resolve_nested_dot_notation(): void
    {
        // Test via condition karena resolveField private
        $cond = ['op' => '=', 'field' => 'a.b.c', 'value' => 'val'];
        $this->assertTrue($this->svc->evaluate($cond, ['a' => ['b' => ['c' => 'val']]]));
    }

    public function test_resolve_missing_field_returns_null(): void
    {
        $cond = ['op' => 'IS_NULL', 'field' => 'missing.key', 'value' => null];
        $this->assertTrue($this->svc->evaluate($cond, []));
    }

    // ── Depth guard (anti nested-bomb) ──────────────────────────────────────

    public function test_deeply_nested_condition_throws(): void
    {
        // Bangun GROUP bersarang > 20 level → harus dilempar (DoS guard).
        $cond = ['op' => '=', 'field' => 'x', 'value' => 1];
        for ($i = 0; $i < 25; $i++) {
            $cond = ['logic' => 'AND', 'conditions' => [$cond]];
        }
        $this->expectException(\RuntimeException::class);
        $this->svc->evaluate($cond, ['x' => 1]);
    }

    public function test_reasonable_nesting_ok(): void
    {
        // 5 level masih jauh di bawah batas → evaluasi normal.
        $cond = ['op' => '=', 'field' => 'x', 'value' => 1];
        for ($i = 0; $i < 5; $i++) {
            $cond = ['logic' => 'AND', 'conditions' => [$cond]];
        }
        $this->assertTrue($this->svc->evaluate($cond, ['x' => 1]));
    }
}
