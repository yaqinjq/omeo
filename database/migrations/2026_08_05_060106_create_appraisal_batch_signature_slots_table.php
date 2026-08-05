<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti skema tanda tangan appraisal dari 5 kolom tetap
     * (signer_employee_id/hrd_id/supervisor_id/manager_id/director_id) jadi
     * baris dinamis, supaya jumlah penanda tangan bisa 3-4 (bukan selalu 5)
     * dan kategori penanda tangan (PIC/HRD/Manager/Owner In Charge) bisa
     * difilter dari Master Data Posisi. Kolom lama SENGAJA TIDAK dihapus di
     * migration ini — dibiarkan sebagai cadangan sampai data di tabel baru
     * ini dikonfirmasi benar di production, baru dibersihkan menyusul.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appraisal_batch_signature_slots')) {
            Schema::create('appraisal_batch_signature_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_signature_id');
                $table->unsignedInteger('slot_order')->default(1);
                $table->string('slot_type', 20)->default('category'); // employee | category | manual
                $table->string('category', 30)->nullable(); // pic | hrd | supervisor | manager | director | owner_in_charge
                $table->string('label', 100);
                $table->unsignedBigInteger('signer_user_id')->nullable();
                $table->string('external_name', 150)->nullable(); // dipakai slot_type=manual (mis. Owner In Charge)
                $table->longText('signature_data')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();

                $table->index('batch_signature_id');
                $table->index(['batch_signature_id', 'slot_order'], 'abs_slots_batch_order_idx');
            });
        }

        if (! Schema::hasTable('appraisal_batch_signatures')) {
            return;
        }

        // Migrasi 1x: setiap baris appraisal_batch_signatures lama dipecah
        // jadi 5 baris slot (urutan sama seperti tampilan lama: Karyawan,
        // HRD, Supervisor, Manager, Director) supaya tanda tangan yang sudah
        // dikumpulkan sebelumnya tidak hilang sedikit pun.
        if (DB::table('appraisal_batch_signature_slots')->exists()) {
            return; // sudah pernah dimigrasi (mis. migration di-rerun)
        }

        $legacyRoles = [
            ['role' => 'employee',   'order' => 1, 'type' => 'employee', 'category' => null,         'label' => 'Karyawan'],
            ['role' => 'hrd',        'order' => 2, 'type' => 'category', 'category' => 'hrd',        'label' => 'HRD / Super Administrator'],
            ['role' => 'supervisor', 'order' => 3, 'type' => 'category', 'category' => 'supervisor', 'label' => 'Supervisor / PIC'],
            ['role' => 'manager',    'order' => 4, 'type' => 'category', 'category' => 'manager',    'label' => 'Manager / ASPV / ASM'],
            ['role' => 'director',   'order' => 5, 'type' => 'category', 'category' => 'director',   'label' => 'Managing Director'],
        ];

        DB::table('appraisal_batch_signatures')->orderBy('id')->chunkById(200, function ($batches) use ($legacyRoles) {
            $now = now();
            $rows = [];

            foreach ($batches as $batch) {
                foreach ($legacyRoles as $cfg) {
                    $rows[] = [
                        'batch_signature_id' => $batch->id,
                        'slot_order'          => $cfg['order'],
                        'slot_type'           => $cfg['type'],
                        'category'            => $cfg['category'],
                        'label'               => $cfg['label'],
                        'signer_user_id'      => $batch->{"signer_{$cfg['role']}_id"},
                        'external_name'       => null,
                        'signature_data'      => $batch->{"sig_{$cfg['role']}"},
                        'signed_at'           => $batch->{"sig_{$cfg['role']}_at"},
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('appraisal_batch_signature_slots')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_batch_signature_slots');
    }
};
