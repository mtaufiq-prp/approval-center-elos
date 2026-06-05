<?php

namespace Tests\Unit;

use App\Services\ApproverPayloadEditService as S;
use PHPUnit\Framework\TestCase;

/**
 * Resolver path payload (gaya form_schema) untuk fitur edit approver.
 * Aturan: array-of-objects (mis. header[]) → turun ke index 0 sebelum ambil key.
 */
class PayloadPathTest extends TestCase
{
    private array $p = [
        'header' => [['keterangan' => 'a', 'nilai' => 1000, 'aktif' => true]],
        'detail' => [['qty' => 1], ['qty' => 2]],
        'top'    => 'x',
    ];

    public function test_get_value_resolves_header_array_of_objects(): void
    {
        $this->assertSame('a', S::getValue($this->p, 'header.keterangan'));
        $this->assertSame(1000, S::getValue($this->p, 'header.nilai'));
        $this->assertSame('x', S::getValue($this->p, 'top'));
    }

    public function test_get_value_missing_returns_null(): void
    {
        $this->assertNull(S::getValue($this->p, 'header.tidakada'));
        $this->assertNull(S::getValue($this->p, 'nope'));
        $this->assertNull(S::getValue($this->p, 'nope.deep'));
    }

    public function test_path_exists(): void
    {
        $this->assertTrue(S::pathExists($this->p, 'header.keterangan'));
        $this->assertTrue(S::pathExists($this->p, 'top'));
        $this->assertFalse(S::pathExists($this->p, 'header.tidakada'));
        $this->assertFalse(S::pathExists($this->p, 'nope'));
    }

    public function test_set_value_updates_only_target_leaf(): void
    {
        $out = S::setValue($this->p, 'header.keterangan', 'b');
        $this->assertSame('b', $out['header'][0]['keterangan']);
        // field lain tidak berubah
        $this->assertSame(1000, $out['header'][0]['nilai']);
        $this->assertSame('x', $out['top']);
        $this->assertSame(2, $out['detail'][1]['qty']);
    }

    public function test_set_value_does_not_create_new_keys(): void
    {
        $out = S::setValue($this->p, 'header.fieldbaru', 'z');
        $this->assertArrayNotHasKey('fieldbaru', $out['header'][0], 'tidak boleh membuat key baru');
        $out2 = S::setValue($this->p, 'nope.deep', 'z');
        $this->assertSame($this->p, $out2, 'path tidak ada → payload tidak berubah');
    }
}
