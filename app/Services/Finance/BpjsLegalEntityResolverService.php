<?php

namespace App\Services\Finance;

use App\Models\BpjsLegalAccount;
use App\Models\LegalEntity;

class BpjsLegalEntityResolverService
{
    /**
     * Cari LegalEntity berdasarkan NPP (nomor pendaftaran perusahaan BPJS
     * Ketenagakerjaan) yang sudah terdaftar di bpjs_legal_accounts. Kalau
     * belum ada, buat otomatis PT baru + akun BPJS-nya dari data yang
     * berhasil diparsing dari file tagihan (npp + nama perusahaan) —
     * tidak perlu master data diisi manual dulu.
     */
    public function resolveOrCreate(?string $npp, ?string $namaPerusahaan): ?LegalEntity
    {
        $npp = trim((string) $npp);
        if ($npp === '') {
            return null;
        }

        $account = BpjsLegalAccount::where('npp', $npp)->first();
        if ($account) {
            return $account->legalEntity;
        }

        $name = trim((string) $namaPerusahaan) ?: "PT (NPP {$npp})";

        $legalEntity = LegalEntity::create([
            'name'      => $name,
            'is_active' => true,
            'notes'     => 'Dibuat otomatis dari upload tagihan BPJS (NPP: ' . $npp . ')',
        ]);

        BpjsLegalAccount::create([
            'legal_entity_id' => $legalEntity->id,
            'bpjs_type'       => 'ketenagakerjaan',
            'account_number'  => $npp,
            'npp'             => $npp,
            'is_active'       => true,
            'notes'           => 'Dibuat otomatis dari upload tagihan BPJS.',
        ]);

        return $legalEntity;
    }
}
