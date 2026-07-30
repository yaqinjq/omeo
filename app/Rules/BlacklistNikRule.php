<?php

namespace App\Rules;

use App\Services\CandidateBlacklistService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BlacklistNikRule implements ValidationRule
{
    public function __construct(private readonly CandidateBlacklistService $blacklistService)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nik = (string) $value;

        if ($this->blacklistService->isNikBlacklisted($nik)) {
            $fail('NIK Anda tidak dapat digunakan untuk mendaftar kembali. Hubungi HRD bila perlu.');
        }
    }
}
