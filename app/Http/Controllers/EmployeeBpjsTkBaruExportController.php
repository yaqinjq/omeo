<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\ApplicantProfileResolver;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeBpjsTkBaruExportController extends Controller
{
    /**
     * Export "Data TK Baru" — template resmi BPJS Ketenagakerjaan untuk
     * mendaftarkan tenaga kerja baru. File template asli (sheet "Petunjuk
     * Pengisian" + "Lokasi Pekerjaan") dipakai sebagai dasar dan TIDAK
     * disentuh, supaya hasil export tetap file yang sama persis dengan yang
     * dipakai untuk upload ke sistem BPJS — cuma sheet "data_tk_baru" yang
     * diisi.
     *
     * Sumber data per kolom:
     * - Data dasar (nama, NIK, HP, gaji, tanggal masuk) dari tabel employees.
     * - Data pribadi (tempat/tanggal lahir, gender, gol. darah, status
     *   kawin, alamat KTP) dari applicant_profiles milik karyawan yang
     *   bersangkutan (personal_json/address_json) — hanya tersedia untuk
     *   karyawan yang melamar lewat form digital OMEO.
     * - Nama Ibu Kandung diambil dari applicant_profiles.family_json,
     *   baris dengan relation = "Ibu" (wajib diisi saat melamar).
     * - Lokasi Pekerjaan (kode wilayah BPJS) dari outlets.bpjs_lokasi_kode,
     *   dipetakan 1x oleh HRD di Master Outlet.
     * - Kode Pos, Jenis Identitas, Masa Berlaku Identitas, Surat-menyurat
     *   Ke, Status Pegawai, Tanggal Akhir Kontrak — dari employees, diisi
     *   HRD lewat form Data Karyawan (belum ada alur self-service karyawan).
     */
    public function export(Request $request, ApplicantProfileResolver $resolver)
    {
        $onlyUnregistered = $request->boolean('only_unregistered');

        $employees = Employee::with('outlet')
            ->whereNotIn('status_employment', ['resigned', 'terminated'])
            ->when($onlyUnregistered, fn ($q) => $q->whereNull('bpjs_tk_number')->orWhere('bpjs_tk_number', ''))
            ->orderBy('full_name')
            ->get();

        $templatePath = resource_path('data/bpjs_tk_baru_template.xlsx');
        $reader = IOFactory::createReaderForFile($templatePath);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getSheetByName('data_tk_baru');

        $row = 2;
        $skipped = [];

        foreach ($employees as $employee) {
            $profile = $resolver->resolveForEmployee($employee);
            $personal = (array) ($profile?->personal_json ?? []);
            $address = (array) ($profile?->address_json ?? []);
            $families = (array) ($profile?->family_json ?? []);

            $ibu = collect($families)->first(fn ($f) => mb_strtolower(trim((string) ($f['relation'] ?? ''))) === 'ibu');

            if (! $profile) {
                $skipped[] = $employee->full_name . ' (tidak ada data pribadi dari form lamaran — kolom pribadi dikosongkan)';
            }

            $alamat = trim(implode(', ', array_filter([
                data_get($address, 'ktp_address'),
                data_get($address, 'ktp_rt') ? 'RT ' . data_get($address, 'ktp_rt') : null,
                data_get($address, 'ktp_rw') ? 'RW ' . data_get($address, 'ktp_rw') : null,
                data_get($address, 'ktp_kelurahan'),
                data_get($address, 'ktp_kecamatan'),
                data_get($address, 'ktp_city'),
            ])));

            $genderMap = ['Laki-laki' => 'L', 'Perempuan' => 'P'];
            $statusKawinMap = ['Menikah' => 'Y', 'Single' => 'T', 'Duda' => 'T', 'Janda' => 'T'];

            $sheet->setCellValueExplicit("A{$row}", (string) ($employee->employee_number ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $employee->full_name);
            // C: GELAR — tidak ada sumber data, dikosongkan
            // D-H: telepon rumah/kantor — tidak dikumpulkan OMEO, dikosongkan
            $sheet->setCellValueExplicit("I{$row}", (string) ($employee->phone_number ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("J{$row}", $employee->email_private ?? '');
            $sheet->setCellValue("K{$row}", data_get($personal, 'place_of_birth', ''));
            $dob = data_get($personal, 'date_of_birth');
            $sheet->setCellValue("L{$row}", $dob ? \Carbon\Carbon::parse($dob)->format('d-m-Y') : '');
            $sheet->setCellValue("M{$row}", $ibu['name'] ?? '');
            $sheet->setCellValue("N{$row}", $employee->jenis_identitas ?: 'KTP');
            $sheet->setCellValueExplicit("O{$row}", (string) ($employee->nik ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("P{$row}", $employee->masa_laku_identitas ?? '');
            $sheet->setCellValue("Q{$row}", $genderMap[data_get($personal, 'gender')] ?? '');
            $sheet->setCellValue("R{$row}", $employee->surat_menyurat_ke ?: 'E');
            // S: TANGGAL_KEPESERTAAN — diisi sistem BPJS sendiri, dikosongkan
            $sheet->setCellValue("T{$row}", $statusKawinMap[data_get($personal, 'marital_status')] ?? '');
            $sheet->setCellValue("U{$row}", data_get($personal, 'blood_type', ''));
            $sheet->setCellValueExplicit("V{$row}", (string) ($employee->npwp_number ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("W{$row}", 'ID');
            $sheet->setCellValue("X{$row}", $employee->current_salary ? (float) $employee->current_salary : '');
            $sheet->setCellValue("Y{$row}", $alamat);
            $sheet->setCellValueExplicit("Z{$row}", (string) ($employee->kode_pos ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("AA{$row}", (string) ($employee->outlet?->bpjs_lokasi_kode ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("AB{$row}", $employee->status_pegawai_bpjs ?? '');
            $sheet->setCellValue("AC{$row}", $employee->join_date ? $employee->join_date->format('d-m-Y') : '');
            $sheet->setCellValue("AD{$row}", $employee->tanggal_akhir_kontrak ? $employee->tanggal_akhir_kontrak->format('d-m-Y') : '');

            $row++;
        }

        $filename = 'BPJS_Data_TK_Baru_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'bpjs_tk_');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        if ($skipped !== [] && $request->boolean('debug_skipped')) {
            \Illuminate\Support\Facades\Log::info('Export BPJS TK Baru: karyawan tanpa data pribadi lengkap', ['list' => $skipped]);
        }

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
