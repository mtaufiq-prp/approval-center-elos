<?php

namespace App\Support;

/**
 * Hasil validasi flow oleh FlowValidationService.
 *
 * Disimpan sebagai DTO sederhana (bukan Eloquent) supaya FlowValidationService
 * mudah di-unit-test tanpa DB.
 *
 * isValid     : true jika tidak ada error level ERROR
 * errors      : pesan blocking (mencegah deploy)
 * warnings    : pesan non-blocking
 * checks      : daftar pengecekan yang dijalankan (untuk audit)
 */
class FlowValidationResult
{
    /** @param string[] $errors  @param string[] $warnings  @param string[] $checks */
    public function __construct(
        public bool $isValid = true,
        public array $errors = [],
        public array $warnings = [],
        public array $checks = [],
    ) {}

    public function addError(string $msg): void
    {
        $this->errors[] = $msg;
        $this->isValid = false;
    }

    public function addWarning(string $msg): void
    {
        $this->warnings[] = $msg;
    }

    public function addCheck(string $check, bool $passed): void
    {
        $this->checks[] = ($passed ? '[OK] ' : '[FAIL] ') . $check;
    }

    public function summary(): string
    {
        if ($this->isValid) {
            $s = 'VALID. ' . count($this->checks) . ' checks dijalankan';
            if ($this->warnings) $s .= ', ' . count($this->warnings) . ' warnings';
            return $s . '.';
        }
        return 'INVALID. ' . count($this->errors) . ' error(s): ' . implode(' | ', $this->errors);
    }
}
