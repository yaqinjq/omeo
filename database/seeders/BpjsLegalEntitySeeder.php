<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BpjsLegalEntitySeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['name' => 'Galeri Rumah Capung - My Kopi O', 'npp' => '21122457'],
            ['name' => 'Mitra Boga Selaras',              'npp' => '20129455'],
            ['name' => 'Bintang Gloria',                  'npp' => '21246059'],
            ['name' => 'Sayap Mandiri Kopitiam',          'npp' => '26091846'],
            ['name' => 'Imperial Selaras Rasa',           'npp' => '26091839'],
            ['name' => 'Kirana Indo Kopi Jaya',           'npp' => '19296025'],
        ];

        $now = now()->toDateTimeString();

        foreach ($entities as $data) {
            $existing = DB::table('legal_entities')->where('name', $data['name'])->first();

            if (! $existing) {
                $legalEntityId = DB::table('legal_entities')->insertGetId([
                    'name'        => $data['name'],
                    'entity_type' => 'PT',
                    'is_active'   => true,
                    'notes'       => 'NPP BPJS Ketenagakerjaan: ' . $data['npp'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $legalEntityId = $existing->id;
            }

            // Upsert ke bpjs_legal_accounts (NPP sebagai account_number)
            $existingAccount = DB::table('bpjs_legal_accounts')
                ->where('legal_entity_id', $legalEntityId)
                ->where('bpjs_type', 'ketenagakerjaan')
                ->first();

            if (! $existingAccount) {
                DB::table('bpjs_legal_accounts')->insert([
                    'legal_entity_id' => $legalEntityId,
                    'bpjs_type'       => 'ketenagakerjaan',
                    'account_number'  => $data['npp'],
                    'account_name'    => $data['name'],
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        $this->command->info('6 PT BPJS berhasil di-seed ke legal_entities + bpjs_legal_accounts.');
    }
}
